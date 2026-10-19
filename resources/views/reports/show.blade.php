@extends('layouts.app')
@section('title', $report->number)

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); $t = $report->term_snapshot; $o = $report->obligation; $hasErr = collect($issues)->contains('level', 'error');
@endphp
@include('partners._subnav')

<div x-data="{ modal: null, busy: false }">
<div class="card mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><div class="flex flex-wrap items-center gap-2"><h1 class="font-display text-3xl font-semibold">{{ $report->number }}</h1><span class="pill {{ App\Models\SalesReport::COLORS[$report->status] }}">{{ $report->status_label }}</span>@if ($report->is_late)<span class="pill bg-butter">Terlambat</span>@endif @if ($report->revision_no)<span class="pill bg-lav">Revisi {{ $report->revision_no }}</span>@endif</div>
        <div class="text-sm font-semibold text-cocoa/70"><a href="{{ route('partners.show', $report->store_id) }}" class="text-berry hover:underline">{{ $report->store->name }}</a> | Periode {{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }} | Diterima {{ $report->received_at?->format('d/m/Y') ?? '-' }} | Sumber {{ $report->source ?? '-' }}</div>
        @if ($report->review_note)<p class="mt-1 text-sm font-bold text-berry">Catatan reviu: {{ $report->review_note }}</p>@endif
        @if ($report->status !== 'verified')<p class="mt-1 text-xs font-bold text-cocoa/60">Angka di halaman ini belum final sampai laporan terverifikasi.</p>@endif</div>
    <div class="flex flex-wrap gap-2">
        @if ($report->proof_path)<a href="{{ route('files.proof', ['report', $report->id]) }}" target="_blank" class="btn-soft">Lihat bukti</a>@endif
        @if ($report->isEditable())<a href="{{ route('reports.edit', $report) }}" class="btn-soft">Edit</a>
            <form method="POST" action="{{ route('reports.submit', $report) }}">{{ csrf_field() }}<button class="btn">Ajukan verifikasi</button></form>@endif
        @if ($report->status === 'pending_verification')
            <button x-on:click="modal='reject'" class="btn-soft">Kembalikan</button>
            <form method="POST" action="{{ route('reports.verify', $report) }}" x-on:submit="if (busy) $event.preventDefault(); busy = true" data-confirm="Verifikasi laporan? Stok toko akan berkurang dan kewajiban setoran dibuat." data-confirm-title="Verifikasi laporan?" data-confirm-type="primary" data-confirm-ok="Ya, verifikasi">{{ csrf_field() }}<button class="btn" @disabled($hasErr)>Verifikasi</button></form>@endif
        @if ($report->status === 'verified')<a href="{{ route('reports.edit', [$report, 'revise' => 1]) }}" class="btn-soft">Revisi</a>@endif
        @if ($report->status !== 'cancelled')<button x-on:click="modal='cancel'" class="btn-soft">Batalkan</button>@endif
    </div>
</div>

@if ($issues)
<div class="card mb-5"><h2 class="mb-2 font-display text-xl font-semibold">Pemeriksaan otomatis</h2>
    @foreach ($issues as $i)<div class="mb-1 flex items-start gap-2 rounded-xl p-2 text-sm font-semibold {{ $i['level'] === 'error' ? 'bg-berry-soft/50' : 'bg-butter/70' }}"><span class="pill bg-white">{{ $i['level'] === 'error' ? 'Memblokir' : 'Peringatan' }}</span>{{ $i['msg'] }}</div>@endforeach</div>
@endif

<div class="grid gap-5 lg:grid-cols-3">
    <section class="card lg:col-span-2 overflow-x-auto"><h2 class="mb-2 font-display text-xl font-semibold">Rincian produk</h2>
        <table class="w-full min-w-[640px]"><thead><tr class="border-b border-lav"><th class="th">Produk</th><th class="th">Terjual</th><th class="th">Harga</th><th class="th">Diskon</th><th class="th">Retur</th><th class="th">Unit bersih</th></tr></thead><tbody>
        @foreach ($report->lines as $l)<tr class="border-b border-cloud"><td class="td font-bold">{{ $l->product->name }}<div class="text-xs font-normal text-cocoa/60">{{ $l->product->sku }}</div></td><td class="td">{{ $l->qty_sold }}</td><td class="td">{{ $rp($l->unit_price) }}</td><td class="td">{{ $rp($l->discount_amount) }}</td><td class="td">{{ $l->return_qty }}</td><td class="td">{{ $l->net_units }}</td></tr>@endforeach
        </tbody></table></section>

    <section class="card"><h2 class="mb-2 font-display text-xl font-semibold">Perhitungan bagi hasil</h2>
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between"><dt>Penjualan kotor</dt><dd>{{ $rp($report->gross_amount) }}</dd></div>
            <div class="flex justify-between"><dt>Diskon</dt><dd>- {{ $rp($report->discount_amount) }}</dd></div>
            <div class="flex justify-between"><dt>Retur</dt><dd>- {{ $rp($report->returns_amount) }}</dd></div>
            <div class="flex justify-between"><dt>Penyesuaian disetujui</dt><dd>{{ $rp($report->adjustments_amount) }}</dd></div>
            <div class="flex justify-between border-t border-lav pt-1 font-bold"><dt>Penjualan bersih</dt><dd>{{ $rp($report->net_sales) }}</dd></div>
            <div class="flex justify-between font-bold"><dt>Dasar bagi hasil</dt><dd>{{ $rp($report->base_amount) }}</dd></div>
            @if ($report->base_amount !== $report->net_sales)<p class="text-xs text-cocoa/70">Dasar berbeda dari penjualan bersih karena skema memakai harga jual normal, bukan harga aktual setelah diskon.</p>@endif
            <div class="flex justify-between"><dt>Bagian toko</dt><dd>{{ $rp($report->store_share) }}</dd></div>
            <div class="flex justify-between rounded-xl bg-mint/60 p-2 font-bold"><dt>Bagian Strawraaberry</dt><dd>{{ $rp($report->own_share) }}</dd></div>
        </dl>
        <div class="mt-3 rounded-xl bg-cloud p-3 text-xs font-semibold text-cocoa/70">Skema snapshot: {{ App\Models\PartnerTerm::SCHEMES[$t['scheme_type']] }}@if ($t['scheme_type'] === 'percentage') (toko {{ rtrim(rtrim($t['store_pct'], '0'), '.') }}%)@endif | {{ App\Models\PartnerTerm::BASES[$t['basis']] }} | berlaku {{ $t['effective_from'] }} @if ($t['agreement_ref'])| Ref. {{ $t['agreement_ref'] }}@endif. Pembulatan: setengah menjauhi nol; selisih bulat selalu ke Strawraaberry.</div>
    </section>
