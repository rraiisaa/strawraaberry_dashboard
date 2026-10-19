@extends('layouts.app')
@section('title', 'Restock')

@section('content')
@include('partners._subnav')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3"><h1 class="font-display text-3xl font-semibold">Restock dan Serah Terima</h1><a href="{{ route('restocks.create') }}" class="btn">Buat restock</a></div>

<form method="GET" class="card mb-5 grid gap-3 md:grid-cols-4">
    <div><label class="label">Cari nomor</label><input type="search" name="q" value="{{ request('q') }}" class="input"></div>
    <div><label class="label">Mitra</label><select name="store_id" class="input"><option value="">Semua</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (App\Models\Restock::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="flex items-end"><button class="btn">Terapkan</button></div>
</form>

<div class="card overflow-x-auto"><table class="w-full min-w-[760px]">
    <thead><tr class="border-b border-lav"><th class="th">Nomor</th><th class="th">Mitra</th><th class="th">Dikirim</th><th class="th">Diterima</th><th class="th">Unit dikirim</th><th class="th">Unit diterima</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
    @forelse ($restocks as $r)
        <tr class="border-b border-cloud"><td class="td font-bold">{{ $r->number }}</td><td class="td">{{ $r->store->name }}</td><td class="td">{{ $r->ship_date?->format('d/m/Y') ?? '-' }}</td><td class="td">{{ $r->received_date?->format('d/m/Y') ?? '-' }}</td>
            <td class="td">{{ $r->total_sent }}</td><td class="td">{{ $r->total_received }}</td><td class="td"><span class="pill {{ App\Models\Restock::COLORS[$r->status] }}">{{ $r->status_label }}</span></td>
            <td class="td text-right"><a href="{{ route('restocks.show', $r) }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a></td></tr>
    @empty <tr><td colspan="8" class="td py-10 text-center font-semibold">Belum ada restock yang cocok.</td></tr> @endforelse
    </tbody></table><div class="mt-4">{{ $restocks->links() }}</div></div>
@endsection