<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipn_aula_persona', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ipn_aula_id')->constrained('ipn_aulas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['ipn_aula_id', 'persona_id']);
            $table->index(['persona_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipn_aula_persona');
    }
};
