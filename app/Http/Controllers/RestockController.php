<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RunsBusinessActions;
use App\Models\{Product, Restock, Store};
use App\Services\RestockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestockController extends Controller
{
    use RunsBusinessActions;

    public function __construct(private RestockService $svc) {}

    public function index(Request $request)
    {
        $restocks = Restock::with('store', 'items')
            ->when($request->store_id, fn ($q, $v) => $q->where('store_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->q, fn ($q, $v) => $q->where('number', 'like', "%{$v}%"))
            ->latest('id')->paginate(15)->withQueryString();
        return view('restocks.index', ['restocks' => $restocks, 'stores' => Store::orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        return view('restocks.form', ['restock' => new Restock(['store_id' => $request->store_id]), 'stores' => Store::whereIn('status', ['active', 'paused'])->orderBy('name')->get(), 'products' => Product::orderBy('name')->get()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'store_id' => 'required|exists:stores,id', 'ship_date' => 'nullable|date', 'handed_by' => 'nullable|string|max:120', 'received_by' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000', 'request_key' => 'required|string|max:64', 'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'items' => 'required|array|min:1', 'items.*.product_id' => 'required|exists:products,id', 'items.*.qty_sent' => 'required|integer|min:1|max:100000',
            'items.*.condition' => 'nullable|in:baik,rusak',
        ]);
    }

    /** action: draft = simpan saja | send = kirim (gudang berkurang) | send_receive = serah terima langsung (gudang berkurang, stok toko bertambah). */
    private function persist(Request $request, ?Restock $restock = null)
    {
        $d = $this->validated($request);
        $action = in_array($request->input('action'), ['send', 'send_receive'], true) ? $request->input('action') : 'draft';
        if ($action !== 'draft' && empty($d['ship_date'])) $d['ship_date'] = today()->toDateString();

        $msg = ['draft' => 'Draft restock disimpan. Stok belum berubah sampai dikirim.',
                'send' => 'Restock dikirim. Stok gudang sudah berkurang; stok toko bertambah setelah penerimaan dikonfirmasi.',
                'send_receive' => 'Serah terima tercatat. Stok gudang berkurang dan stok toko langsung bertambah.'][$action];

        return $this->attempt(function () use ($request, $d, $action, $restock) {
            return DB::transaction(function () use ($request, $d, $action, $restock) {
                $r = $this->svc->save($d, $d['items'], $restock);
                if ($p = $this->storeProof($request, 'restock')) $r->update(['proof_path' => $p]);
                if ($action !== 'draft') {
                    $this->svc->send($r->refresh());
                    if ($action === 'send_receive') {
                        $r->refresh()->load('items');
                        $this->svc->confirm($r, $r->items->pluck('qty_sent', 'id')->all(), 0, ['received_date' => today()->toDateString(), 'received_by' => $d['received_by'] ?? null]);
                    }
                }
                return $r;
            });
        }, $msg, fn ($r) => route('restocks.show', $r));
    }

    public function store(Request $request) { return $this->persist($request); }

    public function show(Restock $restock)
    {
        return view('restocks.show', ['restock' => $restock->load('store', 'items.product')]);
    }

    public function edit(Restock $restock)
    {
        abort_unless($restock->status === 'draft', 403, 'Hanya draft yang bisa diedit.');
        return view('restocks.form', ['restock' => $restock->load('items'), 'stores' => Store::whereIn('status', ['active', 'paused'])->orderBy('name')->get(), 'products' => Product::orderBy('name')->get()]);
    }

    public function update(Request $request, Restock $restock) { return $this->persist($request, $restock); }

    public function send(Restock $restock) { return $this->attempt(fn () => $this->svc->send($restock), 'Restock ditandai dikirim. Stok gudang berkurang.'); }

    public function confirm(Request $request, Restock $restock)
    {
        $d = $request->validate(['received' => 'required|array', 'received.*' => 'nullable|integer|min:0', 'seq' => 'required|integer|min:0',
            'received_date' => 'required|date|before_or_equal:today', 'received_by' => 'nullable|string|max:120']);
        return $this->attempt(fn () => $this->svc->confirm($restock, $d['received'], (int) $d['seq'], $d), 'Penerimaan dikonfirmasi. Stok toko diperbarui.');
    }

    public function cancel(Request $request, Restock $restock)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        return $this->attempt(fn () => $this->svc->cancel($restock, $d['reason']), 'Restock dibatalkan dan stok dikoreksi lewat reversal.');
    }

    public function print(Restock $restock) { return view('restocks.print', ['restock' => $restock->load('store', 'items.product')]); }
}