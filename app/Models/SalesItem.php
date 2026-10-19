<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    protected $guarded = [];
    public function transaction() { return $this->belongsTo(SalesTransaction::class, 'transaction_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
