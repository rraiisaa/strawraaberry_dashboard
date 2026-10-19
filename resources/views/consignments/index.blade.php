@extends('layouts.app')
@section('title', 'Titip Jual')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $stat = [
        ['Total mitra', $cards['partners'], 'bg-white', 'Semua mitra yang tidak diarsipkan.'],
        ['Mitra aktif', $cards['active'], 'bg-mint/60', 'Mitra berstatus Aktif.'],
        ['Mitra jeda', $cards['paused'], 'bg-butter/60', 'Kerja sama sedang dijeda.'],
        ['Unit di toko', number_format($cards['units'], 0, ',', '.') . ' unit', 'bg-lav/60', 'Stok layak jual di seluruh toko menurut ledger. Tidak termasuk karantina dan barang rusak.'],
        ['Penjualan bersih', $rp($cards['sales']), 'bg-white', 'Penjualan terverifikasi pada periode terpilih, setelah diskon, retur, dan penyesuaian. BUKAN pendapatan Strawraaberry.'],
        ['Bagian Strawraaberry', $rp($cards['own']), 'bg-mint/60', 'Hak Strawraaberry dari laporan terverifikasi (kewajiban setoran mitra). Belum tentu sudah diterima.'],
        ['Bagian mitra', $rp($cards['store']), 'bg-berry-soft/30', 'Hak toko dari laporan terverifikasi pada periode terpilih.'],
        ['Setoran diterima', $rp($cards['received']), 'bg-mint/60', 'Setoran terverifikasi dengan tanggal bayar pada periode terpilih. Termasuk kelebihan bayar bila ada.'],
        ['Belum disetorkan', $rp($cards['outstanding']), 'bg-butter/60', 'Sisa seluruh kewajiban aktif saat ini (tidak dibatasi periode).'],
        ['Lewat jatuh tempo', $rp($cards['overdue']), $cards['overdue'] > 0 ? 'bg-berry-soft/50' : 'bg-white', 'Bagian dari belum disetorkan yang jatuh temponya sudah lewat.'],
        ['Laporan belum diterima', $cards['reports_missing'], 'bg-white', 'Mitra aktif yang lewat dari interval laporan ditambah batas keterlambatannya.'],
        ['Menunggu verifikasi', $cards['reports_pending'] . ' laporan, ' . $cards['payments_pending'] . ' setoran', 'bg-lav/60', 'Belum menjadi angka final sampai diverifikasi.'],
        ['Restock 30 hari', $cards['restocks_recent'], 'bg-white', 'Restock berstatus dikirim, sebagian, atau diterima dalam 30 hari terakhir.'],
    ];
@endphp

@include('partners._subnav')

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display text-3xl font-semibold">Mitra Titip Jual</h1>
        <p class="text-sm font-semibold text-cocoa/60">Periode {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} s/d {{ $to === '2999-12-31' ? 'sekarang' : \Carbon\Carbon::parse($to)->format('d/m/Y') }}. Sumber: laporan dan setoran terverifikasi.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('partners.create') }}" class="btn-soft">Tambah mitra</a>
        <a href="{{ route('restocks.create') }}" class="btn-soft">Buat restock</a>
        <a href="{{ route('reports.create') }}" class="btn">Masukkan laporan penjualan</a>
    </div>
</div>

