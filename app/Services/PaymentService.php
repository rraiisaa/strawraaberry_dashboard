<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\{CashFlow, Obligation, Payment, PaymentAllocation};
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /** Catat setoran (status Menunggu Verifikasi). Belum mengurangi kewajiban dan belum masuk kas. */
    public function record(array $data, array $allocations): Payment
    {
        return DB::transaction(function () use ($data, $allocations) {
            if ($dup = Payment::where('request_key', $data['request_key'])->first()) return $dup;
            $amount = (int) $data['amount_received'];
            if ($amount <= 0) throw new BusinessRuleException('Nominal yang diterima harus lebih dari 0.');

            $allocations = array_filter(array_map('intval', $allocations), fn ($v) => $v > 0);
            if (array_sum($allocations) > $amount) throw new BusinessRuleException('Total alokasi melebihi nominal yang diterima.');

            foreach ($allocations as $obId => $amt) $this->assertCanAllocate(Obligation::lockForUpdate()->findOrFail($obId), (int) $data['store_id'], $amt);

            $p = Payment::create([
                'store_id' => $data['store_id'], 'paid_on' => $data['paid_on'], 'amount_expected' => ($data['amount_expected'] ?? '') !== '' ? (int) $data['amount_expected'] : null,
                'amount_received' => $amount, 'method' => $data['method'] ?? 'Transfer', 'reference_no' => $data['reference_no'] ?? null,
                'destination_account' => $data['destination_account'] ?? null, 'notes' => $data['notes'] ?? null,
                'request_key' => $data['request_key'], 'number' => Numbering::temp(), 'created_by' => ActivityLogger::actor(),
            ]);
            Numbering::assign($p, 'ST');
            foreach ($allocations as $obId => $amt) $p->allocations()->create(['obligation_id' => $obId, 'amount' => $amt]);

            ActivityLogger::log('payment.record', "Setoran {$p->number} dicatat Rp " . number_format($amount, 0, ',', '.') . ' (menunggu verifikasi)', $p, $p->store_id);
            return $p->refresh();
        });
    }

    private function assertCanAllocate(Obligation $o, int $storeId, int $amt, bool $verifiedOnly = false): void
    {
        if ($o->store_id !== $storeId) throw new BusinessRuleException('Kewajiban bukan milik mitra yang sama dengan setoran.');
        if ($o->cancelled_at) throw new BusinessRuleException('Kewajiban sudah dibatalkan.');
        $room = $verifiedOnly ? $o->amount_due - $o->paid : $o->available_to_allocate;
        if ($amt > $room) throw new BusinessRuleException("Alokasi Rp " . number_format($amt, 0, ',', '.') . " melebihi sisa kewajiban Rp " . number_format($room, 0, ',', '.') . " ({$o->report->number}). Kelebihan bayar tidak dialokasikan otomatis.");
    }

    /** Verifikasi: setoran benar-benar diterima. Membuat catatan kas per alokasi, atomik dan idempoten. */
    public function verify(Payment $p): void
    {
        DB::transaction(function () use ($p) {
            $p = Payment::lockForUpdate()->with('allocations.obligation.report')->findOrFail($p->id);
            if ($p->status !== 'pending') throw new BusinessRuleException('Hanya setoran Menunggu Verifikasi yang bisa diverifikasi.');
            foreach ($p->allocations as $a) $this->assertCanAllocate(Obligation::lockForUpdate()->find($a->obligation_id), $p->store_id, $a->amount, true);

            $p->update(['status' => 'verified', 'verified_by' => ActivityLogger::actor(), 'verified_at' => now()]);
            foreach ($p->allocations as $a) $this->postCash($p, $a);
            ActivityLogger::log('payment.verify', "Setoran {$p->number} diverifikasi dan dicatat ke Aliran Dana (Rp " . number_format($p->allocated, 0, ',', '.') . ' teralokasi)', $p, $p->store_id);
        });
    }

    private function postCash(Payment $p, PaymentAllocation $a): void
    {
        $cf = CashFlow::firstOrCreate(['source_key' => "alloc:{$a->id}"], [
            'type' => 'income', 'category' => 'Setoran Mitra', 'amount' => $a->amount, 'date' => $p->paid_on,
            'description' => "Setoran {$p->number} - {$p->store->name} - " . ($a->obligation->report->number ?? ''),
            'source_type' => 'payment_allocation', 'source_id' => $a->id,
        ]);
        $a->update(['cash_flow_id' => $cf->id]);
    }

    public function reject(Payment $p, string $reason): void
    {
        if (trim($reason) === '') throw new BusinessRuleException('Alasan penolakan wajib diisi.');
        DB::transaction(function () use ($p, $reason) {
            $p = Payment::lockForUpdate()->findOrFail($p->id);
            if ($p->status !== 'pending') throw new BusinessRuleException('Hanya setoran yang menunggu verifikasi yang bisa ditolak.');
            $p->update(['status' => 'rejected', 'reversed_reason' => $reason]);
            ActivityLogger::log('payment.reject', "Setoran {$p->number} ditolak", $p, $p->store_id, null, null, $reason);
        });
    }

    /** Reversal setoran terverifikasi: kas dikoreksi dengan catatan lawan (bukan dihapus), kewajiban kembali terbuka. */
    public function reverse(Payment $p, string $reason): void
    {
        if (trim($reason) === '') throw new BusinessRuleException('Alasan reversal wajib diisi.');
        DB::transaction(function () use ($p, $reason) {
            $p = Payment::lockForUpdate()->with('allocations')->findOrFail($p->id);
            if ($p->status !== 'verified') throw new BusinessRuleException('Hanya setoran terverifikasi yang bisa direversal.');
            $p->update(['status' => 'reversed', 'reversed_reason' => $reason]);
            foreach ($p->allocations as $a) {
                CashFlow::firstOrCreate(['source_key' => "alloc:{$a->id}:rev"], [
                    'type' => 'expense', 'category' => 'Koreksi Setoran', 'amount' => $a->amount, 'date' => now()->toDateString(),
                    'description' => "Reversal setoran {$p->number}: {$reason}", 'source_type' => 'payment_allocation', 'source_id' => $a->id,
                ]);
            }
            ActivityLogger::log('payment.reverse', "Setoran {$p->number} direversal", $p, $p->store_id, null, null, $reason);
        });
    }

    /** Alokasikan saldo lebih dari setoran terverifikasi ke kewajiban lain milik mitra yang sama. */
    public function allocateSurplus(Payment $p, int $obligationId, int $amount): void
    {
        DB::transaction(function () use ($p, $obligationId, $amount) {
            $p = Payment::lockForUpdate()->findOrFail($p->id);
            if ($p->status !== 'verified') throw new BusinessRuleException('Hanya setoran terverifikasi yang saldonya bisa dialokasikan.');
            if ($amount <= 0 || $amount > $p->unallocated) throw new BusinessRuleException('Nominal melebihi saldo lebih setoran (Rp ' . number_format($p->unallocated, 0, ',', '.') . ').');
            $o = Obligation::lockForUpdate()->with('report')->findOrFail($obligationId);
            $this->assertCanAllocate($o, $p->store_id, $amount);
            $a = $p->allocations()->create(['obligation_id' => $o->id, 'amount' => $amount]);
            $a->setRelation('obligation', $o);
            $this->postCash($p, $a);
            ActivityLogger::log('payment.allocate', "Saldo lebih setoran {$p->number} dialokasikan Rp " . number_format($amount, 0, ',', '.') . " ke {$o->report->number}", $p, $p->store_id);
        });
    }
}