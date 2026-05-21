<?php

namespace App\Http\Controllers;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class ReporteGruposCrecimientoPdfController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['admin', 'coordinador_grupos']),
            403
        );

        $grupos = $this->getGrupos();

        $logoPath = public_path('images/logo-iglesia-libres.png');
        $logoBase64 = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('pdf.reporte-grupos-crecimiento', [
            'grupos' => $grupos,
            'generadoEn' => now()->format('d/m/Y H:i'),
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte-grupos-crecimiento.pdf');
    }

    private function getGrupos()
    {
        $fechaActual = now()->toDateString();

        return Grupo::query()
            ->select('grupos.*')
            ->join('tipo_grupos', 'tipo_grupos.id', '=', 'grupos.tipo_grupo_id')
            ->where('tipo_grupos.nombre', 'Crecimiento')
            ->where('grupos.activo', true)
            ->selectSub(
                ParticipacionGrupo::query()
                    ->selectRaw('COUNT(DISTINCT persona_id)')
                    ->whereColumn('grupo_id', 'grupos.id')
                    ->where(function (Builder $query) use ($fechaActual): void {
                        $query->whereNull('fecha_fin')
                            ->orWhereDate('fecha_fin', '>=', $fechaActual);
                    }),
                'participantes_count'
            )
            ->selectSub(
                AsistenciaGrupo::query()
                    ->selectRaw('COUNT(DISTINCT fecha)')
                    ->whereColumn('grupo_id', 'grupos.id'),
                'reuniones_registradas_count'
            )
            ->selectSub(
                AsistenciaGrupo::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('grupo_id', 'grupos.id')
                    ->where('presente', true),
                'presentes_count'
            )
            ->orderBy('grupos.nombre')
            ->get()
            ->map(function (Grupo $grupo): Grupo {
                $participantes = (int) ($grupo->participantes_count ?? 0);
                $reuniones = (int) ($grupo->reuniones_registradas_count ?? 0);

                $grupo->promedio_asistencia = ($participantes === 0 || $reuniones === 0)
                    ? 0
                    : (int) round(((int) ($grupo->presentes_count ?? 0) / ($participantes * $reuniones)) * 100);

                return $grupo;
            });
    }
}
