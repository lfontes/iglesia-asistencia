<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('activo')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('lider_persona_id')
                ->nullable()
                ->after('created_by')
                ->constrained('personas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lider_persona_id');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