<form method="GET" class="card mb-5 flex flex-wrap items-end gap-3" x-data="{ range: '{{ $range }}' }">
    <div><label class="label">Periode</label>
        <select name="range" x-model="range" class="input" onchange="if(this.value!=='custom') this.form.submit()">
            <option value="month">Bulan ini</option><option value="3m">3 bulan terakhir</option><option value="year">Tahun ini</option><option value="all">Semua data</option><option value="custom">Rentang khusus</option>
        </select></div>
    <div x-show="range==='custom'" x-cloak class="flex gap-2">
        <div><label class="label">Dari</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
        <div><label class="label">Sampai</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
    </div>
    <div><label class="label">Mitra</label>
        <select name="store_id" class="input" onchange="this.form.submit()"><option value="">Semua mitra</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected($storeId == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <button class="btn">Terapkan</button>
</form>

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ($stat as [$label, $value, $bg, $tip])
        <div class="card {{ $bg }} !p-4" x-data="{ tip: false }">
            <div class="flex items-start justify-between gap-1">
                <span class="label !mb-0">{{ $label }}</span>
                <button type="button" @click="tip = !tip" class="rounded-full bg-white/80 px-1.5 text-xs font-bold" aria-label="Penjelasan {{ $label }}">?</button>
            </div>
            <div class="mt-1 font-display text-xl font-semibold">{{ $value }}</div>
            <p x-show="tip" x-cloak x-transition class="mt-2 text-xs font-semibold text-cocoa/70">{{ $tip }}</p>
        </div>
    @endforeach
</section>

<section class="card mt-6">
    <h2 class="font-display text-xl font-semibold">Penjualan, kewajiban, dan setoran 6 bulan kalender terakhir</h2>
    <p class="mb-3 text-xs font-semibold text-cocoa/60">Penjualan dan kewajiban mengikuti periode terjadinya penjualan. Setoran mengikuti tanggal pembayaran diterima. Hanya data terverifikasi.</p>
    @if (collect($monthly)->sum(fn ($m) => $m['sales'] + $m['paid'] + $m['obligation']) == 0)
        <div class="py-10 text-center text-sm font-semibold">Belum ada laporan atau setoran terverifikasi pada enam bulan terakhir.</div>
    @else
        <div class="h-72"><canvas id="chart"></canvas></div>
    @endif
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card">
        <h2 class="mb-3 font-display text-xl font-semibold">Peringatan dan tindak lanjut ({{ count($alerts) }})</h2>
        @forelse ($alerts as $a)
            <a href="{{ $a['url'] }}" class="mb-2 flex items-start gap-3 rounded-xl p-3 text-sm font-semibold hover:brightness-95 {{ ['error' => 'bg-berry-soft/50', 'warning' => 'bg-butter/70', 'info' => 'bg-lav/60'][$a['level']] }}">
                <span class="pill bg-white">{{ ['error' => 'Mendesak', 'warning' => 'Perhatian', 'info' => 'Info'][$a['level']] }}</span><span>{{ $a['text'] }}</span>
            </a>
        @empty
            <div class="py-8 text-center text-sm font-semibold">Tidak ada peringatan. Semua jadwal dan saldo dalam kondisi baik.</div>
        @endforelse
    </section>

    <section class="card">
        <h2 class="mb-3 font-display text-xl font-semibold">Aktivitas terbaru</h2>
        @forelse ($logs as $l)
            <div class="flex items-start justify-between gap-3 border-b border-cloud py-2 text-sm">
                <div><div class="font-bold">{{ $l->description }}</div>
                    <div class="text-xs text-cocoa/60">{{ $l->created_at->format('d/m/Y H:i') }} | {{ $l->store?->name ?? 'Umum' }} | {{ $l->actor }}</div></div>
                @if ($u = $l->url())<a href="{{ $u }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a>@endif
            </div>
        @empty
            <div class="py-8 text-center text-sm font-semibold">Belum ada aktivitas. Mulai dengan menambah mitra.</div>
        @endforelse
    </section>
</div>
@endsection

@push('scripts')
@if (collect($monthly)->sum(fn ($m) => $m['sales'] + $m['paid'] + $m['obligation']) != 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const M = @json($monthly), rp = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    const names = {'01':'Jan','02':'Feb','03':'Mar','04':'Apr','05':'Mei','06':'Jun','07':'Jul','08':'Agu','09':'Sep','10':'Okt','11':'Nov','12':'Des'};
    new Chart(document.getElementById('chart'), { type: 'bar', data: {
        labels: Object.keys(M).map(k => names[k.slice(5)] + ' ' + k.slice(2, 4)),
        datasets: [
            { label: 'Penjualan bersih', data: Object.values(M).map(m => m.sales), backgroundColor: '#D8F3DC', borderRadius: 8 },
            { label: 'Kewajiban setoran', data: Object.values(M).map(m => m.obligation), backgroundColor: '#FFF3B0', borderRadius: 8 },
            { label: 'Setoran diterima', data: Object.values(M).map(m => m.paid), backgroundColor: '#FF4D6D', borderRadius: 8 },
        ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${rp(c.parsed.y)}` } } },
        scales: { y: { ticks: { callback: v => (v / 1000) + 'k' }, grid: { color: '#EDE9FE' } }, x: { grid: { display: false } } } } });
</script>
@endif
@endpush