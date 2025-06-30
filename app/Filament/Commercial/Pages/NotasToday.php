<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Models\Note;
use Illuminate\Support\Facades\Log;
class NotasToday extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas Hoy';
    protected static ?string $title = 'Notas Hoy';
    protected static ?string $slug = 'notas-hoy';
    protected static string $view = 'filament.commercial.pages.notas-today';

    protected $listeners = ['notaActualizada' => '$refresh'];

    public function toggleDeCamino($noteId)
    {
        $note = Note::find($noteId);

        if (!$note || $note->comercial_id !== auth()->id()) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->body('No tienes permiso para modificar esta nota.')
                ->send();

            return;
        }

        $note->de_camino = !$note->de_camino;
        $note->save();

        Notification::make()
            ->title('Estado actualizado')
            ->success()
            ->body('La nota ha sido marcada como ' . ($note->de_camino ? 'EN CAMINO' : 'NO EN CAMINO'))
            ->send();

        $this->emit('notaActualizada'); // Para refrescar los datos si es necesario
    }


    // Deshabilitar completamente los widgets del header
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    // Método para obtener las notas directamente
    public function getNotes()
    {
        $hoy = now()->format('Y-m-d'); // Obtener fecha actual en formato Y-m-d

        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', auth()->id()) // Solo notas del usuario en sesión
            ->whereDate('assignment_date', $hoy) // Solo notas asignadas hoy
            ->latest()
            ->get()
            ->map(function ($note) {
                $postalCode = $note->customer->postalCode->code ?? null;
                $city = $note->customer->postalCode->city->title ?? null;
                $addressInfo = $postalCode && $city ? "$postalCode, $city" : ($postalCode ?? $city ?? 'Sin ubicación');

                return [
                    'id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'customer' => $note->customer->name ?? 'Sin cliente',
                    'primary_address' => $note->customer->primary_address ?? 'Sin dirección',
                    'address_info' => $addressInfo,
                    'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
                    'visit_date' => \Carbon\Carbon::parse($note->visit_date)->format('d/m/Y'),
                    'visit_schedule' => $note->visit_schedule ?? '--:--',
                    'observations' => $note->observations,
                    'fuente' => $note->fuente->value,
                    'fuente_label' => $note->fuente->getLabel(),
                    'fuente_puntaje' => $note->fuente->getPuntaje(),
                    'de_camino' => $note->de_camino,
                ];
            });
    }

}