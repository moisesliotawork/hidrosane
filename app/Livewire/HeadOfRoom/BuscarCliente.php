<?php

namespace App\Livewire\HeadOfRoom;

use Livewire\Component;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Models\Customer;
use App\Filament\Teleoperator\Resources\NoteResource;
use Filament\Notifications\Notification;

class BuscarCliente extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public bool $phoneNotFound = false;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,

            'nro_piso' => null,
            'postal_code' => null,
            'ayuntamiento' => null,
            'provincia' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Buscar cliente')
                ->schema([
                    Forms\Components\TextInput::make('phone_query')
                        ->label('INGRESA NÚMERO DE TELÉFONO')
                        ->tel()
                        ->mask('999 999 999')
                        ->placeholder('999 999 999')
                        ->required()
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $digits = preg_replace('/\D+/', '', (string) $value);
                                if (strlen($digits) !== 9) {
                                    $fail('Debe tener exactamente 9 cifras.');
                                }
                            };
                        }),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('buscarTelefono')
                            ->label('Buscar')
                            ->color('warning')
                            ->action(fn() => $this->buscarTelefono()),
                    ]),

                    Forms\Components\Placeholder::make('no_encontrado')
                        ->content('NO SE ENCONTRO TELÉFONO')
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\TextInput::make('nro_piso')
                        ->label('No. y Piso')
                        ->placeholder('Nº 1')
                        ->required()
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Código Postal')
                        ->placeholder('15551')
                        ->required()
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\TextInput::make('ayuntamiento')
                        ->label('Ciudad')
                        
                        ->required()
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\TextInput::make('provincia')
                        ->label('Provincia')
                        ->placeholder('CORUÑA')
                        ->required()
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('buscarDireccion')
                            ->label('Buscar por dirección')
                            ->color('info')
                            ->visible(fn() => $this->phoneNotFound)
                            ->action(fn() => $this->buscarDireccion()),
                    ]),
                ])
                ->columns(1),
        ])->statePath('data');
    }

    protected function notifyNotaDuplicada(string $detalle): void
    {
        Notification::make()
            ->title('NOTA DUPLICADA')
            ->body($detalle)
            ->danger()
            ->persistent() // opcional: que no se cierre sola
            ->send();
    }

    public function buscarTelefono(): void
    {
        $state = $this->form->getState();

        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        if (strlen($digits) !== 9) {
            $this->phoneNotFound = false;
            return;
        }

        $exists = Customer::query()
            ->where('phone', $digits)
            ->orWhere('secondary_phone', $digits)
            ->exists();

        if ($exists) {
            $this->notifyNotaDuplicada("Ya existe una nota con este numero de telefono");
            // ✅ Si existe, a ListNotes
            redirect()->to(NoteResource::getUrl('index'));
            return;
        }

        // ❌ No existe: mostramos mensaje + habilitamos dirección (paso 2)
        $this->phoneNotFound = true;
    }

    public function buscarDireccion(): void
    {
        $state = $this->form->getState();

        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        // Normalizar (trim + colapsar espacios + lowercase)
        $norm = function (?string $v): string {
            $v = trim((string) $v);
            $v = preg_replace('/\s+/u', ' ', $v);
            return mb_strtolower($v, 'UTF-8');
        };

        $nroPiso = $norm($state['nro_piso'] ?? null);
        $postalCode = $norm($state['postal_code'] ?? null);
        $ayto = $norm($state['ayuntamiento'] ?? null);
        $provincia = $norm($state['provincia'] ?? null);

        // Si falta algo, no busques (y no redirijas)
        if ($nroPiso === '' || $postalCode === '' || $ayto === '' || $provincia === '') {
            Notification::make()
                ->title('Faltan datos')
                ->body('Completa No. y Piso, Código Postal, Ayuntamiento y Provincia.')
                ->warning()
                ->send();
            return;
        }

        // ✅ Deben coincidir LOS 4 en el mismo registro (ignorando mayúsculas/minúsculas)
        $exists = Customer::query()
            ->whereRaw('LOWER(TRIM(nro_piso)) = ?', [$nroPiso])
            ->whereRaw('LOWER(TRIM(postal_code)) = ?', [$postalCode])
            ->whereRaw('LOWER(TRIM(ciudad)) = ?', [$ayto])
            ->whereRaw('LOWER(TRIM(provincia)) = ?', [$provincia])
            ->exists();

        if ($exists) {
            $this->notifyNotaDuplicada("Ya existe una nota con esta dirección (4 campos coinciden).");
            redirect()->to(NoteResource::getUrl('index'));
            return;
        }

        // ❌ No existe => crear nota, enviando data para precargar (opcional)
        redirect()->to(NoteResource::getUrl('create', [
            'phone' => $digits ?: null,
            'nro_piso' => $state['nro_piso'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            'ayuntamiento' => $state['ayuntamiento'] ?? null,
            'provincia' => $state['provincia'] ?? null,
        ]));
    }

    public function render()
    {
        return view('livewire.head-of-room.buscar-cliente');
    }
}