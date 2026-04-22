<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditingTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear un usuario para las pruebas
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_logs_when_a_persona_is_created()
    {
        Activity::truncate();

        $persona = Persona::factory()->create();

        $this->assertCount(1, Activity::all());
        
        $activity = Activity::first();
        $this->assertEquals('created', $activity->event);
        $this->assertEquals($persona->id, $activity->subject_id);
        $this->assertEquals($this->user->id, $activity->causer_id);
    }

    /** @test */
    public function it_logs_when_a_persona_is_updated()
    {
        Activity::truncate();

        $persona = Persona::factory()->create();
        Activity::truncate(); // Limpiar el log de creación

        $originalName = $persona->nombre;
        $persona->update(['nombre' => 'Nuevo Nombre']);

        $this->assertCount(1, Activity::all());
        
        $activity = Activity::first();
        $this->assertEquals('updated', $activity->event);
        $this->assertEquals($originalName, $activity->properties['old']['nombre']);
        $this->assertEquals('Nuevo Nombre', $activity->properties['new']['nombre']);
    }

    /** @test */
    public function it_logs_when_a_persona_is_deleted()
    {
        Activity::truncate();

        $persona = Persona::factory()->create();
        $personaId = $persona->id;
        
        Activity::truncate(); // Limpiar el log de creación
        
        $persona->delete();

        $this->assertCount(1, Activity::all());
        
        $activity = Activity::first();
        $this->assertEquals('deleted', $activity->event);
        $this->assertEquals($personaId, $activity->subject_id);
    }

    /** @test */
    public function it_stores_user_information_in_activity_log()
    {
        Activity::truncate();

        Persona::factory()->create();

        $activity = Activity::first();
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertIsNotNull($activity->causer);
        $this->assertEquals($this->user->name, $activity->causer->name);
    }

    /** @test */
    public function it_stores_original_values_in_activity_properties()
    {
        Activity::truncate();

        $persona = Persona::factory()->create([
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
        ]);

        Activity::truncate();

        $persona->update([
            'nombre' => 'Carlos',
            'email' => 'carlos@example.com',
        ]);

        $activity = Activity::first();
        $properties = $activity->properties;

        // Debe guardar solo los campos que cambiaron
        $this->assertArrayHasKey('old', $properties);
        $this->assertArrayHasKey('new', $properties);
        $this->assertEquals('Juan', $properties['old']['nombre']);
        $this->assertEquals('Carlos', $properties['new']['nombre']);
    }

    /** @test */
    public function it_retrieves_activities_for_a_specific_model()
    {
        Activity::truncate();

        $persona1 = Persona::factory()->create();
        $persona2 = Persona::factory()->create();

        $activitiesForPersona1 = $persona1->activities;

        $this->assertCount(1, $activitiesForPersona1);
        $this->assertEquals($persona1->id, $activitiesForPersona1->first()->subject_id);
    }

    /** @test */
    public function it_retrieves_activities_for_a_specific_user()
    {
        Activity::truncate();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        Persona::factory()->create();

        $this->actingAs($user2);
        Persona::factory()->create();

        $user1Activities = Activity::causedBy($user1)->get();
        $user2Activities = Activity::causedBy($user2)->get();

        $this->assertCount(1, $user1Activities);
        $this->assertCount(1, $user2Activities);
    }
}
