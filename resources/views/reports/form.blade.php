@extends('layouts.app')
@section('title', 'Laporan Penjualan')

@section('content')
@include('partners._subnav')
@php
    $edit = $report->exists; $revise = $mode === 'revise'; $custom = ($term?->scheme_type ?? $report->term_snapshot['scheme_type'] ?? null) === 'custom';
    $init = old('lines', $edit ? $report->lines->map(fn ($l) => ['product_id' => $l->product_id, 'qty_sold' => $l->qty_sold, 'unit_price' => $l->unit_price, 'discount_amount' => $l->discount_amount, 'return_qty' => $l->return_qty])->all() : [['product_id' => '', 'qty_sold' => 1, 'unit_price' => '', 'discount_amount' => 0, 'return_qty' => 0]]);
    $price = $products->pluck('selling_price', 'id');
    $action = $revise ? route('reports.revise', $report) : ($edit ? route('reports.update', $report) : route('reports.store'));
@endphp
<h1 class="mb-1 font-display text-3xl font-semibold">{{ $revise ? 'Revisi ' . $report->number : ($edit ? 'Edit draft ' . $report->number : 'Laporan penjualan baru') }}</h1>
@if (! $edit && $report->store_id && ! $term)<div class="mb-4 rounded-xl bg-berry-soft/50 p-3 text-sm font-bold">Mitra ini belum punya skema bagi hasil yang berlaku. Atur dulu di halaman detail mitra, tab Skema.</div>@endif
@if ($revise)<div class="mb-4 rounded-xl bg-butter p-3 text-sm font-bold">Laporan sudah terverifikasi. Perubahan akan membalik ledger lama, memposting ulang, mengubah kewajiban, dan dicatat sebagai revisi {{ $report->revision_no + 1 }} lengkap dengan alasan.</div>@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      x-data='{ rows: @json(array_values($init)), price: @json($price), stock: @json($stock), busy: false,
        add() { this.rows.push({ product_id: "", qty_sold: 1, unit_price: "", discount_amount: 0, return_qty: 0 }) },
        up(r) { return r.unit_price !== "" ? parseInt(r.unit_price) : (this.price[r.product_id] || 0) },
        gross() { return this.rows.reduce((a, r) => a + (parseInt(r.qty_sold) || 0) * this.up(r), 0) },
        disc() { return this.rows.reduce((a, r) => a + (parseInt(r.discount_amount) || 0), 0) },
        ret() { return this.rows.reduce((a, r) => a + (parseInt(r.return_qty) || 0) * this.up(r), 0) },
        fmt(n) { return "Rp " + Number(n).toLocaleString("id-ID") } }'
      x-on:submit="if (busy) $event.preventDefault(); busy = true" class="space-y-5">
    {{ csrf_field() }} @if ($edit && ! $revise) {{ method_field('PUT') }} @endif
    <input type="hidden" name="request_key" value="{{ $edit ? $report->request_key : Illuminate\Support\Str::uuid() }}">

    <section class="card grid gap-4 md:grid-cols-3">
        @if ($edit)
            <div><div class="label">Mitra</div><div class="font-bold">{{ $report->store->name }}</div></div>
            <div><div class="label">Periode (tetap)</div><div class="font-bold">{{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }}</div></div><div></div>
        @else
            <div><label class="label">Mitra *</label><select name="store_id" required class="input" onchange="location.href='{{ route('reports.create') }}?store_id=' + this.value"><option value="">Pilih mitra</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $report->store_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div><label class="label">Periode mulai *</label><input type="date" name="period_start" value="{{ old('period_start') }}" required class="input"></div>
            <div><label class="label">Periode akhir *</label><input type="date" name="period_end" value="{{ old('period_end') }}" required class="input"></div>
        @endif
        <div><label class="label">Tanggal laporan diterima</label><input type="date" name="received_at" value="{{ old('received_at', optional($report->received_at)->toDateString()) }}" class="input"></div>
        <div><label class="label">Sumber laporan</label><input name="source" value="{{ old('source', $report->source) }}" class="input" placeholder="WhatsApp, Excel kasir, nota"></div>
        <div><label class="label">Total penjualan menurut toko (cek silang)</label><input type="number" name="reported_total" value="{{ old('reported_total', $report->reported_total) }}" class="input"></div>
        <div><label class="label">Bukti / file pendukung</label><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.csv,.xlsx" class="input"></div>
        @if ($custom)<div><label class="label">Bagian toko (Rp) sesuai perjanjian khusus *</label><input type="number" name="custom_store_share" value="{{ old('custom_store_share', $report->custom_store_share) }}" min="0" class="input"></div>@endif
    </section>

    <section class="card">
        <div class="mb-3 flex items-center justify-between"><h2 class="font-display text-xl font-semibold">Rincian produk terjual</h2><button type="button" x-on:click="add()" class="btn-soft">Tambah baris</button></div>
        <div class="space-y-2">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid grid-cols-12 items-end gap-2 rounded-xl bg-cloud p-3">
                    <div class="col-span-12 md:col-span-4"><label class="label">Produk (SKU) <span class="font-normal" x-show="row.product_id && stock[row.product_id] !== undefined" x-text="'| stok toko: ' + stock[row.product_id]"></span></label>
                        <select :name="`lines[${i}][product_id]`" x-model="row.product_id" required class="input"><option value="">Pilih produk</option>@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->label }}</option>@endforeach</select></div>
                    <div class="col-span-3 md:col-span-1"><label class="label">Terjual</label><input type="number" min="1" :name="`lines[${i}][qty_sold]`" x-model="row.qty_sold" required class="input"></div>
                    <div class="col-span-4 md:col-span-2"><label class="label">Harga/unit</label><input type="number" min="0" :name="`lines[${i}][unit_price]`" x-model="row.unit_price" :placeholder="price[row.product_id] || ''" class="input"></div>
                    <div class="col-span-5 md:col-span-2"><label class="label">Diskon (total baris)</label><input type="number" min="0" :name="`lines[${i}][discount_amount]`" x-model="row.discount_amount" class="input"></div>
                    <div class="col-span-3 md:col-span-1"><label class="label">Retur</label><input type="number" min="0" :name="`lines[${i}][return_qty]`" x-model="row.return_qty" class="input"></div>
                    <div class="col-span-9 md:col-span-2 text-right"><button type="button" x-on:click="rows.splice(i, 1)" x-show="rows.length > 1" class="rounded-lg bg-berry-soft/50 px-2 py-1 text-xs font-bold">Hapus</button></div>
                </div>
            </template>
        </div>
        <div class="mt-4 grid gap-3 rounded-xl bg-mint/40 p-4 text-sm font-bold md:grid-cols-3"><div>Penjualan kotor: <span x-text="fmt(gross())"></span></div><div>Diskon: <span x-text="fmt(disc())"></span></div><div>Retur: <span x-text="fmt(ret())"></span></div>
            <p class="text-xs font-semibold text-cocoa/70 md:col-span-3">Ini hanya pratinjau. Angka final (bagi hasil sesuai skema periode) dihitung server saat disimpan.</p></div>
    </section>

    <section class="card grid gap-4 md:grid-cols-2">
        <div><label class="label">Penyesuaian disetujui (Rp, boleh negatif)</label><input type="number" name="adjustments_amount" value="{{ old('adjustments_amount', $report->adjustments_amount ?? 0) }}" class="input"></div>
        <div><label class="label">Alasan penyesuaian</label><input name="adjustments_note" value="{{ old('adjustments_note', $report->adjustments_note) }}" class="input"></div>
        <div class="md:col-span-2"><label class="label">Catatan</label><textarea name="notes" rows="2" class="input">{{ old('notes', $report->notes) }}</textarea></div>
        @if ($revise)<div class="md:col-span-2"><label class="label">Alasan revisi *</label><textarea name="reason" rows="2" required class="input">{{ old('reason') }}</textarea></div>@endif
    </section>
    <div class="flex gap-2"><button class="btn" :disabled="busy">{{ $revise ? 'Simpan revisi' : 'Simpan draft' }}</button><a href="{{ $edit ? route('reports.show', $report) : route('reports.index') }}" class="btn-soft">Batal</a></div>
</form>
@endsection