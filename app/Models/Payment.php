<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['paid_on' => 'date', 'verified_at' => 'datetime'];
    public const STATUSES = ['pending' => 'Menunggu Verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak', 'reversed' => 'Direversal'];
    public const COLORS = ['pending' => 'bg-butter', 'verified' => 'bg-mint', 'rejected' => 'bg-berry-soft/60', 'reversed' => 'bg-gray-200'];

    public function store() { return $this->belongsTo(Store::class)->withTrashed(); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
    public function getAllocatedAttribute(): int { return (int) $this->allocations()->sum('amount'); }
    /** Kelebihan bayar: tetap saldo lebih milik mitra, BUKAN pendapatan, sampai dialokasikan. */
    public function getUnallocatedAttribute(): int { return $this->amount_received - $this->allocated; }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status]; }
}