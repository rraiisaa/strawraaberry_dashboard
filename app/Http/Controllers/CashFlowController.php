<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RunsBusinessActions;
use App\Models\{CashFlow, Product};
use App\Services\DirectSaleService;
use Illuminate\Http\Request;

class CashFlowController extends Controller
{
    use RunsBusinessActions;

    public function index()
    {
        return view('cashflow.index', [
            'flows' => CashFlow::latest('date')->latest('id')->paginate(20),
            'incomeCats' => CashFlow::INCOME_CATEGORIES,
            'expenseCats' => CashFlow::EXPENSE_CATEGORIES,
            'products' => Product::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, DirectSaleService $sales)
    {
        $isSale = $request->input('type') === 'income' && $request->input('category') === 'Penjualan Langsung';

        $d = $request->validate([
            'type' => 'required|in:income,expense',
            'category' => 'required|string|max:60',
            'amount' => $isSale ? 'nullable' : 'required|integer|min:1',
            'description' => 'nullable|string|max:200',
            'date' => 'required|date',
            'request_key' => $isSale ? 'required|string|max:64' : 'nullable|string|max:64',
            'items' => $isSale ? 'required|array|min:1' : 'nullable',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.qty' => 'required_with:items|integer|min:1|max:100000',
            'items.*.unit_price' => 'nullable|integer|min:0',
        ]);

        if ($isSale) {
            return $this->attempt(fn () => $sales->record($d, $d['items']), 'Penjualan tercatat. Stok produk di gudang sudah berkurang otomatis.');
        }
        CashFlow::create(collect($d)->only(['type', 'category', 'amount', 'description', 'date'])->all());
        return back()->with('success', 'Catatan kas tersimpan.');
    }

    public function destroy(CashFlow $cashFlow, DirectSaleService $sales)
    {
        if ($cashFlow->source_type === 'sales_transaction') {
            return $this->attempt(fn () => $sales->void($cashFlow), 'Penjualan dibatalkan dan stok dikembalikan ke gudang.');
        }
        if ($cashFlow->source_key) {
            return back()->withErrors(['bisnis' => 'Catatan ini berasal dari modul Titip Jual. Koreksi lewat reversal setoran, bukan dihapus.']);
        }
        $cashFlow->delete();
        return back()->with('success', 'Catatan dihapus.');
    }
}