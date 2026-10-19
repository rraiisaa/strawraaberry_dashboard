<?php
namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\{CashFlow, Obligation, PartnerTerm, Payment, Product, Restock, SalesReport, Store, StockMovement};
use App\Services\{PaymentService, ReportService, RestockService, StockLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerFlowTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Product $pin;
    private int $k = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pin = Product::create(['name' => 'Pin A', 'category' => 'Pin', 'cost_price' => 4500, 'selling_price' => 10000, 'stock_qty' => 100, 'defective_qty' => 0]);
        $this->store = $this->makeStore('Toko A', '20');
    }

    private function makeStore(string $name, string $pct): Store
    {
        $s = Store::create(['name' => $name, 'status' => 'active', 'commission_rate' => $pct, 'start_date' => '2026-01-01', 'report_due_days' => 7, 'payment_due_days' => 14]);
        $s->terms()->create(['scheme_type' => 'percentage', 'store_pct' => $pct, 'own_pct' => 100 - $pct, 'basis' => 'actual_price', 'effective_from' => '2026-01-01']);
        return $s;
    }

    private function key(): string { return 'k' . (++$this->k); }

    private function restock(int $qty, ?Store $store = null, bool $confirm = true): Restock
    {
        $svc = app(RestockService::class); $store ??= $this->store;
        $r = $svc->save(['store_id' => $store->id, 'ship_date' => '2026-02-01', 'request_key' => $this->key()], [['product_id' => $this->pin->id, 'qty_sent' => $qty]]);
        if ($confirm) { $svc->send($r); $svc->confirm($r, [$r->items()->first()->id => $qty], 0, ['received_date' => '2026-02-02']); }
        return $r->refresh();
    }

    private function report(array $lines, string $start = '2026-02-01', string $end = '2026-02-28', ?Store $store = null, array $extra = []): SalesReport
    {
        return app(ReportService::class)->save($extra + ['store_id' => ($store ?? $this->store)->id, 'period_start' => $start, 'period_end' => $end, 'received_at' => $end, 'request_key' => $this->key()], $lines);
    }

    private function verified(int $sold = 10, string $start = '2026-02-01', string $end = '2026-02-28'): SalesReport
    {
        $r = $this->report([['product_id' => $this->pin->id, 'qty_sold' => $sold]], $start, $end);
        app(ReportService::class)->submit($r); app(ReportService::class)->verify($r);
        return $r->refresh();
    }

    private function sellable(?Store $s = null): int { return StockLedger::balance(($s ?? $this->store)->id, $this->pin->id); }

    // ---- stok & restock ----
    public function test_draft_restock_and_sent_do_not_add_store_stock(): void
    {
        $r = $this->restock(30, null, false);
        $this->assertSame(0, $this->sellable());
        $this->assertSame(100, $this->pin->fresh()->stock_qty);
        app(RestockService::class)->send($r);
        $this->assertSame(0, $this->sellable());                      // dikirim belum berarti diterima
        $this->assertSame(70, $this->pin->fresh()->stock_qty);        // gudang berkurang saat kirim
    }

    public function test_partial_receipt_and_double_confirm_is_idempotent(): void
    {
        $svc = app(RestockService::class);
        $r = $this->restock(30, null, false); $svc->send($r); $item = $r->items()->first();
        $svc->confirm($r, [$item->id => 20], 0);
        $this->assertSame('partial', $r->fresh()->status);
        $this->assertSame(20, $this->sellable());
        $this->expectException(BusinessRuleException::class);          // form usang / klik ganda dengan seq lama
        $svc->confirm($r, [$item->id => 20], 0);
    }

    public function test_double_confirm_does_not_change_stock(): void
    {
        $svc = app(RestockService::class);
        $r = $this->restock(30, null, false); $svc->send($r); $item = $r->items()->first();
        $svc->confirm($r, [$item->id => 30], 0);
        try { $svc->confirm($r, [$item->id => 30], 0); } catch (BusinessRuleException) {}
        $this->assertSame(30, $this->sellable());
    }

    public function test_duplicate_request_key_creates_one_restock(): void
    {
        $svc = app(RestockService::class);
        $d = ['store_id' => $this->store->id, 'request_key' => 'same'];
        $svc->save($d, [['product_id' => $this->pin->id, 'qty_sent' => 5]]);
        $svc->save($d, [['product_id' => $this->pin->id, 'qty_sent' => 5]]);
        $this->assertSame(1, Restock::count());
    }

    public function test_cancel_confirmed_restock_reverses_ledger_and_restores_warehouse(): void
    {
        $r = $this->restock(30);
        app(RestockService::class)->cancel($r, 'salah kirim');
        $this->assertSame(0, $this->sellable());
        $this->assertSame(100, $this->pin->fresh()->stock_qty);
        $this->assertSame(2, StockMovement::count());                  // asli + reversal, tidak ada yang dihapus
    }

    public function test_ledger_rows_cannot_be_edited_or_deleted(): void
    {
        $this->restock(5);
        $m = StockMovement::first();
        $this->expectException(\LogicException::class);
        $m->update(['qty' => 999]);
    }

    // ---- laporan & bagi hasil ----
    public function test_draft_report_does_not_touch_stock_or_create_obligation(): void
    {
        $this->restock(50);
        $this->report([['product_id' => $this->pin->id, 'qty_sold' => 10]]);
        $this->assertSame(50, $this->sellable());
        $this->assertSame(0, Obligation::count());
    }

    public function test_verify_deducts_stock_and_creates_obligation_with_correct_split(): void
    {
        $this->restock(50);
        $r = $this->verified(10);
        $this->assertSame(40, $this->sellable());
        $this->assertSame(100000, $r->net_sales);
        $this->assertSame(20000, $r->store_share);
        $this->assertSame(80000, $r->own_share);
        $this->assertSame(80000, $r->obligation->amount_due);
        $this->assertSame('2026-03-14', $r->obligation->due_date->toDateString());  // akhir periode + 14 hari
    }

    public function test_verify_blocked_when_sold_exceeds_store_stock(): void
    {
        $this->restock(5);
        $r = $this->report([['product_id' => $this->pin->id, 'qty_sold' => 10]]);
        $this->expectException(BusinessRuleException::class);
        app(ReportService::class)->submit($r);
    }

    public function test_two_partners_use_their_own_percentages(): void
    {
        $b = $this->makeStore('Toko B', '35');
        $this->restock(50); $this->restock(50, $b);
        $ra = $this->verified(10);
        $rb = $this->report([['product_id' => $this->pin->id, 'qty_sold' => 10]], '2026-02-01', '2026-02-28', $b);
        app(ReportService::class)->submit($rb); app(ReportService::class)->verify($rb);
        $this->assertSame(20000, $ra->store_share);
        $this->assertSame(35000, $rb->fresh()->store_share);
    }

    public function test_new_term_does_not_change_historical_report(): void
    {
        $this->restock(50);
        $old = $this->verified(10);
        $this->store->terms()->first()->update(['effective_to' => '2026-02-28']);
        $this->store->terms()->create(['scheme_type' => 'percentage', 'store_pct' => 40, 'own_pct' => 60, 'basis' => 'actual_price', 'effective_from' => '2026-03-01']);
        $new = $this->report([['product_id' => $this->pin->id, 'qty_sold' => 10]], '2026-03-01', '2026-03-31');
        $this->assertSame(20000, $old->fresh()->store_share);          // tetap 20%
        $this->assertSame(40000, $new->store_share);                    // 40% untuk periode baru
        $this->assertEquals(20, (float) $old->fresh()->term_snapshot['store_pct']);
    }

    public function test_report_spanning_term_change_is_rejected(): void
    {
        $this->store->terms()->first()->update(['effective_to' => '2026-02-14']);
        $this->store->terms()->create(['scheme_type' => 'percentage', 'store_pct' => 40, 'own_pct' => 60, 'basis' => 'actual_price', 'effective_from' => '2026-02-15']);
        $this->expectException(BusinessRuleException::class);
        $this->report([['product_id' => $this->pin->id, 'qty_sold' => 1]], '2026-02-01', '2026-02-28');
    }

    public function test_overlapping_verified_period_is_blocked(): void
    {
        $this->restock(50); $this->verified(5);
        $dup = $this->report([['product_id' => $this->pin->id, 'qty_sold' => 5]], '2026-02-15', '2026-03-15');
        $this->expectException(BusinessRuleException::class);
        app(ReportService::class)->submit($dup);
    }

    public function test_customer_return_goes_to_quarantine_not_sellable(): void
    {
        $this->restock(50);
        $r = $this->report([['product_id' => $this->pin->id, 'qty_sold' => 10, 'return_qty' => 2]]);
        app(ReportService::class)->submit($r); app(ReportService::class)->verify($r);
        $this->assertSame(40, $this->sellable());
        $this->assertSame(2, StockLedger::balance($this->store->id, $this->pin->id, 'quarantine'));
        $this->assertSame(80000, $r->fresh()->net_sales);               // 100000 - 20000
    }

    public function test_revise_verified_report_updates_ledger_obligation_and_keeps_history(): void
    {
        $this->restock(50); $r = $this->verified(10);
        $r = app(ReportService::class)->revise($r, ['adjustments_amount' => 0], [['product_id' => $this->pin->id, 'qty_sold' => 12]], 'Nota toko ternyata 12');
        $this->assertSame(38, $this->sellable());
        $this->assertSame(96000, $r->obligation->fresh()->amount_due);
        $this->assertSame(1, $r->revisions()->count());
        $this->assertSame(80000, $r->revisions()->first()->before['own_share']);
        $this->assertSame(96000, $r->revisions()->first()->after['own_share']);
    }

    public function test_cancel_verified_report_with_payment_is_blocked(): void
    {
        $this->restock(50); $r = $this->verified(10);
        $p = app(PaymentService::class)->record(['store_id' => $this->store->id, 'paid_on' => '2026-03-01', 'amount_received' => 80000, 'request_key' => $this->key()], [$r->obligation->id => 80000]);
        app(PaymentService::class)->verify($p);
        $this->expectException(BusinessRuleException::class);
        app(ReportService::class)->cancel($r, 'salah input');
    }

    // ---- setoran ----
    private function pay(int $amount, array $alloc, bool $verify = true): Payment
    {
        $p = app(PaymentService::class)->record(['store_id' => $this->store->id, 'paid_on' => '2026-03-01', 'amount_received' => $amount, 'request_key' => $this->key()], $alloc);
        if ($verify) app(PaymentService::class)->verify($p);
        return $p->refresh();
    }

    public function test_pending_payment_does_not_reduce_obligation_or_create_cashflow(): void
    {
        $this->restock(50); $r = $this->verified(10); $o = $r->obligation;
        $this->pay(80000, [$o->id => 80000], false);
        $this->assertSame(80000, $o->fresh()->remaining);
        $this->assertSame(0, CashFlow::count());
    }

    public function test_partial_then_full_payment(): void
    {
        $this->restock(50); $o = $this->verified(10)->obligation;
        $this->pay(30000, [$o->id => 30000]);
        $this->assertSame('partial', $o->fresh()->status);
        $this->assertSame(50000, $o->fresh()->remaining);
        $this->pay(50000, [$o->id => 50000]);
        $this->assertSame('paid', $o->fresh()->status);
        $this->assertSame(80000, (int) CashFlow::where('type', 'income')->sum('amount'));  // tidak ganda
    }

    public function test_one_payment_allocated_to_two_periods(): void
    {
        $this->restock(100);
        $o1 = $this->verified(10, '2026-02-01', '2026-02-28')->obligation;
        $o2 = $this->verified(5, '2026-03-01', '2026-03-31')->obligation;
        $this->pay(120000, [$o1->id => 80000, $o2->id => 40000]);
        $this->assertSame('paid', $o1->fresh()->status);
        $this->assertSame('paid', $o2->fresh()->status);
    }

    public function test_overpayment_is_not_auto_income_and_cannot_overallocate(): void
    {
        $this->restock(50); $o = $this->verified(10)->obligation;
        $p = $this->pay(100000, [$o->id => 80000]);
        $this->assertSame(20000, $p->unallocated);
        $this->assertSame(80000, (int) CashFlow::where('type', 'income')->sum('amount'));  // saldo lebih bukan pendapatan
        $this->expectException(BusinessRuleException::class);
        app(PaymentService::class)->record(['store_id' => $this->store->id, 'paid_on' => '2026-03-02', 'amount_received' => 50000, 'request_key' => $this->key()], [$o->id => 50000]);
    }

    public function test_duplicate_payment_key_is_recorded_once(): void
    {
        $this->restock(50); $o = $this->verified(10)->obligation;
        $d = ['store_id' => $this->store->id, 'paid_on' => '2026-03-01', 'amount_received' => 10000, 'request_key' => 'dup'];
        app(PaymentService::class)->record($d, [$o->id => 10000]);
        app(PaymentService::class)->record($d, [$o->id => 10000]);
        $this->assertSame(1, Payment::count());
    }

    public function test_reversal_reopens_obligation_and_offsets_cash_without_deleting(): void
    {
        $this->restock(50); $o = $this->verified(10)->obligation;
        $p = $this->pay(80000, [$o->id => 80000]);
        app(PaymentService::class)->reverse($p, 'transfer gagal');
        $this->assertSame(80000, $o->fresh()->remaining);
        $this->assertSame(2, CashFlow::count());
        $this->assertSame(0, (int) CashFlow::where('type', 'income')->sum('amount') - (int) CashFlow::where('type', 'expense')->sum('amount'));
    }
}