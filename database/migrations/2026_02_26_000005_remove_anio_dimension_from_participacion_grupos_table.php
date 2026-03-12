<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS participacion_grupos_unique_con_rol');
        DB::statement('DROP INDEX IF EXISTS participacion_grupos_unique_sin_rol');
        DB::statement('ALTER TABLE participacion_grupos ALTER COLUMN anio DROP NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_con_rol ON participacion_grupos (persona_id, grupo_id, rol_grupo_id) WHERE rol_grupo_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_sin_rol ON participacion_grupos (persona_id, grupo_id) WHERE rol_grupo_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS participacion_grupos_unique_con_rol');
        DB::statement('DROP INDEX IF EXISTS participacion_grupos_unique_sin_rol');
        DB::statement('UPDATE participacion_grupos SET anio = EXTRACT(YEAR FROM CURRENT_DATE)::int WHERE anio IS NULL');
        DB::statement('ALTER TABLE participacion_grupos ALTER COLUMN anio SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_con_rol ON participacion_grupos (persona_id, grupo_id, rol_grupo_id, anio) WHERE rol_grupo_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_sin_rol ON participacion_grupos (persona_id, grupo_id, anio) WHERE rol_grupo_id IS NULL');
    }
};

