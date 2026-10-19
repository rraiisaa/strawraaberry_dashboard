<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];
    public const CATEGORIES = ['Pin','Sticker','Lanyard','Hairpin', 'Notepad', 'Art Print'];
    public const LOW_STOCK = 10;

    protected static function booted(): void
    {
        static::created(function (Product $p) {
            if (! $p->sku) $p->forceFill(['sku' => 'SJ-' . str_pad($p->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function salesItems() { return $this->hasMany(SalesItem::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }

    public function getMarginAttribute(): float {
        return $this->selling_price > 0 ? round(($this->selling_price - $this->cost_price) / $this->selling_price * 100, 1) : 0;
    }
    public function getIsLowStockAttribute(): bool { return $this->stock_qty <= self::LOW_STOCK; }
    public function getLabelAttribute(): string { return "{$this->name} ({$this->sku})"; }
}