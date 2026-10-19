<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['before' => 'array', 'after' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log tidak boleh diubah.'));
        static::deleting(fn () => throw new \LogicException('Audit log tidak boleh dihapus.'));
    }
    public function store() { return $this->belongsTo(Store::class)->withTrashed(); }

    public function url(): ?string
    {
        return match ($this->entity_type) {
            'restock' => route('restocks.show', $this->entity_id),
            'report' => route('reports.show', $this->entity_id),
            'payment' => route('payments.show', $this->entity_id),
            'store' => route('partners.show', $this->entity_id),
            default => $this->store_id ? route('partners.show', $this->store_id) : null,
        };
    }
}