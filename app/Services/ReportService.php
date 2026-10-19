<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\{Obligation, PartnerTerm, Product, SalesReport, Store};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private StockLedger $ledger) {}

    /** Simpan laporan DRAFT (baru atau edit draft/perlu revisi). Perhitungan memakai SNAPSHOT skema periode itu. */
    public function save(array $data, array $lines, ?SalesReport $report = null): SalesReport
    {
        return DB::transaction(function () use ($data, $lines, $report) {
            if (! $report && ($dup = SalesReport::where('request_key', $data['request_key'])->first())) return $dup;

            $store = Store::findOrFail($data['store_id']);
            if (in_array($store->status, ['inactive', 'prospect'], true)) throw new BusinessRuleException('Hanya mitra aktif atau jeda yang bisa dibuatkan laporan.');
            if ($data['period_end'] < $data['period_start']) throw new BusinessRuleException('Periode akhir tidak boleh sebelum periode mulai.');

            if ($report) {
                if (! $report->isEditable()) throw new BusinessRuleException('Laporan ini tidak bisa diedit. Gunakan proses revisi untuk laporan terverifikasi.');
                $snapshot = $report->term_snapshot; $termId = $report->partner_term_id;
            } else {
                $term = PartnerTerm::coveringPeriod($store->id, $data['period_start'], $data['period_end']);
                if (! $term) throw new BusinessRuleException('Tidak ada skema bagi hasil yang berlaku penuh pada periode ini. Atur skema, atau pecah laporan di titik pergantian skema.');
                $snapshot = $term->snapshot(); $termId = $term->id;
            }

            $prepared = $this->prepareLines($lines);
            $calc = ProfitShare::calculate($prepared, $snapshot, (int) ($data['adjustments_amount'] ?? 0), isset($data['custom_store_share']) && $data['custom_store_share'] !== '' ? (int) $data['custom_store_share'] : null);

            $fields = [
                'store_id' => $store->id, 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
                'received_at' => $data['received_at'] ?: null, 'source' => $data['source'] ?? null, 'notes' => $data['notes'] ?? null,
                'reported_total' => ($data['reported_total'] ?? '') !== '' ? (int) $data['reported_total'] : null,
                'adjustments_amount' => (int) ($data['adjustments_amount'] ?? 0), 'adjustments_note' => $data['adjustments_note'] ?? null,
                'custom_store_share' => ($data['custom_store_share'] ?? '') !== '' ? (int) $data['custom_store_share'] : null,
            ] + $this->totals($calc);
            $fields['is_late'] = $this->isLate($store, $fields['period_end'], $fields['received_at']);

            if ($report) {
                $report->update($fields);
                $report->lines()->delete();
            } else {
                $report = SalesReport::create($fields + ['request_key' => $data['request_key'], 'number' => Numbering::temp(),
                    'partner_term_id' => $termId, 'term_snapshot' => $snapshot, 'created_by' => ActivityLogger::actor()]);
                Numbering::assign($report, 'LP');
            }
            foreach ($prepared as $l) $report->lines()->create($l);

            ActivityLogger::log('report.save', "Draft laporan {$report->number} disimpan ({$store->name})", $report, $store->id);
            return $report->refresh();
        });
    }

    private function prepareLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            if (empty($l['product_id']) || (int) ($l['qty_sold'] ?? 0) <= 0) continue;
            if (isset($out[$l['product_id']])) throw new BusinessRuleException('Produk yang sama muncul dua kali pada laporan. Gabungkan barisnya.');
            $p = Product::find($l['product_id']) ?? throw new BusinessRuleException('Produk tidak ditemukan (SKU tidak terdaftar).');
            $out[$p->id] = [
                'product_id' => $p->id, 'qty_sold' => (int) $l['qty_sold'],
                'unit_price' => ($l['unit_price'] ?? '') !== '' ? (int) $l['unit_price'] : $p->selling_price,
                'list_price' => $p->selling_price, 'discount_amount' => (int) ($l['discount_amount'] ?? 0), 'return_qty' => (int) ($l['return_qty'] ?? 0),
            ];
        }
        if (! $out) throw new BusinessRuleException('Tambahkan minimal satu baris produk dengan unit terjual lebih dari 0.');
        return array_values($out);
    }

    private function totals(array $c): array
    {
        return ['gross_amount' => $c['gross'], 'discount_amount' => $c['discount'], 'returns_amount' => $c['returns'],
            'net_sales' => $c['net_sales'], 'base_amount' => $c['base'], 'store_share' => $c['store_share'], 'own_share' => $c['own_share'], 'total_units' => $c['units']];
    }

    private function isLate(Store $s, string $periodEnd, ?string $receivedAt): bool
    {
        $deadline = Carbon::parse($periodEnd)->addDays($s->report_due_days);
        return ($receivedAt ? Carbon::parse($receivedAt) : now())->startOfDay()->gt($deadline);
    }

    /** Temuan validasi. level 'error' memblokir pengajuan/verifikasi; 'warning' hanya peringatan. */
    public function issues(SalesReport $r): array
    {
        $r->loadMissing('lines.product', 'store');
        $out = [];
        $add = fn (string $lvl, string $msg) => $out[] = ['level' => $lvl, 'msg' => $msg];

        $overlap = SalesReport::where('store_id', $r->store_id)->where('id', '!=', $r->id)->where('status', 'verified')
            ->whereDate('period_start', '<=', $r->period_end)->whereDate('period_end', '>=', $r->period_start)->first();
        if ($overlap) $add('error', "Periode tumpang tindih dengan laporan terverifikasi {$overlap->number}. Gunakan revisi pada laporan tersebut.");

        $dup = SalesReport::where('store_id', $r->store_id)->where('id', '!=', $r->id)->whereNotIn('status', ['cancelled', 'verified'])
            ->whereDate('period_start', $r->period_start)->whereDate('period_end', $r->period_end)->first();
        if ($dup) $add('warning', "Berpotensi duplikat dengan {$dup->number} (periode sama, belum terverifikasi).");

        if ($r->status !== 'verified') {
            foreach ($r->lines as $l) {
                $avail = StockLedger::balance($r->store_id, $l->product_id);
                if ($l->qty_sold > $avail) $add('error', "{$l->product->label}: terjual {$l->qty_sold} melebihi stok sistem di toko ({$avail}). Periksa restock atau rekonsiliasi.");
            }
        }
        foreach ($r->lines as $l) {
            if ($l->unit_price !== $l->list_price) $add('warning', "{$l->product->label}: harga laporan Rp " . number_format($l->unit_price, 0, ',', '.') . ' berbeda dari harga berlaku Rp ' . number_format($l->list_price, 0, ',', '.') . '.');
        }
        if ($r->reported_total !== null && $r->reported_total !== $r->gross_amount - $r->discount_amount - $r->returns_amount) {
            $add('warning', 'Total versi toko (Rp ' . number_format($r->reported_total, 0, ',', '.') . ') tidak cocok dengan hasil rincian (Rp ' . number_format($r->gross_amount - $r->discount_amount - $r->returns_amount, 0, ',', '.') . ', yaitu kotor - diskon - retur).');
        }
        if (($r->returns_amount > 0 || $r->adjustments_amount !== 0) && ! $r->proof_path && ! $r->notes && ! $r->adjustments_note) {
            $add('warning', 'Ada retur atau penyesuaian tetapi belum ada catatan atau bukti.');
        }
        $prev = SalesReport::where('store_id', $r->store_id)->where('status', 'verified')->where('id', '!=', $r->id)->whereDate('period_end', '<', $r->period_start)->orderByDesc('period_end')->first();
        if ($prev && $prev->period_end->addDay()->lt($r->period_start)) {
            $add('warning', 'Ada jeda periode yang belum dilaporkan: ' . $prev->period_end->addDay()->format('d/m/Y') . ' s/d ' . $r->period_start->copy()->subDay()->format('d/m/Y') . '.');
        }
        if (($r->term_snapshot['scheme_type'] ?? '') === 'custom' && $r->custom_store_share === null) $add('error', 'Skema khusus: nominal bagian toko belum diisi.');
        if ($r->is_late) $add('warning', 'Laporan diterima terlambat dari jadwal (batas ' . $r->store->report_due_days . ' hari setelah periode berakhir).');
        return $out;
    }

    private function errors(SalesReport $r): array { return array_column(array_filter($this->issues($r), fn ($i) => $i['level'] === 'error'), 'msg'); }

    public function submit(SalesReport $r): void
    {
        DB::transaction(function () use ($r) {
            $r = SalesReport::lockForUpdate()->findOrFail($r->id);
            if (! $r->isEditable()) throw new BusinessRuleException('Hanya draft atau laporan perlu revisi yang bisa diajukan.');
            if ($e = $this->errors($r)) throw new BusinessRuleException(implode(' ', $e));
            $r->update(['status' => 'pending_verification', 'review_note' => null]);
            ActivityLogger::log('report.submit', "Laporan {$r->number} diajukan untuk verifikasi", $r, $r->store_id);
        });
    }

    public function reject(SalesReport $r, string $note): void
    {
        if (trim($note) === '') throw new BusinessRuleException('Catatan penolakan wajib diisi.');
        DB::transaction(function () use ($r, $note) {
            $r = SalesReport::lockForUpdate()->findOrFail($r->id);
            if ($r->status !== 'pending_verification') throw new BusinessRuleException('Laporan tidak sedang menunggu verifikasi.');
            $r->update(['status' => 'needs_revision', 'review_note' => $note]);
            ActivityLogger::log('report.reject', "Laporan {$r->number} dikembalikan: perlu revisi", $r, $r->store_id, null, null, $note);
        });
    }

    /** Verifikasi: satu-satunya titik yang memotong stok toko dan melahirkan kewajiban. Atomik. */
    public function verify(SalesReport $r): void
    {
        DB::transaction(function () use ($r) {
            $r = SalesReport::lockForUpdate()->with('lines.product')->findOrFail($r->id);
            if ($r->status !== 'pending_verification') throw new BusinessRuleException('Laporan harus berstatus Menunggu Verifikasi.');
            if ($e = $this->errors($r)) throw new BusinessRuleException(implode(' ', $e));

            $this->postMovements($r);
            Obligation::create([
                'store_id' => $r->store_id, 'sales_report_id' => $r->id, 'amount_due' => $r->own_share,
                'due_date' => $r->period_end->copy()->addDays($r->store->payment_due_days),
            ]);
            $r->update(['status' => 'verified', 'verified_by' => ActivityLogger::actor(), 'verified_at' => now()]);
            ActivityLogger::log('report.verify', "Laporan {$r->number} diverifikasi: kewajiban Rp " . number_format($r->own_share, 0, ',', '.'), $r, $r->store_id);
        });
    }

    private function postMovements(SalesReport $r): void
    {
        foreach ($r->lines as $l) {
            $base = "report:{$r->id}:r{$r->revision_no}:l{$l->id}";
            $this->ledger->post($r->store_id, $l->product_id, StockLedger::SELLABLE, 'sale', -$l->qty_sold, "$base:sale", null, 'report', $r->id, $r->period_end->toDateString());
            if ($l->return_qty > 0) {
                // Retur pembeli TIDAK otomatis layak jual: masuk karantina sampai diperiksa.
                $this->ledger->post($r->store_id, $l->product_id, StockLedger::QUARANTINE, 'customer_return', $l->return_qty, "$base:ret", null, 'report', $r->id, $r->period_end->toDateString());
            }
        }
    }

    /** Revisi laporan terverifikasi: wajib alasan; versi lama disimpan; ledger direversal lalu diposting ulang. */
    public function revise(SalesReport $r, array $data, array $lines, string $reason): SalesReport
    {
        if (trim($reason) === '') throw new BusinessRuleException('Alasan revisi wajib diisi.');
        return DB::transaction(function () use ($r, $data, $lines, $reason) {
            $r = SalesReport::lockForUpdate()->with('lines')->findOrFail($r->id);
            if ($r->status !== 'verified') throw new BusinessRuleException('Hanya laporan terverifikasi yang direvisi lewat proses ini.');

            $before = $this->payload($r);
            $this->ledger->reverse("report:{$r->id}:r{$r->revision_no}:", "Revisi laporan: {$reason}");

            $prepared = $this->prepareLines($lines);
            $custom = ($data['custom_store_share'] ?? '') !== '' ? (int) $data['custom_store_share'] : null;
            $calc = ProfitShare::calculate($prepared, $r->term_snapshot, (int) ($data['adjustments_amount'] ?? 0), $custom);

            $r->lines()->delete();
            $r->update([
                'revision_no' => $r->revision_no + 1, 'received_at' => $data['received_at'] ?: $r->received_at, 'source' => $data['source'] ?? $r->source,
                'notes' => $data['notes'] ?? $r->notes, 'adjustments_amount' => (int) ($data['adjustments_amount'] ?? 0), 'adjustments_note' => $data['adjustments_note'] ?? null,
                'reported_total' => ($data['reported_total'] ?? '') !== '' ? (int) $data['reported_total'] : null, 'custom_store_share' => $custom,
            ] + $this->totals($calc));
            foreach ($prepared as $l) $r->lines()->create($l);
            $r->refresh()->load('lines.product');

            $this->postMovements($r);
            $r->obligation?->update(['amount_due' => $r->own_share]);

            $r->revisions()->create(['revision_no' => $r->revision_no, 'reason' => $reason, 'before' => $before, 'after' => $this->payload($r), 'actor' => ActivityLogger::actor()]);
            ActivityLogger::log('report.revise', "Laporan {$r->number} direvisi (rev. {$r->revision_no})", $r, $r->store_id, $before, $this->payload($r), $reason);
            return $r;
        });
    }

    public function cancel(SalesReport $r, string $reason): void
    {
        if (trim($reason) === '') throw new BusinessRuleException('Alasan pembatalan wajib diisi.');
        DB::transaction(function () use ($r, $reason) {
            $r = SalesReport::lockForUpdate()->findOrFail($r->id);
            if ($r->status === 'cancelled') throw new BusinessRuleException('Laporan sudah dibatalkan.');
            if ($r->status === 'verified') {
                if ($r->obligation && $r->obligation->allocations()->exists()) {
                    throw new BusinessRuleException('Sudah ada pembayaran yang dialokasikan ke laporan ini. Reversal pembayaran terlebih dulu.');
                }
                $this->ledger->reverse("report:{$r->id}:r{$r->revision_no}:", "Pembatalan laporan: {$reason}");
                $r->obligation?->update(['cancelled_at' => now()]);
            }
            $r->update(['status' => 'cancelled', 'review_note' => $reason]);
            ActivityLogger::log('report.cancel', "Laporan {$r->number} dibatalkan", $r, $r->store_id, null, null, $reason);
        });
    }

    private function payload(SalesReport $r): array
    {
        return [
            'revision_no' => $r->revision_no, 'net_sales' => $r->net_sales, 'base_amount' => $r->base_amount, 'store_share' => $r->store_share,
            'own_share' => $r->own_share, 'adjustments_amount' => $r->adjustments_amount, 'total_units' => $r->total_units,
            'lines' => $r->lines->map(fn ($l) => $l->only(['product_id', 'qty_sold', 'unit_price', 'discount_amount', 'return_qty']))->all(),
        ];
    }
}