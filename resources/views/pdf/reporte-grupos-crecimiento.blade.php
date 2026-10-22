<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Grupos de Crecimiento</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            margin: 22px 26px 22px 26px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            line-height: 1.3;
        }

        .header {
            margin-bottom: 12px;
            border-bottom: 2px solid #d97706;
            padding-bottom: 8px;
        }

        .header-inner {
            width: 100%;
        }

        .header-inner td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .header h1 {
            font-size: 17px;
            font-weight: bold;
            color: #92400e;
        }

        .header .meta {
            font-size: 10.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        .header-logo {
            text-align: right;
        }

        .header-logo img {
            width: 44px;
            height: 44px;
        }

        .section-header {
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #92400e;
            border-bottom: 1px solid #fed7aa;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }

        .section-desc {
            font-size: 9.5px;
            color: #4b5563;
        }

        /* KPI Cards Table */
        .kpi-table {
            width: 100%;
            margin-top: 6px;
            margin-bottom: 12px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .kpi-card {
            background-color: #fffbeb;
            border: 1px solid #fed7aa;
            border-radius: 4px;
            padding: 8px 6px;
            text-align: center;
            vertical-align: top;
            width: 25%;
        }

        .kpi-title {
            font-size: 9px;
            text-transform: uppercase;
            color: #78350f;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .kpi-value {
            font-size: 17px;
            font-weight: bold;
            color: #92400e;
        }

        .kpi-sub {
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.data-table thead tr {
            background-color: #92400e;
            color: #ffffff;
        }

        table.data-table thead th {
            padding: 5px 7px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
        }

        table.data-table thead th.center { text-align: center; }
        table.data-table thead th.right { text-align: right; }

        table.data-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        table.data-table tbody td {
            padding: 4.5px 7px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9.5px;
        }

        table.data-table tbody td.center { text-align: center; }
        table.data-table tbody td.right { text-align: right; }

        .badge {
            display: inline-block;
            padding: 1.5px 6px;
            border-radius: 9999px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger  { background-color: #fee2e2; color: #991b1b; }
        .badge-gray    { background-color: #f3f4f6; color: #374151; }
        .badge-primary { background-color: #eff6ff; color: #1e40af; }

        .page-break {
            page-break-before: always;
        }

        .footer {
            margin-top: 10px;
            font-size: 9px;
            color: #9ca3af;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-inner">
            <tr>
                <td>
                    <h1>Informe de Grupos de Crecimiento &mdash; Asistencia y Cumplimiento</h1>
                    <div class="meta">Generado el {{ $generadoEn }} &mdash; Iglesia de los Libres</div>
                </td>
                @if ($logoBase64)
                    <td class="header-logo">
                        <img src="{{ $logoBase64 }}" alt="Logo">
                    </td>
                @endif
            </tr>
        </table>
    </div>

    <!-- SECCIÓN 1: RESUMEN GENERAL -->
    <div class="section-header">
        <div class="section-title">1. Resumen Ejecutivo y Métricas Globales de Asistencia</div>
        <div class="section-desc">
            Período analizado: <strong>{{ $resumen['fecha_inicio'] }}</strong> al <strong>{{ $resumen['fecha_fin'] }}</strong>
            ({{ $resumen['semanas_ciclo'] }} semanas de actividad). Total de asistencias efectivas registradas: <strong>{{ number_format($resumen['total_asistencias'], 0, ',', '.') }}</strong>.
        </div>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-title">Padrón en Sistema</div>
                <div class="kpi-value">{{ number_format($resumen['total_personas_bd'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Total de personas registradas</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Personas Asistentes</div>
                <div class="kpi-value">{{ number_format($resumen['total_asistentes'], 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $resumen['pct_asistentes_sobre_padron'] }}% del padrón general</div>
            </td>
            <td class="kpi-card" style="background-color: #ecfdf5; border-color: #a7f3d0;">
                <div class="kpi-title" style="color: #065f46;">2 o Más Asistencias</div>
                <div class="kpi-value" style="color: #065f46;">{{ number_format($resumen['personas_2_o_mas'], 0, ',', '.') }}</div>
                <div class="kpi-sub"><strong>{{ $resumen['pct_2_o_mas_sobre_asistentes'] }}%</strong> de los asistentes ({{ $resumen['pct_2_o_mas_sobre_padron'] }}% padrón)</div>
            </td>
            <td class="kpi-card" style="background-color: #fff1f2; border-color: #fecdd3;">
                <div class="kpi-title" style="color: #9f1239;">Solo 1 Asistencia</div>
                <div class="kpi-value" style="color: #9f1239;">{{ number_format($resumen['personas_1_asistencia'], 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $resumen['pct_1_sobre_asistentes'] }}% de los asistentes ({{ $resumen['pct_1_sobre_padron'] }}% padrón)</div>
            </td>
        </tr>
    </table>

    <div class="section-header" style="margin-top: 4px;">
        <div class="section-title" style="font-size: 11px;">Distribución de Asistencias por Persona</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Rango de Asistencias</th>
                <th class="center">Cantidad de Personas</th>
                <th class="center">% sobre Asistentes ({{ $resumen['total_asistentes'] }})</th>
                <th class="center">% sobre Padrón Total ({{ $resumen['total_personas_bd'] }})</th>
                <th class="center">Frecuencia / Fidelización</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($distribucion as $item)
                @php
                    $esUna = $item['rango'] === '1 asistencia';
                    $esAlta = in_array($item['rango'], ['11 a 20 asistencias', 'Más de 20 asistencias']);
                    $badgeClase = $esUna ? 'badge-danger' : ($esAlta ? 'badge-success' : 'badge-warning');
                    $textoEstado = match($item['rango']) {
                        '1 asistencia' => 'Visita única (no volvió)',
                        '2 a 5 asistencias' => 'Integración inicial',
                        '6 a 10 asistencias' => 'Regular / En consolidación',
                        '11 a 20 asistencias' => 'Frecuente / Consolidado',
                        'Más de 20 asistencias' => 'Alta fidelidad / Líder / Núcleo',
                        default => '-'
                    };
                @endphp
                <tr>
                    <td><strong>{{ $item['rango'] }}</strong></td>
                    <td class="center">
                        <span class="badge badge-gray">{{ $item['cantidad'] }}</span>
                    </td>
                    <td class="center">{{ $item['pct_asistentes'] }}%</td>
                    <td class="center">{{ $item['pct_padron'] }}%</td>
                    <td class="center">
                        <span class="badge {{ $badgeClase }}">{{ $textoEstado }}</span>
                    </td>
                </tr>
            @endforeach
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td>Total con Asistencias Registradas</td>
                <td class="center">
                    <span class="badge badge-primary">{{ $resumen['total_asistentes'] }}</span>
                </td>
                <td class="center">100,0%</td>
                <td class="center">{{ $resumen['pct_asistentes_sobre_padron'] }}%</td>
                <td class="center">
                    <span class="badge badge-primary">Base de participantes activos</span>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- SECCIÓN 2: DETALLE POR GRUPO -->
    <div class="header">
        <table class="header-inner">
            <tr>
                <td>
                    <h1>2. Detalle por Grupo de Crecimiento: Reuniones Realizadas vs. Esperadas</h1>
                    <div class="meta">
                        Ciclo analizado: <strong>{{ $resumen['semanas_ciclo'] }} semanas</strong>
                        &bull; Criterio: Semanales esperadas = <strong>{{ $resumen['semanas_ciclo'] }}</strong> | Quincenales esperadas = <strong>{{ (int) round($resumen['semanas_ciclo'] / 2) }}</strong>
                    </div>
                </td>
                @if ($logoBase64)
                    <td class="header-logo">
                        <img src="{{ $logoBase64 }}" alt="Logo">
                    </td>
                @endif
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 38%;">Grupo</th>
                <th class="center" style="width: 10%;">Frecuencia</th>
                <th class="center" style="width: 10%;">Participantes</th>
                <th class="center" style="width: 11%;">Reuniones Realizadas</th>
                <th class="center" style="width: 10%;">Reuniones Esperadas</th>
                <th class="center" style="width: 11%;">% Cumplimiento Reuniones</th>
                <th class="center" style="width: 10%;">% Promedio Asistencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grupos as $grupo)
                @php
                    $frecuencia = \App\Models\Grupo::frecuenciasAsistencia()[$grupo->frecuencia_asistencia] ?? ucfirst($grupo->frecuencia_asistencia);
                    $cumplimiento = (int) ($grupo->cumplimiento_reuniones ?? 0);
                    $badgeCumplimiento = match(true) {
                        $cumplimiento >= 80 => 'badge-success',
                        $cumplimiento >= 50 => 'badge-warning',
                        default            => 'badge-danger',
                    };

                    $promedio = (int) ($grupo->promedio_asistencia ?? 0);
                    $badgePromedio = match(true) {
                        $promedio >= 80 => 'badge-success',
                        $promedio >= 50 => 'badge-warning',
                        default         => 'badge-danger',
                    };
                @endphp
                <tr>
                    <td>{{ $grupo->nombre }}</td>
                    <td class="center">
                        <span class="badge badge-gray">{{ $frecuencia }}</span>
                    </td>
                    <td class="center">
                        <span class="badge badge-gray">{{ $grupo->participantes_count }}</span>
                    </td>
                    <td class="center">
                        <strong>{{ $grupo->reuniones_registradas_count }}</strong>
                    </td>
                    <td class="center">
                        {{ $grupo->reuniones_esperadas }}
                    </td>
                    <td class="center">
                        <span class="badge {{ $badgeCumplimiento }}">{{ $cumplimiento }}%</span>
                    </td>
                    <td class="center">
                        <span class="badge {{ $badgePromedio }}">{{ $promedio }}%</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center" style="padding: 20px; color: #6b7280;">
                        No hay grupos de crecimiento activos.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Iglesia de los Libres &mdash; Reporte generado el {{ $generadoEn }}</div>
</body>
</html>
