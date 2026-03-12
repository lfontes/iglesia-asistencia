<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('grupos', function (Blueprint $table) {
            $table->foreignId('tipo_grupo_id')
                ->nullable()
                ->after('anio')
                ->constrained('tipo_grupos')
                ->nullOnDelete();
        });

        $tipos = DB::table('grupos')
            ->select('tipo')
            ->whereNotNull('tipo')
            ->where('tipo', '<>', '')
            ->distinct()
            ->pluck('tipo');

        foreach ($tipos as $tipo) {
            $tipoGrupoId = DB::table('tipo_grupos')->insertGetId([
                'nombre' => $tipo,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('grupos')
                ->where('tipo', $tipo)
                ->update(['tipo_grupo_id' => $tipoGrupoId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('grupos')->update(['tipo_grupo_id' => null]);

        Schema::table('grupos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_grupo_id');
        });

        Schema::dropIfExists('tipo_grupos');
    }
};

