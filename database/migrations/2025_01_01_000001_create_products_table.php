<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('category', ['Pin', 'Sticker', 'Lanyard', 'Hairclip', 'Notepad']);
            $t->unsignedInteger('cost_price')->default(0);    // HPP
            $t->unsignedInteger('selling_price');
            $t->integer('stock_qty')->default(0);             // stok aktif (layak jual)
            $t->integer('defective_qty')->default(0);         // cacat / reject, TIDAK masuk stok aktif
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
