<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('consignment_stocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('qty_deposited');
            $t->unsignedInteger('qty_sold')->default(0);
            $t->enum('settlement_status', ['pending', 'paid'])->default('pending');
            $t->date('batch_date');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('consignment_stocks'); }
};
