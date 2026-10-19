<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Restock extends Model
{
    protected $guarded = [];
    protected $casts = ['ship_date' => 'date', 'received_date' => 'date', 'cancelled_at' => 'datetime'];
    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Dikirim', 'partial' => 'Diterima Sebagian', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];
    public const COLORS = ['draft' => 'bg-gray-200', 'sent' => 'bg-butter', 'partial' => 'bg-lav', 'received' => 'bg-mint', 'cancelled' => 'bg-berry-soft/60'];

    public function store() { return $this->belongsTo(Store::class)->withTrashed(); }
    public function items() { return $this->hasMany(RestockItem::class); }
    public function getTotalSentAttribute(): int { return (int) $this->items->sum('qty_sent'); }
    public function getTotalReceivedAttribute(): int { return (int) $this->items->sum('qty_received'); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status]; }
}