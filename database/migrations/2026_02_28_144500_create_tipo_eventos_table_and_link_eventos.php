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
        Schema::create('tipo_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->foreignId('tipo_evento_id')
                ->nullable()
                ->after('tipo_evento')
                ->constrained('tipo_eventos')
                ->nullOnDelete();
        });

        $tipos = DB::table('eventos')
            ->select('tipo_evento')
            ->whereNotNull('tipo_evento')
            ->where('tipo_evento', '<>', '')
            ->distinct()
            ->pluck('tipo_evento');

        foreach ($tipos as $tipo) {
            $tipoEventoId = DB::table('tipo_eventos')->insertGetId([
                'nombre' => $tipo,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('eventos')
                ->where('tipo_evento', $tipo)
                ->update(['tipo_evento_id' => $tipoEventoId]);
        }

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('tipo_evento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('tipo_evento', 100)->nullable()->after('nombre');
        });

        $tiposPorId = DB::table('tipo_eventos')
            ->pluck('nombre', 'id');

        DB::table('eventos')
            ->whereNotNull('tipo_evento_id')
            ->select('id', 'tipo_evento_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($tiposPorId): void {
                foreach ($rows as $row) {
                    $nombre = $tiposPorId[$row->tipo_evento_id] ?? null;

                    DB::table('eventos')
                        ->where('id', $row->id)
                        ->update(['tipo_evento' => $nombre]);
                }
            });

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_evento_id');
        });

        Schema::dropIfExists('tipo_eventos');
    }
};
