<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $guarded = [];
    public function payment() { return $this->belongsTo(Payment::class); }
    public function obligation() { return $this->belongsTo(Obligation::class); }
}