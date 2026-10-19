<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    protected $guarded = [];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'received_at' => 'date', 'verified_at' => 'datetime', 'term_snapshot' => 'array', 'is_late' => 'boolean'];
    public const STATUSES = ['draft' => 'Draft', 'pending_verification' => 'Menunggu Verifikasi', 'verified' => 'Terverifikasi', 'needs_revision' => 'Perlu Revisi', 'cancelled' => 'Dibatalkan'];
    public const COLORS = ['draft' => 'bg-gray-200', 'pending_verification' => 'bg-butter', 'verified' => 'bg-mint', 'needs_revision' => 'bg-lav', 'cancelled' => 'bg-berry-soft/60'];

    public function store() { return $this->belongsTo(Store::class)->withTrashed(); }
    public function term() { return $this->belongsTo(PartnerTerm::class, 'partner_term_id'); }
    public function lines() { return $this->hasMany(SalesReportLine::class); }
    public function revisions() { return $this->hasMany(SalesReportRevision::class)->orderByDesc('revision_no'); }
    public function obligation() { return $this->hasOne(Obligation::class); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status]; }
    public function isEditable(): bool { return in_array($this->status, ['draft', 'needs_revision'], true); }
}