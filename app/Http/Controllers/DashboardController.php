<?php
namespace App\Http\Controllers;

use App\Models\{CashFlow, Product, SalesItem, SalesTransaction};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Pemasukan = cash_flows(income) + penjualan langsung (sales_transactions tanpa store_id).
     *  Penjualan via mitra masuk lewat "Pelunasan Mitra" saat settlement, jadi tidak dobel hitung. */
    private function income(?int $m = null, ?int $y = null): int
    {
        $cf = CashFlow::where('type', 'income');
        // Penjualan dari Aliran Dana sudah punya catatan kas sendiri; jangan dihitung dua kali.
        $linked = CashFlow::where('source_type', 'sales_transaction')->pluck('source_id');
        $st = SalesTransaction::whereNull('store_id')->whereNotIn('id', $linked);
        if ($y) { $cf->whereYear('date', $y); $st->whereYear('date', $y); }
        if ($m) { $cf->whereMonth('date', $m); $st->whereMonth('date', $m); }
        return (int) $cf->sum('amount') + (int) $st->sum('total_amount');
    }

    private function expense(?int $m = null, ?int $y = null): int
    {
        $q = CashFlow::where('type', 'expense');
        if ($y) $q->whereYear('date', $y);
        if ($m) $q->whereMonth('date', $m);
        return (int) $q->sum('amount');
    }

    public function __invoke(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);

        $kpi = [
            'saldo'         => $this->income() - $this->expense(),
            'income_month'  => $this->income(now()->month, now()->year),
            'expense_month' => $this->expense(now()->month, now()->year),
            'stock_value'   => (int) Product::selectRaw('COALESCE(SUM(stock_qty * cost_price),0) v')->value('v'),
            'avg_margin'    => round(Product::all()->avg('margin') ?? 0, 1),
        ];

        // Tren bulanan sepanjang tahun terpilih
        $labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $incomeSeries = $expenseSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            $incomeSeries[]  = $this->income($m, $year);
            $expenseSeries[] = $this->expense($m, $year);
        }

        // Best seller bulan terpilih
        $best = SalesItem::query()
            ->join('sales_transactions as t', 't.id', '=', 'sales_items.transaction_id')
            ->whereYear('t.date', $year)->whereMonth('t.date', $month)
            ->selectRaw('sales_items.product_id, SUM(sales_items.qty) as total_qty, SUM(sales_items.subtotal) as total_omzet')
            ->groupBy('sales_items.product_id')
            ->orderByDesc('total_qty')->limit(3)->get()
            ->each(fn ($row) => $row->setRelation('product', Product::find($row->product_id)));

        $lowStock = Product::where('stock_qty', '<=', Product::LOW_STOCK)->orderBy('stock_qty')->get();

        return view('dashboard.index', compact('kpi', 'labels', 'incomeSeries', 'expenseSeries', 'best', 'lowStock', 'month', 'year'));
    }
}