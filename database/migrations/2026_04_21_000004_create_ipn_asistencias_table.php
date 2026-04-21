<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipn_asistencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ipn_aula_id')->constrained('ipn_aulas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->date('fecha');
            $table->boolean('presente')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ipn_aula_id', 'persona_id', 'fecha'], 'ipn_asistencias_aula_persona_fecha_unique');
            $table->index(['ipn_aula_id', 'fecha']);
            $table->index(['persona_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipn_asistencias');
    }
};
