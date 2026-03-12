<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('participacion_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rol_grupo_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['persona_id', 'grupo_id', 'rol_grupo_id', 'anio'], 'persona_grupo_rol_anio_unique');
            $table->index('anio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participacion_grupos');
    }
};

