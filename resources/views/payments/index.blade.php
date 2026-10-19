@extends('layouts.app')
@section('title', 'Setoran dan Tagihan')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
@include('partners._subnav')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3"><h1 class="font-display text-3xl font-semibold">Setoran dan Tagihan</h1><a href="{{ route('payments.create') }}" class="btn">Catat setoran</a></div>

<form method="GET" class="card mb-5 grid gap-3 md:grid-cols-4">
    <div><label class="label">Mitra</label><select name="store_id" class="input"><option value="">Semua</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div><label class="label">Status setoran</label><select name="status" class="input"><option value="">Semua</option>@foreach (App\Models\Payment::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="flex items-end"><button class="btn">Terapkan</button></div>
</form>

<section class="card mb-5 overflow-x-auto"><h2 class="mb-2 font-display text-xl font-semibold">Tagihan terbuka</h2>
    <table class="w-full min-w-[820px]"><thead><tr class="border-b border-lav"><th class="th">Mitra</th><th class="th">Laporan</th><th class="th">Jatuh tempo</th><th class="th">Tagihan</th><th class="th">Dibayar</th><th class="th">Sisa</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
    @forelse ($obligations as $o)
        <tr class="border-b border-cloud"><td class="td">{{ $o->store->name }}</td><td class="td"><a class="font-bold text-berry hover:underline" href="{{ route('reports.show', $o->sales_report_id) }}">{{ $o->report->number }}</a></td>
            <td class="td">{{ $o->due_date->format('d/m/Y') }}@if ($o->days_late)<span class="pill ml-1 bg-berry-soft/60">{{ $o->days_late }} hari</span>@endif</td><td class="td">{{ $rp($o->amount_due) }}</td><td class="td">{{ $rp((int) $o->paid_sum) }}</td><td class="td font-bold">{{ $rp($o->amount_due - (int) $o->paid_sum) }}</td>
            <td class="td"><span class="pill {{ App\Models\Obligation::STATUS_COLORS[$o->status] }}">{{ $o->status_label }}</span></td>
            <td class="td text-right">@unless ($o->billed_at)<form method="POST" action="{{ route('payments.bill', $o) }}">{{ csrf_field() }}<button class="btn-soft !px-3 !py-1 text-xs">Tandai ditagih</button></form>@endunless</td></tr>
    @empty <tr><td colspan="8" class="td py-8 text-center font-semibold">Tidak ada tagihan terbuka.</td></tr> @endforelse
    </tbody></table></section>

<section class="card overflow-x-auto"><h2 class="mb-2 font-display text-xl font-semibold">Riwayat setoran</h2>
    <table class="w-full min-w-[760px]"><thead><tr class="border-b border-lav"><th class="th">Nomor</th><th class="th">Mitra</th><th class="th">Tanggal</th><th class="th">Diterima</th><th class="th">Teralokasi</th><th class="th">Saldo lebih</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
    @forelse ($payments as $p)
        <tr class="border-b border-cloud"><td class="td font-bold">{{ $p->number }}</td><td class="td">{{ $p->store->name }}</td><td class="td">{{ $p->paid_on->format('d/m/Y') }}</td><td class="td">{{ $rp($p->amount_received) }}</td><td class="td">{{ $rp($p->allocated) }}</td><td class="td">{{ $rp($p->unallocated) }}</td>
            <td class="td"><span class="pill {{ App\Models\Payment::COLORS[$p->status] }}">{{ $p->status_label }}</span></td><td class="td text-right"><a href="{{ route('payments.show', $p) }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a></td></tr>
    @empty <tr><td colspan="8" class="td py-8 text-center font-semibold">Belum ada setoran.</td></tr> @endforelse
    </tbody></table><div class="mt-4">{{ $payments->links() }}</div></section>
@endsection