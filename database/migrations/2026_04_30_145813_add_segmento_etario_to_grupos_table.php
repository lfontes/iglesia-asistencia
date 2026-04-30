<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table): void {
            $table->string('segmento_etario', 20)
                ->nullable()
                ->after('tipo_grupo_id');

            $table->unsignedTinyInteger('edad_min')
                ->nullable()
                ->after('segmento_etario');

            $table->unsignedTinyInteger('edad_max')
                ->nullable()
                ->after('edad_min');
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table): void {
            $table->dropColumn([
                'segmento_etario',
                'edad_min',
                'edad_max',
            ]);
        });
    }
};
