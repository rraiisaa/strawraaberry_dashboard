<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transaction_id')->constrained('sales_transactions')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('qty');
            $t->unsignedInteger('unit_price');
            $t->unsignedBigInteger('subtotal');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sales_items'); }
};
