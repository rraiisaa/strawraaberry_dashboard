<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesTransaction extends Model
{
    protected $guarded = [];
    protected $casts = ['date' => 'date'];
    public function items() { return $this->hasMany(SalesItem::class, 'transaction_id'); }
    public function store() { return $this->belongsTo(Store::class); }
}
