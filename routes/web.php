<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoPreviewController;
use App\Http\Controllers\ContratoPreviewBController;
use App\Http\Controllers\NotasSalaPdfController;
use App\Models\PickingDiario;
use App\Models\ListaAmano;
use App\Support\ContratosPorMesStats;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CreamTransferController;

Route::middleware(['auth'])    // añade otros middlewares si los usas (verified, etc.)
    ->prefix('comercial')
    ->group(function () {

        // Ver la solicitud (pantalla con aceptar / rechazar)
        Route::get('/cream-transfers/{transfer}', [CreamTransferController::class, 'show'])
            ->name('cream-transfers.show');

        // Aceptar transferencia
        Route::post('/cream-transfers/{transfer}/accept', [CreamTransferController::class, 'accept'])
            ->name('cream-transfers.accept');

        // Rechazar transferencia
        Route::post('/cream-transfers/{transfer}/reject', [CreamTransferController::class, 'reject'])
            ->name('cream-transfers.reject');
    });

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', 'verified'])          // los mismos middlewares de tu panel
    ->prefix('admin/ventas')                   // coincide con la URL de Filament («admin»)
    ->as('ventas.')                            // prefijo de nombre
    ->group(function () {
        Route::get('{venta}/preview', ContratoPreviewController::class)
            ->name('preview');                // ☑ ventas.preview
        Route::get('{venta}/preview-b', ContratoPreviewBController::class)
            ->name('preview-b');              // ☑ ventas.preview-b (sólo las 5 hojas -B)
    });


Route::middleware(['web', 'auth']) // añade tus middlewares/panel si aplica
    ->get('/head-of-room/notas/sala/pdf', [NotasSalaPdfController::class, 'index'])
    ->name('notas.sala.pdf');


Route::get('/picking-diario/pdf/{date}', function (string $date) {
    $rows = PickingDiario::with('producto')
        ->where('fecha', $date)
        ->get()
        ->sortBy(fn($r) => mb_strtolower($r->producto->nombre ?? ''));

    $pdf = Pdf::loadView('pdf.picking-diario', [
        'fecha' => $date,
        'rows' => $rows,
    ])->setPaper('a4', 'portrait');

    $filename = 'hoja-carga-' . $date . '.pdf';

    // Mostrar en navegador en vez de descargar
    return $pdf->stream($filename);
})->name('picking-diario.pdf');

Route::middleware(['web', 'auth'])
    ->get('/superadmin/contratos-por-mes/pdf', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        abort_unless(
            $user && method_exists($user, 'hasRole') && $user->hasRole('app_support'),
            403
        );

        $mes = $request->query('mes');
        $showAll = blank($mes) || $request->boolean('todos');

        if (! $showAll) {
            try {
                \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $mes);
            } catch (\Throwable) {
                $showAll = true;
                $mes = null;
            }
        }

        $rows = ContratosPorMesStats::rows();
        if (! $showAll && filled($mes)) {
            $rows = $rows
                ->filter(fn ($row) => (string) $row->mes_key === (string) $mes)
                ->values();
        }

        $items = ContratosPorMesStats::variationDetailItems();
        if (! $showAll && filled($mes)) {
            $items = $items
                ->filter(fn ($item) => $item->mes_key === $mes)
                ->values();
        }

        $quitados = $items->filter(fn ($item) => in_array($item->estado, [
            \App\Models\ContratoMesVariacionItem::ESTADO_SOFT_DELETE,
            \App\Models\ContratoMesVariacionItem::ESTADO_BORRADO,
        ], true))->values();

        $agregados = $items->filter(fn ($item) => in_array($item->estado, [
            \App\Models\ContratoMesVariacionItem::ESTADO_NUEVO,
            \App\Models\ContratoMesVariacionItem::ESTADO_RESTAURADO,
        ], true))->values();

        $periodoLabel = $showAll || blank($mes)
            ? 'Todos los meses'
            : ContratosPorMesStats::labelForMonthKey((string) $mes);

        $pdf = Pdf::loadView('pdf.contratos-por-mes', [
            'rows' => $rows,
            'quitados' => $quitados,
            'agregados' => $agregados,
            'fechaReporte' => now()->format('d/m/Y H:i:s'),
            'periodoVariaciones' => $periodoLabel,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('contratos-por-mes.pdf');
    })->name('contratos-por-mes.pdf');

