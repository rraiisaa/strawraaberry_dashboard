<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $guarded = [];
    public function consignments() { return $this->hasMany(ConsignmentStock::class); }
}