</div>

@if ($o)
<section class="card mt-5"><div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-display text-xl font-semibold">Kewajiban setoran</h2><span class="pill {{ App\Models\Obligation::STATUS_COLORS[$o->status] }}">{{ $o->status_label }}</span></div>
    <div class="mt-2 grid gap-3 text-sm sm:grid-cols-4"><div><div class="label">Tagihan</div><b>{{ $rp($o->amount_due) }}</b></div><div><div class="label">Dibayar (terverifikasi)</div><b>{{ $rp($o->paid) }}</b></div><div><div class="label">Sisa</div><b>{{ $rp($o->remaining) }}</b></div><div><div class="label">Jatuh tempo</div><b>{{ $o->due_date->format('d/m/Y') }}</b>@if ($o->days_late)<span class="pill ml-1 bg-berry-soft/60">{{ $o->days_late }} hari</span>@endif</div></div>
    @foreach ($o->allocations as $a)<div class="mt-2 text-sm"><a class="font-bold text-berry hover:underline" href="{{ route('payments.show', $a->payment_id) }}">{{ $a->payment->number }}</a> {{ $rp($a->amount) }} <span class="pill {{ App\Models\Payment::COLORS[$a->payment->status] }}">{{ $a->payment->status_label }}</span></div>@endforeach
    <div class="mt-3 flex gap-2"><a href="{{ route('payments.create', ['store_id' => $report->store_id]) }}" class="btn-soft">Catat setoran</a>
        @unless ($o->billed_at)<form method="POST" action="{{ route('payments.bill', $o) }}">{{ csrf_field() }}<button class="btn-soft">Tandai sudah ditagih</button></form>@endunless</div></section>
@endif

@if ($report->revisions->isNotEmpty())
<section class="card mt-5"><h2 class="mb-2 font-display text-xl font-semibold">Riwayat revisi</h2>
    @foreach ($report->revisions as $rv)<div class="mb-2 rounded-xl bg-cloud p-3 text-sm"><div class="font-bold">Revisi {{ $rv->revision_no }} | {{ $rv->created_at->format('d/m/Y H:i') }} | {{ $rv->actor }}</div><div>Alasan: {{ $rv->reason }}</div>
        <div class="text-xs text-cocoa/70">Sebelum: penjualan bersih {{ $rp($rv->before['net_sales']) }}, bagian Strawraaberry {{ $rp($rv->before['own_share']) }}. Sesudah: penjualan bersih {{ $rp($rv->after['net_sales']) }}, bagian Strawraaberry {{ $rp($rv->after['own_share']) }}.</div></div>@endforeach</section>
@endif

<div x-show="modal" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" x-on:keydown.escape.window="modal = null">
    <form method="POST" :action="modal === 'reject' ? '{{ route('reports.reject', $report) }}' : '{{ route('reports.cancel', $report) }}'" x-on:click.outside="modal = null" class="card w-full max-w-md space-y-3">{{ csrf_field() }}
        <h3 class="font-display text-xl font-semibold" x-text="modal === 'reject' ? 'Kembalikan untuk direvisi' : 'Batalkan laporan'"></h3>
        <p class="text-sm" x-show="modal === 'cancel'">Laporan terverifikasi yang dibatalkan akan membalik pergerakan stok lewat reversal dan membatalkan kewajibannya. Ditolak bila sudah ada setoran teralokasi.</p>
        <div><label class="label">Alasan *</label><textarea name="reason" rows="2" required class="input"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" x-on:click="modal=null" class="btn-soft">Kembali</button><button class="btn">Lanjutkan</button></div>
    </form>
</div>
</div>
@endsection