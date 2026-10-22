<?php

namespace App\Services;

use App\Models\AsistenciaGrupo;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReporteGruposCrecimientoService
{
    /**
     * @return array{
     *     resumen: array<string, mixed>,
     *     distribucion: array<int, array<string, mixed>>,
     *     grupos: \Illuminate\Support\Collection<int, Grupo>,
     *     generadoEn: string,
     *     logoBase64: ?string
     * }
     */
    public function obtenerDatosReporte(): array
    {
        $fechaActual = now()->toDateString();
        $tipoGrupo = TipoGrupo::query()
            ->whereRaw('LOWER(nombre) LIKE ?', ['%crecimiento%'])
            ->first();
        $tipoGrupoId = $tipoGrupo?->id ?? 2;

        $totalPersonasBd = Persona::count();

        // Asistencias en grupos de crecimiento
        $asistenciasQuery = AsistenciaGrupo::query()
            ->join('grupos', 'asistencia_grupos.grupo_id', '=', 'grupos.id')
            ->where('grupos.tipo_grupo_id', $tipoGrupoId)
            ->where('asistencia_grupos.presente', true);

        $totalAsistencias = (clone $asistenciasQuery)->count();

        $minFecha = (clone $asistenciasQuery)->min('asistencia_grupos.fecha');
        $maxFecha = (clone $asistenciasQuery)->max('asistencia_grupos.fecha');

        $semanasCiclo = ($minFecha && $maxFecha)
            ? max(1, (int) round(Carbon::parse($minFecha)->diffInWeeks(Carbon::parse($maxFecha))))
            : 32;

        // Conteo por persona
        $conteoPorPersona = (clone $asistenciasQuery)
            ->select('asistencia_grupos.persona_id', DB::raw('COUNT(*) as total_asistencias'))
            ->groupBy('asistencia_grupos.persona_id')
            ->get();

        $totalAsistentes = $conteoPorPersona->count();
        $personas2OMas = $conteoPorPersona->where('total_asistencias', '>=', 2)->count();
        $personas1Sola = $conteoPorPersona->where('total_asistencias', 1)->count();
        $personasSinAsistencia = max(0, $totalPersonasBd - $totalAsistentes);

        // Distribución detallada
        $distribucionRaw = [
            '1 asistencia' => $conteoPorPersona->where('total_asistencias', 1)->count(),
            '2 a 5 asistencias' => $conteoPorPersona->filter(fn ($i) => $i->total_asistencias >= 2 && $i->total_asistencias <= 5)->count(),
            '6 a 10 asistencias' => $conteoPorPersona->filter(fn ($i) => $i->total_asistencias >= 6 && $i->total_asistencias <= 10)->count(),
            '11 a 20 asistencias' => $conteoPorPersona->filter(fn ($i) => $i->total_asistencias >= 11 && $i->total_asistencias <= 20)->count(),
            'Más de 20 asistencias' => $conteoPorPersona->where('total_asistencias', '>', 20)->count(),
        ];

        $distribucion = [];
        foreach ($distribucionRaw as $rango => $cantidad) {
            $distribucion[] = [
                'rango' => $rango,
                'cantidad' => $cantidad,
                'pct_asistentes' => $totalAsistentes > 0 ? round(($cantidad / $totalAsistentes) * 100, 1) : 0,
                'pct_padron' => $totalPersonasBd > 0 ? round(($cantidad / $totalPersonasBd) * 100, 1) : 0,
            ];
        }

        $resumen = [
            'total_personas_bd' => $totalPersonasBd,
            'total_asistentes' => $totalAsistentes,
            'personas_sin_asistencia' => $personasSinAsistencia,
            'personas_2_o_mas' => $personas2OMas,
            'personas_1_asistencia' => $personas1Sola,
            'pct_asistentes_sobre_padron' => $totalPersonasBd > 0 ? round(($totalAsistentes / $totalPersonasBd) * 100, 1) : 0,
            'pct_2_o_mas_sobre_asistentes' => $totalAsistentes > 0 ? round(($personas2OMas / $totalAsistentes) * 100, 1) : 0,
            'pct_2_o_mas_sobre_padron' => $totalPersonasBd > 0 ? round(($personas2OMas / $totalPersonasBd) * 100, 1) : 0,
            'pct_1_sobre_asistentes' => $totalAsistentes > 0 ? round(($personas1Sola / $totalAsistentes) * 100, 1) : 0,
            'pct_1_sobre_padron' => $totalPersonasBd > 0 ? round(($personas1Sola / $totalPersonasBd) * 100, 1) : 0,
            'fecha_inicio' => $minFecha ? Carbon::parse($minFecha)->format('d/m/Y') : '-',
            'fecha_fin' => $maxFecha ? Carbon::parse($maxFecha)->format('d/m/Y') : '-',
            'semanas_ciclo' => $semanasCiclo,
            'total_asistencias' => $totalAsistencias,
        ];

        $grupos = Grupo::query()
            ->select('grupos.*')
            ->join('tipo_grupos', 'tipo_grupos.id', '=', 'grupos.tipo_grupo_id')
            ->where('tipo_grupos.id', $tipoGrupoId)
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
            ->map(function (Grupo $grupo) use ($semanasCiclo): Grupo {
                $participantes = (int) ($grupo->participantes_count ?? 0);
                $reuniones = (int) ($grupo->reuniones_registradas_count ?? 0);
                $presentes = (int) ($grupo->presentes_count ?? 0);

                $esperadas = ($grupo->frecuencia_asistencia === Grupo::FRECUENCIA_QUINCENAL)
                    ? (int) round($semanasCiclo / 2)
                    : $semanasCiclo;

                $grupo->reuniones_esperadas = $esperadas;
                $grupo->cumplimiento_reuniones = $esperadas > 0
                    ? (int) round(($reuniones / $esperadas) * 100)
                    : 0;

                $grupo->promedio_asistencia = ($participantes === 0 || $reuniones === 0)
                    ? 0
                    : (int) round(($presentes / ($participantes * $reuniones)) * 100);

                return $grupo;
            });

        $logoPath = public_path('images/logo-iglesia-libres.png');
        $logoBase64 = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;

        return [
            'resumen' => $resumen,
            'distribucion' => $distribucion,
            'grupos' => $grupos,
            'generadoEn' => now()->format('d/m/Y H:i'),
            'logoBase64' => $logoBase64,
        ];
    }
}
