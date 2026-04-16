<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_inscripciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evento_fecha_id')->constrained()->cascadeOnDelete();
            $table->string('estado', 30)->default('inscripto');
            $table->text('observaciones')->nullable();
            $table->json('datos_capturados')->nullable();
            $table->timestamps();

            $table->unique(['persona_id', 'evento_fecha_id']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_inscripciones');
    }
};
