<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_number')->unique();
            $t->foreignId('store_id')->nullable()->constrained()->nullOnDelete(); // null = customer langsung
            $t->unsignedBigInteger('total_amount');
            $t->string('payment_method')->default('Transfer');
            $t->date('date');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sales_transactions'); }
};
