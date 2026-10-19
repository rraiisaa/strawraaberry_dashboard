<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ConsignmentStock extends Model
{
    protected $guarded = [];
    protected $casts = ['batch_date' => 'date'];

    public function store() { return $this->belongsTo(Store::class); }
    public function product() { return $this->belongsTo(Product::class); }

    public function getQtyLeftAttribute(): int { return $this->qty_deposited - $this->qty_sold; }
    public function getGrossAttribute(): int { return $this->qty_sold * $this->product->selling_price; }
}
