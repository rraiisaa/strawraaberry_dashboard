@extends('layouts.app')
@section('title', 'Restock')

@section('content')
@include('partners._subnav')
@php
    $edit = $restock->exists;
    $init = old('items', $edit ? $restock->items->map(fn ($i) => ['product_id' => $i->product_id, 'qty_sent' => $i->qty_sent, 'condition' => $i->condition])->all() : [['product_id' => '', 'qty_sent' => 1, 'condition' => 'baik']]);
    $price = $products->pluck('selling_price', 'id');
@endphp
<h1 class="mb-5 font-display text-3xl font-semibold">{{ $edit ? 'Edit draft ' . $restock->number : 'Restock baru' }}</h1>

<form method="POST" action="{{ $edit ? route('restocks.update', $restock) : route('restocks.store') }}" enctype="multipart/form-data"
      x-data='{ rows: @json(array_values($init)), price: @json($price), busy: false, add() { this.rows.push({ product_id: "", qty_sent: 1, condition: "baik" }) }, total() { return this.rows.reduce((a, r) => a + (parseInt(r.qty_sent) || 0), 0) } }'
      x-on:submit="if (busy) $event.preventDefault(); busy = true" class="space-y-5">
    {{ csrf_field() }} @if ($edit) {{ method_field('PUT') }} @endif
    <input type="hidden" name="request_key" value="{{ $edit ? $restock->request_key : Illuminate\Support\Str::uuid() }}">

    <section class="card grid gap-4 md:grid-cols-3">
        <div><label class="label">Mitra tujuan *</label><select name="store_id" required class="input"><option value="">Pilih mitra</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $restock->store_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
        <div><label class="label">Tanggal pengiriman</label><input type="date" name="ship_date" value="{{ old('ship_date', optional($restock->ship_date)->toDateString() ?? today()->toDateString()) }}" class="input"></div>
        <div></div>
        <div><label class="label">Pihak yang menyerahkan</label><input name="handed_by" value="{{ old('handed_by', $restock->handed_by) }}" class="input"></div>
        <div><label class="label">Pihak yang menerima</label><input name="received_by" value="{{ old('received_by', $restock->received_by) }}" class="input"></div>
        <div><label class="label">Foto / bukti pengiriman</label><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" class="input"></div>
        <div class="md:col-span-3"><label class="label">Catatan</label><textarea name="notes" rows="2" class="input">{{ old('notes', $restock->notes) }}</textarea></div>
    </section>

    <section class="card">
        <div class="mb-3 flex items-center justify-between"><h2 class="font-display text-xl font-semibold">Produk (<span x-text="total()"></span> unit)</h2><button type="button" x-on:click="add()" class="btn-soft">Tambah baris</button></div>
        <p class="mb-3 text-xs font-semibold text-cocoa/60">Produk diambil dari master Produk dan Stok. Harga jual berlaku dicatat saat disimpan. Stok gudang baru berkurang saat restock ditandai dikirim.</p>
        <div class="space-y-2">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid grid-cols-12 items-end gap-2 rounded-xl bg-cloud p-3">
                    <div class="col-span-12 md:col-span-6"><label class="label">Produk (SKU)</label>
                        <select :name="`items[${i}][product_id]`" x-model="row.product_id" required class="input"><option value="">Pilih produk</option>@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->label }} | stok gudang {{ $p->stock_qty }}</option>@endforeach</select></div>
                    <div class="col-span-4 md:col-span-2"><label class="label">Jumlah</label><input type="number" min="1" :name="`items[${i}][qty_sent]`" x-model="row.qty_sent" required class="input"></div>
                    <div class="col-span-5 md:col-span-2"><label class="label">Kondisi</label><select :name="`items[${i}][condition]`" x-model="row.condition" class="input"><option value="baik">Baik</option><option value="rusak">Rusak</option></select></div>
                    <div class="col-span-3 md:col-span-2 text-right"><div class="text-xs font-semibold text-cocoa/60" x-text="row.product_id ? 'Rp ' + Number(price[row.product_id]).toLocaleString('id-ID') : ''"></div>
                        <button type="button" x-on:click="rows.splice(i, 1)" x-show="rows.length > 1" class="mt-1 rounded-lg bg-berry-soft/50 px-2 py-1 text-xs font-bold">Hapus</button></div>
                </div>
            </template>
        </div>
    </section>
    <section class="card space-y-3">
        <p class="text-sm font-semibold text-cocoa/70">Pilih cara menyimpan. Stok gudang di Produk dan Stok berkurang saat barang dikirim, bukan saat draft.</p>
        <div class="flex flex-wrap gap-2" :class="busy && 'opacity-60'">
            <button type="submit" name="action" value="draft" class="btn-soft">Simpan draft</button>
            <button type="submit" name="action" value="send" class="btn-soft">Simpan dan kirim</button>
            <button type="submit" name="action" value="send_receive" class="btn">Serah terima langsung</button>
            <a href="{{ route('restocks.index') }}" class="btn-soft">Batal</a>
        </div>
        <p class="text-xs font-semibold text-cocoa/60"><b>Serah terima langsung</b>: barang diserahkan di tempat. Stok gudang langsung berkurang dan stok toko langsung bertambah sesuai jumlah di atas.</p>
    </section>
</form>
@endsection