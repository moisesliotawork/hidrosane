<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTerminal;
use App\Models\Note;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotasSalaPdfController extends Controller
{
    /**
     * PDF para IDs seleccionados (GET /head-of-room/notas/pdf-oficina?ids=1,5,9)
     */
    public function sala(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->filter()
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            abort(404, 'Sin IDs válidos.');
        }

        // (Opcional) valida estados permitidos
        $validIds = Note::query()
            ->whereIn('id', $ids)
            ->where(function (Builder $q) {
                $q->whereNull('estado_terminal')
                    ->orWhereIn('estado_terminal', [
                        EstadoTerminal::SIN_ESTADO->value,
                        EstadoTerminal::SALA->value,
                    ]);
            })
            ->pluck('id')
            ->all();

        if (empty($validIds)) {
            abort(404, 'No hay notas válidas para el PDF.');
        }

        // ✅ Marca como impresas ANTES de generar el PDF
        DB::transaction(function () use ($validIds) {
            DB::table('notes')
                ->whereIn('id', $validIds)
                ->update([
                    'printed' => 1,
                    'updated_at' => now(),
                ]);
        });

        $notes = Note::query()
            ->whereIn('id', $validIds)
            ->with([
                'customer.postalCode.city',
                'user',
                'comercial',
                'observations.author',
                'observacionesSala.author',
            ])
            ->orderBy('nro_nota')
            ->get();

        if ($notes->isEmpty()) {
            abort(404, 'No hay notas válidas para el PDF.');
        }

        $pdf = Pdf::loadView('pdf.notas-sala', ['notes' => $notes])->setPaper('a4');

        return response()->streamDownload(
            fn() => print ($pdf->output()),
            'notas-oficina-' . now()->format('Ymd-His') . '.pdf'
        );
    }

    /**
     * (Opcional) Versión "histórica" por si quieres seguir teniendo un index general.
     * NOTA: acá no marcamos printed — solo genera PDF de todas en SALA.
     */
    public function index()
    {
        $notes = Note::query()
            ->where('estado_terminal', EstadoTerminal::SALA->value)
            ->with(['customer.postalCode.city', 'user', 'comercial'])
            ->orderBy('assignment_date', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.notas-sala', ['notes' => $notes])->setPaper('a4');

        return response()->streamDownload(
            fn() => print ($pdf->output()),
            'notas-oficina-' . now()->format('Ymd-His') . '.pdf'
        );
    }
}
