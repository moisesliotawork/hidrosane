<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoPreviewController;

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

