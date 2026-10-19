<?php
namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\{Product, Store};
use Illuminate\Support\Facades\DB;

/** Pergerakan stok manual di toko mitra (retur diperiksa, rusak, hilang, dikembalikan, penyesuaian). Selalu lewat ledger. */
class StockService
{
    public const TYPES = [
        'inspect_ok' => 'Retur pembeli lolos pemeriksaan (karantina menjadi layak jual)',
        'mark_damaged' => 'Tandai rusak (masih berada di toko)',
        'write_off_damaged' => 'Keluarkan barang rusak (dimusnahkan)',
        'return_to_owner' => 'Dikembalikan ke Strawraaberry',
        'write_off_lost' => 'Barang hilang',
        'adjustment' => 'Penyesuaian stok (koreksi)',
    ];

    public function __construct(private StockLedger $ledger) {}

    public function move(Store $store, array $d): void
    {
        DB::transaction(function () use ($store, $d) {
            $type = $d['type']; $qty = (int) $d['qty']; $pid = (int) $d['product_id'];
            $from = $d['from_bucket'] ?? StockLedger::SELLABLE;
            $reason = trim((string) ($d['reason'] ?? ''));
            $key = 'manual:' . $d['request_key'];
            $date = $d['occurred_on'];
            Product::findOrFail($pid);
            if ($qty <= 0 && $type !== 'adjustment') throw new BusinessRuleException('Jumlah harus lebih dari 0.');
            if (in_array($type, ['mark_damaged', 'write_off_damaged', 'write_off_lost', 'adjustment', 'return_to_owner'], true) && $reason === '') {
                throw new BusinessRuleException('Alasan wajib diisi untuk pergerakan stok jenis ini.');
            }

            switch ($type) {
                case 'inspect_ok':
                    $this->ledger->transfer($store->id, $pid, $type, $qty, StockLedger::QUARANTINE, StockLedger::SELLABLE, $key, $reason, 'manual', null, $date); break;
                case 'mark_damaged':
                    if (! in_array($from, [StockLedger::SELLABLE, StockLedger::QUARANTINE], true)) throw new BusinessRuleException('Asal bucket tidak valid.');
                    $this->ledger->transfer($store->id, $pid, $type, $qty, $from, StockLedger::DAMAGED, $key, $reason, 'manual', null, $date); break;
                case 'write_off_damaged':
                    $this->ledger->post($store->id, $pid, StockLedger::DAMAGED, $type, -$qty, $key, $reason, 'manual', null, $date); break;
                case 'write_off_lost':
                    if (! in_array($from, [StockLedger::SELLABLE, StockLedger::QUARANTINE], true)) throw new BusinessRuleException('Asal bucket tidak valid.');
                    $this->ledger->post($store->id, $pid, $from, $type, -$qty, $key, $reason, 'manual', null, $date); break;
                case 'return_to_owner':
                    if (! in_array($from, [StockLedger::SELLABLE, StockLedger::DAMAGED], true)) throw new BusinessRuleException('Hanya stok layak jual atau rusak yang bisa dikembalikan.');
                    $this->ledger->post($store->id, $pid, $from, $type, -$qty, $key, $reason, 'manual', null, $date);
                    // Layak jual kembali ke stok gudang; rusak masuk catatan cacat gudang.
                    Product::whereKey($pid)->increment($from === StockLedger::SELLABLE ? 'stock_qty' : 'defective_qty', $qty);
                    break;
                case 'adjustment':
                    if ($qty === 0) throw new BusinessRuleException('Jumlah penyesuaian tidak boleh 0.');
                    $this->ledger->post($store->id, $pid, StockLedger::SELLABLE, $type, $qty, $key, $reason, 'manual', null, $date); break;
                default: throw new BusinessRuleException('Jenis pergerakan tidak dikenal.');
            }
            ActivityLogger::log('stock.' . $type, self::TYPES[$type] . " ({$qty} unit) pada {$store->name}", $store, $store->id, null, ['product_id' => $pid, 'qty' => $qty, 'from' => $from], $reason ?: null);
        });
    }
}