<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoPreviewController;
use App\Http\Controllers\NotasSalaPdfController;
use App\Models\PickingDiario;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Auth\LogoutController;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', 'verified'])          // los mismos middlewares de tu panel
    ->prefix('admin/ventas')                   // coincide con la URL de Filament («admin»)
    ->as('ventas.')                            // prefijo de nombre
    ->group(function () {
        Route::get('{venta}/preview', ContratoPreviewController::class)
            ->name('preview');                // ☑ ventas.preview
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

// Logout global de Laravel
Route::post('/logout', LogoutController::class)->name('logout');

// Logout de todos los paneles Filament (apuntan al mismo controlador)
foreach (['admin', 'comercial', 'teleoperador', 'jefe-sala', 'gerente', 'repartidor', 'superAdmin'] as $panel) {
    Route::post("/{$panel}/logout", LogoutController::class)
        ->name("filament.{$panel}.auth.logout");
}
