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
        Schema::table('personas', function (Blueprint $table) {
            $table->string('telefono_normalizado')->nullable()->after('telefono');
        });

        DB::statement("UPDATE personas SET telefono_normalizado = NULLIF(regexp_replace(COALESCE(telefono, ''), '[^0-9]', '', 'g'), '')");

        // Conserva el primer registro por telefono y limpia el resto para permitir unicidad parcial.
        DB::statement("
            UPDATE personas
            SET telefono_normalizado = NULL
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY telefono_normalizado ORDER BY id) AS rn
                    FROM personas
                    WHERE telefono_normalizado IS NOT NULL
                ) t
                WHERE t.rn > 1
            )
        ");

        DB::statement('CREATE UNIQUE INDEX personas_telefono_normalizado_unique ON personas (telefono_normalizado) WHERE telefono_normalizado IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS personas_telefono_normalizado_unique');

        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn('telefono_normalizado');
        });
    }
};

