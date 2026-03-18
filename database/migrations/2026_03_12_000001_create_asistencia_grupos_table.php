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
        Schema::create('asistencia_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->boolean('presente')->default(true);
            $table->text('observaciones')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['grupo_id', 'persona_id', 'fecha']);
            $table->index(['grupo_id', 'fecha']);
            $table->index(['persona_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia_grupos');
    }
};
