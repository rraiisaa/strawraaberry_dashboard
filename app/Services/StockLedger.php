<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\{Product, StockMovement};

/**
 * Satu-satunya jalan mengubah stok konsinyasi. Stok = SUM(ledger). Tidak ada kolom stok yang diedit langsung.
 * Bucket: sellable (layak jual di toko), quarantine (retur pembeli belum diperiksa), damaged (rusak masih di toko).
 */
class StockLedger
{
    public const SELLABLE = 'sellable', QUARANTINE = 'quarantine', DAMAGED = 'damaged';

    public static function balance(int $storeId, int $productId, string $bucket = self::SELLABLE): int
    {
        return (int) StockMovement::where(['store_id' => $storeId, 'product_id' => $productId, 'bucket' => $bucket])->sum('qty');
    }

    /** Idempoten: kunci yang sama tidak akan pernah menulis dua kali. */
    public function post(int $storeId, int $productId, string $bucket, string $type, int $qty, string $key, ?string $reason = null,
                         ?string $refType = null, ?int $refId = null, ?string $date = null, ?int $reversalOf = null): ?StockMovement
    {
        if ($qty === 0) return null;
        if ($existing = StockMovement::where('idempotency_key', $key)->first()) return $existing;

        if ($qty < 0) {
            $bal = self::balance($storeId, $productId, $bucket);
            if ($bal + $qty < 0) {
                $name = Product::find($productId)?->label ?? "produk #{$productId}";
                throw new BusinessRuleException("Stok {$bucket} {$name} tidak cukup: tersedia {$bal}, diminta " . abs($qty) . '.');
            }
        }

        return StockMovement::create([
            'store_id' => $storeId, 'product_id' => $productId, 'bucket' => $bucket, 'type' => $type, 'qty' => $qty,
            'occurred_on' => $date ?? now()->toDateString(), 'reference_type' => $refType, 'reference_id' => $refId,
            'reversal_of_id' => $reversalOf, 'idempotency_key' => $key, 'reason' => $reason, 'actor' => ActivityLogger::actor(),
        ]);
    }

    /** Pindah antar-bucket (dua baris ledger yang saling meniadakan, jadi barang tidak terhitung dua kali). */
    public function transfer(int $storeId, int $productId, string $type, int $qty, string $from, string $to, string $key,
                             ?string $reason = null, ?string $refType = null, ?int $refId = null, ?string $date = null): void
    {
        $this->post($storeId, $productId, $from, $type, -$qty, $key . ':out', $reason, $refType, $refId, $date);
        $this->post($storeId, $productId, $to, $type, $qty, $key . ':in', $reason, $refType, $refId, $date);
    }

    /** Balik semua baris yang kuncinya berawalan $keyPrefix dengan baris reversal baru (aslinya tidak diubah). */
    public function reverse(string $keyPrefix, string $reason): int
    {
        $rows = StockMovement::where('idempotency_key', 'like', $keyPrefix . '%')
            ->whereNull('reversal_of_id')->where('type', '!=', 'reversal')->orderByDesc('id')->get();
        $n = 0;
        foreach ($rows as $r) {
            if (StockMovement::where('reversal_of_id', $r->id)->exists()) continue;
            $this->post($r->store_id, $r->product_id, $r->bucket, 'reversal', -$r->qty, $r->idempotency_key . ':rev', $reason,
                $r->reference_type, $r->reference_id, now()->toDateString(), $r->id);
            $n++;
        }
        return $n;
    }

    /** Ringkasan per produk untuk satu mitra, dari ledger. */
    public static function summary(int $storeId): array
    {
        $rows = StockMovement::where('store_id', $storeId)
            ->selectRaw('product_id, type, bucket, SUM(qty) AS q, MAX(occurred_on) AS last_on')
            ->groupBy('product_id', 'type', 'bucket')->get();
        $products = Product::whereIn('id', $rows->pluck('product_id')->unique())->get()->keyBy('id');

        $out = [];
        foreach ($rows->groupBy('product_id') as $pid => $set) {
            $g = fn (string $type, ?string $bucket = null) => (int) $set->where('type', $type)
                ->when($bucket, fn ($c) => $c->where('bucket', $bucket))->sum('q');
            $p = $products[$pid] ?? null;
            $out[] = [
                'product' => $p,
                'in' => $g('restock_in'),
                'sold' => -$g('sale'),
                'customer_return' => $g('customer_return'),
                'inspect_ok' => $g('inspect_ok', self::SELLABLE),
                'returned' => -$g('return_to_owner'),
                'damaged_marked' => $g('mark_damaged', self::DAMAGED),
                'lost' => -$g('write_off_lost'),
                'corrections' => $g('adjustment', self::SELLABLE) + $g('reversal', self::SELLABLE),
                'sellable' => (int) $set->where('bucket', self::SELLABLE)->sum('q'),
                'quarantine' => (int) $set->where('bucket', self::QUARANTINE)->sum('q'),
                'damaged' => (int) $set->where('bucket', self::DAMAGED)->sum('q'),
                'last_on' => $set->max('last_on'),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['product']->name ?? '', $b['product']->name ?? ''));
        return $out;
    }
}