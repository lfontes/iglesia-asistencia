<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('recipient_wa_id')->constrained('personas')->nullOnDelete();
            $table->foreignId('grupo_id')->nullable()->after('persona_id')->constrained('grupos')->nullOnDelete();
            $table->string('use_case')->nullable()->after('direction');
            $table->date('periodo_inicio')->nullable()->after('use_case');
            $table->date('periodo_fin')->nullable()->after('periodo_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_id');
            $table->dropConstrainedForeignId('persona_id');
            $table->dropColumn([
                'use_case',
                'periodo_inicio',
                'periodo_fin',
            ]);
        });
    }
};
