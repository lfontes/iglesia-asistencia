<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_pares_ignorados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_a_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('persona_b_id')->constrained('personas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['persona_a_id', 'persona_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_pares_ignorados');
    }
};