Route::middleware(['web', 'auth'])
    ->get('/superadmin/lista-amano/pdf', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        abort_unless(
            $user && method_exists($user, 'hasRole') && $user->hasRole('app_support'),
            403
        );

        $mes = $request->query('mes');
        $showAll = blank($mes) || $request->boolean('todos');
        $clienteQ = trim((string) $request->query('q', ''));

        if (! $showAll) {
            try {
                \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $mes);
            } catch (\Throwable) {
                $showAll = true;
                $mes = null;
            }
        }

        $query = ListaAmano::query()->orderBy('id');

        if (! $showAll && filled($mes)) {
            [$year, $month] = array_map('intval', explode('-', (string) $mes));
            $query->where('anio', $year)->where('mes', $month);
        }

        if ($clienteQ !== '') {
            $query->where('cliente', 'like', '%'.$clienteQ.'%');
        }

        $rows = $query->get();

        $periodoLabel = $showAll || blank($mes)
            ? 'Todos los registros'
            : (string) $mes;

        if (! $showAll && filled($mes)) {
            try {
                $periodoLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $mes)
                    ->locale('es')
                    ->translatedFormat('F Y');
                $periodoLabel = mb_convert_case($periodoLabel, MB_CASE_TITLE, 'UTF-8');
            } catch (\Throwable) {
                $periodoLabel = (string) $mes;
            }
        }

        $pdf = Pdf::loadView('pdf.lista-amano', [
            'rows' => $rows,
            'periodoLabel' => $periodoLabel,
            'clienteQ' => $clienteQ !== '' ? $clienteQ : null,
            'fechaReporte' => now('Europe/Madrid')->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('lista-amano.pdf');
    })->name('lista-amano.pdf');

Route::middleware(['web', 'auth'])
    ->get('/superadmin/recuperados-aceptados/pdf', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        abort_unless(
            $user && method_exists($user, 'hasRole') && $user->hasRole('app_support'),
            403
        );

        $rows = \App\Models\ContratoRecoveryItem::query()
            ->with(['venta'])
            ->where('status', \App\Models\ContratoRecoveryItem::STATUS_ADDED)
            ->orderByDesc('id')
            ->get();

        $pdf = Pdf::loadView('pdf.recuperados-aceptados', [
            'rows' => $rows,
            'fechaReporte' => now('Europe/Madrid')->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('recuperados-aceptados.pdf');
    })->name('recuperados-aceptados.pdf');

Route::middleware(['web', 'auth'])
    ->get('/superadmin/contratos-por-mes/numeros/pdf', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        abort_unless(
            $user && method_exists($user, 'hasRole') && $user->hasRole('app_support'),
            403
        );

        $mes = $request->query('mes');
        $showAll = blank($mes) || $request->boolean('todos');

        if (! $showAll) {
            try {
                \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $mes);
            } catch (\Throwable) {
                $showAll = true;
                $mes = null;
            }
        }

        $grupos = ContratosPorMesStats::adminContractNumbersByMonth(
            $showAll ? null : (string) $mes
        );

        $periodoLabel = $showAll || blank($mes)
            ? 'Todos los meses'
            : ContratosPorMesStats::labelForMonthKey((string) $mes);

        $pdf = Pdf::loadView('pdf.contratos-por-mes-numeros', [
            'grupos' => $grupos,
            'fechaReporte' => now()->format('d/m/Y H:i:s'),
            'periodoLabel' => $periodoLabel,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('contratos-por-mes-numeros-admin.pdf');
    })->name('contratos-por-mes.numeros.pdf');

Route::middleware(['web', 'auth'])
    ->get('/superadmin/contratos-por-mes/solo-numeros/pdf', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        abort_unless(
            $user && method_exists($user, 'hasRole') && $user->hasRole('app_support'),
            403
        );

        $mes = $request->query('mes');
        $showAll = blank($mes) || $request->boolean('todos');

        if (! $showAll) {
            try {
                \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $mes);
            } catch (\Throwable) {
                $showAll = true;
                $mes = null;
            }
        }

        $numeros = ContratosPorMesStats::adminContractNumbersOnly(
            $showAll ? null : (string) $mes
        );

        $periodoLabel = $showAll || blank($mes)
            ? 'Todos los meses'
            : ContratosPorMesStats::labelForMonthKey((string) $mes);

        $pdf = Pdf::loadView('pdf.contratos-por-mes-solo-numeros', [
            'numeros' => $numeros,
            'fechaReporte' => now()->format('d/m/Y H:i:s'),
            'periodoLabel' => $periodoLabel,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('contratos-por-mes-solo-numeros.pdf');
    })->name('contratos-por-mes.solo-numeros.pdf');

// Logout global de Laravel (solo POST: CSRF). GET → login para evitar 405 en barra/atrás.
Route::get('/logout', fn () => redirect('/admin/login'));
Route::post('/logout', LogoutController::class)->name('logout');

// Logout de todos los paneles Filament (apuntan al mismo controlador)
foreach (['admin', 'comercial', 'teleoperador', 'jefe-sala', 'gerente', 'repartidor', 'superAdmin'] as $panel) {
    Route::get("/{$panel}/logout", fn () => redirect("/{$panel}/login"));
    Route::post("/{$panel}/logout", LogoutController::class)
        ->name("filament.{$panel}.auth.logout");
}
