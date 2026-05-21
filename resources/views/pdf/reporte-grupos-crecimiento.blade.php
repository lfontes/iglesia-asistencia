<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #1a1a1a;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #d97706;
            padding-bottom: 10px;
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
            font-size: 18px;
            font-weight: bold;
            color: #92400e;
        }

        .header .meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .header-logo {
            text-align: right;
        }

        .header-logo img {
            width: 52px;
            height: 52px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background-color: #92400e;
            color: #ffffff;
        }

        thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 13px;
            font-weight: bold;
        }

        thead th.center { text-align: center; }

        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody td.center { text-align: center; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger  { background-color: #fee2e2; color: #991b1b; }
        .badge-gray    { background-color: #f3f4f6; color: #374151; }

        .footer {
            margin-top: 16px;
            font-size: 11px;
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
                    <h1>Reporte de grupos de crecimiento</h1>
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

    <table>
        <thead>
            <tr>
                <th>Grupo</th>
                <th class="center">Participantes</th>
                <th class="center">Reuniones registradas</th>
                <th>Frecuencia</th>
                <th class="center">% promedio asistencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grupos as $grupo)
                @php
                    $promedio = $grupo->promedio_asistencia;
                    $badgeClass = match(true) {
                        $promedio >= 80 => 'badge-success',
                        $promedio >= 50 => 'badge-warning',
                        default         => 'badge-danger',
                    };
                    $frecuencia = \App\Models\Grupo::frecuenciasAsistencia()[$grupo->frecuencia_asistencia] ?? '-';
                @endphp
                <tr>
                    <td>{{ $grupo->nombre }}</td>
                    <td class="center">
                        <span class="badge badge-gray">{{ $grupo->participantes_count }}</span>
                    </td>
                    <td class="center">
                        <span class="badge badge-gray">{{ $grupo->reuniones_registradas_count }}</span>
                    </td>
                    <td>{{ $frecuencia }}</td>
                    <td class="center">
                        <span class="badge {{ $badgeClass }}">{{ $promedio }}%</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center" style="padding: 20px; color: #6b7280;">
                        No hay grupos de crecimiento activos.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Iglesia de los Libres &mdash; {{ $generadoEn }}</div>
</body>
</html>
