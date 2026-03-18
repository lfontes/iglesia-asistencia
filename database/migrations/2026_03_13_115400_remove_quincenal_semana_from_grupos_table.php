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
        if (! Schema::hasColumn('grupos', 'quincenal_semana')) {
            return;
        }

        Schema::table('grupos', function (Blueprint $table) {
            $table->dropColumn('quincenal_semana');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('grupos', 'quincenal_semana')) {
            return;
        }

        Schema::table('grupos', function (Blueprint $table) {
            $table->string('quincenal_semana', 10)->nullable()->after('frecuencia_asistencia');
        });
    }
};
