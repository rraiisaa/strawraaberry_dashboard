@extends('layouts.app')
@section('title', 'Aliran Dana')

@section('content')
<script type="application/json" id="sale-products">{!! json_encode($products->mapWithKeys(fn ($p) => [$p->id => ['stock' => $p->stock_qty, 'price' => $p->selling_price]]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

<div x-data="cashflowForm()" class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('cashflow.store') }}" class="card h-fit space-y-3" x-on:submit="if (busy || !canSubmit()) { $event.preventDefault(); return } busy = true">
        {{ csrf_field() }}
        <input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}">
        <h2 class="font-display text-xl font-semibold">Catat cepat</h2>

        <div class="grid grid-cols-2 gap-2 text-sm font-bold">
            <label class="cursor-pointer rounded-xl p-2 text-center transition" :class="type==='income' ? 'bg-mint' : 'bg-cloud'"><input type="radio" name="type" value="income" x-model="type" class="sr-only">Pemasukan</label>
            <label class="cursor-pointer rounded-xl p-2 text-center transition" :class="type==='expense' ? 'bg-berry-soft' : 'bg-cloud'"><input type="radio" name="type" value="expense" x-model="type" class="sr-only">Pengeluaran</label>
        </div>

        <div>
            <label class="label">Kategori</label>
            <select name="category" x-model="catIncome" x-show="type==='income'" :disabled="type!=='income'" class="input">
                @foreach ($incomeCats as $c)
                    <option>{{ $c }}</option>
                @endforeach
            </select>
            <select name="category" x-model="catExpense" x-show="type==='expense'" :disabled="type!=='expense'" class="input">
                @foreach ($expenseCats as $c)
                    <option>{{ $c }}</option>
                @endforeach
            </select>
        </div>

        {{-- Penjualan produk: stok gudang berkurang otomatis --}}
        <template x-if="isSale()">
            <div class="space-y-2 rounded-2xl bg-mint/40 p-3">
                <div class="text-xs font-bold text-cocoa/70">Produk terjual (diambil dari Produk dan Stok). Stok berkurang otomatis saat disimpan.</div>
                <template x-for="(row, i) in rows" :key="i">
                    <div class="space-y-1 rounded-xl bg-white p-2">
                        <select :name="`items[${i}][product_id]`" x-model="row.product_id" x-on:change="row.unit_price = ''" required class="input">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} | stok {{ $p->stock_qty }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-5 items-center gap-2">
                            <input type="number" min="1" :name="`items[${i}][qty]`" x-model.number="row.qty" class="input col-span-2" placeholder="Jumlah" required>
                            <input type="number" min="0" :name="`items[${i}][unit_price]`" x-model="row.unit_price" :placeholder="row.product_id ? price(row.product_id) : 'Harga'" class="input col-span-2">
                            <button type="button" x-on:click="rows.splice(i, 1)" x-show="rows.length > 1" class="rounded-lg bg-berry-soft/50 px-2 py-2 text-xs font-bold">Hapus</button>
                        </div>
                        <p class="text-xs font-bold text-berry" x-show="row.product_id && row.qty > stock(row.product_id)" x-text="'Melebihi stok gudang (' + stock(row.product_id) + ')'"></p>
                    </div>
                </template>
                <button type="button" x-on:click="rows.push({ product_id: '', qty: 1, unit_price: '' })" class="btn-soft w-full">Tambah produk</button>
                <div class="flex items-center justify-between rounded-xl bg-white px-3 py-2 text-sm font-bold"><span>Total pemasukan</span><span x-text="rp(total())"></span></div>
            </div>
        </template>

        <div x-show="!isSale()"><label class="label">Jumlah (Rp)</label><input type="number" min="1" name="amount" :disabled="isSale()" :required="!isSale()" class="input"></div>
        <div><label class="label">Catatan</label><input name="description" class="input" placeholder="mis. Pesanan Aima, PO Batch 2"></div>
        <div><label class="label">Tanggal</label><input type="date" name="date" value="{{ today()->toDateString() }}" class="input"></div>
        <button class="btn w-full" :class="busy && 'opacity-60'">Simpan</button>
    </form>

    <div class="card overflow-x-auto lg:col-span-2">
        <h2 class="mb-3 font-display text-xl font-semibold">Riwayat</h2>
        <table class="w-full min-w-[520px]">
            <thead><tr class="border-b border-lav"><th class="th">Tanggal</th><th class="th">Kategori</th><th class="th">Catatan</th><th class="th text-right">Jumlah</th><th class="th"></th></tr></thead>
            <tbody>
            @forelse ($flows as $f)
                <tr class="border-b border-cloud">
                    <td class="td">{{ $f->date->format('d M Y') }}</td>
                    <td class="td"><span class="pill {{ $f->type === 'income' ? 'bg-mint' : 'bg-berry-soft/60' }}">{{ $f->category }}</span>
                        @if ($f->source_type === 'sales_transaction')<span class="pill bg-lav">Stok otomatis</span>@endif</td>
                    <td class="td">{{ $f->description }}</td>
                    <td class="td text-right font-bold {{ $f->type === 'income' ? 'text-emerald-600' : 'text-berry' }}">{{ $f->type === 'income' ? '+' : '-' }}Rp {{ number_format($f->amount, 0, ',', '.') }}</td>
                    <td class="td">
                        <form method="POST" action="{{ route('cashflow.destroy', $f) }}" data-confirm-type="danger"
                              data-confirm-title="{{ $f->source_type === 'sales_transaction' ? 'Batalkan penjualan ini?' : 'Hapus catatan ini?' }}"
                              data-confirm="{{ $f->source_type === 'sales_transaction' ? 'Stok produk akan dikembalikan ke gudang dan catatan kas ikut dihapus.' : 'Catatan kas ini akan dihapus permanen.' }}"
                              data-confirm-ok="{{ $f->source_type === 'sales_transaction' ? 'Ya, batalkan' : 'Ya, hapus' }}">
                            {{ csrf_field() }}{{ method_field('DELETE') }}<button class="rounded-lg p-1 hover:bg-berry-soft/40" title="Hapus"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="td py-8 text-center font-semibold">Belum ada catatan. Mulai dari pemasukan atau pengeluaran pertamamu.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $flows->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function cashflowForm() {
        const P = JSON.parse(document.getElementById('sale-products').textContent);
        return {
            type: 'income', catIncome: 'Penjualan Langsung', catExpense: 'Bahan Baku', busy: false,
            rows: [{ product_id: '', qty: 1, unit_price: '' }],
            isSale() { return this.type === 'income' && this.catIncome === 'Penjualan Langsung'; },
            price(id) { return P[id] ? P[id].price : 0; },
            stock(id) { return P[id] ? P[id].stock : 0; },
            total() { return this.rows.reduce((a, r) => a + (parseInt(r.qty) || 0) * (r.unit_price !== '' ? parseInt(r.unit_price) || 0 : this.price(r.product_id)), 0); },
            rp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); },
            canSubmit() { return !this.isSale() || this.rows.every(r => r.product_id && r.qty > 0 && r.qty <= this.stock(r.product_id)); },
        };
    }
</script>
@endpush