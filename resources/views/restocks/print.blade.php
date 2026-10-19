<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><link rel="icon" type="image/png" href="{{ asset('assets/logo_pink.png') }}"><title>Bukti Restock {{ $restock->number }}</title>
<script src="https://cdn.tailwindcss.com"></script><style>@media print { .no-print { display: none } body { background: #fff } }</style></head>
<body class="bg-[#F8F9FA] p-6 font-sans text-[#2D2A32]">
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<div class="mx-auto max-w-3xl rounded-2xl bg-white p-8 shadow">
    <div class="flex items-start justify-between"><div><div class="text-2xl font-semibold text-[#FF4D6D]">Strawraaberry Journey</div><div class="text-sm">Bukti Restock dan Serah Terima Barang Titip Jual</div></div>
        <div class="text-right text-sm"><div class="font-bold">{{ $restock->number }}</div><div>Status: {{ $restock->status_label }}</div></div></div>
    <div class="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-[#FFF3B0]/60 p-4 text-sm">
        <div><b>Toko:</b> {{ $restock->store->name }}<br>{{ $restock->store->address }}, {{ $restock->store->city }}</div>
        <div><b>Tanggal kirim:</b> {{ $restock->ship_date?->format('d/m/Y') ?? '-' }}<br><b>Tanggal terima:</b> {{ $restock->received_date?->format('d/m/Y') ?? '-' }}</div></div>
    <table class="mt-6 w-full text-sm"><thead><tr class="border-b-2 border-[#FFB7C5] text-left"><th class="py-2">Produk</th><th>SKU</th><th>Kondisi</th><th>Harga jual</th><th class="text-right">Dikirim</th><th class="text-right">Diterima</th></tr></thead><tbody>
    @foreach ($restock->items as $i)<tr class="border-b border-gray-100"><td class="py-2">{{ $i->product->name }}</td><td>{{ $i->product->sku }}</td><td>{{ ucfirst($i->condition) }}</td><td>{{ $rp($i->unit_price) }}</td><td class="text-right">{{ $i->qty_sent }}</td><td class="text-right">{{ $i->qty_received }}</td></tr>@endforeach
    </tbody><tfoot><tr class="font-bold"><td colspan="4" class="py-2">Total unit</td><td class="text-right">{{ $restock->total_sent }}</td><td class="text-right">{{ $restock->total_received }}</td></tr></tfoot></table>
    @if ($restock->notes)<p class="mt-4 text-sm"><b>Catatan:</b> {{ $restock->notes }}</p>@endif
    <div class="mt-14 grid grid-cols-2 text-center text-sm"><div>Diserahkan oleh<br><br><br>( {{ $restock->handed_by ?: '........................' }} )</div><div>Diterima oleh<br><br><br>( {{ $restock->received_by ?: '........................' }} )</div></div>
    <button onclick="window.print()" class="no-print mt-8 rounded-xl bg-[#FF4D6D] px-4 py-2 font-bold text-white">Cetak / Simpan PDF</button>
</div></body></html>