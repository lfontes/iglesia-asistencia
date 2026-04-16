<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->foreignId('evento_fecha_id')
                ->nullable()
                ->after('grupo_id')
                ->constrained('evento_fechas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('evento_fecha_id');
        });
    }
};
