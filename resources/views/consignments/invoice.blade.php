<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $number }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>@media print { .no-print { display: none } body { background: #fff } }</style></head>
<body class="bg-[#F8F9FA] p-6 font-sans text-[#2D2A32]">
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); $rate = (float) $store->commission_rate; @endphp
<div class="mx-auto max-w-3xl rounded-2xl bg-white p-8 shadow">
    <div class="flex items-start justify-between">
        <div><div class="text-2xl font-semibold text-[#FF4D6D]"> Strawraaberry Journey</div><div class="text-sm">Surat Jalan & Invoice Tagihan Titip Jual</div></div>
        <div class="text-right text-sm"><div class="font-bold">{{ $number }}</div><div>{{ now()->translatedFormat('d F Y') }}</div></div>
    </div>
    <div class="mt-6 rounded-xl bg-[#FFF3B0]/60 p-4 text-sm"><b>Kepada:</b> {{ $store->name }} – {{ $store->location }}<br>@if ($store->contact_person)<b>Kontak:</b> {{ $store->contact_person }}@endif</div>

    <table class="mt-6 w-full text-sm">
        <thead><tr class="border-b-2 border-[#FFB7C5] text-left"><th class="py-2">Produk</th><th>Harga</th><th>Titip</th><th>Terjual</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($s['rows']->where('settlement_status', 'pending') as $c)
            <tr class="border-b border-gray-100"><td class="py-2">{{ $c->product->name }}</td><td>{{ $rp($c->product->selling_price) }}</td><td>{{ $c->qty_deposited }}</td><td>{{ $c->qty_sold }}</td><td class="text-right">{{ $rp($c->gross) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <dl class="ml-auto mt-6 w-72 space-y-1 text-sm">
        <div class="flex justify-between"><dt>Total penjualan</dt><dd>{{ $rp($s['pending_gross']) }}</dd></div>
        <div class="flex justify-between"><dt>Komisi mitra ({{ $rate }}%)</dt><dd>– {{ $rp($s['commission']) }}</dd></div>
        <div class="flex justify-between border-t-2 border-[#FFB7C5] pt-2 text-base font-bold"><dt>Tagihan ke mitra</dt><dd>{{ $rp($s['net']) }}</dd></div>
    </dl>

    <div class="mt-12 grid grid-cols-2 text-center text-sm"><div>Diserahkan oleh<br><br><br>( Strawraaberry Journey )</div><div>Diterima oleh<br><br><br>( {{ $store->contact_person ?: $store->name }} )</div></div>
    <button onclick="window.print()" class="no-print mt-8 rounded-xl bg-[#FF4D6D] px-4 py-2 font-bold text-white">Cetak / Simpan PDF</button>
</div></body></html>
