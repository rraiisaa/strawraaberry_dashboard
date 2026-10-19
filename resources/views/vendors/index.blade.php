@extends('layouts.app')
@section('title', 'Vendor')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); $tints = ['bg-berry-soft/40', 'bg-butter', 'bg-mint', 'bg-lav']; @endphp

<div x-data="{ form: false, edit: null, blank() { return { id: null, name: '', contact: '', social_media: '', cost_price: 0, detail: '', location: '' } } }"
     x-init="edit = blank()">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold">Vendor</h1>
            <p class="text-sm font-semibold text-cocoa/60">{{ $total }} vendor tercatat, rata-rata harga pokok {{ $rp($avgCost) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="GET" class="flex gap-2">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, lokasi, detail" class="input !w-56">
                <button class="btn-soft">Cari</button>
            </form>
            <button x-on:click="edit = blank(); form = true" class="btn"><i data-lucide="plus" class="h-4 w-4"></i> Vendor baru</button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($vendors as $v)
            <article class="card flex flex-col">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl font-display text-lg font-semibold {{ $tints[$loop->index % 4] }}">{{ strtoupper(mb_substr($v->name, 0, 1)) }}</div>
                        <div>
                            <h2 class="font-display text-lg font-semibold leading-tight">{{ $v->name }}</h2>
                            @if ($v->location)<div class="flex items-center gap-1 text-xs font-semibold text-cocoa/60"><i data-lucide="map-pin" class="h-3 w-3"></i>{{ $v->location }}</div>@endif
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" data-row="{{ json_encode($v->only(['id','name','contact','social_media','cost_price','detail','location'])) }}" x-on:click="edit = JSON.parse($el.dataset.row); form = true" class="rounded-lg bg-lav p-1.5" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                        <form method="POST" action="{{ route('vendors.destroy', $v) }}" data-confirm="Hapus {{ $v->name }}?" data-confirm-title="Hapus data ini?" data-confirm-type="danger" data-confirm-ok="Ya, hapus">{{ csrf_field() }} {{ method_field('DELETE') }}<button class="rounded-lg bg-berry-soft/50 p-1.5" title="Hapus"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-butter/50 px-3 py-2">
                    <div class="label !mb-0">Harga pokok</div>
                    <div class="font-display text-xl font-semibold">{{ $rp($v->cost_price) }}</div>
                </div>

                <dl class="mt-3 space-y-1.5 text-sm">
                    @if ($v->contact)<div class="flex items-center gap-2"><i data-lucide="phone" class="h-4 w-4 shrink-0"></i><dd class="font-semibold">{{ $v->contact }}</dd></div>@endif
                    @if ($v->social_media)<div class="flex items-center gap-2"><i data-lucide="at-sign" class="h-4 w-4 shrink-0"></i><dd><a href="{{ $v->social_url }}" target="_blank" rel="noopener" class="font-semibold text-berry underline-offset-2 hover:underline">{{ $v->social_media }}</a></dd></div>@endif
                </dl>

                @if ($v->detail)<p class="mt-3 flex-1 whitespace-pre-line text-sm text-cocoa/80">{{ $v->detail }}</p>@endif
            </article>
        @empty
            <div class="card col-span-full py-10 text-center text-sm font-semibold">
                {{ request('q') ? 'Tidak ada vendor yang cocok dengan pencarian.' : 'Belum ada vendor. Tambahkan vendor pertama kamu.' }}
            </div>
        @endforelse
    </div>

    <div x-show="form" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" x-on:keydown.escape.window="form = false">
        <form method="POST" :action="edit.id ? '{{ url('vendor') }}/' + edit.id : '{{ route('vendors.store') }}'" x-on:click.outside="form = false" class="card max-h-[90vh] w-full max-w-md space-y-3 overflow-y-auto">
            {{ csrf_field() }}
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <h3 class="font-display text-xl font-semibold" x-text="edit.id ? 'Edit vendor' : 'Vendor baru'"></h3>
            <div><label class="label">Nama vendor</label><input name="name" x-model="edit.name" required class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Kontak</label><input name="contact" x-model="edit.contact" class="input" placeholder="WA / telepon / email"></div>
                <div><label class="label">Sosial media</label><input name="social_media" x-model="edit.social_media" class="input" placeholder="@akun atau URL"></div>
            </div>
            <div><label class="label">Harga pokok (Rp)</label><input type="number" min="0" name="cost_price" x-model.number="edit.cost_price" required class="input"></div>
            <div><label class="label">Lokasi</label><input name="location" x-model="edit.location" class="input"></div>
            <div><label class="label">Detail</label><textarea name="detail" x-model="edit.detail" rows="3" class="input" placeholder="Barang yang disuplai, minimal order, lead time, catatan"></textarea></div>
            <div class="flex justify-end gap-2"><button type="button" x-on:click="form=false" class="btn-soft">Batal</button><button class="btn">Simpan</button></div>
        </form>
    </div>
</div>
@endsection