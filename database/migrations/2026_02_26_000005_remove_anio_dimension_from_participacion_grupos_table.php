<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected function isMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->isMysql()) {
            DB::statement('DROP INDEX participacion_grupos_unique_rol_anio ON participacion_grupos');
            DB::statement('ALTER TABLE participacion_grupos MODIFY anio SMALLINT UNSIGNED NULL');
            DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_rol ON participacion_grupos (persona_id, grupo_id, ((IFNULL(rol_grupo_id, 0))))');

            return;
        }

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
        if ($this->isMysql()) {
            DB::statement('DROP INDEX participacion_grupos_unique_rol ON participacion_grupos');
            DB::statement('UPDATE participacion_grupos SET anio = YEAR(CURDATE()) WHERE anio IS NULL');
            DB::statement('ALTER TABLE participacion_grupos MODIFY anio SMALLINT UNSIGNED NOT NULL');
            DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_rol_anio ON participacion_grupos (persona_id, grupo_id, ((IFNULL(rol_grupo_id, 0))), anio)');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS participacion_grupos_unique_con_rol');
        DB::statement('DROP INDEX IF EXISTS participacion_grupos_unique_sin_rol');
        DB::statement('UPDATE participacion_grupos SET anio = EXTRACT(YEAR FROM CURRENT_DATE)::int WHERE anio IS NULL');
        DB::statement('ALTER TABLE participacion_grupos ALTER COLUMN anio SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_con_rol ON participacion_grupos (persona_id, grupo_id, rol_grupo_id, anio) WHERE rol_grupo_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_sin_rol ON participacion_grupos (persona_id, grupo_id, anio) WHERE rol_grupo_id IS NULL');
    }
};
