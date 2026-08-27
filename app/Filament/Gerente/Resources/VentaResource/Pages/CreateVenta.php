<?php

namespace App\Filament\Gerente\Resources\VentaResource\Pages;

use App\Filament\Gerente\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Forms\Components\Wizard\Step;
use App\Models\Venta;
use App\Models\Note;
use App\Models\Reparto;
use App\Support\VentaFechaVenta;
use App\Support\VentaOrigenResolver;

class CreateVenta extends CreateRecord
{
    use HasWizard;

    protected static string $resource = VentaResource::class;

    public int $noteId;

    // ─── Session persistence ─────────────────────────────────────────────────

    private function sessionKey(): string
    {
        return 'venta_draft_gerente_' . auth()->id() . '_' . ($this->noteId ?? 0);
    }

    private function fileFields(): array
    {
        return [
            'albaran', 'precontractual', 'foto_sorteo', 'dni_anverso', 'dni_reverso',
            'documento_titularidad', 'nomina', 'pension', 'contrato_firmado', 'otros_documentos',
        ];
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'data')) {
            $toSave = $this->data;
            foreach ($this->fileFields() as $field) {
                unset($toSave[$field]);
            }
            session()->put($this->sessionKey(), $toSave);
        }
    }

    protected function afterCreate(): void
    {
        session()->forget($this->sessionKey());
    }

    // ─── Wizard steps ─────────────────────────────────────────────────────────

    protected function getSteps(): array
    {
        return [
            Step::make('Datos del contrato')
                ->icon('heroicon-o-document-text')
                ->description('Información del cliente y de la venta')
                ->schema(VentaResource::step1Schema()),

            Step::make('Documentos y Fotos')
                ->icon('heroicon-o-camera')
                ->description('Sube los documentos requeridos')
                ->schema(VentaResource::step2Schema()),
        ];
    }

    // ─── Mount ────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        parent::mount();

        $this->noteId = (int) request()->route('note');
        abort_if(empty($this->noteId), 404, 'Nota no especificada');

        $note = Note::with('customer')->findOrFail($this->noteId);
        $customer = $note->customer;

        $this->form->fill(array_merge(
            ['note_id' => $note->id],
            $customer->formFillableAttributes()
        ));

        // Restore session draft AFTER pre-filling with note data
        $key = $this->sessionKey();
        if (session()->has($key)) {
            $saved = session($key);
            foreach ($this->fileFields() as $field) {
                unset($saved[$field]);
            }
            $this->data = array_merge($this->data, $saved);
        }
    }

    // ─── Record creation ──────────────────────────────────────────────────────

    protected function handleRecordCreation(array $data): Venta
    {
        $note = Note::with('customer')->findOrFail($this->noteId);

        /* 2. Actualizar cliente --------------------------------------------- */
        $customer = $note->customer;
        $customer->update(array_intersect_key(
            $data,
            array_flip($customer->getFillable())
        ));

        /* 3. Crear venta (pasa el id del CP a la venta si fuera necesario) -- */
        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'fecha_venta' => VentaFechaVenta::normalizeOnCreate($data['fecha_venta'] ?? null),
            'importe_total' => $data['importe_total'],
            'num_cuotas' => $data['num_cuotas'] ?? null,
            'interes_art' => $data['interes_art'] ?? false,
            'origen_venta' => VentaOrigenResolver::origenForCreateFromNote($note),
        ]);

        /* 4. Guardar ofertas + productos ------------------------------------ */
        $this->form->model($venta)->saveRelationships();

        if (!Reparto::where('venta_id', $venta->id)->exists()) {
            Reparto::create(['venta_id' => $venta->id]);
        }

        return $venta;
    }
}
