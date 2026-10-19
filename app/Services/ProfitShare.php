<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;

/**
 * Mesin bagi hasil MURNI (tanpa database) agar mudah diuji. Semua uang = integer rupiah.
 *
 * gross       = SUM(qty_sold * unit_price)
 * discount    = SUM(diskon baris)
 * returns     = SUM(return_qty * unit_price)
 * net_sales   = gross - discount - returns + adjustments(disetujui)
 * base        = basis 'actual_price' : net_sales
 *               basis 'list_price'   : SUM((qty_sold - return_qty) * list_price) + adjustments
 * store_share : percentage  -> round(base * store_pct%)   (half away from zero, dalam basis point)
 *               fixed       -> SUM((qty_sold - return_qty) * komisi_per_unit)
 *               custom      -> nominal yang diinput manual sesuai perjanjian
 * own_share   = base - store_share  (sisa pembulatan selalu ke Strawraaberry, jumlah keduanya selalu = base)
 */
class ProfitShare
{
    public static function calculate(array $lines, array $term, int $adjustments = 0, ?int $customStoreShare = null): array
    {
        $gross = $discount = $returns = $listBase = $units = $fixed = 0;
        $commissions = $term['commissions'] ?? [];

        foreach ($lines as $i => $l) {
            $qty = (int) $l['qty_sold']; $ret = (int) ($l['return_qty'] ?? 0);
            $price = (int) $l['unit_price']; $disc = (int) ($l['discount_amount'] ?? 0);
            $list = (int) ($l['list_price'] ?? $price);
            if ($qty < 0 || $ret < 0 || $price < 0 || $disc < 0) throw new BusinessRuleException('Nilai negatif tidak diperbolehkan pada baris ' . ($i + 1) . '.');
            if ($ret > $qty) throw new BusinessRuleException('Retur melebihi unit terjual pada baris ' . ($i + 1) . '.');
            if ($disc > $qty * $price) throw new BusinessRuleException('Diskon melebihi nilai penjualan pada baris ' . ($i + 1) . '.');

            $gross += $qty * $price;
            $discount += $disc;
            $returns += $ret * $price;
            $units += $qty - $ret;
            $listBase += ($qty - $ret) * $list;
            if ($term['scheme_type'] === 'fixed_per_product' && ! array_key_exists($l['product_id'], $commissions)) {
                throw new BusinessRuleException('Komisi tetap untuk produk ID ' . $l['product_id'] . ' belum diatur pada skema ini.');
            }
            $fixed += ($qty - $ret) * (int) ($commissions[$l['product_id']] ?? 0);
        }

        $net = $gross - $discount - $returns + $adjustments;
        $base = ($term['basis'] ?? 'actual_price') === 'list_price' ? $listBase + $adjustments : $net;

        $store = match ($term['scheme_type']) {
            'percentage' => self::roundDiv($base * self::basisPoints($term['store_pct'] ?? null), 10000),
            'fixed_per_product' => $fixed,
            'custom' => $customStoreShare ?? throw new BusinessRuleException('Skema khusus: isi nominal bagian toko sesuai perjanjian.'),
            default => throw new BusinessRuleException('Skema bagi hasil tidak dikenal.'),
        };

        return [
            'gross' => $gross, 'discount' => $discount, 'returns' => $returns, 'adjustments' => $adjustments,
            'net_sales' => $net, 'base' => $base, 'store_share' => $store, 'own_share' => $base - $store, 'units' => $units,
        ];
    }

    public static function basisPoints($pct): int
    {
        if ($pct === null || $pct === '' || ! is_numeric($pct) || $pct < 0 || $pct > 100) throw new BusinessRuleException('Persentase toko harus 0 sampai 100.');
        return (int) round(((float) $pct) * 100);
    }

    /** Pembulatan setengah menjauhi nol, hanya dengan integer. */
    public static function roundDiv(int $n, int $d): int
    {
        return intdiv(2 * $n + ($n >= 0 ? $d : -$d), 2 * $d);
    }
}