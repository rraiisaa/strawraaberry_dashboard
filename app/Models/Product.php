<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];
    public const CATEGORIES = ['Pin','Sticker','Lanyard','Hairclip','Notepad'];
    public const LOW_STOCK = 10;

    public function salesItems() { return $this->hasMany(SalesItem::class); }
    public function consignments() { return $this->hasMany(ConsignmentStock::class); }

    public function getMarginAttribute(): float {
        return $this->selling_price > 0 ? round(($this->selling_price - $this->cost_price) / $this->selling_price * 100, 1) : 0;
    }
    public function getIsLowStockAttribute(): bool { return $this->stock_qty <= self::LOW_STOCK; }
}
