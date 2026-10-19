<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::create('restocks', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('request_key', 64)->unique();
            $t->foreignId('store_id')->constrained();
            $t->string('status', 12)->default('draft'); // draft, sent, partial, received, cancelled
            $t->date('ship_date')->nullable();
            $t->date('received_date')->nullable();
            $t->string('handed_by')->nullable();
            $t->string('received_by')->nullable();
            $t->text('notes')->nullable();
            $t->string('proof_path')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->unsignedSmallInteger('confirm_count')->default(0);
            $t->timestamp('cancelled_at')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('restock_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('restock_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->unsignedInteger('qty_sent');
            $t->unsignedInteger('qty_received')->default(0);
            $t->unsignedInteger('unit_price'); // snapshot harga jual saat restock
            $t->string('condition', 30)->default('baik');
            $t->timestamps();
        });
        // Ledger append-only: satu-satunya sumber stok konsinyasi.
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->string('bucket', 12);  // sellable | quarantine | damaged
            $t->string('type', 24);
            $t->integer('qty');        // bertanda: + masuk bucket, - keluar bucket
            $t->date('occurred_on');
            $t->string('reference_type', 30)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->unsignedBigInteger('reversal_of_id')->nullable();
            $t->string('idempotency_key', 120)->unique();
            $t->text('reason')->nullable();
            $t->string('actor')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['store_id', 'product_id', 'bucket']);
            $t->index(['reference_type', 'reference_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_movements'); Schema::dropIfExists('restock_items'); Schema::dropIfExists('restocks'); }
};