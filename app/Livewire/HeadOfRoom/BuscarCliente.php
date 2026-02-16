<?php

namespace App\Livewire\HeadOfRoom;

use Livewire\Component;

use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;

use App\Models\Customer;
use App\Models\Note;
use App\Filament\Teleoperator\Resources\NoteResource;

use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;

use App\Enums\EstadoTerminal;
// use Illuminate\Support\Facades\DB; // ⛔ comentado: no se usa sin búsqueda por dirección

class BuscarCliente extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];
    public bool $phoneNotFound = false;

    // ⛔ comentado: no se usa sin búsqueda por dirección
    // public array $addressMatches = [];
    // public ?string $addressMatchesTitle = null;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,

            // ⛔ comentado: campos de dirección (no se usan por ahora)
            // 'primary_address' => null,
            // 'secondary_address' => null,
            // 'nro_piso' => null,
            // 'postal_code' => null,
            // 'ayuntamiento' => null,
            // 'provincia' => null,
        ]);
    }

    // ✅ Importante: con HasActions NO uses form(Form $form)
    protected function getFormStatePath(): string
    {
        return 'data';
    }

    protected function getFormSchema(): array
    {
        return [
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

                    /**
                     * ⛔ Comentado: UI de dirección + botón buscar por dirección
                     */
                    /*
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('primary_address')
                                ->label('Dirección (principal)')
                                ->placeholder('Calle')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('secondary_address')
                                ->label('Dirección (secundaria)')
                                ->placeholder('Bloque / Escalera / Referencia')
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
                                    'Lugo' => 'Lugo',
                                ])
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
                    */
                ])
                ->columns(1),
        ];
    }

    // ⛔ comentado: ya no se usa si no hay dirección
    /*
    protected function continueCreateUrl(): string
    {
        $state = $this->form->getState();

        return NoteResource::getUrl('create', array_filter([
            'phone' => preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? '')) ?: null,

            'primary_address' => $state['primary_address'] ?? null,
            'secondary_address' => $state['secondary_address'] ?? null,
            'nro_piso' => $state['nro_piso'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            'provincia' => $state['provincia'] ?? null,
            'ciudad' => $state['ayuntamiento'] ?? null,
        ]));
    }
    */

    /**
     * Regla Jefe de Sala:
     * - muestra resumen de notas
     * - permite continuar SOLO si NO tiene OF ni ST
     */
    protected function notifyCustomerEncontrado(Customer $customer): void
    {
        $q = Note::query()->where('customer_id', $customer->id);

        $total = (clone $q)->count();

        $nulas = (clone $q)->where('estado_terminal', EstadoTerminal::NUL->value)->count();
        $conf = (clone $q)->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)->count();
        $vta = (clone $q)->where('estado_terminal', EstadoTerminal::VENTA->value)->count();
        $of = (clone $q)->where('estado_terminal', EstadoTerminal::SALA->value)->count();
        $st = (clone $q)->where('estado_terminal', EstadoTerminal::SIN_ESTADO->value)->count();

        $body = "Notas asoci: {$total}, Nulas: {$nulas}, Conf: {$conf}, Vta: {$vta}, Of: {$of}, ST: {$st}";

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

        // Si no cumple formato, no hagas nada (o podrías notificar)
        if (strlen($digits) !== 9) {
            $this->phoneNotFound = false;
            return;
        }

        $customer = Customer::query()
            ->where('phone', $digits)
            ->orWhere('secondary_phone', $digits)
            ->first();

        // ✅ Si existe, aplica tu regla actual (notificación jefe de sala)
        if ($customer) {
            $this->phoneNotFound = false;
            $this->notifyCustomerEncontrado($customer);
            return;
        }

        // ✅ Si NO existe, saltar a crear nota con el teléfono ya llenado
        $this->phoneNotFound = true;

        redirect()->to(
            NoteResource::getUrl('create', [
                'phone' => $digits,
            ])
        );
    }


    /**
     * ⛔ Comentado: Action y método de dirección (no se usan por ahora)
     */
    /*
    public function addressMatchesAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('addressMatches')
            ->label('Coincidencias')
            ->modalHeading($this->addressMatchesTitle ?: 'Coincidencias por dirección')
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(view('livewire.head-of-room.modals.address-matches', [
                'rows' => $this->addressMatches,
                'continue_url' => $this->continueCreateUrl(),
            ]));
    }

    public function buscarDireccion(): void
    {
        // deshabilitado por ahora
    }
    */

    public function render()
    {
        return view('livewire.head-of-room.buscar-cliente');
    }
}
