<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ledger append-only: tidak boleh diubah atau dihapus. Koreksi = baris reversal baru. */
class StockMovement extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['occurred_on' => 'date'];

    public const TYPES = [
        'restock_in' => 'Barang masuk (restock)', 'sale' => 'Terjual', 'customer_return' => 'Retur dari pembeli',
        'inspect_ok' => 'Lolos pemeriksaan retur', 'mark_damaged' => 'Ditandai rusak', 'return_to_owner' => 'Dikembalikan ke Strawraaberry',
        'write_off_lost' => 'Hilang', 'write_off_damaged' => 'Rusak dikeluarkan', 'adjustment' => 'Penyesuaian', 'reversal' => 'Reversal',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger stok tidak boleh diubah.'));
        static::deleting(fn () => throw new \LogicException('Ledger stok tidak boleh dihapus.'));
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function store() { return $this->belongsTo(Store::class); }
}