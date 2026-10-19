@extends('layouts.app')
@section('title', $payment->number)

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
@include('partners._subnav')
<div x-data="{ modal: null, busy: false }">
<div class="card mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><div class="flex items-center gap-2"><h1 class="font-display text-3xl font-semibold">{{ $payment->number }}</h1><span class="pill {{ App\Models\Payment::COLORS[$payment->status] }}">{{ $payment->status_label }}</span></div>
        <div class="text-sm font-semibold text-cocoa/70"><a href="{{ route('partners.show', $payment->store_id) }}" class="text-berry hover:underline">{{ $payment->store->name }}</a> | {{ $payment->paid_on->format('d/m/Y') }} | {{ $payment->method }} @if ($payment->reference_no)| Ref. {{ $payment->reference_no }}@endif</div>
        @if ($payment->destination_account)<div class="text-sm">Rekening tujuan: {{ $payment->destination_account }}</div>@endif
        @if ($payment->notes)<p class="text-sm">{{ $payment->notes }}</p>@endif
        @if ($payment->reversed_reason)<p class="text-sm font-bold text-berry">Alasan: {{ $payment->reversed_reason }}</p>@endif
        @if ($payment->status === 'pending')<p class="mt-1 text-xs font-bold text-cocoa/60">Bukti terunggah belum berarti lunas. Verifikasi setelah dana benar-benar masuk.</p>@endif</div>
    <div class="flex flex-wrap gap-2">
        @if ($payment->proof_path)<a href="{{ route('files.proof', ['payment', $payment->id]) }}" target="_blank" class="btn-soft">Lihat bukti</a>@endif
        @if ($payment->status === 'pending')
            <button x-on:click="modal='reject'" class="btn-soft">Tolak</button>
            <form method="POST" action="{{ route('payments.verify', $payment) }}" x-on:submit="if (busy) $event.preventDefault(); busy = true" data-confirm="Verifikasi? Dana dicatat ke Aliran Dana." data-confirm-title="Verifikasi setoran?" data-confirm-type="primary" data-confirm-ok="Ya, verifikasi">{{ csrf_field() }}<button class="btn">Verifikasi dana diterima</button></form>
        @elseif ($payment->status === 'verified')<button x-on:click="modal='reverse'" class="btn-soft">Reversal</button>@endif
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-4">
    <div class="card"><div class="label">Diterima</div><div class="font-display text-xl font-semibold">{{ $rp($payment->amount_received) }}</div></div>
    <div class="card"><div class="label">Seharusnya</div><div class="font-display text-xl font-semibold">{{ $payment->amount_expected !== null ? $rp($payment->amount_expected) : '-' }}</div></div>
    <div class="card bg-mint/50"><div class="label">Teralokasi</div><div class="font-display text-xl font-semibold">{{ $rp($payment->allocated) }}</div></div>
    <div class="card {{ $payment->unallocated > 0 ? 'bg-butter/70' : '' }}"><div class="label">Saldo lebih (bukan pendapatan)</div><div class="font-display text-xl font-semibold">{{ $rp($payment->unallocated) }}</div></div>
</div>

<section class="card mt-5 overflow-x-auto"><h2 class="mb-2 font-display text-xl font-semibold">Alokasi</h2>
    <table class="w-full min-w-[520px]"><thead><tr class="border-b border-lav"><th class="th">Laporan</th><th class="th">Nominal</th><th class="th">Dicatat ke kas</th></tr></thead><tbody>
    @forelse ($payment->allocations as $a)<tr class="border-b border-cloud"><td class="td"><a class="font-bold text-berry hover:underline" href="{{ route('reports.show', $a->obligation->sales_report_id) }}">{{ $a->obligation->report->number }}</a></td><td class="td">{{ $rp($a->amount) }}</td><td class="td">{{ $a->cash_flow_id ? 'Ya (Aliran Dana #' . $a->cash_flow_id . ')' : 'Belum' }}</td></tr>
    @empty <tr><td colspan="3" class="td py-6 text-center font-semibold">Belum ada alokasi.</td></tr> @endforelse
    </tbody></table>
    @if ($payment->status === 'verified' && $payment->unallocated > 0 && $open->isNotEmpty())
        <form method="POST" action="{{ route('payments.allocate', $payment) }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl bg-butter/50 p-4">{{ csrf_field() }}
            <div><label class="label">Alokasikan saldo lebih ke</label><select name="obligation_id" class="input">@foreach ($open as $o)<option value="{{ $o->id }}">{{ $o->report->number }} (sisa {{ $rp($o->available_to_allocate) }})</option>@endforeach</select></div>
            <div><label class="label">Nominal</label><input type="number" name="amount" min="1" max="{{ $payment->unallocated }}" value="{{ $payment->unallocated }}" class="input"></div>
            <button class="btn">Alokasikan</button></form>
    @endif
</section>

<div x-show="modal" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" x-on:keydown.escape.window="modal = null">
    <form method="POST" :action="modal === 'reject' ? '{{ route('payments.reject', $payment) }}' : '{{ route('payments.reverse', $payment) }}'" x-on:click.outside="modal = null" class="card w-full max-w-md space-y-3">{{ csrf_field() }}
        <h3 class="font-display text-xl font-semibold" x-text="modal === 'reject' ? 'Tolak setoran' : 'Reversal setoran'"></h3>
        <p class="text-sm" x-show="modal === 'reverse'">Kas tidak dihapus: dibuat catatan lawan di Aliran Dana, dan kewajiban terkait terbuka kembali.</p>
        <div><label class="label">Alasan *</label><textarea name="reason" rows="2" required class="input"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" x-on:click="modal=null" class="btn-soft">Kembali</button><button class="btn">Lanjutkan</button></div>
    </form>
</div>
</div>
@endsection