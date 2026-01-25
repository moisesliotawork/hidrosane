<?php

namespace App\Livewire\Teleoperator;

use Livewire\Component;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Models\Customer;
use App\Filament\Teleoperator\Resources\NoteResource;

class BuscarCliente extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public bool $phoneNotFound = false;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,
            'address_query' => null,
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

                    Forms\Components\Textarea::make('address_query')
                        ->label('Dirección')
                        ->rows(3)
                        ->placeholder('Escribe la dirección completa…')
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

        $address = trim((string) ($state['address_query'] ?? ''));
        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        if ($address === '') {
            // si está vacío, directo a crear nota
            redirect()->to(NoteResource::getUrl('create', ['phone' => $digits]));
            return;
        }

        $exists = Customer::query()
            ->whereRaw('TRIM(primary_address) = ?', [$address])
            ->exists();

        if ($exists) {
            // ✅ Si existe la dirección, a ListNotes
            redirect()->to(NoteResource::getUrl('index'));
            return;
        }

        // ❌ Si no existe, a crear nota (pasamos phone y address opcionalmente)
        redirect()->to(NoteResource::getUrl('create', [
            'phone' => $digits ?: null,
            'primary_address' => $address,
        ]));
    }

    public function render()
    {
        return view('livewire.teleoperator.buscar-cliente');
    }
}
