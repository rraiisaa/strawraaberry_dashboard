<?php
namespace Database\Seeders;

use App\Models\{CashFlow, ConsignmentStock, Product, SalesItem, SalesTransaction, Store};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // HPP di bawah adalah ESTIMASI placeholder → ganti dengan HPP riil kamu.
        $catalog = [
            ['Pin', 10000, 4500, ['100% My Type','Mushroom','Centil Era','#ApelHolic','Legit','Stay Juicy']],
            ['Sticker Wave', 25000, 11000, ['Waves Series Udang']],
            ['Sticker Word', 5000, 1800, ['Delulu is the Solulu','Overthinking Olympics']],
            ['Sticker Sheet', 25000, 9000, ['Walk In','Fin-tastic','Ano-mali','Juicy Mood']],
            ['Lanyard', 35000, 16000, ['Silly Lanyard']],
            ['Hairpin', 45000, 20000, ['Shipi','Axomato','Squidy','Appey']],
        ];
        foreach ($catalog as [$cat, $sell, $cost, $names])
            foreach ($names as $n)
                Product::create(['name' => $n, 'category' => $cat, 'selling_price' => $sell, 'cost_price' => $cost, 'stock_qty' => rand(5, 40), 'defective_qty' => rand(0, 3)]);

        $tabbyna = Store::create(['name' => 'Store Tabbyna', 'location' => 'Bogor', 'commission_rate' => 20, 'contact_person' => '-']);
        $triple  = Store::create(['name' => 'Store Triple A', 'location' => 'Cilincing', 'commission_rate' => 20, 'contact_person' => '-']);

        foreach ([$tabbyna, $triple] as $store)
            Product::inRandomOrder()->limit(5)->get()->each(fn ($p) => ConsignmentStock::create([
                'store_id' => $store->id, 'product_id' => $p->id,
                'qty_deposited' => $d = rand(5, 15), 'qty_sold' => rand(0, $d), 'batch_date' => now()->subDays(rand(5, 40)),
            ]));

        // Contoh penjualan langsung & cash flow bulan 1..bulan ini
        $inv = 1;
        for ($m = 1; $m <= now()->month; $m++) {
            for ($i = 0; $i < 4; $i++) {
                $date = now()->setMonth($m)->setDay(rand(1, 25));
                $trx = SalesTransaction::create(['invoice_number' => 'SJ-' . $date->format('Ym') . '-' . str_pad($inv++, 4, '0', STR_PAD_LEFT), 'total_amount' => 0, 'payment_method' => 'Transfer', 'date' => $date]);
                $total = 0;
                Product::inRandomOrder()->limit(2)->get()->each(function ($p) use ($trx, &$total) {
                    $q = rand(1, 6);
                    SalesItem::create(['transaction_id' => $trx->id, 'product_id' => $p->id, 'qty' => $q, 'unit_price' => $p->selling_price, 'subtotal' => $q * $p->selling_price]);
                    $total += $q * $p->selling_price;
                });
                $trx->update(['total_amount' => $total]);
            }
            foreach (['Bahan Baku', 'Packaging', 'Desain'] as $cat)
                CashFlow::create(['type' => 'expense', 'category' => $cat, 'amount' => rand(100, 450) * 1000, 'description' => "Belanja $cat", 'date' => now()->setMonth($m)->setDay(rand(1, 25))]);
        }
    }
}
