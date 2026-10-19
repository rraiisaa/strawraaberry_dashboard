<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_reports', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('request_key', 64)->unique();
            $t->foreignId('store_id')->constrained();
            $t->foreignId('partner_term_id')->constrained();
            $t->json('term_snapshot');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('received_at')->nullable();
            $t->string('source')->nullable();
            $t->string('proof_path')->nullable();
            $t->string('status', 24)->default('draft'); // draft, pending_verification, verified, needs_revision, cancelled
            $t->boolean('is_late')->default(false);
            $t->bigInteger('reported_total')->nullable();   // total versi toko (untuk cek kecocokan)
            $t->bigInteger('gross_amount')->default(0);
            $t->bigInteger('discount_amount')->default(0);
            $t->bigInteger('returns_amount')->default(0);
            $t->bigInteger('adjustments_amount')->default(0);
            $t->text('adjustments_note')->nullable();
            $t->bigInteger('net_sales')->default(0);
            $t->bigInteger('base_amount')->default(0);
            $t->bigInteger('store_share')->default(0);
            $t->bigInteger('own_share')->default(0);
            $t->bigInteger('custom_store_share')->nullable(); // diisi manual bila skema = custom
            $t->unsignedInteger('total_units')->default(0);
            $t->unsignedSmallInteger('revision_no')->default(0);
            $t->text('notes')->nullable();
            $t->text('review_note')->nullable();
            $t->string('created_by')->nullable();
            $t->string('verified_by')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['store_id', 'period_start', 'period_end']);
        });
        Schema::create('sales_report_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->unsignedInteger('qty_sold');
            $t->unsignedInteger('unit_price');
            $t->unsignedInteger('list_price');
            $t->unsignedBigInteger('discount_amount')->default(0);
            $t->unsignedInteger('return_qty')->default(0);
            $t->timestamps();
        });
        Schema::create('sales_report_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('revision_no');
            $t->text('reason');
            $t->json('before');
            $t->json('after');
            $t->string('actor')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('obligations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained();
            $t->foreignId('sales_report_id')->unique()->constrained();
            $t->bigInteger('amount_due');
            $t->date('due_date');
            $t->timestamp('billed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('request_key', 64)->unique();
            $t->foreignId('store_id')->constrained();
            $t->date('paid_on');
            $t->bigInteger('amount_expected')->nullable();
            $t->bigInteger('amount_received');
            $t->string('method', 30)->default('Transfer');
            $t->string('reference_no')->nullable();
            $t->string('destination_account')->nullable();
            $t->string('proof_path')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 12)->default('pending'); // pending, verified, rejected, reversed
            $t->string('verified_by')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->text('reversed_reason')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('obligation_id')->constrained();
            $t->bigInteger('amount');
            $t->unsignedBigInteger('cash_flow_id')->nullable();
            $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->nullable()->constrained();
            $t->string('actor')->nullable();
            $t->string('action', 40);
            $t->string('entity_type', 40)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->text('description');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['store_id', 'created_at']);
        });
    }
    public function down(): void {
        foreach (['activity_logs','payment_allocations','payments','obligations','sales_report_revisions','sales_report_lines','sales_reports'] as $t) Schema::dropIfExists($t);
    }
};