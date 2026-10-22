<?php

namespace App\Http\Controllers;

use App\Services\ReporteGruposCrecimientoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReporteGruposCrecimientoPdfController extends Controller
{
    public function __invoke(ReporteGruposCrecimientoService $service): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['admin', 'coordinador_grupos']),
            403
        );

        $datos = $service->obtenerDatosReporte();

        $pdf = Pdf::loadView('pdf.reporte-grupos-crecimiento', $datos)
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-grupos-crecimiento.pdf');
    }
}
