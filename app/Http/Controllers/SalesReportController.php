<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RunsBusinessActions;
use App\Models\{PartnerTerm, Product, SalesReport, Store};
use App\Services\{ReportService, StockLedger};
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    use RunsBusinessActions;

    public function __construct(private ReportService $svc) {}

    public function index(Request $request)
    {
        $reports = SalesReport::with('store')
            ->when($request->store_id, fn ($q, $v) => $q->where('store_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('period_end', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('period_end', '<=', $v))
            ->latest('period_end')->latest('id')->paginate(15)->withQueryString();
        return view('reports.index', ['reports' => $reports, 'stores' => Store::orderBy('name')->get()]);
    }

    private function formData(SalesReport $report, ?string $mode = null): array
    {
        $storeId = $report->store_id;
        return [
            'report' => $report, 'mode' => $mode, 'stores' => Store::whereIn('status', ['active', 'paused'])->orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'stock' => $storeId ? collect(StockLedger::summary($storeId))->mapWithKeys(fn ($r) => [$r['product']->id => $r['sellable']]) : collect(),
            'term' => $storeId ? PartnerTerm::activeOn($storeId, today()->toDateString()) : null,
        ];
    }

    public function create(Request $request)
    {
        return view('reports.form', $this->formData(new SalesReport(['store_id' => $request->store_id, 'received_at' => today()])));
    }

    private function validated(Request $request, bool $revise = false): array
    {
        return $request->validate([
            'store_id' => $revise ? 'nullable' : 'required|exists:stores,id', 'period_start' => $revise ? 'nullable' : 'required|date', 'period_end' => $revise ? 'nullable' : 'required|date|after_or_equal:period_start',
            'received_at' => 'nullable|date', 'source' => 'nullable|string|max:120', 'reported_total' => 'nullable|integer|min:0',
            'adjustments_amount' => 'nullable|integer', 'adjustments_note' => 'nullable|string|max:500', 'custom_store_share' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000', 'request_key' => 'required|string|max:64', 'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png,csv,xlsx|max:5120',
            'reason' => $revise ? 'required|string|max:500' : 'nullable',
            'lines' => 'required|array|min:1', 'lines.*.product_id' => 'required|exists:products,id', 'lines.*.qty_sold' => 'required|integer|min:0|max:1000000',
            'lines.*.unit_price' => 'nullable|integer|min:0', 'lines.*.discount_amount' => 'nullable|integer|min:0', 'lines.*.return_qty' => 'nullable|integer|min:0',
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        return $this->attempt(function () use ($request, $d) {
            $r = $this->svc->save($d, $d['lines']);
            if ($p = $this->storeProof($request, 'report')) $r->update(['proof_path' => $p]);
            return $r;
        }, 'Draft laporan disimpan. Belum memengaruhi stok maupun bagi hasil sampai terverifikasi.', fn ($r) => route('reports.show', $r));
    }

    public function show(SalesReport $report)
    {
        $report->load('store', 'lines.product', 'revisions', 'obligation.allocations.payment');
        return view('reports.show', ['report' => $report, 'issues' => $this->svc->issues($report)]);
    }

    public function edit(Request $request, SalesReport $report)
    {
        $mode = $request->boolean('revise') && $report->status === 'verified' ? 'revise' : null;
        abort_unless($report->isEditable() || $mode, 403, 'Laporan terverifikasi hanya bisa diubah lewat revisi.');
        return view('reports.form', $this->formData($report->load('lines'), $mode));
    }

    public function update(Request $request, SalesReport $report)
    {
        $d = $this->validated($request);
        return $this->attempt(function () use ($request, $d, $report) {
            $r = $this->svc->save($d + ['store_id' => $report->store_id, 'period_start' => $report->period_start->toDateString(), 'period_end' => $report->period_end->toDateString()], $d['lines'], $report);
            if ($p = $this->storeProof($request, 'report')) $r->update(['proof_path' => $p]);
        }, 'Draft laporan diperbarui.', route('reports.show', $report));
    }

    public function submit(SalesReport $report) { return $this->attempt(fn () => $this->svc->submit($report), 'Laporan diajukan untuk verifikasi.'); }
    public function verify(SalesReport $report) { return $this->attempt(fn () => $this->svc->verify($report), 'Laporan diverifikasi. Stok toko dan kewajiban setoran diperbarui.'); }

    public function reject(Request $request, SalesReport $report)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        return $this->attempt(fn () => $this->svc->reject($report, $d['reason']), 'Laporan dikembalikan untuk direvisi.');
    }

    public function revise(Request $request, SalesReport $report)
    {
        $d = $this->validated($request, true);
        return $this->attempt(fn () => $this->svc->revise($report, $d, $d['lines'], $d['reason']), 'Revisi tersimpan. Versi sebelumnya dicatat di riwayat revisi.', route('reports.show', $report));
    }

    public function cancel(Request $request, SalesReport $report)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        return $this->attempt(fn () => $this->svc->cancel($report, $d['reason']), 'Laporan dibatalkan.');
    }
}