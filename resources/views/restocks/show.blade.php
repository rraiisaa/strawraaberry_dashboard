@extends('layouts.app')
@section('title', $restock->number)

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
@include('partners._subnav')

<div x-data="{ cancelForm: false, busy: false }">
<div class="card mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><div class="flex items-center gap-2"><h1 class="font-display text-3xl font-semibold">{{ $restock->number }}</h1><span class="pill {{ App\Models\Restock::COLORS[$restock->status] }}">{{ $restock->status_label }}</span></div>
        <div class="text-sm font-semibold text-cocoa/70">Mitra: <a href="{{ route('partners.show', $restock->store_id) }}" class="text-berry hover:underline">{{ $restock->store->name }}</a> | Dikirim {{ $restock->ship_date?->format('d/m/Y') ?? '-' }} | Diterima {{ $restock->received_date?->format('d/m/Y') ?? '-' }}</div>
        <div class="text-sm text-cocoa/70">Menyerahkan: {{ $restock->handed_by ?? '-' }} | Menerima: {{ $restock->received_by ?? '-' }}</div>
        @if ($restock->notes)<p class="mt-1 text-sm">{{ $restock->notes }}</p>@endif
        @if ($restock->cancel_reason)<p class="mt-1 text-sm font-bold text-berry">Dibatalkan: {{ $restock->cancel_reason }}</p>@endif</div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('restocks.print', $restock) }}" target="_blank" class="btn-soft">Cetak bukti restock</a>
        @if ($restock->proof_path)<a href="{{ route('files.proof', ['restock', $restock->id]) }}" target="_blank" class="btn-soft">Lihat bukti</a>@endif
        @if ($restock->status === 'draft')
            <a href="{{ route('restocks.edit', $restock) }}" class="btn-soft">Edit draft</a>
            <form method="POST" action="{{ route('restocks.send', $restock) }}" data-confirm="Tandai dikirim? Stok gudang akan berkurang." data-confirm-title="Kirim restock sekarang?" data-confirm-type="primary" data-confirm-ok="Ya, kirim">{{ csrf_field() }}<button class="btn">Tandai dikirim</button></form>
        @endif
        @if ($restock->status !== 'cancelled')<button x-on:click="cancelForm = true" class="btn-soft">Batalkan</button>@endif
    </div>
</div>

<form method="POST" action="{{ route('restocks.confirm', $restock) }}" x-on:submit="if (busy) $event.preventDefault(); busy = true">
{{ csrf_field() }} <input type="hidden" name="seq" value="{{ $restock->confirm_count }}">
<div class="card overflow-x-auto">
    <h2 class="mb-2 font-display text-xl font-semibold">Rincian produk</h2>
    <table class="w-full min-w-[720px]"><thead><tr class="border-b border-lav"><th class="th">Produk</th><th class="th">Harga jual</th><th class="th">Kondisi</th><th class="th">Dikirim</th><th class="th">Diterima</th><th class="th">Selisih</th>@if (in_array($restock->status, ['sent', 'partial']))<th class="th">Diterima sekarang</th>@endif</tr></thead><tbody>
    @foreach ($restock->items as $i)
        <tr class="border-b border-cloud"><td class="td font-bold">{{ $i->product->name }}<div class="text-xs font-normal text-cocoa/60">{{ $i->product->sku }}</div></td><td class="td">{{ $rp($i->unit_price) }}</td><td class="td">{{ ucfirst($i->condition) }}</td>
            <td class="td">{{ $i->qty_sent }}</td><td class="td">{{ $i->qty_received }}</td><td class="td">@if ($i->qty_gap > 0 && $restock->status !== 'draft')<span class="pill bg-butter">Kurang {{ $i->qty_gap }}</span>@else - @endif</td>
            @if (in_array($restock->status, ['sent', 'partial']))<td class="td"><input type="number" name="received[{{ $i->id }}]" min="0" max="{{ $i->qty_gap }}" value="{{ $i->qty_gap }}" class="input !w-24 !py-1"></td>@endif</tr>
    @endforeach
    </tbody><tfoot><tr><td class="td font-bold" colspan="3">Total</td><td class="td font-bold">{{ $restock->total_sent }}</td><td class="td font-bold">{{ $restock->total_received }}</td><td colspan="2"></td></tr></tfoot></table>

    @if (in_array($restock->status, ['sent', 'partial']))
        <div class="mt-4 grid gap-3 rounded-xl bg-mint/40 p-4 md:grid-cols-3">
            <div><label class="label">Tanggal diterima</label><input type="date" name="received_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input"></div>
            <div><label class="label">Nama penerima</label><input name="received_by" value="{{ $restock->received_by }}" class="input"></div>
            <div class="flex items-end"><button class="btn w-full" :disabled="busy">Konfirmasi penerimaan</button></div>
            <p class="text-xs font-semibold text-cocoa/70 md:col-span-3">Stok toko bertambah sesuai jumlah yang benar-benar diterima. Isi sebagian bila barang belum lengkap; sisa dapat dikonfirmasi kemudian.</p>
        </div>
    @endif
</div></form>

<div x-show="cancelForm" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4">
    <form method="POST" action="{{ route('restocks.cancel', $restock) }}" x-on:click.outside="cancelForm = false" class="card w-full max-w-md space-y-3">{{ csrf_field() }}
        <h3 class="font-display text-xl font-semibold">Batalkan {{ $restock->number }}</h3>
        <p class="text-sm">Restock tidak dihapus. Jika stok sudah masuk toko, ledger dikoreksi lewat reversal dan seluruh unit dikirim dikembalikan ke stok gudang.</p>
        <div><label class="label">Alasan *</label><textarea name="reason" rows="2" required class="input"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" x-on:click="cancelForm=false" class="btn-soft">Kembali</button><button class="btn">Batalkan restock</button></div>
    </form>
</div>
</div>
@endsection