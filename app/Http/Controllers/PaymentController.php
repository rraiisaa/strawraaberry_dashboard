<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RunsBusinessActions;
use App\Models\{Obligation, Payment, Store};
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use RunsBusinessActions;

    public function __construct(private PaymentService $svc) {}

    public function index(Request $request)
    {
        $payments = Payment::with('store', 'allocations')
            ->when($request->store_id, fn ($q, $v) => $q->where('store_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('paid_on')->latest('id')->paginate(15)->withQueryString();

        $obligations = Obligation::with('store', 'report')->whereNull('cancelled_at')
            ->when($request->store_id, fn ($q, $v) => $q->where('store_id', $v))
            ->withSum(['allocations as paid_sum' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'verified'))], 'amount')
            ->orderBy('due_date')->get()->filter(fn ($o) => $o->amount_due - (int) $o->paid_sum != 0);

        return view('payments.index', ['payments' => $payments, 'obligations' => $obligations, 'stores' => Store::orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        $storeId = $request->integer('store_id') ?: null;
        $open = $storeId ? Obligation::with('report')->where('store_id', $storeId)->whereNull('cancelled_at')->orderBy('due_date')->get()->filter(fn ($o) => $o->available_to_allocate > 0) : collect();
        return view('payments.form', ['stores' => Store::orderBy('name')->get(), 'storeId' => $storeId, 'open' => $open]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'store_id' => 'required|exists:stores,id', 'paid_on' => 'required|date|before_or_equal:today', 'amount_expected' => 'nullable|integer|min:0',
            'amount_received' => 'required|integer|min:1', 'method' => 'required|string|max:30', 'reference_no' => 'nullable|string|max:120',
            'destination_account' => 'nullable|string|max:160', 'notes' => 'nullable|string|max:1000', 'request_key' => 'required|string|max:64',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', 'alloc' => 'nullable|array', 'alloc.*' => 'nullable|integer|min:0',
        ]);
        return $this->attempt(function () use ($request, $d) {
            $p = $this->svc->record($d, $d['alloc'] ?? []);
            if ($f = $this->storeProof($request, 'payment')) $p->update(['proof_path' => $f]);
            return $p;
        }, 'Setoran dicatat dengan status Menunggu Verifikasi. Belum masuk kas sampai diverifikasi.', fn ($p) => route('payments.show', $p));
    }

    public function show(Payment $payment)
    {
        $payment->load('store', 'allocations.obligation.report');
        $open = Obligation::with('report')->where('store_id', $payment->store_id)->whereNull('cancelled_at')->get()->filter(fn ($o) => $o->available_to_allocate > 0);
        return view('payments.show', ['payment' => $payment, 'open' => $open]);
    }

    public function verify(Payment $payment) { return $this->attempt(fn () => $this->svc->verify($payment), 'Setoran diverifikasi dan dicatat ke Aliran Dana.'); }

    public function reject(Request $request, Payment $payment)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        return $this->attempt(fn () => $this->svc->reject($payment, $d['reason']), 'Setoran ditolak.');
    }

    public function reverse(Request $request, Payment $payment)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        return $this->attempt(fn () => $this->svc->reverse($payment, $d['reason']), 'Setoran direversal. Kas dikoreksi dengan catatan lawan.');
    }

    public function allocate(Request $request, Payment $payment)
    {
        $d = $request->validate(['obligation_id' => 'required|exists:obligations,id', 'amount' => 'required|integer|min:1']);
        return $this->attempt(fn () => $this->svc->allocateSurplus($payment, (int) $d['obligation_id'], (int) $d['amount']), 'Saldo lebih dialokasikan.');
    }

    public function bill(Obligation $obligation)
    {
        abort_if($obligation->cancelled_at, 422);
        $obligation->update(['billed_at' => $obligation->billed_at ?? now()]);
        \App\Services\ActivityLogger::log('obligation.bill', "Tagihan {$obligation->report->number} ditandai sudah ditagih", $obligation->report, $obligation->store_id);
        return back()->with('success', 'Tagihan ditandai sudah ditagih.');
    }
}