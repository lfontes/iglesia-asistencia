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
        DB::statement('DROP INDEX participacion_grupos_unique_con_rol');
        DB::statement('DROP INDEX participacion_grupos_unique_sin_rol');
        DB::statement('ALTER TABLE participacion_grupos DROP CONSTRAINT participacion_grupos_rol_grupo_id_foreign');
        DB::statement('ALTER TABLE participacion_grupos ADD CONSTRAINT participacion_grupos_rol_grupo_id_foreign FOREIGN KEY (rol_grupo_id) REFERENCES rol_grupos(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE participacion_grupos ALTER COLUMN rol_grupo_id SET NOT NULL');
        DB::statement('ALTER TABLE participacion_grupos ADD CONSTRAINT persona_grupo_rol_anio_unique UNIQUE (persona_id, grupo_id, rol_grupo_id, anio)');
    }
};

