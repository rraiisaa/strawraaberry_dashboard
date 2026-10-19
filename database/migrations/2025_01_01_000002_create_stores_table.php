<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('location')->nullable();
            $t->decimal('commission_rate', 5, 2)->default(20); // persen
            $t->string('contact_person')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('stores'); }
};
