<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RestockItem extends Model
{
    protected $guarded = [];
    public function restock() { return $this->belongsTo(Restock::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function getQtyGapAttribute(): int { return $this->qty_sent - $this->qty_received; }
}