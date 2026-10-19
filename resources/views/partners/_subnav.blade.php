@php
    $nav = [['partners.overview', 'Ringkasan', 'partners.overview'], ['partners.index', 'Daftar Mitra', 'partners.index'], ['restocks.index', 'Restock', 'restocks.*'],
            ['reports.index', 'Laporan Penjualan', 'reports.*'], ['payments.index', 'Setoran & Tagihan', 'payments.*']];
@endphp
<nav class="mb-6 flex flex-wrap gap-2" aria-label="Menu Titip Jual">
    @foreach ($nav as [$route, $label, $pat])
        <a href="{{ route($route) }}" class="rounded-2xl px-4 py-2 text-sm font-bold transition {{ request()->routeIs($pat) || (str_starts_with($route, 'partners.index') && request()->routeIs('partners.show', 'partners.create', 'partners.edit')) ? 'bg-mint shadow-pastel' : 'bg-white hover:bg-cloud' }}">{{ $label }}</a>
    @endforeach
</nav>