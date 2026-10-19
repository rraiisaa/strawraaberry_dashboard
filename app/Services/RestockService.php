<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\{Product, Restock, RestockItem, Store};
use Illuminate\Support\Facades\DB;

class RestockService
{
    public function __construct(private StockLedger $ledger) {}

    /** Buat / ubah DRAFT. Draft tidak pernah mengubah stok. */
    public function save(array $data, array $items, ?Restock $restock = null): Restock
    {
        return DB::transaction(function () use ($data, $items, $restock) {
            if (! $restock && ($dup = Restock::where('request_key', $data['request_key'])->first())) return $dup; // submit ganda

            $store = Store::findOrFail($data['store_id']);
            if ($store->status === 'inactive') throw new BusinessRuleException('Mitra nonaktif tidak bisa menerima restock.');

            $clean = [];
            foreach ($items as $i) {
                $qty = (int) ($i['qty_sent'] ?? 0);
                if (empty($i['product_id']) || $qty <= 0) continue;
                if (isset($clean[$i['product_id']])) throw new BusinessRuleException('Produk yang sama muncul dua kali. Gabungkan jumlahnya.');
                $clean[$i['product_id']] = ['product_id' => (int) $i['product_id'], 'qty_sent' => $qty, 'condition' => $i['condition'] ?? 'baik'];
            }
            if (! $clean) throw new BusinessRuleException('Tambahkan minimal satu produk dengan jumlah lebih dari 0.');

            $fields = collect($data)->only(['store_id', 'ship_date', 'handed_by', 'received_by', 'notes'])->all();
            if ($restock) {
                if ($restock->status !== 'draft') throw new BusinessRuleException('Hanya draft yang bisa diedit.');
                $restock->update($fields);
                $restock->items()->delete();
            } else {
                $restock = Restock::create($fields + ['request_key' => $data['request_key'], 'number' => Numbering::temp(), 'created_by' => ActivityLogger::actor()]);
                Numbering::assign($restock, 'RS');
            }
            foreach ($clean as $c) {
                $restock->items()->create($c + ['unit_price' => Product::findOrFail($c['product_id'])->selling_price]);
            }
            ActivityLogger::log('restock.save', "Draft restock {$restock->number} disimpan (" . count($clean) . ' produk)', $restock, $store->id);
            return $restock->refresh();
        });
    }

    public function send(Restock $r): void
    {
        DB::transaction(function () use ($r) {
            $r = Restock::lockForUpdate()->findOrFail($r->id);
            if ($r->status !== 'draft') throw new BusinessRuleException('Hanya draft yang bisa ditandai dikirim.');
            if (! $r->ship_date) throw new BusinessRuleException('Isi tanggal pengiriman terlebih dulu.');
            foreach ($r->items()->with('product')->get() as $item) {
                $prod = Product::lockForUpdate()->findOrFail($item->product_id);
                if ($prod->stock_qty < $item->qty_sent) throw new BusinessRuleException("Stok gudang {$prod->label} tidak cukup: tersedia {$prod->stock_qty}, dikirim {$item->qty_sent}.");
                $prod->decrement('stock_qty', $item->qty_sent); // keluar gudang saat dikirim
            }
            $r->update(['status' => 'sent']);
            ActivityLogger::log('restock.send', "Restock {$r->number} dikirim, stok gudang berkurang. Stok toko belum bertambah sampai penerimaan dikonfirmasi.", $r, $r->store_id);
        });
    }

    /**
     * Konfirmasi penerimaan (boleh bertahap). $seq = confirm_count saat form dibuka: bila berbeda, form sudah usang
     * (mis. tombol ditekan dua kali) sehingga ditolak dan stok tidak bertambah ganda.
     */
    public function confirm(Restock $r, array $received, int $seq, array $meta = []): void
    {
        DB::transaction(function () use ($r, $received, $seq, $meta) {
            $r = Restock::lockForUpdate()->with('items')->findOrFail($r->id);
            if (! in_array($r->status, ['sent', 'partial'], true)) throw new BusinessRuleException('Restock ini tidak sedang menunggu penerimaan.');
            if ($r->confirm_count !== $seq) throw new BusinessRuleException('Data restock sudah berubah (mungkin sudah dikonfirmasi). Muat ulang halaman.');

            $n = $r->confirm_count + 1;
            $total = 0;
            foreach ($r->items as $item) {
                $delta = (int) ($received[$item->id] ?? 0);
                if ($delta < 0 || $delta > $item->qty_sent - $item->qty_received) {
                    throw new BusinessRuleException("Jumlah diterima untuk {$item->product->label} tidak valid (maks " . ($item->qty_sent - $item->qty_received) . ').');
                }
                if ($delta === 0) continue;
                $bucket = $item->condition === 'baik' ? StockLedger::SELLABLE : StockLedger::DAMAGED;
                $this->ledger->post($r->store_id, $item->product_id, $bucket, 'restock_in', $delta, "restock:{$r->id}:c{$n}:i{$item->id}",
                    null, 'restock', $r->id, $meta['received_date'] ?? now()->toDateString());
                $item->increment('qty_received', $delta);
                $total += $delta;
            }
            if ($total === 0) throw new BusinessRuleException('Isi jumlah diterima minimal pada satu produk.');

            $r->refresh()->load('items');
            $full = $r->items->every(fn ($i) => $i->qty_received >= $i->qty_sent);
            $r->update([
                'status' => $full ? 'received' : 'partial', 'confirm_count' => $n,
                'received_date' => $meta['received_date'] ?? now()->toDateString(),
                'received_by' => $meta['received_by'] ?? $r->received_by,
            ]);
            ActivityLogger::log('restock.confirm', "Penerimaan restock {$r->number}: {$total} unit masuk stok toko" . ($full ? '' : ' (sebagian)'), $r, $r->store_id,
                null, ['total_diterima' => $total, 'status' => $r->status]);
        });
    }

    public function cancel(Restock $r, string $reason): void
    {
        if (trim($reason) === '') throw new BusinessRuleException('Alasan pembatalan wajib diisi.');
        DB::transaction(function () use ($r, $reason) {
            $r = Restock::lockForUpdate()->findOrFail($r->id);
            if ($r->status === 'cancelled') throw new BusinessRuleException('Restock sudah dibatalkan.');
            $reversed = in_array($r->status, ['partial', 'received'], true) ? $this->ledger->reverse("restock:{$r->id}:", $reason) : 0;
            if (in_array($r->status, ['sent', 'partial', 'received'], true)) {
                // Seluruh qty dikirim kembali ke gudang. Jika ada yang hilang di jalan, catat lewat penyesuaian stok.
                foreach ($r->items as $item) Product::whereKey($item->product_id)->increment('stock_qty', $item->qty_sent);
            }
            $r->update(['status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_at' => now()]);
            ActivityLogger::log('restock.cancel', "Restock {$r->number} dibatalkan ({$reversed} baris ledger direversal)", $r, $r->store_id, null, null, $reason);
        });
    }
}