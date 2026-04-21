<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipn_aulas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('edad_desde')->nullable();
            $table->unsignedTinyInteger('edad_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipn_aulas');
    }
};
