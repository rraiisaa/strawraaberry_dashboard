<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::table('products', fn (Blueprint $t) => $t->string('sku', 40)->nullable()->unique()->after('id'));
        foreach (DB::table('products')->get() as $p) {
            DB::table('products')->where('id', $p->id)->update(['sku' => 'SJ-' . str_pad($p->id, 4, '0', STR_PAD_LEFT)]);
        }

        Schema::table('stores', function (Blueprint $t) {
            $t->string('code', 20)->nullable()->unique();
            $t->string('owner_name')->nullable();
            $t->string('pic_name')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('city')->nullable();
            $t->string('province')->nullable();
            $t->string('maps_url', 500)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status', 20)->default('active'); // prospect, active, paused, inactive
            $t->string('report_schedule')->nullable();
            $t->string('payment_schedule')->nullable();
            $t->unsignedSmallInteger('report_interval_days')->default(30);
            $t->unsignedSmallInteger('report_due_days')->default(7);
            $t->unsignedSmallInteger('payment_due_days')->default(14);
            $t->text('notes')->nullable();
            $t->softDeletes();
        });

        // Migrasi data lama: kode mitra, PIC, kota, dan skema awal dari commission_rate lama.
        foreach (DB::table('stores')->get() as $s) {
            $pct = (float) $s->commission_rate;
            DB::table('stores')->where('id', $s->id)->update([
                'code' => 'MT-' . str_pad($s->id, 4, '0', STR_PAD_LEFT),
                'pic_name' => $s->contact_person, 'city' => $s->location,
                'start_date' => substr((string) $s->created_at, 0, 10) ?: now()->toDateString(),
            ]);
            DB::table('partner_terms')->insert([
                'store_id' => $s->id, 'scheme_type' => 'percentage', 'store_pct' => $pct, 'own_pct' => 100 - $pct,
                'basis' => 'actual_price', 'effective_from' => substr((string) $s->created_at, 0, 10) ?: now()->toDateString(),
                'change_note' => 'Dimigrasi dari kolom commission_rate lama. WAJIB diverifikasi dengan perjanjian.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('cash_flows', function (Blueprint $t) {
            $t->string('source_type', 30)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('source_key', 80)->nullable()->unique(); // idempotensi: satu sumber = satu catatan kas
        });
    }
    public function down(): void { /* sengaja tidak dibalik: data keuangan */ }
};