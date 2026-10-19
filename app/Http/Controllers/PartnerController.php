<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RunsBusinessActions;
use App\Models\{ActivityLog, Obligation, PartnerTerm, Payment, Product, Restock, SalesReport, Store, StockMovement};
use App\Services\{ActivityLogger, PartnerStats, StockLedger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerController extends Controller
{
    use RunsBusinessActions;

    public function overview(Request $request)
    {
        [$from, $to, $range] = $this->period($request);
        $storeId = $request->integer('store_id') ?: null;
        $stats = new PartnerStats($from, $to, $storeId);

        return view('partners.overview', [
            'cards' => $stats->cards(), 'alerts' => $stats->alerts(), 'monthly' => $stats->monthly(),
            'logs' => ActivityLog::with('store')->when($storeId, fn ($q) => $q->where('store_id', $storeId))->latest('id')->limit(15)->get(),
            'stores' => Store::orderBy('name')->get(), 'from' => $from, 'to' => $to, 'range' => $range, 'storeId' => $storeId,
        ]);
    }

    private function period(Request $r): array
    {
        $range = $r->input('range', 'year');
        return match ($range) {
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), $range],
            '3m' => [now()->subMonths(2)->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), $range],
            'all' => ['1970-01-01', '2999-12-31', $range],
            'custom' => [$r->input('from', now()->startOfYear()->toDateString()), $r->input('to', now()->toDateString()), $range],
            default => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString(), 'year'],
        };
    }

    public function index(Request $request)
    {
        $q = Store::query()
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('pic_name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->city, fn ($q, $c) => $q->where('city', $c));

        $q = match ($request->input('sort', 'name')) {
            'joined' => $q->orderByDesc('start_date'),
            'activity' => $q->orderByDesc(ActivityLog::select('id')->whereColumn('store_id', 'stores.id')->latest('id')->limit(1)),
            default => $q->orderBy('name'),
        };
        $stores = $q->paginate(15)->withQueryString();
        $ids = $stores->pluck('id');

        return view('partners.index', [
            'stores' => $stores,
            'units' => StockMovement::whereIn('store_id', $ids)->where('bucket', 'sellable')->groupBy('store_id')->selectRaw('store_id, SUM(qty) q')->pluck('q', 'store_id'),
            'owed' => Obligation::whereIn('store_id', $ids)->whereNull('cancelled_at')
                ->withSum(['allocations as paid_sum' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'verified'))], 'amount')->get()
                ->groupBy('store_id')->map(fn ($g) => $g->sum(fn ($o) => $o->amount_due - (int) $o->paid_sum)),
            'cities' => Store::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function create() { return view('partners.form', ['store' => new Store(['status' => 'active', 'report_interval_days' => 30, 'report_due_days' => 7, 'payment_due_days' => 14, 'start_date' => now()]), 'products' => Product::orderBy('name')->get()]); }

    private function rules(?Store $store = null): array
    {
        return [
            'name' => 'required|string|max:120', 'owner_name' => 'nullable|string|max:120', 'pic_name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^[0-9+\-\s()]{6,30}$/'], 'email' => 'nullable|email|max:160',
            'address' => 'required|string|max:500', 'city' => 'required|string|max:100', 'province' => 'nullable|string|max:100',
            'maps_url' => 'nullable|url|max:500', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180',
            'start_date' => 'required|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:' . implode(',', array_keys(Store::STATUSES)),
            'report_schedule' => 'nullable|string|max:160', 'payment_schedule' => 'nullable|string|max:160',
            'report_interval_days' => 'required|integer|min:1|max:365', 'report_due_days' => 'required|integer|min:0|max:90', 'payment_due_days' => 'required|integer|min:0|max:180',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function store(Request $request)
    {
        $termRules = [
            'scheme_type' => 'required|in:percentage,fixed_per_product,custom', 'store_pct' => 'required_if:scheme_type,percentage|nullable|numeric|between:0,100',
            'basis' => 'required|in:actual_price,list_price', 'agreement_ref' => 'nullable|string|max:160',
            'discount_policy' => 'nullable|string|max:1000', 'return_policy' => 'nullable|string|max:1000', 'custom_description' => 'nullable|string|max:1000',
        ];
        $data = $request->validate($this->rules() + $termRules);

        return $this->attempt(function () use ($data) {
            return DB::transaction(function () use ($data) {
                $storeData = collect($data)->only(array_keys($this->rules()))->all();
                $store = Store::create($storeData + ['commission_rate' => $data['store_pct'] ?? 0]);
                $store->forceFill(['code' => 'MT-' . str_pad($store->id, 4, '0', STR_PAD_LEFT)])->save();
                $store->terms()->create([
                    'scheme_type' => $data['scheme_type'], 'store_pct' => $data['scheme_type'] === 'percentage' ? $data['store_pct'] : null,
                    'own_pct' => $data['scheme_type'] === 'percentage' ? 100 - $data['store_pct'] : null, 'basis' => $data['basis'],
                    'discount_policy' => $data['discount_policy'] ?? null, 'return_policy' => $data['return_policy'] ?? null, 'custom_description' => $data['custom_description'] ?? null,
                    'effective_from' => $data['start_date'], 'agreement_ref' => $data['agreement_ref'] ?? null, 'change_note' => 'Skema awal',
                ]);
                ActivityLogger::log('partner.create', "Mitra {$store->name} ({$store->code}) dibuat", $store, $store->id);
                return $store;
            });
        }, 'Mitra berhasil ditambahkan.', fn ($s) => route('partners.show', $s));
    }

    public function edit(Store $store) { return view('partners.form', ['store' => $store, 'products' => collect()]); }

    public function update(Request $request, Store $store)
    {
        $data = $request->validate($this->rules($store));
        $before = $store->only(array_keys($data));
        $store->update($data);
        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', $before)));
        ActivityLogger::log($before['status'] !== $data['status'] ? 'partner.status' : 'partner.update', "Profil {$store->name} diperbarui" . ($changed ? ' (' . implode(', ', $changed) . ')' : ''), $store, $store->id, $before, $data);
        return redirect()->route('partners.show', $store)->with('success', 'Profil mitra diperbarui.');
    }

    public function archive(Request $request, Store $store)
    {
        $store->delete(); // soft delete: seluruh transaksi, stok, dokumen, audit tetap tersimpan
        ActivityLogger::log('partner.archive', "Mitra {$store->name} diarsipkan", $store, $store->id);
        return redirect()->route('partners.index')->with('success', 'Mitra diarsipkan. Seluruh riwayat tetap tersimpan.');
    }

    public function restore(Store $store)
    {
        $store->restore();
        ActivityLogger::log('partner.restore', "Mitra {$store->name} diaktifkan kembali dari arsip", $store, $store->id);
        return redirect()->route('partners.show', $store)->with('success', 'Mitra diaktifkan kembali dari arsip.');
    }

    public function show(Request $request, Store $store)
    {
        $tab = $request->input('tab', 'ringkasan');
        $obligations = $store->obligations()->with('report')->withSum(['allocations as paid_sum' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'verified'))], 'amount')->orderByDesc('due_date')->get();
        $verified = $store->reports()->where('status', 'verified');

        $monthly = [];
        foreach ((clone $verified)->get(['period_end', 'net_sales']) as $r) $monthly[$r->period_end->format('Y-m')] = ($monthly[$r->period_end->format('Y-m')] ?? 0) + $r->net_sales;
        ksort($monthly);

        return view('partners.show', [
            'store' => $store, 'tab' => $tab, 'obligations' => $obligations, 'monthly' => $monthly,
            'stock' => StockLedger::summary($store->id),
            'term' => PartnerTerm::activeOn($store->id, today()->toDateString()), 'terms' => $store->terms()->with('commissions.product')->get(),
            'restocks' => $store->restocks()->with('items')->latest('id')->limit(30)->get(),
            'reports' => $store->reports()->latest('period_end')->limit(30)->get(),
            'payments' => $store->payments()->with('allocations')->latest('paid_on')->limit(30)->get(),
            'logs' => $store->logs()->latest('id')->limit(40)->get(),
            'totals' => ['sales' => (int) (clone $verified)->sum('net_sales'), 'own' => (int) (clone $verified)->sum('own_share'), 'store' => (int) (clone $verified)->sum('store_share'),
                'due' => (int) $obligations->sum('amount_due'), 'paid' => (int) $obligations->sum('paid_sum'), 'remaining' => (int) $obligations->sum(fn ($o) => $o->amount_due - (int) $o->paid_sum),
                'overdue' => (int) $obligations->filter(fn ($o) => $o->due_date->lt(today()))->sum(fn ($o) => max(0, $o->amount_due - (int) $o->paid_sum))],
            'products' => Product::orderBy('name')->get(), 'stockTypes' => \App\Services\StockService::TYPES,
        ]);
    }
}