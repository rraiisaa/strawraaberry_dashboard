@extends('layouts.app')
@section('title', 'Daftar Mitra')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
@include('partners._subnav')

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <h1 class="font-display text-3xl font-semibold">Daftar Mitra</h1>
    <a href="{{ route('partners.create') }}" class="btn">Tambah mitra</a>
</div>

<form method="GET" class="card mb-5 grid gap-3 md:grid-cols-6">
    <div class="md:col-span-2"><label class="label">Cari nama toko, PIC, atau kode</label><input type="search" name="q" value="{{ request('q') }}" class="input"></div>
    <div><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (App\Models\Store::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label">Kota</label><select name="city" class="input"><option value="">Semua</option>@foreach ($cities as $c)<option @selected(request('city') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div><label class="label">Urutkan</label><select name="sort" class="input"><option value="name">Nama</option><option value="joined" @selected(request('sort') === 'joined')>Tanggal bergabung</option><option value="activity" @selected(request('sort') === 'activity')>Aktivitas terbaru</option></select></div>
    <div class="flex items-end gap-2"><label class="flex items-center gap-1 text-xs font-bold"><input type="checkbox" name="archived" value="1" @checked(request()->boolean('archived'))> Arsip</label><button class="btn">Terapkan</button></div>
</form>

<div class="card overflow-x-auto">
    <table class="w-full min-w-[820px]">
        <thead><tr class="border-b border-lav"><th class="th">Mitra</th><th class="th">PIC</th><th class="th">Kota</th><th class="th">Status</th><th class="th">Unit di toko</th><th class="th">Belum disetor</th><th class="th"></th></tr></thead>
        <tbody>
        @forelse ($stores as $s)
            <tr class="border-b border-cloud">
                <td class="td"><a href="{{ route('partners.show', $s) }}" class="font-bold text-berry hover:underline">{{ $s->name }}</a><div class="text-xs text-cocoa/60">{{ $s->code }}</div></td>
                <td class="td">{{ $s->pic_name ?? '-' }}<div class="text-xs text-cocoa/60">{{ $s->phone }}</div></td>
                <td class="td">{{ $s->city ?? '-' }}</td>
                <td class="td"><span class="pill {{ App\Models\Store::STATUS_COLORS[$s->status] }}">{{ $s->status_label }}</span>@if ($s->trashed())<span class="pill bg-gray-200">Diarsipkan</span>@endif</td>
                <td class="td">{{ $units[$s->id] ?? 0 }}</td>
                <td class="td font-bold">{{ $rp($owed[$s->id] ?? 0) }}</td>
                <td class="td text-right"><a href="{{ route('partners.show', $s) }}" class="btn-soft !px-3 !py-1 text-xs">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="td py-10 text-center font-semibold">Belum ada mitra yang cocok. Tambahkan mitra pertama kamu.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $stores->links() }}</div>
</div>
@endsection