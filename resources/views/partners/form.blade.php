@extends('layouts.app')
@section('title', $store->exists ? 'Edit Mitra' : 'Mitra Baru')

@section('content')
@include('partners._subnav')
@php $new = ! $store->exists; $v = fn ($k, $d = null) => old($k, $store->{$k} ?? $d); @endphp

<h1 class="mb-5 font-display text-3xl font-semibold">{{ $new ? 'Mitra baru' : 'Edit ' . $store->name }}</h1>

<form method="POST" action="{{ $new ? route('partners.store') : route('partners.update', $store) }}" x-data="{ busy: false, scheme: '{{ old('scheme_type', 'percentage') }}' }" x-on:submit="if (busy) $event.preventDefault(); busy = true" class="space-y-6">
    {{ csrf_field() }} @if (! $new) {{ method_field('PUT') }} @endif

    <section class="card grid gap-4 md:grid-cols-2">
        <h2 class="font-display text-xl font-semibold md:col-span-2">Profil toko</h2>
        <div><label class="label">Nama toko *</label><input name="name" value="{{ $v('name') }}" required class="input"></div>
        <div><label class="label">Status *</label><select name="status" class="input">@foreach (App\Models\Store::STATUSES as $k => $t)<option value="{{ $k }}" @selected($v('status') === $k)>{{ $t }}</option>@endforeach</select></div>
        <div><label class="label">Nama pemilik</label><input name="owner_name" value="{{ $v('owner_name') }}" class="input"></div>
        <div><label class="label">Nama PIC *</label><input name="pic_name" value="{{ $v('pic_name') }}" required class="input"></div>
        <div><label class="label">Telepon / WhatsApp *</label><input name="phone" value="{{ $v('phone') }}" required class="input" placeholder="+62 812 ..."></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ $v('email') }}" class="input"></div>
        <div class="md:col-span-2"><label class="label">Alamat lengkap *</label><textarea name="address" rows="2" required class="input">{{ $v('address') }}</textarea></div>
        <div><label class="label">Kota *</label><input name="city" value="{{ $v('city') }}" required class="input"></div>
        <div><label class="label">Provinsi</label><input name="province" value="{{ $v('province') }}" class="input"></div>
        <div class="md:col-span-2"><label class="label">Link Google Maps</label><input type="url" name="maps_url" value="{{ $v('maps_url') }}" class="input" placeholder="https://maps.app.goo.gl/..."></div>
        <div><label class="label">Latitude (opsional)</label><input name="latitude" value="{{ $v('latitude') }}" class="input"></div>
        <div><label class="label">Longitude (opsional)</label><input name="longitude" value="{{ $v('longitude') }}" class="input"></div>
    </section>

    <section class="card grid gap-4 md:grid-cols-3">
        <h2 class="font-display text-xl font-semibold md:col-span-3">Kerja sama dan jadwal</h2>
        <div><label class="label">Tanggal mulai *</label><input type="date" name="start_date" value="{{ old('start_date', optional($store->start_date)->toDateString()) }}" required class="input"></div>
        <div><label class="label">Tanggal akhir (kosongkan bila tanpa batas)</label><input type="date" name="end_date" value="{{ old('end_date', optional($store->end_date)->toDateString()) }}" class="input"></div>
        <div></div>
        <div><label class="label">Jadwal pelaporan (keterangan)</label><input name="report_schedule" value="{{ $v('report_schedule') }}" class="input" placeholder="Akhir bulan"></div>
        <div><label class="label">Interval laporan (hari) *</label><input type="number" name="report_interval_days" value="{{ $v('report_interval_days') }}" min="1" class="input"></div>
        <div><label class="label">Batas terlambat lapor (hari) *</label><input type="number" name="report_due_days" value="{{ $v('report_due_days') }}" min="0" class="input"></div>
        <div><label class="label">Jadwal setoran (keterangan)</label><input name="payment_schedule" value="{{ $v('payment_schedule') }}" class="input"></div>
        <div><label class="label">Jatuh tempo setoran (hari setelah periode) *</label><input type="number" name="payment_due_days" value="{{ $v('payment_due_days') }}" min="0" class="input"></div>
        <div class="md:col-span-3"><label class="label">Catatan internal</label><textarea name="notes" rows="2" class="input">{{ $v('notes') }}</textarea></div>
    </section>

    @if ($new)
    <section class="card grid gap-4 md:grid-cols-3">
        <h2 class="font-display text-xl font-semibold md:col-span-3">Skema bagi hasil awal (sesuai perjanjian)</h2>
        <div><label class="label">Jenis skema *</label>
            <select name="scheme_type" x-model="scheme" class="input">@foreach (App\Models\PartnerTerm::SCHEMES as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
        <div x-show="scheme==='percentage'"><label class="label">Persentase bagian toko (%) *</label><input type="number" step="0.01" min="0" max="100" name="store_pct" value="{{ old('store_pct') }}" class="input"><p class="mt-1 text-xs text-cocoa/60">Bagian Strawraaberry = 100 dikurangi angka ini.</p></div>
        <div><label class="label">Dasar perhitungan *</label><select name="basis" class="input">@foreach (App\Models\PartnerTerm::BASES as $k => $t)<option value="{{ $k }}" @selected(old('basis') === $k)>{{ $t }}</option>@endforeach</select></div>
        <div><label class="label">Nomor / referensi perjanjian</label><input name="agreement_ref" value="{{ old('agreement_ref') }}" class="input"></div>
        <div class="md:col-span-2"><label class="label">Ketentuan diskon</label><input name="discount_policy" value="{{ old('discount_policy') }}" class="input"></div>
        <div class="md:col-span-3"><label class="label">Ketentuan retur</label><input name="return_policy" value="{{ old('return_policy') }}" class="input"></div>
        <p class="text-xs font-semibold text-cocoa/60 md:col-span-3" x-show="scheme==='fixed_per_product'">Komisi tetap per produk diisi di tab Skema pada halaman detail mitra, karena membutuhkan daftar produk.</p>
        <div class="md:col-span-3" x-show="scheme==='custom'"><label class="label">Deskripsi skema khusus</label><textarea name="custom_description" rows="2" class="input">{{ old('custom_description') }}</textarea></div>
    </section>
    @endif

    <div class="flex gap-2"><button class="btn" :disabled="busy">Simpan</button><a href="{{ $new ? route('partners.index') : route('partners.show', $store) }}" class="btn-soft">Batal</a></div>
</form>
@endsection