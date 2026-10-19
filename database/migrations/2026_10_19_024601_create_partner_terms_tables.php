<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::create('partner_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('scheme_type', 20); // percentage | fixed_per_product | custom
            $t->decimal('store_pct', 5, 2)->nullable();
            $t->decimal('own_pct', 5, 2)->nullable();
            $t->string('basis', 20)->default('actual_price'); // actual_price | list_price
            $t->text('discount_policy')->nullable();
            $t->text('return_policy')->nullable();
            $t->text('custom_description')->nullable();
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('agreement_ref')->nullable();
            $t->text('change_note')->nullable();
            $t->timestamps();
            $t->index(['store_id', 'effective_from']);
        });
        Schema::create('partner_term_commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('amount_per_unit');
            $t->unique(['partner_term_id', 'product_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('partner_term_commissions'); Schema::dropIfExists('partner_terms'); }
};