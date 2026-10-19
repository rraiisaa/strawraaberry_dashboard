@extends('layouts.app')
@section('title', 'Produk & Stok')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $badge = ['Pin' => 'bg-berry-soft/60', 'Sticker' => 'bg-lav', 'Lanyard' => 'bg-berry-soft/30', 'Hairclip' => 'bg-lav/60', 'Notepad' => 'bg-mint'];
@endphp

<div x-data="{ form: false, edit: null, rejectId: null, blank() { return { id: null, name: '', category: 'Pin', cost_price: 0, selling_price: 0, stock_qty: 0, note: '' } } }"
     x-init="edit = blank()">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold">Produk, Stok & HPP</h1>
            <p class="text-sm font-semibold text-cocoa/60">Nilai stok aktif {{ $rp($stockValue) }} · modal tertahan di produk cacat {{ $rp($defectLoss) }}</p>
        </div>
        <div class="flex gap-2">
            <form method="GET"><select name="category" class="input !w-auto" onchange="this.form.submit()"><option value="">Semua kategori</option>@foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></form>
            <button @click="edit = blank(); form = true" class="btn"><i data-lucide="plus" class="h-4 w-4"></i> Produk baru</button>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[820px]">
            <thead><tr class="border-b border-lav"><th class="th">Produk</th><th class="th">HPP</th><th class="th">Harga jual</th><th class="th">Margin</th><th class="th">Stok aktif</th><th class="th">Cacat</th><th class="th"></th></tr></thead>
            <tbody>
            @foreach ($products as $p)
                <tr class="border-b border-cloud">
                    <td class="td"><div class="font-bold">{{ $p->name }}</div><span class="pill {{ $badge[$p->category] ?? 'bg-cloud' }}">{{ $p->category }}</span></td>
                    <td class="td">{{ $rp($p->cost_price) }}</td>
                    <td class="td">{{ $rp($p->selling_price) }}</td>
                    <td class="td"><span class="pill {{ $p->margin >= 50 ? 'bg-mint' : 'bg-butter' }}">{{ $p->margin }}%</span></td>
                    <td class="td"><span class="font-bold">{{ $p->stock_qty }}</span> @if ($p->is_low_stock)<span class="pill bg-berry text-white">Restock!</span>@endif</td>
                    <td class="td">@if ($p->defective_qty)<span class="pill bg-berry-soft/60"> {{ $p->defective_qty }}</span>@else <span class="text-cocoa/40">–</span>@endif</td>
                    <td class="td">
                        <div class="flex justify-end gap-1">
                            <button @click="rejectId = {{ $p->id }}" class="rounded-lg bg-butter p-1.5" title="Pindah ke cacat"><i data-lucide="shield-alert" class="h-4 w-4"></i></button>
                            <button @click='edit = @json($p->only(["id","name","category","cost_price","selling_price","stock_qty","note"]); form = true' class="rounded-lg bg-lav p-1.5" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                            <form method="POST" action="{{ route('products.destroy', $p) }}" onsubmit="return confirm('Hapus {{ $p->name }}?')">@csrf @method('DELETE')<button class="rounded-lg bg-berry-soft/50 p-1.5" title="Hapus"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>
                        </div>
                        <form x-show="rejectId === {{ $p->id }}" x-cloak method="POST" action="{{ route('products.reject', $p) }}" class="mt-2 flex justify-end gap-1">
                            @csrf <input type="number" name="qty" min="1" max="{{ $p->stock_qty }}" placeholder="qty cacat" class="input !w-24 !py-1" required><button class="btn !px-3 !py-1">OK</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal tambah / edit --}}
    <div x-show="form" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" @keydown.escape.window="form = false">
        <form method="POST" :action="edit.id ? '{{ url('produk') }}/' + edit.id : '{{ route('products.store') }}'" @click.outside="form = false" class="card w-full max-w-md space-y-3">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <h3 class="font-display text-xl font-semibold" x-text="edit.id ? 'Edit produk' : 'Produk baru'"></h3>
            <div><label class="label">Nama</label><input name="name" x-model="edit.name" required class="input"></div>
            <div><label class="label">Kategori</label><select name="category" x-model="edit.category" class="input">@foreach ($categories as $c)<option>{{ $c }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">HPP (Rp)</label><input type="number" min="0" name="cost_price" x-model.number="edit.cost_price" class="input"></div>
                <div><label class="label">Harga jual (Rp)</label><input type="number" min="0" name="selling_price" x-model.number="edit.selling_price" class="input"></div>
            </div>
            <div class="rounded-xl bg-mint/60 px-3 py-2 text-xs font-bold">Margin otomatis: <span x-text="edit.selling_price > 0 ? Math.round((edit.selling_price - edit.cost_price) / edit.selling_price * 100) + '%' : '–'"></span> · laba/pcs Rp <span x-text="(edit.selling_price - edit.cost_price).toLocaleString('id-ID')"></span></div>
            <div><label class="label">Stok aktif</label><input type="number" min="0" name="stock_qty" x-model.number="edit.stock_qty" class="input"></div>
            <div><label class="label">Catatan</label><input name="note" x-model="edit.note" class="input"></div>
            <div class="flex justify-end gap-2"><button type="button" @click="form=false" class="btn-soft">Batal</button><button class="btn">Simpan</button></div>
        </form>
    </div>
</div>
@endsection