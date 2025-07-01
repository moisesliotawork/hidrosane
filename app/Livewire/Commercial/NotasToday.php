<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use Filament\Notifications\Notification;

class NotasToday extends Component
{
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

        if (!$note->de_camino) {
            Notification::make()
                ->title('Estado actualizado')
                ->warning()
                ->body('La nota ha sido marcada como NO EN CAMINO')
                ->send();
        } else {
            Notification::make()
                ->title('Estado actualizado')
                ->success()
                ->body('La nota ha sido marcada como EN CAMINO')
                ->send();
        }

        $this->dispatch('notaActualizada');
    }

    public function getNotesProperty()
    {
        $hoy = now()->format('Y-m-d');

        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', auth()->id())
            ->whereDate('assignment_date', $hoy)
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

    public function render()
    {
        return view('livewire.commercial.notas-today');
    }
}
