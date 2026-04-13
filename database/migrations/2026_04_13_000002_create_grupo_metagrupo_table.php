<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo_metagrupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metagrupo_id')->constrained('metagrupos')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['metagrupo_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_metagrupo');
    }
};
