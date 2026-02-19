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
       Schema::create('asistencias', function (Blueprint $table) {
        $table->id();

        $table->foreignId('persona_id')
              ->constrained()
              ->cascadeOnDelete();

        $table->foreignId('evento_fecha_id')
              ->constrained()
              ->cascadeOnDelete();

        $table->boolean('presente')->default(true);

        $table->text('observaciones')->nullable();

        $table->timestamps();

        $table->unique(['persona_id', 'evento_fecha_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
