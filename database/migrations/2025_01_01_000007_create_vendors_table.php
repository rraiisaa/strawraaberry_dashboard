<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('contact')->nullable();       // WhatsApp / telepon / email
            $t->string('social_media')->nullable();  // @handle atau URL
            $t->unsignedBigInteger('cost_price')->default(0); // harga pokok
            $t->text('detail')->nullable();          // barang/jasa yang disuplai, MOQ, lead time, dll
            $t->string('location')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('vendors'); }
};