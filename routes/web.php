<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoPreviewController;
use App\Http\Controllers\NotasSalaPdfController;

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


