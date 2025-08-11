<?php

// app/Http/Controllers/NotasSalaPdfController.php
namespace App\Http\Controllers;

use App\Enums\EstadoTerminal;
use App\Models\Note;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;

class NotasSalaPdfController extends Controller
{
    public function index()
    {
        $notes = Note::query()
            ->where(function (Builder $q) {
                $q->whereNull('estado_terminal')
                  ->orWhereIn('estado_terminal', [
                      EstadoTerminal::SIN_ESTADO->value,
                      EstadoTerminal::SALA->value,
                  ]);
            })
            ->where('estado_terminal', EstadoTerminal::SALA->value)
            ->with(['customer.postalCode.city', 'user', 'comercial'])
            ->orderBy('assignment_date', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.notas-sala', ['notes' => $notes])->setPaper('a4');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'notas-sala-' . now()->format('Ymd-His') . '.pdf'
        );
    }
}
