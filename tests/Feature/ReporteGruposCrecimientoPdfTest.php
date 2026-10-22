<?php

namespace Tests\Feature;

use App\Models\Grupo;
use App\Models\TipoGrupo;
use App\Models\User;
use App\Services\ReporteGruposCrecimientoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReporteGruposCrecimientoPdfTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected User $usuarioComun;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'coordinador_grupos', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->usuarioComun = User::factory()->create();
    }

    #[Test]
    public function un_usuario_no_autorizado_no_puede_descargar_el_pdf(): void
    {
        $this->actingAs($this->usuarioComun)
            ->get(route('reporte-grupos-crecimiento.pdf'))
            ->assertForbidden();
    }

    #[Test]
    public function un_admin_puede_descargar_el_pdf(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reporte-grupos-crecimiento.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function el_servicio_de_reporte_calcula_metricas_esperadas(): void
    {
        $service = app(ReporteGruposCrecimientoService::class);
        $datos = $service->obtenerDatosReporte();

        $this->assertArrayHasKey('resumen', $datos);
        $this->assertArrayHasKey('distribucion', $datos);
        $this->assertArrayHasKey('grupos', $datos);

        $this->assertArrayHasKey('total_personas_bd', $datos['resumen']);
        $this->assertArrayHasKey('personas_2_o_mas', $datos['resumen']);
        $this->assertArrayHasKey('semanas_ciclo', $datos['resumen']);

        if ($datos['grupos']->isNotEmpty()) {
            $primerGrupo = $datos['grupos']->first();
            $this->assertNotNull($primerGrupo->reuniones_esperadas);
            $this->assertNotNull($primerGrupo->cumplimiento_reuniones);
        }
    }

    #[Test]
    public function el_comando_artisan_genera_el_archivo_pdf(): void
    {
        $tempPath = storage_path('app/test_reporte.pdf');

        $this->artisan('reporte:grupos-pdf', ['--path' => $tempPath])
            ->assertSuccessful();

        $this->assertFileExists($tempPath);
        $this->assertGreaterThan(1000, filesize($tempPath));

        @unlink($tempPath);
    }
}
