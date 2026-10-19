<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesReportLine extends Model
{
    protected $guarded = [];
    public function product() { return $this->belongsTo(Product::class); }
    public function getNetUnitsAttribute(): int { return $this->qty_sold - $this->return_qty; }
}