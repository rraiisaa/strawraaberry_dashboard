@extends('layouts.app')
@section('title', $store->name)

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $tabs = ['ringkasan' => 'Ringkasan', 'stok' => 'Stok', 'restock' => 'Restock', 'laporan' => 'Laporan', 'setoran' => 'Setoran dan Tagihan', 'skema' => 'Skema Bagi Hasil', 'aktivitas' => 'Aktivitas'];
@endphp
@include('partners._subnav')

<div x-data="{ tab: '{{ array_key_exists($tab, $tabs) ? $tab : 'ringkasan' }}', stockForm: false, termForm: false, busy: false }">

    <div class="card mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-display text-3xl font-semibold">{{ $store->name }}</h1>
                <span class="pill {{ App\Models\Store::STATUS_COLORS[$store->status] }}">{{ $store->status_label }}</span>
                @if ($store->trashed())<span class="pill bg-gray-200">Diarsipkan</span>@endif
            </div>
            <div class="mt-1 text-sm font-semibold text-cocoa/70">{{ $store->code }} | PIC {{ $store->pic_name ?? '-' }} | {{ $store->phone }} @if ($store->email)| {{ $store->email }}@endif</div>
            <div class="text-sm text-cocoa/70">{{ $store->address }}, {{ $store->city }} {{ $store->province }}</div>
            <div class="mt-1 text-xs font-semibold text-cocoa/60">Kerja sama sejak {{ optional($store->start_date)->format('d/m/Y') ?? '-' }} @if ($store->end_date)sampai {{ $store->end_date->format('d/m/Y') }}@else tanpa tanggal akhir @endif</div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($store->maps_url)<a href="{{ $store->maps_url }}" target="_blank" rel="noopener" class="btn-soft">Buka Google Maps</a>@endif
            @unless ($store->trashed())
                <a href="{{ route('restocks.create', ['store_id' => $store->id]) }}" class="btn-soft">Buat restock</a>
                <a href="{{ route('reports.create', ['store_id' => $store->id]) }}" class="btn-soft">Tambah laporan</a>
                <a href="{{ route('payments.create', ['store_id' => $store->id]) }}" class="btn-soft">Catat setoran</a>
                <a href="{{ route('partners.edit', $store) }}" class="btn-soft">Edit profil</a>
                <form method="POST" action="{{ route('partners.archive', $store) }}" data-confirm="Arsipkan {{ $store->name }}? Seluruh riwayat transaksi tetap tersimpan." data-confirm-title="Arsipkan mitra?" data-confirm-type="danger" data-confirm-ok="Ya, arsipkan">{{ csrf_field() }}<button class="btn-soft">Arsipkan</button></form>
            @else
                <form method="POST" action="{{ route('partners.restore', $store) }}">{{ csrf_field() }}<button class="btn">Aktifkan kembali</button></form>
            @endunless
        </div>
    </div>

    <div class="mb-5 flex flex-wrap gap-2" role="tablist">
        @foreach ($tabs as $k => $label)
            <button role="tab" x-on:click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-mint shadow-pastel' : 'bg-white'" class="rounded-2xl px-4 py-2 text-sm font-bold transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- RINGKASAN --}}
    <section x-show="tab==='ringkasan'" x-cloak class="space-y-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card bg-white"><div class="label">Penjualan bersih (terverifikasi)</div><div class="font-display text-xl font-semibold">{{ $rp($totals['sales']) }}</div></div>
            <div class="card bg-mint/60"><div class="label">Bagian Strawraaberry</div><div class="font-display text-xl font-semibold">{{ $rp($totals['own']) }}</div></div>
            <div class="card bg-berry-soft/30"><div class="label">Bagian mitra</div><div class="font-display text-xl font-semibold">{{ $rp($totals['store']) }}</div></div>
            <div class="card bg-lav/60"><div class="label">Unit layak jual di toko</div><div class="font-display text-xl font-semibold">{{ collect($stock)->sum('sellable') }}</div></div>
            <div class="card bg-white"><div class="label">Total kewajiban</div><div class="font-display text-xl font-semibold">{{ $rp($totals['due']) }}</div></div>
            <div class="card bg-mint/60"><div class="label">Sudah disetor (terverifikasi)</div><div class="font-display text-xl font-semibold">{{ $rp($totals['paid']) }}</div></div>
            <div class="card bg-butter/60"><div class="label">Belum disetor</div><div class="font-display text-xl font-semibold">{{ $rp($totals['remaining']) }}</div></div>
            <div class="card {{ $totals['overdue'] > 0 ? 'bg-berry-soft/50' : 'bg-white' }}"><div class="label">Lewat jatuh tempo</div><div class="font-display text-xl font-semibold">{{ $rp($totals['overdue']) }}</div></div>
        </div>
        <div class="card"><div class="label">Ketentuan bagi hasil yang berlaku hari ini</div>
            @if ($term)
                <div class="font-bold">{{ App\Models\PartnerTerm::SCHEMES[$term->scheme_type] }}@if ($term->scheme_type === 'percentage'): toko {{ rtrim(rtrim($term->store_pct, '0'), '.') }}% | Strawraaberry {{ rtrim(rtrim($term->own_pct, '0'), '.') }}%@endif</div>
                <div class="text-sm text-cocoa/70">Dasar: {{ App\Models\PartnerTerm::BASES[$term->basis] }} | berlaku sejak {{ $term->effective_from->format('d/m/Y') }} @if ($term->agreement_ref)| Ref. {{ $term->agreement_ref }}@endif</div>
            @else
                <div class="text-sm font-bold text-berry">Belum ada skema yang berlaku hari ini. Atur di tab Skema Bagi Hasil sebelum membuat laporan.</div>
            @endif</div>
        <div class="card"><h2 class="mb-2 font-display text-xl font-semibold">Penjualan bersih per bulan (terverifikasi)</h2>
            @if ($monthly) <div class="h-64"><canvas id="storeChart"></canvas></div> @else <div class="py-8 text-center text-sm font-semibold">Belum ada laporan terverifikasi untuk mitra ini.</div> @endif</div>
        @if ($store->notes)<div class="card"><div class="label">Catatan internal</div><p class="whitespace-pre-line text-sm">{{ $store->notes }}</p></div>@endif
    </section>

    {{-- STOK --}}
    <section x-show="tab==='stok'" x-cloak class="card">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div><h2 class="font-display text-xl font-semibold">Stok konsinyasi di toko</h2><p class="text-xs font-semibold text-cocoa/60">Dihitung dari ledger pergerakan. Akhir = masuk + lolos periksa - terjual - dikembalikan - hilang - rusak + koreksi.</p></div>
            <button x-on:click="stockForm = true" class="btn-soft">Catat pergerakan stok</button>
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[960px]">
            <thead><tr class="border-b border-lav"><th class="th">Produk</th><th class="th">Masuk</th><th class="th">Terjual</th><th class="th">Retur pembeli</th><th class="th">Lolos periksa</th><th class="th">Dikembalikan</th><th class="th">Rusak</th><th class="th">Hilang</th><th class="th">Koreksi</th><th class="th">Akhir (layak jual)</th><th class="th">Karantina</th><th class="th">Rusak di toko</th><th class="th">Terakhir</th></tr></thead>
            <tbody>
            @forelse ($stock as $r)
                <tr class="border-b border-cloud"><td class="td font-bold">{{ $r['product']->name }}<div class="text-xs font-normal text-cocoa/60">{{ $r['product']->sku }} | {{ $rp($r['product']->selling_price) }}</div></td>
                    <td class="td">{{ $r['in'] }}</td><td class="td">{{ $r['sold'] }}</td><td class="td">{{ $r['customer_return'] }}</td><td class="td">{{ $r['inspect_ok'] }}</td><td class="td">{{ $r['returned'] }}</td><td class="td">{{ $r['damaged_marked'] }}</td><td class="td">{{ $r['lost'] }}</td><td class="td">{{ $r['corrections'] }}</td>
                    <td class="td"><span class="pill {{ $r['sellable'] <= 2 ? 'bg-berry-soft/60' : 'bg-mint' }}">{{ $r['sellable'] }}</span></td><td class="td">{{ $r['quarantine'] }}</td><td class="td">{{ $r['damaged'] }}</td><td class="td">{{ $r['last_on'] ? \Carbon\Carbon::parse($r['last_on'])->format('d/m/Y') : '-' }}</td></tr>
            @empty
                <tr><td colspan="13" class="td py-10 text-center font-semibold">Belum ada pergerakan stok. Stok muncul setelah restock dikonfirmasi diterima.</td></tr>
            @endforelse
            </tbody></table></div>
    </section>

    {{-- RESTOCK --}}
    <section x-show="tab==='restock'" x-cloak class="card overflow-x-auto">
        <table class="w-full min-w-[640px]"><thead><tr class="border-b border-lav"><th class="th">Nomor</th><th class="th">Dikirim</th><th class="th">Unit dikirim</th><th class="th">Unit diterima</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
        @forelse ($restocks as $r)
            <tr class="border-b border-cloud"><td class="td font-bold">{{ $r->number }}</td><td class="td">{{ $r->ship_date?->format('d/m/Y') ?? '-' }}</td><td class="td">{{ $r->total_sent }}</td><td class="td">{{ $r->total_received }}</td>
                <td class="td"><span class="pill {{ App\Models\Restock::COLORS[$r->status] }}">{{ $r->status_label }}</span></td><td class="td text-right"><a href="{{ route('restocks.show', $r) }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a></td></tr>
        @empty <tr><td colspan="6" class="td py-10 text-center font-semibold">Belum ada restock.</td></tr> @endforelse
        </tbody></table>
    </section>

    {{-- LAPORAN --}}
    <section x-show="tab==='laporan'" x-cloak class="card overflow-x-auto">
        <table class="w-full min-w-[760px]"><thead><tr class="border-b border-lav"><th class="th">Nomor</th><th class="th">Periode</th><th class="th">Penjualan bersih</th><th class="th">Bagian toko</th><th class="th">Bagian Strawraaberry</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
        @forelse ($reports as $r)
            <tr class="border-b border-cloud"><td class="td font-bold">{{ $r->number }}@if ($r->is_late)<span class="pill ml-1 bg-butter">Terlambat</span>@endif</td><td class="td">{{ $r->period_start->format('d/m/Y') }} - {{ $r->period_end->format('d/m/Y') }}</td>
                <td class="td">{{ $rp($r->net_sales) }}</td><td class="td">{{ $rp($r->store_share) }}</td><td class="td">{{ $rp($r->own_share) }}</td>
                <td class="td"><span class="pill {{ App\Models\SalesReport::COLORS[$r->status] }}">{{ $r->status_label }}</span></td><td class="td text-right"><a href="{{ route('reports.show', $r) }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a></td></tr>
        @empty <tr><td colspan="7" class="td py-10 text-center font-semibold">Belum ada laporan penjualan.</td></tr> @endforelse
        </tbody></table>
    </section>

    {{-- SETORAN --}}
    <section x-show="tab==='setoran'" x-cloak class="space-y-5">
        <div class="card overflow-x-auto"><h2 class="mb-2 font-display text-xl font-semibold">Tagihan</h2>
            <table class="w-full min-w-[720px]"><thead><tr class="border-b border-lav"><th class="th">Laporan</th><th class="th">Jatuh tempo</th><th class="th">Tagihan</th><th class="th">Dibayar</th><th class="th">Sisa</th><th class="th">Status</th></tr></thead><tbody>
            @forelse ($obligations as $o)
                <tr class="border-b border-cloud"><td class="td"><a class="font-bold text-berry hover:underline" href="{{ route('reports.show', $o->sales_report_id) }}">{{ $o->report->number }}</a></td><td class="td">{{ $o->due_date->format('d/m/Y') }}@if ($o->days_late > 0)<span class="pill ml-1 bg-berry-soft/60">{{ $o->days_late }} hari</span>@endif</td>
                    <td class="td">{{ $rp($o->amount_due) }}</td><td class="td">{{ $rp((int) $o->paid_sum) }}</td><td class="td font-bold">{{ $rp($o->amount_due - (int) $o->paid_sum) }}</td>
                    <td class="td"><span class="pill {{ App\Models\Obligation::STATUS_COLORS[$o->status] }}">{{ $o->status_label }}</span></td></tr>
            @empty <tr><td colspan="6" class="td py-8 text-center font-semibold">Belum ada tagihan. Tagihan muncul saat laporan diverifikasi.</td></tr> @endforelse
            </tbody></table></div>
        <div class="card overflow-x-auto"><h2 class="mb-2 font-display text-xl font-semibold">Riwayat setoran</h2>
            <table class="w-full min-w-[640px]"><thead><tr class="border-b border-lav"><th class="th">Nomor</th><th class="th">Tanggal</th><th class="th">Diterima</th><th class="th">Teralokasi</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
            @forelse ($payments as $p)
                <tr class="border-b border-cloud"><td class="td font-bold">{{ $p->number }}</td><td class="td">{{ $p->paid_on->format('d/m/Y') }}</td><td class="td">{{ $rp($p->amount_received) }}</td><td class="td">{{ $rp($p->allocated) }}</td>
                    <td class="td"><span class="pill {{ App\Models\Payment::COLORS[$p->status] }}">{{ $p->status_label }}</span></td><td class="td text-right"><a href="{{ route('payments.show', $p) }}" class="btn-soft !px-3 !py-1 text-xs">Buka</a></td></tr>
            @empty <tr><td colspan="6" class="td py-8 text-center font-semibold">Belum ada setoran.</td></tr> @endforelse
            </tbody></table></div>
    </section>

    {{-- SKEMA --}}
    <section x-show="tab==='skema'" x-cloak class="space-y-4">
        <div class="flex items-center justify-between"><p class="text-sm font-semibold text-cocoa/70">Skema baru tidak mengubah laporan lama. Setiap laporan menyimpan snapshot skema pada periodenya.</p>
            @unless ($store->trashed())<button x-on:click="termForm = true" class="btn">Skema baru</button>@endunless</div>
        @foreach ($terms as $t)
            <div class="card {{ $loop->first ? 'ring-2 ring-berry-soft' : '' }}">
                <div class="flex flex-wrap items-center justify-between gap-2"><div class="font-bold">{{ App\Models\PartnerTerm::SCHEMES[$t->scheme_type] }}@if ($t->scheme_type === 'percentage'): toko {{ rtrim(rtrim($t->store_pct, '0'), '.') }}% | Strawraaberry {{ rtrim(rtrim($t->own_pct, '0'), '.') }}%@endif</div>
                    <span class="pill bg-lav">{{ $t->effective_from->format('d/m/Y') }} s/d {{ $t->effective_to?->format('d/m/Y') ?? 'sekarang' }}</span></div>
                <div class="mt-1 text-sm text-cocoa/70">Dasar: {{ App\Models\PartnerTerm::BASES[$t->basis] }} @if ($t->agreement_ref)| Ref. {{ $t->agreement_ref }}@endif</div>
                @if ($t->discount_policy)<div class="text-sm"><b>Diskon:</b> {{ $t->discount_policy }}</div>@endif
                @if ($t->return_policy)<div class="text-sm"><b>Retur:</b> {{ $t->return_policy }}</div>@endif
                @if ($t->custom_description)<div class="text-sm"><b>Keterangan:</b> {{ $t->custom_description }}</div>@endif
                @if ($t->commissions->isNotEmpty())<div class="mt-1 text-sm"><b>Komisi tetap:</b> {{ $t->commissions->map(fn ($c) => $c->product->name . ' ' . $rp($c->amount_per_unit))->implode(', ') }}</div>@endif
                @if ($t->change_note)<div class="mt-1 text-xs font-semibold text-cocoa/60">Catatan perubahan: {{ $t->change_note }}</div>@endif
            </div>
        @endforeach
    </section>

    {{-- AKTIVITAS --}}
    <section x-show="tab==='aktivitas'" x-cloak class="card">
        @forelse ($logs as $l)
            <div class="border-b border-cloud py-2 text-sm"><div class="font-bold">{{ $l->description }}</div>
                <div class="text-xs text-cocoa/60">{{ $l->created_at->format('d/m/Y H:i') }} | {{ $l->actor }} | {{ $l->action }}@if ($l->reason) | Alasan: {{ $l->reason }}@endif</div></div>
        @empty <div class="py-8 text-center text-sm font-semibold">Belum ada aktivitas.</div> @endforelse
    </section>

    {{-- Modal stok --}}
    <div x-show="stockForm" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" x-on:keydown.escape.window="stockForm = false">
        <form method="POST" action="{{ route('partners.stock.move', $store) }}" x-on:click.outside="stockForm = false" x-on:submit="if (busy) $event.preventDefault(); busy = true" class="card w-full max-w-md space-y-3" x-data="{ type: 'inspect_ok' }">
            {{ csrf_field() }} <input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}">
            <h3 class="font-display text-xl font-semibold">Catat pergerakan stok</h3>
            <div><label class="label">Jenis</label><select name="type" x-model="type" class="input">@foreach ($stockTypes as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
            <div><label class="label">Produk</label><select name="product_id" class="input">@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->label }}</option>@endforeach</select></div>
            <div x-show="['mark_damaged','write_off_lost','return_to_owner'].includes(type)"><label class="label">Diambil dari</label>
                <select name="from_bucket" class="input"><option value="sellable">Layak jual</option><option value="quarantine" x-show="type!=='return_to_owner'">Karantina</option><option value="damaged" x-show="type==='return_to_owner'">Rusak di toko</option></select></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="label">Jumlah</label><input type="number" name="qty" required class="input"><p class="mt-1 text-xs text-cocoa/60" x-show="type==='adjustment'">Boleh negatif untuk mengurangi.</p></div>
                <div><label class="label">Tanggal</label><input type="date" name="occurred_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input"></div></div>
            <div><label class="label">Alasan dan bukti (wajib untuk rusak, hilang, dikembalikan, penyesuaian)</label><textarea name="reason" rows="2" class="input"></textarea></div>
            <div class="flex justify-end gap-2"><button type="button" x-on:click="stockForm=false" class="btn-soft">Batal</button><button class="btn" :disabled="busy">Simpan ke ledger</button></div>
        </form>
    </div>

    {{-- Modal skema --}}
    <div x-show="termForm" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-cocoa/40 p-4" x-on:keydown.escape.window="termForm = false">
        <form method="POST" action="{{ route('partners.terms.store', $store) }}" x-on:click.outside="termForm = false" class="card max-h-[90vh] w-full max-w-lg space-y-3 overflow-y-auto" x-data="{ scheme: 'percentage' }">
            {{ csrf_field() }}
            <h3 class="font-display text-xl font-semibold">Skema bagi hasil baru</h3>
            <div><label class="label">Jenis</label><select name="scheme_type" x-model="scheme" class="input">@foreach (App\Models\PartnerTerm::SCHEMES as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
            <div x-show="scheme==='percentage'"><label class="label">Persentase bagian toko (%)</label><input type="number" step="0.01" min="0" max="100" name="store_pct" class="input"></div>
            <div><label class="label">Dasar perhitungan</label><select name="basis" class="input">@foreach (App\Models\PartnerTerm::BASES as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="label">Berlaku mulai</label><input type="date" name="effective_from" required class="input"></div><div><label class="label">Referensi perjanjian</label><input name="agreement_ref" class="input"></div></div>
            <div><label class="label">Ketentuan diskon</label><input name="discount_policy" class="input"></div>
            <div><label class="label">Ketentuan retur</label><input name="return_policy" class="input"></div>
            <div x-show="scheme==='custom'"><label class="label">Deskripsi skema khusus</label><textarea name="custom_description" rows="2" class="input"></textarea></div>
            <div x-show="scheme==='fixed_per_product'" class="max-h-48 space-y-2 overflow-y-auto rounded-xl bg-cloud p-3"><div class="label">Komisi tetap per unit (Rp)</div>
                @foreach ($products as $p)<div class="flex items-center gap-2 text-sm"><span class="flex-1">{{ $p->label }}</span><input type="number" min="0" name="commissions[{{ $p->id }}]" class="input !w-28 !py-1"></div>@endforeach</div>
            <div><label class="label">Alasan / catatan perubahan *</label><textarea name="change_note" rows="2" required class="input"></textarea></div>
            <div class="flex justify-end gap-2"><button type="button" x-on:click="termForm=false" class="btn-soft">Batal</button><button class="btn">Simpan skema</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if ($monthly)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const D = @json($monthly), rp = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    document.addEventListener('alpine:initialized', () => {
        new Chart(document.getElementById('storeChart'), { type: 'bar', data: { labels: Object.keys(D), datasets: [{ label: 'Penjualan bersih', data: Object.values(D), backgroundColor: '#FFB7C5', borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: c => ' ' + rp(c.parsed.y) } } }, scales: { y: { ticks: { callback: v => (v / 1000) + 'k' } } } } });
    });
</script>
@endif
@endpush