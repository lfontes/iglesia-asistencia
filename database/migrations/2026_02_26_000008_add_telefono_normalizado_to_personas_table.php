<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function isMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    protected function mysqlIndexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('personas', 'telefono_normalizado')) {
            Schema::table('personas', function (Blueprint $table) {
                $table->string('telefono_normalizado')->nullable()->after('telefono');
            });
        }

        if ($this->isMysql()) {
            DB::statement("UPDATE personas SET telefono_normalizado = NULLIF(REGEXP_REPLACE(COALESCE(telefono, ''), '[^0-9]', ''), '')");
        } else {
            DB::statement("UPDATE personas SET telefono_normalizado = NULLIF(regexp_replace(COALESCE(telefono, ''), '[^0-9]', '', 'g'), '')");
        }

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

        if ($this->isMysql() && ! $this->mysqlIndexExists('personas', 'personas_telefono_normalizado_unique')) {
            DB::statement('CREATE UNIQUE INDEX personas_telefono_normalizado_unique ON personas (telefono_normalizado)');
        } elseif (! $this->isMysql()) {
            DB::statement('CREATE UNIQUE INDEX personas_telefono_normalizado_unique ON personas (telefono_normalizado) WHERE telefono_normalizado IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->isMysql()) {
            if ($this->mysqlIndexExists('personas', 'personas_telefono_normalizado_unique')) {
                DB::statement('DROP INDEX personas_telefono_normalizado_unique ON personas');
            }
        } else {
            DB::statement('DROP INDEX IF EXISTS personas_telefono_normalizado_unique');
        }

        if (Schema::hasColumn('personas', 'telefono_normalizado')) {
            Schema::table('personas', function (Blueprint $table) {
                $table->dropColumn('telefono_normalizado');
            });
        }
    }
};
