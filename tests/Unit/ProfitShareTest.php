<?php
namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Services\ProfitShare;
use PHPUnit\Framework\TestCase;

class ProfitShareTest extends TestCase
{
    private function pct(string $store, string $basis = 'actual_price'): array
    {
        return ['scheme_type' => 'percentage', 'store_pct' => $store, 'own_pct' => (string) (100 - (float) $store), 'basis' => $basis, 'commissions' => []];
    }
    private function line(int $qty, int $price, int $disc = 0, int $ret = 0, int $pid = 1, ?int $list = null): array
    {
        return ['product_id' => $pid, 'qty_sold' => $qty, 'unit_price' => $price, 'list_price' => $list ?? $price, 'discount_amount' => $disc, 'return_qty' => $ret];
    }

    public function test_percentage_basic(): void
    {
        $r = ProfitShare::calculate([$this->line(10, 25000)], $this->pct('20'));
        $this->assertSame(250000, $r['gross']);
        $this->assertSame(250000, $r['base']);
        $this->assertSame(50000, $r['store_share']);
        $this->assertSame(200000, $r['own_share']);
    }

    public function test_two_partners_with_different_percentages(): void
    {
        $lines = [$this->line(10, 10000)];
        $this->assertSame(20000, ProfitShare::calculate($lines, $this->pct('20'))['store_share']);
        $this->assertSame(35000, ProfitShare::calculate($lines, $this->pct('35'))['store_share']);
    }

    public function test_rounding_is_half_up_and_shares_always_sum_to_base(): void
    {
        $r = ProfitShare::calculate([$this->line(1, 33333)], $this->pct('20'));
        $this->assertSame(6667, $r['store_share']);   // 6666.6 dibulatkan
        $this->assertSame(26666, $r['own_share']);
        foreach ([1, 7, 99, 12345, 99999] as $price) {
            $x = ProfitShare::calculate([$this->line(3, $price)], $this->pct('33.33'));
            $this->assertSame($x['base'], $x['store_share'] + $x['own_share']);
        }
        $this->assertSame(1, ProfitShare::roundDiv(5, 10));
        $this->assertSame(-1, ProfitShare::roundDiv(-5, 10));
    }

    public function test_discount_follows_basis(): void
    {
        $lines = [$this->line(4, 10000, 5000)];
        $actual = ProfitShare::calculate($lines, $this->pct('30', 'actual_price'));
        $this->assertSame(35000, $actual['net_sales']);
        $this->assertSame(35000, $actual['base']);
        $this->assertSame(10500, $actual['store_share']);
        $this->assertSame(24500, $actual['own_share']);

        $list = ProfitShare::calculate($lines, $this->pct('30', 'list_price'));
        $this->assertSame(35000, $list['net_sales']);   // dilaporkan tetap tampil
        $this->assertSame(40000, $list['base']);        // dasar bagi hasil berbeda, harus dijelaskan di UI
        $this->assertSame(12000, $list['store_share']);
    }

    public function test_returns_and_adjustments(): void
    {
        $r = ProfitShare::calculate([$this->line(5, 10000, 0, 2)], $this->pct('20'), -1000);
        $this->assertSame(50000, $r['gross']);
        $this->assertSame(20000, $r['returns']);
        $this->assertSame(29000, $r['net_sales']);      // 50000 - 20000 - 1000
        $this->assertSame(3, $r['units']);
    }

    public function test_fixed_commission_per_product(): void
    {
        $term = ['scheme_type' => 'fixed_per_product', 'basis' => 'actual_price', 'commissions' => [1 => 2000]];
        $r = ProfitShare::calculate([$this->line(10, 10000, 0, 1)], $term);
        $this->assertSame(18000, $r['store_share']);    // (10 - 1) x 2000
        $this->assertSame(90000, $r['base']);
        $this->assertSame(72000, $r['own_share']);
    }

    public function test_fixed_commission_requires_every_product(): void
    {
        $this->expectException(BusinessRuleException::class);
        ProfitShare::calculate([$this->line(1, 10000, 0, 0, 99)], ['scheme_type' => 'fixed_per_product', 'basis' => 'actual_price', 'commissions' => [1 => 2000]]);
    }

    public function test_custom_scheme_needs_manual_share(): void
    {
        $term = ['scheme_type' => 'custom', 'basis' => 'actual_price'];
        $r = ProfitShare::calculate([$this->line(2, 10000)], $term, 0, 7000);
        $this->assertSame(7000, $r['store_share']);
        $this->assertSame(13000, $r['own_share']);
        $this->expectException(BusinessRuleException::class);
        ProfitShare::calculate([$this->line(2, 10000)], $term);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        ProfitShare::calculate([$this->line(2, 10000, 0, 3)], $this->pct('20')); // retur > terjual
    }

    public function test_invalid_percentage_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        ProfitShare::calculate([$this->line(1, 1000)], $this->pct('120'));
    }
}