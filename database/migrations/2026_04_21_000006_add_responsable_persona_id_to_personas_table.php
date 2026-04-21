<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->foreignId('responsable_persona_id')
                ->nullable()
                ->after('es_menor')
                ->constrained('personas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsable_persona_id');
        });
    }
};
