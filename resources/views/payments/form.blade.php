@extends('layouts.app')
@section('title', 'Catat Setoran')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
@include('partners._subnav')
<h1 class="mb-5 font-display text-3xl font-semibold">Catat setoran</h1>

<form method="GET" class="card mb-5 flex items-end gap-3"><div><label class="label">Mitra</label>
    <select name="store_id" class="input" onchange="this.form.submit()"><option value="">Pilih mitra</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected($storeId == $s->id)>{{ $s->name }}</option>@endforeach</select></div></form>

@if ($storeId)
<form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" x-data="{ busy: false, received: {{ (int) old('amount_received', 0) }}, alloc: {}, sum() { return Object.values(this.alloc).reduce((a, v) => a + (parseInt(v) || 0), 0) } }" x-on:submit="if (busy) $event.preventDefault(); busy = true" class="space-y-5">
    {{ csrf_field() }} <input type="hidden" name="store_id" value="{{ $storeId }}"><input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}">
    <section class="card grid gap-4 md:grid-cols-3">
        <div><label class="label">Tanggal pembayaran *</label><input type="date" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input"></div>
        <div><label class="label">Nominal diterima (Rp) *</label><input type="number" name="amount_received" x-model.number="received" min="1" required class="input"></div>
        <div><label class="label">Nominal seharusnya (opsional)</label><input type="number" name="amount_expected" value="{{ old('amount_expected') }}" class="input"></div>
        <div><label class="label">Metode</label><select name="method" class="input"><option>Transfer</option><option>Tunai</option><option>E-wallet</option><option>Lainnya</option></select></div>
        <div><label class="label">Nomor referensi transfer</label><input name="reference_no" value="{{ old('reference_no') }}" class="input"></div>
        <div><label class="label">Rekening tujuan</label><input name="destination_account" value="{{ old('destination_account') }}" class="input"></div>
        <div><label class="label">Bukti pembayaran</label><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" class="input"></div>
        <div class="md:col-span-2"><label class="label">Catatan</label><input name="notes" value="{{ old('notes') }}" class="input"></div>
    </section>

    <section class="card overflow-x-auto"><h2 class="font-display text-xl font-semibold">Alokasi ke tagihan</h2>
        <p class="mb-2 text-xs font-semibold text-cocoa/60">Satu setoran bisa melunasi beberapa tagihan. Alokasi baru berlaku setelah setoran diverifikasi. Sisa yang tidak dialokasikan menjadi saldo lebih milik mitra, bukan pendapatan.</p>
        <table class="w-full min-w-[640px]"><thead><tr class="border-b border-lav"><th class="th">Laporan</th><th class="th">Jatuh tempo</th><th class="th">Tersedia dialokasikan</th><th class="th">Alokasi (Rp)</th></tr></thead><tbody>
        @forelse ($open as $o)<tr class="border-b border-cloud"><td class="td font-bold">{{ $o->report->number }}</td><td class="td">{{ $o->due_date->format('d/m/Y') }}</td><td class="td">{{ $rp($o->available_to_allocate) }}</td>
            <td class="td"><input type="number" min="0" max="{{ $o->available_to_allocate }}" name="alloc[{{ $o->id }}]" x-model="alloc[{{ $o->id }}]" class="input !w-40 !py-1"></td></tr>
        @empty <tr><td colspan="4" class="td py-6 text-center font-semibold">Tidak ada tagihan terbuka untuk mitra ini. Setoran tetap bisa dicatat sebagai saldo lebih.</td></tr> @endforelse
        </tbody></table>
        <div class="mt-3 rounded-xl p-3 text-sm font-bold" :class="sum() > received ? 'bg-berry-soft/60' : 'bg-mint/50'">Teralokasi <span x-text="'Rp ' + sum().toLocaleString('id-ID')"></span> | Saldo lebih <span x-text="'Rp ' + Math.max(0, received - sum()).toLocaleString('id-ID')"></span><span x-show="sum() > received"> | Alokasi melebihi nominal diterima</span></div>
    </section>
    <div class="flex gap-2"><button class="btn" :disabled="busy || sum() > received">Simpan setoran</button><a href="{{ route('payments.index') }}" class="btn-soft">Batal</a></div>
</form>
@else
<div class="card py-10 text-center text-sm font-semibold">Pilih mitra untuk melihat tagihan yang bisa dialokasikan.</div>
@endif
@endsection