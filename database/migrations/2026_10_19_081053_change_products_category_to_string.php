<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom category sebelumnya ENUM (hanya 6 nilai tetap), sehingga kategori baru seperti "Sticker"
 * ditolak database. Diubah menjadi string biasa; daftar kategori diatur di Product::CATEGORIES.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->string('category', 40)->change();
        });
    }

    public function down(): void
    {
        // Sengaja kosong: mengembalikan ke ENUM akan memotong data kategori baru.
    }
};