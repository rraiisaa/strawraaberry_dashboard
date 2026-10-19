@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $medals = [['1', 'bg-butter', 'Juara 1'], ['2', 'bg-lav', 'Juara 2'], ['3', 'bg-berry-soft/50', 'Juara 3']];
@endphp

<div x-data="{ quick: false, type: 'income' }">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold">Hai, Rai!</h1>
            <p class="text-sm font-semibold text-cocoa/60">Ringkasan usaha kamu hari ini.</p>
        </div>
        <button x-on:click="quick = true" class="btn"><i data-lucide="plus" class="h-4 w-4"></i> Catat kas cepat</button>
    </div>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card bg-gradient-to-br from-white to-berry-soft/30">
            <div class="flex items-center justify-between"><span class="label">Saldo Kas Bersih</span></div>
            <div class="font-display text-2xl font-semibold {{ $kpi['saldo'] < 0 ? 'text-berry' : '' }}">{{ $rp($kpi['saldo']) }}</div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between"><span class="label">Bulan Ini: Masuk vs Keluar</span></div>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-lg font-semibold text-emerald-600">{{ $rp($kpi['income_month']) }}</span>
                <span class="text-xs">vs</span>
                <span class="font-display text-lg font-semibold text-berry">{{ $rp($kpi['expense_month']) }}</span>
            </div>
            @php $tot = max(1, $kpi['income_month'] + $kpi['expense_month']); @endphp
            <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-cloud">
                <div class="bg-mint" style="width: {{ $kpi['income_month'] / $tot * 100 }}%"></div>
                <div class="bg-berry-soft" style="width: {{ $kpi['expense_month'] / $tot * 100 }}%"></div>
            </div>
        </div>
        <div class="card bg-butter/50">
            <div class="flex items-center justify-between"><span class="label">Valuasi Stok Fisik (HPP)</span></div>
            <div class="font-display text-2xl font-semibold">{{ $rp($kpi['stock_value']) }}</div>
        </div>
        <div class="card bg-mint/50">
            <div class="flex items-center justify-between"><span class="label">Rata-rata Profit Margin</span></div>
            <div class="font-display text-2xl font-semibold">{{ $kpi['avg_margin'] }}%</div>
        </div>
    </section>

    <section class="card mt-6">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-display text-xl font-semibold">Tren Pemasukan vs Pengeluaran {{ $year }}</h2>
            <div class="flex gap-1 rounded-xl bg-cloud p-1 text-xs font-bold" x-data="{ mode: 'line' }" x-init="$watch('mode', m => window.switchChart(m))">
                <button x-on:click="mode='line'" :class="mode==='line' && 'bg-white shadow'" class="rounded-lg px-3 py-1">Garis</button>
                <button x-on:click="mode='bar'" :class="mode==='bar' && 'bg-white shadow'" class="rounded-lg px-3 py-1">Batang</button>
            </div>
        </div>
        <div class="h-72"><canvas id="trendChart"></canvas></div>
    </section>

    <section class="mt-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-2xl font-semibold">Best Seller of the Month</h2>
            <form method="GET" class="flex gap-2">
                <select name="month" class="input !w-auto" onchange="this.form.submit()">
                    @foreach ($months as $i => $name)<option value="{{ $i + 1 }}" @selected($month == $i + 1)>{{ $name }}</option>@endforeach
                </select>
                <select name="year" class="input !w-auto" onchange="this.form.submit()">
                    @for ($y = now()->year; $y >= now()->year - 3; $y--)<option @selected($year == $y)>{{ $y }}</option>@endfor
                </select>
            </form>
        </div>

        @forelse ($best as $i => $row)
            @if ($loop->first)<div class="grid gap-4 md:grid-cols-3">@endif
            @php [$emoji, $bg, $title] = $medals[$i]; $p = $row->product; @endphp
            <div class="card {{ $bg }} {{ $i === 0 ? 'md:order-2 md:-mt-3 ring-2 ring-berry' : ($i === 1 ? 'md:order-1' : 'md:order-3') }}">
                <div class="flex items-center justify-between">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white font-display text-2xl font-semibold text-berry shadow-pastel">{{ $emoji }}</span>
                    <span class="pill bg-white/80">{{ $title }}</span>
                </div>
                <h3 class="mt-2 font-display text-xl font-semibold">{{ $p->name }}</h3>
                <span class="pill bg-white/70">{{ $p->category }}</span>
                <dl class="mt-3 space-y-1 text-sm font-semibold">
                    <div class="flex justify-between"><dt>Terjual</dt><dd>{{ $row->total_qty }} pcs</dd></div>
                    <div class="flex justify-between"><dt>Omzet</dt><dd>{{ $rp($row->total_omzet) }}</dd></div>
                    <div class="flex justify-between"><dt>Sisa stok</dt><dd>{{ $p->stock_qty }} pcs</dd></div>
                </dl>
                @if ($p->is_low_stock)
                    <div class="mt-3 rounded-xl bg-berry px-3 py-1.5 text-center text-xs font-bold text-white"> Perlu Restock Segera</div>
                @endif
            </div>
            @if ($loop->last)</div>@endif
        @empty
            <div class="card text-center text-sm font-semibold">Belum ada penjualan di bulan ini. Catat transaksi pertama kamu!</div>
        @endforelse
    </section>

    @if ($lowStock->isNotEmpty())
    <section class="card mt-6 bg-butter/40">
        <h2 class="font-display text-xl font-semibold">Stok Menipis ({{ $lowStock->count() }})</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($lowStock as $p)<span class="pill bg-white">{{ $p->name }} · sisa {{ $p->stock_qty }}</span>@endforeach
        </div>
    </section>
    @endif

    <div x-show="quick" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" x-on:keydown.escape.window="quick = false">
        <form method="POST" action="{{ route('cashflow.store') }}" x-on:click.outside="quick = false" class="card w-full max-w-md space-y-3">
            {{ csrf_field() }}
            <h3 class="font-display text-xl font-semibold">Catat kas cepat</h3>
            <div class="grid grid-cols-2 gap-2 text-sm font-bold">
                <label class="cursor-pointer rounded-xl p-2 text-center" :class="type==='income' ? 'bg-mint' : 'bg-cloud'"><input type="radio" name="type" value="income" x-model="type" class="sr-only">Pemasukan</label>
                <label class="cursor-pointer rounded-xl p-2 text-center" :class="type==='expense' ? 'bg-berry-soft' : 'bg-cloud'"><input type="radio" name="type" value="expense" x-model="type" class="sr-only">Pengeluaran</label>
            </div>
            <div>
                <label class="label">Kategori</label>
                <select name="category" class="input">
                    <template x-if="type==='income'"><optgroup label="Pemasukan">@foreach (array_diff(App\Models\CashFlow::INCOME_CATEGORIES, ['Penjualan Langsung']) as $c)<option>{{ $c }}</option>@endforeach</optgroup></template>
                    <template x-if="type==='expense'"><optgroup label="Pengeluaran">@foreach (App\Models\CashFlow::EXPENSE_CATEGORIES as $c)<option>{{ $c }}</option>@endforeach</optgroup></template>
                </select>
            </div>
            <div><label class="label">Jumlah (Rp)</label><input type="number" name="amount" min="1" required class="input"></div>
            <div><label class="label">Catatan</label><input name="description" class="input" placeholder="mis. Cetak sticker batch Juni"></div>
            <div><label class="label">Tanggal</label><input type="date" name="date" value="{{ now()->toDateString() }}" class="input"></div>
            <div class="flex justify-end gap-2"><button type="button" x-on:click="quick=false" class="btn-soft">Batal</button><button class="btn">Simpan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const rp = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    const data = {
        labels: @json($labels),
        datasets: [
            { label: 'Pemasukan', data: @json($incomeSeries), borderColor: '#2f9e6b', backgroundColor: '#D8F3DC', tension: .4, fill: true, borderWidth: 3, borderRadius: 8 },
            { label: 'Pengeluaran', data: @json($expenseSeries), borderColor: '#FF4D6D', backgroundColor: '#FFB7C5', tension: .4, fill: true, borderWidth: 3, borderRadius: 8 },
        ],
    };
    const cfg = type => ({
        type, data,
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${rp(c.parsed.y)}` } } },
            scales: { y: { ticks: { callback: v => (v / 1000) + 'k' }, grid: { color: '#EDE9FE' } }, x: { grid: { display: false } } },
        },
    });
    let chart = new Chart(document.getElementById('trendChart'), cfg('line'));
    window.switchChart = type => { chart.destroy(); chart = new Chart(document.getElementById('trendChart'), cfg(type)); };
</script>
@endpush