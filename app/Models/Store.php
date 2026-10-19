<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public const STATUSES = ['prospect' => 'Calon Mitra', 'active' => 'Aktif', 'paused' => 'Jeda', 'inactive' => 'Nonaktif'];
    public const STATUS_COLORS = ['prospect' => 'bg-lav', 'active' => 'bg-mint', 'paused' => 'bg-butter', 'inactive' => 'bg-gray-200'];

    public function terms() { return $this->hasMany(PartnerTerm::class)->orderByDesc('effective_from'); }
    public function restocks() { return $this->hasMany(Restock::class); }
    public function reports() { return $this->hasMany(SalesReport::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function obligations() { return $this->hasMany(Obligation::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
    public function logs() { return $this->hasMany(ActivityLog::class); }

    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function getCurrentTermAttribute(): ?PartnerTerm { return PartnerTerm::activeOn($this->id, now()->toDateString()); }

    /** Mitra yang masih punya transaksi atau kewajiban tidak boleh dihapus permanen. */
    public function hasHistory(): bool
    {
        return $this->restocks()->exists() || $this->reports()->exists() || $this->payments()->exists() || $this->movements()->exists();
    }
}