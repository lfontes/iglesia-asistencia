<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            [
                'name' => 'coordinador_grupos',
                'guard_name' => 'web',
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'coordinador_grupos')
            ->where('guard_name', 'web')
            ->delete();
    }
};
