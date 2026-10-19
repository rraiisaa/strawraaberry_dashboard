<?php
namespace App\Services;

use App\Models\{Obligation, Payment, Restock, SalesReport, Store, StockMovement};
use Carbon\Carbon;

class PartnerStats
{
    public function __construct(public string $from, public string $to, public ?int $storeId = null) {}

    private function scope($q, string $col = 'store_id') { return $this->storeId ? $q->where($col, $this->storeId) : $q; }

    private function obligations()
    {
        return $this->scope(Obligation::whereNull('cancelled_at')->withSum(['allocations as paid_sum' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'verified'))], 'amount'))->get();
    }

    public function cards(): array
    {
        $stores = $this->storeId ? Store::where('id', $this->storeId) : Store::query();
        $verified = $this->scope(SalesReport::where('status', 'verified')->whereBetween('period_end', [$this->from, $this->to]));
        $obs = $this->obligations();
        $open = $obs->filter(fn ($o) => $o->amount_due - (int) $o->paid_sum > 0);

        return [
            'partners' => (clone $stores)->count(),
            'active' => (clone $stores)->where('status', 'active')->count(),
            'paused' => (clone $stores)->where('status', 'paused')->count(),
            'units' => (int) $this->scope(StockMovement::where('bucket', 'sellable'))->sum('qty'),
            'sales' => (int) (clone $verified)->sum('net_sales'),
            'own' => (int) (clone $verified)->sum('own_share'),
            'store' => (int) (clone $verified)->sum('store_share'),
            'received' => (int) $this->scope(Payment::where('status', 'verified')->whereBetween('paid_on', [$this->from, $this->to]))->sum('amount_received'),
            'outstanding' => (int) $open->sum(fn ($o) => $o->amount_due - (int) $o->paid_sum),
            'overdue' => (int) $open->filter(fn ($o) => $o->due_date->lt(today()))->sum(fn ($o) => $o->amount_due - (int) $o->paid_sum),
            'reports_missing' => count($this->missingReports()),
            'reports_pending' => $this->scope(SalesReport::where('status', 'pending_verification'))->count(),
            'payments_pending' => $this->scope(Payment::where('status', 'pending'))->count(),
            'restocks_recent' => $this->scope(Restock::whereIn('status', ['sent', 'partial', 'received'])->where('created_at', '>=', now()->subDays(30)))->count(),
        ];
    }

    /** Mitra aktif yang laporan terakhirnya sudah melewati interval + batas keterlambatan. */
    public function missingReports(): array
    {
        $out = [];
        $stores = ($this->storeId ? Store::where('id', $this->storeId) : Store::query())->where('status', 'active')->get();
        foreach ($stores as $s) {
            $last = SalesReport::where('store_id', $s->id)->whereIn('status', ['pending_verification', 'verified'])->max('period_end');
            $anchor = $last ? Carbon::parse($last) : ($s->start_date ?? $s->created_at);
            $deadline = $anchor->copy()->addDays($s->report_interval_days + $s->report_due_days);
            if (today()->gt($deadline)) $out[] = ['store' => $s, 'since' => $anchor, 'late_days' => (int) $deadline->diffInDays(today())];
        }
        return $out;
    }

    public function alerts(): array
    {
        $a = [];
        foreach ($this->missingReports() as $m) {
            $a[] = ['level' => 'warning', 'text' => "{$m['store']->name}: belum ada laporan sejak " . $m['since']->format('d/m/Y') . " (terlambat {$m['late_days']} hari)", 'url' => route('reports.create', ['store_id' => $m['store']->id])];
        }
        foreach ($this->obligations() as $o) {
            $rem = $o->amount_due - (int) $o->paid_sum;
            if ($rem > 0 && $o->due_date->lt(today())) {
                $a[] = ['level' => 'error', 'text' => "{$o->store->name}: tagihan {$o->report->number} lewat jatuh tempo " . $o->due_date->format('d/m/Y') . ', sisa Rp ' . number_format($rem, 0, ',', '.'), 'url' => route('payments.create', ['store_id' => $o->store_id])];
            } elseif ($rem < 0) {
                $a[] = ['level' => 'error', 'text' => "{$o->store->name}: kewajiban {$o->report->number} terbayar melebihi tagihan (perlu pemeriksaan)", 'url' => route('reports.show', $o->sales_report_id)];
            }
        }
        foreach ($this->scope(SalesReport::where('status', 'pending_verification'))->with('store')->get() as $r) {
            $a[] = ['level' => 'info', 'text' => "Laporan {$r->number} ({$r->store->name}) menunggu verifikasi", 'url' => route('reports.show', $r)];
        }
        foreach ($this->scope(Payment::where('status', 'pending'))->with('store')->get() as $p) {
            $a[] = ['level' => 'info', 'text' => "Setoran {$p->number} ({$p->store->name}) menunggu verifikasi", 'url' => route('payments.show', $p)];
        }
        foreach ($this->scope(Restock::whereIn('status', ['sent', 'partial'])->where('updated_at', '<', now()->subDays(3)))->with('store')->get() as $r) {
            $a[] = ['level' => 'warning', 'text' => "Restock {$r->number} ({$r->store->name}) belum dikonfirmasi lengkap lebih dari 3 hari", 'url' => route('restocks.show', $r)];
        }
        $q = $this->scope(StockMovement::where('bucket', 'quarantine'))->selectRaw('store_id, SUM(qty) q')->groupBy('store_id')->having('q', '>', 0)->get();
        foreach ($q as $row) {
            $a[] = ['level' => 'warning', 'text' => Store::withTrashed()->find($row->store_id)->name . ": {$row->q} unit retur menunggu pemeriksaan", 'url' => route('partners.show', [$row->store_id, 'tab' => 'stok'])];
        }
        $low = $this->scope(StockMovement::where('bucket', 'sellable'))->selectRaw('store_id, product_id, SUM(qty) q')->groupBy('store_id', 'product_id')->having('q', '>=', 0)->having('q', '<=', 2)->get();
        $hadStock = $low->filter(fn ($r) => StockMovement::where(['store_id' => $r->store_id, 'product_id' => $r->product_id, 'type' => 'sale'])->exists());
        foreach ($hadStock as $r) {
            $a[] = ['level' => 'info', 'text' => Store::withTrashed()->find($r->store_id)->name . ': ' . \App\Models\Product::find($r->product_id)->label . " hampir habis ({$r->q} unit)", 'url' => route('restocks.create', ['store_id' => $r->store_id])];
        }
        return $a;
    }

    /** Enam bulan kalender terakhir (termasuk bulan berjalan). Penjualan = periode terjadinya; setoran = tanggal diterima. */
    public function monthly(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) $months[now()->startOfMonth()->subMonths($i)->format('Y-m')] = ['sales' => 0, 'obligation' => 0, 'paid' => 0];
        $start = array_key_first($months) . '-01';

        foreach ($this->scope(SalesReport::where('status', 'verified')->whereDate('period_end', '>=', $start))->get(['period_end', 'net_sales', 'own_share']) as $r) {
            $k = $r->period_end->format('Y-m');
            if (isset($months[$k])) { $months[$k]['sales'] += $r->net_sales; $months[$k]['obligation'] += $r->own_share; }
        }
        foreach ($this->scope(Payment::where('status', 'verified')->whereDate('paid_on', '>=', $start))->get(['paid_on', 'amount_received']) as $p) {
            $k = $p->paid_on->format('Y-m');
            if (isset($months[$k])) $months[$k]['paid'] += $p->amount_received;
        }
        return $months;
    }
}