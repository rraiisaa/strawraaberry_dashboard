@extends('layouts.app')
@section('title', 'Titip Jual')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp

<div x-data="{ tab: {{ $stores->first()->id ?? 0 }}, deposit: false, depStore: null }">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold">Mitra Titip Jual</h1>
            <p class="text-sm font-semibold text-cocoa/60">Pantau barang titipan, bagi hasil, dan pencairan dana.</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-5 flex flex-wrap gap-2" role="tablist">
        @foreach ($stores as $i => $store)
            <button role="tab" @click="tab = {{ $store->id }}"
                    :class="tab === {{ $store->id }} ? '{{ $i % 2 ? 'bg-lav' : 'bg-mint' }} shadow-pastel' : 'bg-white'"
                    class="rounded-2xl px-4 py-2 text-sm font-bold transition">
                {{ $store->name }} – {{ $store->location }}
            </button>
        @endforeach
    </div>

    @foreach ($stores as $store)
        @php $s = $summaries[$store->id]; $rate = (float) $store->commission_rate; @endphp
        <section x-show="tab === {{ $store->id }}" x-cloak x-transition.opacity class="space-y-5">

            {{-- Kalkulator bagi hasil --}}
            <div class="grid gap-4 md:grid-cols-4">
                <div class="card bg-butter/50"><div class="label">Dititipkan / Terjual</div><div class="font-display text-2xl font-semibold">{{ $s['deposited'] }} / {{ $s['sold'] }} pcs</div></div>
                <div class="card"><div class="label">Penjualan belum dicairkan</div><div class="font-display text-2xl font-semibold">{{ $rp($s['pending_gross']) }}</div></div>
                <div class="card bg-lav/60"><div class="label">Komisi mitra ({{ rtrim(rtrim($store->commission_rate, '0'), '.') }}%)</div><div class="font-display text-2xl font-semibold">{{ $rp($s['commission']) }}</div></div>
                <div class="card bg-mint/60 ring-2 ring-berry-soft"><div class="label">Hak bersih Strawraaberry</div><div class="font-display text-2xl font-semibold">{{ $rp($s['net']) }}</div></div>
            </div>

            <div class="card">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-xl font-semibold">Rekap Titipan</h2>
                    <div class="flex flex-wrap gap-2">
                        <button @click="deposit = true; depStore = {{ $store->id }}" class="btn-soft"><i data-lucide="package-plus" class="h-4 w-4"></i> Titip barang</button>
                        <a href="{{ route('consignments.invoice', $store) }}" target="_blank" class="btn-soft"><i data-lucide="file-text" class="h-4 w-4"></i> Surat jalan / invoice</a>
                        <form method="POST" action="{{ route('consignments.settle', $store) }}" onsubmit="return confirm('Cairkan {{ $rp($s['net']) }} dan catat ke kas?')">
                            @csrf
                            <button class="btn" @disabled($s['pending_gross'] <= 0)><i data-lucide="banknote" class="h-4 w-4"></i> Tandai cair</button>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px]">
                        <thead><tr class="border-b border-lav"><th class="th">Produk</th><th class="th">Batch</th><th class="th">Titip</th><th class="th">Terjual</th><th class="th">Sisa</th><th class="th">Nilai jual</th><th class="th">Status</th></tr></thead>
                        <tbody>
                        @forelse ($s['rows'] as $c)
                            <tr class="border-b border-cloud">
                                <td class="td font-bold">{{ $c->product->name }}</td>
                                <td class="td">{{ $c->batch_date->format('d M Y') }}</td>
                                <td class="td">{{ $c->qty_deposited }}</td>
                                <td class="td">
                                    @if ($c->settlement_status === 'pending')
                                    <form method="POST" action="{{ route('consignments.sold', $c) }}" class="flex items-center gap-1">
                                        @csrf @method('PATCH')
                                        <input type="number" name="qty_sold" value="{{ $c->qty_sold }}" min="0" max="{{ $c->qty_deposited }}" class="input !w-16 !py-1">
                                        <button class="rounded-lg bg-berry-soft/50 p-1.5" title="Simpan"><i data-lucide="check" class="h-4 w-4"></i></button>
                                    </form>
                                    @else {{ $c->qty_sold }} @endif
                                </td>
                                <td class="td">{{ $c->qty_left }}</td>
                                <td class="td">{{ $rp($c->gross) }}</td>
                                <td class="td"><span class="pill {{ $c->settlement_status === 'paid' ? 'bg-mint' : 'bg-butter' }}">{{ $c->settlement_status === 'paid' ? 'Cair' : 'Menunggu' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="td py-8 text-center font-semibold">Belum ada barang yang dititipkan ke {{ $store->name }}.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endforeach

    {{-- Modal titip barang --}}
    <div x-show="deposit" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" @keydown.escape.window="deposit = false">
        <form method="POST" action="{{ route('consignments.store') }}" @click.outside="deposit = false" class="card w-full max-w-md space-y-3">
            @csrf
            <h3 class="font-display text-xl font-semibold">Titip barang ke mitra</h3>
            <div>
                <label class="label">Mitra</label>
                <select name="store_id" x-model="depStore" class="input">@foreach ($stores as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label">Produk</label>
                <select name="product_id" class="input">@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }} (stok {{ $p->stock_qty }})</option>@endforeach</select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Jumlah</label><input type="number" name="qty" min="1" required class="input"></div>
                <div><label class="label">Tanggal batch</label><input type="date" name="batch_date" value="{{ now()->toDateString() }}" class="input"></div>
            </div>
            <div class="flex justify-end gap-2"><button type="button" @click="deposit=false" class="btn-soft">Batal</button><button class="btn">Simpan titipan</button></div>
        </form>
    </div>
</div>
@endsection