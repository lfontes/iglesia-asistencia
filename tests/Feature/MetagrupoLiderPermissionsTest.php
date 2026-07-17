<?php

namespace Tests\Feature;

use App\Filament\Resources\MetagrupoResource;
use App\Models\Grupo;
use App\Models\Metagrupo;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MetagrupoLiderPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $lider;

    protected Metagrupo $metagrupo;

    protected Grupo $grupoDelMetagrupo;

    protected Grupo $grupoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'lider', 'guard_name' => 'web']);

        $persona = Persona::factory()->create();

        $this->lider = User::factory()->create(['persona_id' => $persona->id]);
        $this->lider->assignRole('lider');

        $this->grupoDelMetagrupo = Grupo::create([
            'nombre' => 'Grupo del metagrupo',
            'anio' => (int) date('Y'),
            'frecuencia_asistencia' => Grupo::FRECUENCIA_SEMANAL,
            'activo' => true,
        ]);

        $this->grupoAjeno = Grupo::create([
            'nombre' => 'Grupo ajeno',
            'anio' => (int) date('Y'),
            'frecuencia_asistencia' => Grupo::FRECUENCIA_SEMANAL,
            'activo' => true,
        ]);

        $this->metagrupo = Metagrupo::create([
            'nombre' => 'Metagrupo de prueba',
            'lider_persona_id' => $persona->id,
            'activo' => true,
        ]);

        $this->metagrupo->grupos()->attach($this->grupoDelMetagrupo);
    }

    #[Test]
    public function el_lider_gestiona_los_grupos_de_su_metagrupo()
    {
        $this->assertTrue($this->grupoDelMetagrupo->isManagedBy($this->lider));
        $this->assertFalse($this->grupoAjeno->isManagedBy($this->lider));

        $managedIds = Grupo::managedBy($this->lider)->pluck('id');
        $this->assertTrue($managedIds->contains($this->grupoDelMetagrupo->id));
        $this->assertFalse($managedIds->contains($this->grupoAjeno->id));
    }

    #[Test]
    public function el_lider_no_es_dueno_de_los_grupos_del_metagrupo()
    {
        $this->assertFalse($this->grupoDelMetagrupo->isOwnedBy($this->lider));

        $grupoPropio = Grupo::create([
            'nombre' => 'Grupo propio',
            'anio' => (int) date('Y'),
            'frecuencia_asistencia' => Grupo::FRECUENCIA_SEMANAL,
            'activo' => true,
            'created_by' => $this->lider->id,
        ]);

        $this->assertTrue($grupoPropio->isOwnedBy($this->lider));
    }

    #[Test]
    public function el_lider_puede_editar_su_metagrupo_pero_no_uno_ajeno()
    {
        $metagrupoAjeno = Metagrupo::create([
            'nombre' => 'Metagrupo ajeno',
            'activo' => true,
        ]);

        $this->actingAs($this->lider);

        $this->assertTrue(MetagrupoResource::canEdit($this->metagrupo));
        $this->assertFalse(MetagrupoResource::canEdit($metagrupoAjeno));
    }

    #[Test]
    public function el_lider_accede_a_la_pagina_de_edicion_de_su_metagrupo()
    {
        $this->actingAs($this->lider)
            ->get("/admin/metagrupos/{$this->metagrupo->id}/edit")
            ->assertOk();
    }

    #[Test]
    public function el_lider_no_accede_a_la_edicion_de_un_metagrupo_ajeno()
    {
        $metagrupoAjeno = Metagrupo::create([
            'nombre' => 'Metagrupo ajeno',
            'activo' => true,
        ]);

        $this->actingAs($this->lider)
            ->get("/admin/metagrupos/{$metagrupoAjeno->id}/edit")
            ->assertForbidden();
    }
}
