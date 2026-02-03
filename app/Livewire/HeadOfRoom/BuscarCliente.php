<?php

namespace App\Livewire\HeadOfRoom;

use Livewire\Component;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

use App\Models\Customer;
use App\Models\Note;
use App\Filament\Teleoperator\Resources\NoteResource;

use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use App\Enums\EstadoTerminal;

class BuscarCliente extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public bool $phoneNotFound = false;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,

            'primary_address' => null,
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

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('primary_address')
                                ->label('Dirección (principal)')
                                ->placeholder('Calle')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nro_piso')
                                ->label('No. y Piso')
                                ->placeholder('Nº 1')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\TextInput::make('postal_code')
                                ->label('Código Postal')
                                ->placeholder('15551')
                                 ->minLength(5)       
                                ->maxLength(5)
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\TextInput::make('ayuntamiento')
                                ->label('Ayuntamiento/Localidad')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                           Forms\Components\Select::make('provincia')
                          ->label('Provincia')
                        ->required()
                        ->options([
                        'Pontevedra' => 'Pontevedra',
                        'A Coruña' => 'A Coruña',
                        'Orense' => 'Orense', 
                        'Lugo' => 'Lugo'])
                        ->placeholder('Pontevedra')
                          
                                ->visible(fn() => $this->phoneNotFound),
                        ])
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

    /**
     * 🔥 Notificación principal cuando el customer existe:
     * - Siempre muestra resumen de notas
     * - Si NO hay notas con oficina ni estado_terminal => permite continuar a crear nota
     */

    protected function notifyCustomerEncontrado(Customer $customer): void
    {
        $q = Note::query()->where('customer_id', $customer->id);

        $total = (clone $q)->count();

        $nulas = (clone $q)->where('estado_terminal', EstadoTerminal::NUL->value)->count();
        $conf = (clone $q)->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)->count();
        $vta = (clone $q)->where('estado_terminal', EstadoTerminal::VENTA->value)->count();
        $of = (clone $q)->where('estado_terminal', EstadoTerminal::SALA->value)->count();
        $st = (clone $q)->where('estado_terminal', EstadoTerminal::SIN_ESTADO->value)->count(); // '' (string vacío)

        $body = "Notas asoci: {$total}, Nulas: {$nulas}, Conf: {$conf}, Vta: {$vta}, Of: {$of}, ST: {$st}";

        // ✅ regla: permitir continuar SOLO si NO tiene ninguna nota en OF ni ST
        // (es decir, of == 0 y st == 0)
        $canContinue = ($of === 0 && $st === 0);

        $notification = Notification::make()
            ->title('Cliente encontrado en sistema')
            ->body($body)
            ->persistent()
            ->warning();

        if ($canContinue) {
            $notification->actions([
                NotificationAction::make('continuar')
                    ->label('Continuar y crear nota')
                    ->button()
                    ->color('success')
                    ->url(NoteResource::getUrl('create', [
                        'customer_id' => $customer->id,
                    ])),
            ]);
        } else {
            $notification->actions([
                NotificationAction::make('ir_notas')
                    ->label('Ver notas')
                    ->button()
                    ->color('danger')
                    ->url(NoteResource::getUrl('index')),
            ]);
        }

        $notification->send();
    }

    public function buscarTelefono(): void
    {
        $state = $this->form->getState();

        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        if (strlen($digits) !== 9) {
            $this->phoneNotFound = false;
            return;
        }

        $customer = Customer::query()
            ->where('phone', $digits)
            ->orWhere('secondary_phone', $digits)
            ->first();

        if ($customer) {
            $this->phoneNotFound = false;
            $this->notifyCustomerEncontrado($customer);
            return;
        }

        $this->phoneNotFound = true;
    }


    public function buscarDireccion(): void
    {
        $state = $this->form->getState();

        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        $norm = function (?string $v): string {
            $v = trim((string) $v);
            $v = preg_replace('/\s+/u', ' ', $v);
            return mb_strtolower($v, 'UTF-8');
        };

        $primaryAddress = $norm($state['primary_address'] ?? null);
        $nroPiso = $norm($state['nro_piso'] ?? null);
        $postalCode = $norm($state['postal_code'] ?? null);
        $ayto = $norm($state['ayuntamiento'] ?? null);
        $provincia = $norm($state['provincia'] ?? null);

        if ($primaryAddress === '' || $nroPiso === '' || $postalCode === '' || $ayto === '' || $provincia === '') {
            Notification::make()
                ->title('Faltan datos')
                ->body('Completa Dirección, No. y Piso, Código Postal, Ciudad y Provincia.')
                ->warning()
                ->send();
            return;
        }

        // ✅ Traer el customer que coincide por los 5 campos
        $customer = Customer::query()
            ->whereRaw('LOWER(TRIM(primary_address)) = ?', [$primaryAddress])
            ->whereRaw('LOWER(TRIM(nro_piso)) = ?', [$nroPiso])
            ->whereRaw('LOWER(TRIM(postal_code)) = ?', [$postalCode])
            ->whereRaw('LOWER(TRIM(ciudad)) = ?', [$ayto])
            ->whereRaw('LOWER(TRIM(provincia)) = ?', [$provincia])
            ->first();

        if ($customer) {
            $this->notifyCustomerEncontrado($customer);
            return;
        }

        // ✅ No existe customer con esa dirección => crear nota como antes, pasando datos
        redirect()->to(NoteResource::getUrl('create', [
            'phone' => $digits ?: null,
            'primary_address' => $state['primary_address'] ?? null,
            'nro_piso' => $state['nro_piso'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            //'ayuntamiento' => $state['ayuntamiento'] ?? null,
            'provincia' => $state['provincia'] ?? null,
            'ciudad' => $state['ayuntamiento'] ?? null,
        ]));
    }

    public function render()
    {
        return view('livewire.head-of-room.buscar-cliente');
    }
}

//
