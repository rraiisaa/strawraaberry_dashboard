<?php
namespace App\Http\Controllers;

use App\Models\{CashFlow, ConsignmentStock, Product, Store};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsignmentController extends Controller
{
    /** Hitung bagi hasil untuk satu toko dari seluruh batch yang belum dicairkan. */
    private function summary(Store $store): array
    {
        $rows  = $store->consignments->load('product');
        $gross = $rows->sum(fn ($r) => $r->qty_sold * $r->product->selling_price);
        $pend  = $rows->where('settlement_status', 'pending');
        $pendGross = $pend->sum(fn ($r) => $r->qty_sold * $r->product->selling_price);
        $rate  = (float) $store->commission_rate;

        return [
            'rows'         => $rows,
            'deposited'    => $rows->sum('qty_deposited'),
            'sold'         => $rows->sum('qty_sold'),
            'gross'        => $gross,
            'pending_gross'=> $pendGross,
            'commission'   => (int) round($pendGross * $rate / 100), // hak mitra
            'net'          => (int) round($pendGross * (100 - $rate) / 100), // hak Strawraaberry Journey
            'paid_gross'   => $gross - $pendGross,
        ];
    }

    public function index()
    {
        $stores = Store::with('consignments.product')->get();
        $summaries = $stores->mapWithKeys(fn ($s) => [$s->id => $this->summary($s)]);
        $products = Product::where('stock_qty', '>', 0)->orderBy('name')->get();
        return view('consignments.index', compact('stores', 'summaries', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_id'   => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'qty'        => 'required|integer|min:1',
            'batch_date' => 'required|date',
        ]);

        DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            abort_if($product->stock_qty < $data['qty'], 422, 'Stok gudang tidak cukup.');
            $product->decrement('stock_qty', $data['qty']);
            ConsignmentStock::create([
                'store_id' => $data['store_id'], 'product_id' => $data['product_id'],
                'qty_deposited' => $data['qty'], 'batch_date' => $data['batch_date'],
            ]);
        });

        return back()->with('success', 'Barang titipan tercatat & stok gudang dikurangi.');
    }

    public function updateSold(Request $request, ConsignmentStock $consignment)
    {
        $data = $request->validate(['qty_sold' => "required|integer|min:0|max:{$consignment->qty_deposited}"]);
        $consignment->update($data);
        return back()->with('success', 'Jumlah terjual diperbarui.');
    }

    /** Cairkan semua batch pending → catat pemasukan (bagian bersih) + fee mitra sebagai pengeluaran informatif. */
    public function settle(Store $store)
    {
        $s = $this->summary($store);
        abort_if($s['pending_gross'] <= 0, 422, 'Tidak ada penjualan yang bisa dicairkan.');

        DB::transaction(function () use ($store, $s) {
            $store->consignments()->where('settlement_status', 'pending')->where('qty_sold', '>', 0)
                ->update(['settlement_status' => 'paid']);
            CashFlow::create([
                'type' => 'income', 'category' => 'Pelunasan Mitra', 'amount' => $s['net'],
                'description' => "Settlement {$store->name} (bersih setelah komisi {$store->commission_rate}%)",
                'date' => now(),
            ]);
        });

        return back()->with('success', 'Settlement dicatat sebagai pemasukan kas');
    }

    /** Surat jalan / invoice tagihan (halaman siap cetak / Save as PDF). */
    public function invoice(Store $store)
    {
        return view('consignments.invoice', ['store' => $store, 's' => $this->summary($store), 'number' => 'INV-' . now()->format('Ymd') . '-' . $store->id]);
    }
}