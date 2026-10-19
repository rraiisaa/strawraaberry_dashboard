<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\{CashFlow, Product, SalesItem, SalesTransaction};
use Illuminate\Support\Facades\DB;

/**
 * Penjualan langsung dari halaman Aliran Dana: satu form menghasilkan
 * transaksi penjualan + rincian item + catatan kas pemasukan + pengurangan stok gudang (atomik).
 */
class DirectSaleService
{
    public function record(array $data, array $items): SalesTransaction
    {
        return DB::transaction(function () use ($data, $items) {
            $key = 'salereq:' . $data['request_key'];
            if ($dup = CashFlow::where('source_key', $key)->first()) return SalesTransaction::findOrFail($dup->source_id); // klik ganda

            $clean = [];
            foreach ($items as $i) {
                $qty = (int) ($i['qty'] ?? 0);
                if (empty($i['product_id']) || $qty <= 0) continue;
                if (isset($clean[$i['product_id']])) throw new BusinessRuleException('Produk yang sama muncul dua kali. Gabungkan jumlahnya.');
                $clean[$i['product_id']] = ['product_id' => (int) $i['product_id'], 'qty' => $qty, 'unit_price' => ($i['unit_price'] ?? '') !== '' ? (int) $i['unit_price'] : null];
            }
            if (! $clean) throw new BusinessRuleException('Pilih minimal satu produk yang terjual.');

            $trx = SalesTransaction::create(['invoice_number' => Numbering::temp(), 'store_id' => null, 'total_amount' => 0,
                'payment_method' => $data['payment_method'] ?? 'Transfer', 'date' => $data['date']]);
            $trx->update(['invoice_number' => sprintf('PJ-%s-%04d', now()->format('Ym'), $trx->id)]);

            $total = 0; $names = [];
            foreach ($clean as $c) {
                $p = Product::lockForUpdate()->findOrFail($c['product_id']);
                if ($p->stock_qty < $c['qty']) throw new BusinessRuleException("Stok {$p->name} tidak cukup: tersedia {$p->stock_qty}, diminta {$c['qty']}.");
                $price = $c['unit_price'] ?? $p->selling_price;
                $p->decrement('stock_qty', $c['qty']);
                SalesItem::create(['transaction_id' => $trx->id, 'product_id' => $p->id, 'qty' => $c['qty'], 'unit_price' => $price, 'subtotal' => $c['qty'] * $price]);
                $total += $c['qty'] * $price;
                $names[] = "{$p->name} x{$c['qty']}";
            }
            $trx->update(['total_amount' => $total]);

            CashFlow::create([
                'type' => 'income', 'category' => 'Penjualan Langsung', 'amount' => $total, 'date' => $data['date'],
                'description' => trim(($data['description'] ?? '') !== '' ? $data['description'] : 'Penjualan: ' . implode(', ', $names)),
                'source_type' => 'sales_transaction', 'source_id' => $trx->id, 'source_key' => $key,
            ]);
            ActivityLogger::log('sale.direct', "Penjualan langsung {$trx->invoice_number} Rp " . number_format($total, 0, ',', '.') . ', stok gudang berkurang', $trx);
            return $trx;
        });
    }

    /** Batalkan penjualan: stok dikembalikan ke gudang, transaksi dan catatan kas ikut dibersihkan. */
    public function void(CashFlow $cf): void
    {
        DB::transaction(function () use ($cf) {
            $trx = SalesTransaction::with('items')->find($cf->source_id);
            if ($trx) {
                foreach ($trx->items as $item) Product::whereKey($item->product_id)->increment('stock_qty', $item->qty);
                ActivityLogger::log('sale.void', "Penjualan {$trx->invoice_number} dibatalkan, stok dikembalikan ke gudang", $trx);
                $trx->items()->delete();
                $trx->delete();
            }
            $cf->delete();
        });
    }
}