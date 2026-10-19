<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTerm extends Model
{
    protected $guarded = [];
    protected $casts = ['effective_from' => 'date', 'effective_to' => 'date'];

    public const SCHEMES = ['percentage' => 'Persentase', 'fixed_per_product' => 'Komisi tetap per produk', 'custom' => 'Khusus (sesuai perjanjian)'];
    public const BASES = ['actual_price' => 'Harga jual aktual (setelah diskon)', 'list_price' => 'Harga jual normal'];

    public function store() { return $this->belongsTo(Store::class); }
    public function commissions() { return $this->hasMany(PartnerTermCommission::class); }

    public static function activeOn(int $storeId, string $date): ?self
    {
        return static::where('store_id', $storeId)->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->first();
    }

    /** Skema harus berlaku penuh selama periode laporan; jika tidak, laporan harus dipecah. */
    public static function coveringPeriod(int $storeId, string $start, string $end): ?self
    {
        $t = static::activeOn($storeId, $start);
        if (! $t) return null;
        return ($t->effective_to === null || $t->effective_to->toDateString() >= $end) ? $t : null;
    }

    public function snapshot(): array
    {
        return [
            'id' => $this->id, 'scheme_type' => $this->scheme_type,
            'store_pct' => $this->store_pct !== null ? (string) $this->store_pct : null,
            'own_pct' => $this->own_pct !== null ? (string) $this->own_pct : null,
            'basis' => $this->basis, 'discount_policy' => $this->discount_policy, 'return_policy' => $this->return_policy,
            'agreement_ref' => $this->agreement_ref,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'commissions' => $this->commissions()->pluck('amount_per_unit', 'product_id')->all(),
        ];
    }
}