<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->boolean('es_menor')->default(false)->after('telefono_normalizado');
            $table->string('responsable_nombre')->nullable()->after('es_menor');
            $table->string('responsable_telefono')->nullable()->after('responsable_nombre');
            $table->string('responsable_telefono_normalizado')->nullable()->after('responsable_telefono');
            $table->text('observaciones_ipn')->nullable()->after('responsable_telefono_normalizado');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropColumn([
                'es_menor',
                'responsable_nombre',
                'responsable_telefono',
                'responsable_telefono_normalizado',
                'observaciones_ipn',
            ]);
        });
    }
};
