@extends('layouts.app')
@section('title', 'Laporan Penjualan')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
@include('partners._subnav')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3"><h1 class="font-display text-3xl font-semibold">Laporan Penjualan Mitra</h1><a href="{{ route('reports.create') }}" class="btn">Laporan baru</a></div>

<form method="GET" class="card mb-5 grid gap-3 md:grid-cols-5">
    <div><label class="label">Mitra</label><select name="store_id" class="input"><option value="">Semua</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (App\Models\SalesReport::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label">Periode akhir dari</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
    <div><label class="label">sampai</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
    <div class="flex items-end"><button class="btn">Terapkan</button></div>
</form>

<div class="card overflow-x-auto"><table class="w-full min-w-[860px]">
    <thead><tr class="border-b border-lav"><th class="th">Nomor</th><th class="th">Mitra</th><th class="th">Periode</th><th class="th">Unit</th><th class="th">Penjualan bersih</th><th class="th">Bagian toko</th><th class="th">Bagian Strawraaberry</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
    @forelse ($reports as $r)
        <tr class="border-b border-cloud"><td class="td font-bold">{{ $r->number }}@if ($r->is_late)<span class="pill ml-1 bg-butter">Terlambat</span>@endif</td><td class="td">{{ $r->store->name }}</td>
            <td class="td">{{ $r->period_start->format('d/m/Y') }} - {{ $r->period_end->format('d/m/Y') }}</td><td class="td">{{ $r->total_units }}</td><td class="td">{{ $rp($r->net_sales) }}</td><td class="td">{{ $rp($r->store_share) }}</td><td class="td">{{ $rp($r->own_share) }}</td>
            <td class="td"><span class="pill {{ App\Models\SalesReport::COLORS[$r->status] }}">{{ $r->status_label }}</span></td><td class="td text-right"><a href="{{ route('reports.show', $r) }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a></td></tr>
    @empty <tr><td colspan="9" class="td py-10 text-center font-semibold">Belum ada laporan yang cocok. Angka pada laporan Draft belum dihitung sebagai angka final.</td></tr> @endforelse
    </tbody></table><div class="mt-4">{{ $reports->links() }}</div></div>
@endsection