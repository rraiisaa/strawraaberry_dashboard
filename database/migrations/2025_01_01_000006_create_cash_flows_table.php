<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cash_flows', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['income', 'expense']);
            $t->string('category'); // Bahan Baku, Packaging, Desain, Operasional, Fee Mitra, Pelunasan Mitra, Lainnya
            $t->unsignedBigInteger('amount');
            $t->string('description')->nullable();
            $t->date('date');
            $t->timestamps();
            $t->index(['type', 'date']);
        });
    }
    public function down(): void { Schema::dropIfExists('cash_flows'); }
};
