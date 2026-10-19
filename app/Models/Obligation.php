<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Obligation extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date', 'billed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function store() { return $this->belongsTo(Store::class)->withTrashed(); }
    public function report() { return $this->belongsTo(SalesReport::class, 'sales_report_id'); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }

    /** Hanya alokasi dari pembayaran TERVERIFIKASI yang mengurangi kewajiban. */
    public function getPaidAttribute(): int
    {
        return (int) $this->allocations()->whereHas('payment', fn ($q) => $q->where('status', 'verified'))->sum('amount');
    }
    /** Alokasi dari pembayaran yang belum diverifikasi (mencegah alokasi berlebih). */
    public function getPendingAllocatedAttribute(): int
    {
        return (int) $this->allocations()->whereHas('payment', fn ($q) => $q->where('status', 'pending'))->sum('amount');
    }
    public function getRemainingAttribute(): int { return $this->cancelled_at ? 0 : $this->amount_due - $this->paid; }
    public function getAvailableToAllocateAttribute(): int { return max(0, $this->amount_due - $this->paid - $this->pending_allocated); }
    public function getDaysLateAttribute(): int
    {
        return ($this->cancelled_at || $this->remaining <= 0 || ! $this->due_date->isPast()) ? 0 : (int) $this->due_date->diffInDays(now()->startOfDay());
    }
    public function getStatusAttribute(): string
    {
        if ($this->cancelled_at) return 'cancelled';
        $paid = $this->paid;
        if ($paid > $this->amount_due) return 'needs_check';
        if ($paid >= $this->amount_due) return 'paid';
        if ($paid > 0) return 'partial';
        return $this->billed_at ? 'awaiting' : 'not_billed';
    }
    public const STATUS_LABELS = ['not_billed' => 'Belum Ditagih', 'awaiting' => 'Menunggu Pembayaran', 'partial' => 'Dibayar Sebagian', 'paid' => 'Lunas', 'needs_check' => 'Perlu Pemeriksaan', 'cancelled' => 'Dibatalkan'];
    public const STATUS_COLORS = ['not_billed' => 'bg-gray-200', 'awaiting' => 'bg-butter', 'partial' => 'bg-lav', 'paid' => 'bg-mint', 'needs_check' => 'bg-berry text-white', 'cancelled' => 'bg-berry-soft/60'];
    public function getStatusLabelAttribute(): string { return self::STATUS_LABELS[$this->status]; }
}