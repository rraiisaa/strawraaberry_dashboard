@extends('layouts.app')
@section('title', 'Aliran Dana')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<div x-data="{ type: 'expense' }" class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('cashflow.store') }}" class="card h-fit space-y-3">
        @csrf
        <h2 class="font-display text-xl font-semibold">Catat cepat</h2>
        <div class="grid grid-cols-2 gap-2 text-sm font-bold">
            <label class="cursor-pointer rounded-xl p-2 text-center" :class="type==='income' ? 'bg-mint' : 'bg-cloud'"><input type="radio" name="type" value="income" x-model="type" class="sr-only">Pemasukan</label>
            <label class="cursor-pointer rounded-xl p-2 text-center" :class="type==='expense' ? 'bg-berry-soft' : 'bg-cloud'"><input type="radio" name="type" value="expense" x-model="type" class="sr-only">Pengeluaran</label>
        </div>
        <div><label class="label">Kategori</label>
            <select name="category" class="input">
                <template x-if="type==='income'"><optgroup label="Pemasukan">@foreach ($incomeCats as $c)<option>{{ $c }}</option>@endforeach</optgroup></template>
                <template x-if="type==='expense'"><optgroup label="Pengeluaran">@foreach ($expenseCats as $c)<option>{{ $c }}</option>@endforeach</optgroup></template>
            </select></div>
        <div><label class="label">Jumlah (Rp)</label><input type="number" min="1" name="amount" required class="input"></div>
        <div><label class="label">Catatan</label><input name="description" class="input"></div>
        <div><label class="label">Tanggal</label><input type="date" name="date" value="{{ now()->toDateString() }}" class="input"></div>
        <button class="btn w-full">Simpan</button>
    </form>

    <div class="card overflow-x-auto lg:col-span-2">
        <h2 class="mb-3 font-display text-xl font-semibold">Riwayat</h2>
        <table class="w-full min-w-[520px]">
            <thead><tr class="border-b border-lav"><th class="th">Tanggal</th><th class="th">Kategori</th><th class="th">Catatan</th><th class="th text-right">Jumlah</th><th class="th"></th></tr></thead>
            <tbody>
            @forelse ($flows as $f)
                <tr class="border-b border-cloud">
                    <td class="td">{{ $f->date->format('d M Y') }}</td>
                    <td class="td"><span class="pill {{ $f->type === 'income' ? 'bg-mint' : 'bg-berry-soft/60' }}">{{ $f->category }}</span></td>
                    <td class="td">{{ $f->description }}</td>
                    <td class="td text-right font-bold {{ $f->type === 'income' ? 'text-emerald-600' : 'text-berry' }}">{{ $f->type === 'income' ? '+' : '–' }}{{ $rp($f->amount) }}</td>
                    <td class="td"><form method="POST" action="{{ route('cashflow.destroy', $f) }}" onsubmit="return confirm('Hapus catatan ini?')">@csrf @method('DELETE')<button class="rounded-lg p-1 hover:bg-berry-soft/40"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="td py-8 text-center font-semibold">Belum ada catatan. Mulai dari pengeluaran pertamamu!</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $flows->links() }}</div>
    </div>
</div>
@endsection