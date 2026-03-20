<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected function isMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    protected function mysqlConstraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }

    protected function mysqlIndexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    protected function mysqlColumnExists(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->isMysql()) {
            if (! $this->mysqlIndexExists('participacion_grupos', 'participacion_grupos_persona_id_index')) {
                DB::statement('CREATE INDEX participacion_grupos_persona_id_index ON participacion_grupos (persona_id)');
            }
            if ($this->mysqlIndexExists('participacion_grupos', 'persona_grupo_rol_anio_unique')) {
                DB::statement('ALTER TABLE participacion_grupos DROP INDEX persona_grupo_rol_anio_unique');
            }
            DB::statement('ALTER TABLE participacion_grupos MODIFY rol_grupo_id BIGINT UNSIGNED NULL');
            if (! $this->mysqlColumnExists('participacion_grupos', 'rol_grupo_id_unique')) {
                DB::statement('ALTER TABLE participacion_grupos ADD rol_grupo_id_unique BIGINT GENERATED ALWAYS AS (IFNULL(rol_grupo_id, 0)) STORED');
            }
            if (! $this->mysqlIndexExists('participacion_grupos', 'participacion_grupos_unique_rol_anio')) {
                DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_rol_anio ON participacion_grupos (persona_id, grupo_id, rol_grupo_id_unique, anio)');
            }

            return;
        }

        DB::statement('ALTER TABLE participacion_grupos DROP CONSTRAINT persona_grupo_rol_anio_unique');
        DB::statement('ALTER TABLE participacion_grupos ALTER COLUMN rol_grupo_id DROP NOT NULL');
        DB::statement('ALTER TABLE participacion_grupos DROP CONSTRAINT participacion_grupos_rol_grupo_id_foreign');
        DB::statement('ALTER TABLE participacion_grupos ADD CONSTRAINT participacion_grupos_rol_grupo_id_foreign FOREIGN KEY (rol_grupo_id) REFERENCES rol_grupos(id) ON DELETE SET NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_con_rol ON participacion_grupos (persona_id, grupo_id, rol_grupo_id, anio) WHERE rol_grupo_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX participacion_grupos_unique_sin_rol ON participacion_grupos (persona_id, grupo_id, anio) WHERE rol_grupo_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->isMysql()) {
            if ($this->mysqlIndexExists('participacion_grupos', 'participacion_grupos_unique_rol_anio')) {
                DB::statement('DROP INDEX participacion_grupos_unique_rol_anio ON participacion_grupos');
            }
            if ($this->mysqlColumnExists('participacion_grupos', 'rol_grupo_id_unique')) {
                DB::statement('ALTER TABLE participacion_grupos DROP COLUMN rol_grupo_id_unique');
            }
            DB::statement('ALTER TABLE participacion_grupos MODIFY rol_grupo_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE participacion_grupos ADD CONSTRAINT persona_grupo_rol_anio_unique UNIQUE (persona_id, grupo_id, rol_grupo_id, anio)');
            if ($this->mysqlIndexExists('participacion_grupos', 'participacion_grupos_persona_id_index')) {
                DB::statement('DROP INDEX participacion_grupos_persona_id_index ON participacion_grupos');
            }

            return;
        }

        DB::statement('DROP INDEX participacion_grupos_unique_con_rol');
        DB::statement('DROP INDEX participacion_grupos_unique_sin_rol');
        DB::statement('ALTER TABLE participacion_grupos DROP CONSTRAINT participacion_grupos_rol_grupo_id_foreign');
        DB::statement('ALTER TABLE participacion_grupos ADD CONSTRAINT participacion_grupos_rol_grupo_id_foreign FOREIGN KEY (rol_grupo_id) REFERENCES rol_grupos(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE participacion_grupos ALTER COLUMN rol_grupo_id SET NOT NULL');
        DB::statement('ALTER TABLE participacion_grupos ADD CONSTRAINT persona_grupo_rol_anio_unique UNIQUE (persona_id, grupo_id, rol_grupo_id, anio)');
    }
};
