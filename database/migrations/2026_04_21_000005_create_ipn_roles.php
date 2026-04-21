<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['director_ipn', 'servidor_ipn'] as $role) {
            DB::table('roles')->updateOrInsert(
                [
                    'name' => $role,
                    'guard_name' => 'web',
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->whereIn('name', ['director_ipn', 'servidor_ipn'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
