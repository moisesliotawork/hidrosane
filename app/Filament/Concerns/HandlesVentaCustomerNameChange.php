<?php

namespace App\Filament\Concerns;

use App\Models\Venta;
use App\Services\VentaReassignCustomerService;
use Filament\Actions\Action;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Flujo guiado «Cambio/nombre» en EditVenta (Admin/Gerente):
 * nombre fijo en el formulario; reasignar contrato a cliente existente o nuevo.
 */
trait HandlesVentaCustomerNameChange
{
    /** @var list<array{id:int,label:string,name?:string,nro_cliente?:string,dni?:string,phone?:string,address?:string,contratos?:int}> */
    public array $cambioNombreMatches = [];

    public bool $cambioNombreSearchDone = false;

    public string $cambioNombreOpcion = 'buscar';

    public ?string $cambioNombreSelectedId = null;

    public bool $cambioNombreGoNew = false;

    protected function cambioNombreAction(): Action
    {
        return Action::make('cambioNombre')
            ->label('Cambio/nombre')
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->modalHeading('Cambio de cliente de este contrato')
            ->modalDescription('El nombre del formulario está fijo. Aquí solo reasignas ESTE contrato a otro cliente (existente o nuevo).')
            ->modalWidth('2xl')
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (StaticAction $action): StaticAction => $action
                ->label('Cancelar')
                ->color('danger')
                ->extraAttributes([
                    'style' => 'color:#fff !important;margin-right:1.25rem;',
                ]))
            ->modalSubmitAction(fn (StaticAction $action): StaticAction => $action
                ->label('Continuar')
                ->visible(fn (): bool => $this->shouldShowCambioNombreContinuar())
                ->disabled(fn (): bool => $this->isCambioNombreContinuarDisabled())
                ->color(fn (): string => $this->isCambioNombreContinuarDisabled() ? 'gray' : 'warning')
                ->extraAttributes([
                    'style' => 'margin-left:1.25rem;',
                ]))
            ->fillForm(function (array $arguments): array {
                $this->cambioNombreMatches = [];
                $this->cambioNombreSearchDone = false;
                $this->cambioNombreSelectedId = null;
                $this->cambioNombreGoNew = false;
                $opcion = ($arguments['opcion'] ?? 'buscar') === 'nuevo' ? 'nuevo' : 'buscar';
                $this->cambioNombreOpcion = $opcion;

                return [
                    'opcion' => $opcion,
                    'search_name' => (string) ($arguments['search_name'] ?? ''),
                    'search_dni' => (string) ($arguments['search_dni'] ?? ''),
                    'selected_customer_id' => null,
                    'new_first_names' => '',
                    'new_last_names' => '',
                    'new_dni' => '',
                    'go_new_from_empty' => false,
                ];
            })
            ->form(fn (): array => $this->cambioNombreFormSchema())
            ->action(function (array $data): void {
                $this->handleCambioNombreSubmit($data);
            })
            ->after(function (): void {
                $this->cambioNombreMatches = [];
                $this->cambioNombreSearchDone = false;
                $this->cambioNombreOpcion = 'buscar';
                $this->cambioNombreSelectedId = null;
                $this->cambioNombreGoNew = false;
            });
    }

    protected function shouldShowCambioNombreContinuar(): bool
    {
        // En «buscar»: solo tras pulsar Buscar en Ohana. En «nuevo»: siempre.
        return $this->cambioNombreOpcion === 'nuevo' || $this->cambioNombreSearchDone;
    }

    protected function isCambioNombreContinuarDisabled(): bool
    {
        if ($this->cambioNombreOpcion === 'nuevo') {
            return false;
        }

        if (! $this->cambioNombreSearchDone) {
            return true;
        }

        // Con resultados: hay que seleccionar al menos 1 cliente.
        if ($this->cambioNombreMatches !== []) {
            return blank($this->cambioNombreSelectedId);
        }

        // Sin resultados: solo si activa «Asignar a un Nuevo Cliente».
        return ! $this->cambioNombreGoNew;
    }

    protected function confirmarClienteNuevoAction(): Action
    {
        return Action::make('confirmarClienteNuevo')
            ->modalHeading('¿Seguro es un cliente nuevo?')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('SI, estoy segura, ES NUEVO')
            ->modalCancelActionLabel('Cancelar')
            ->extraModalFooterActions(fn (Action $action): array => [
                Action::make('mejorBuscar')
                    ->label('No, mejor voy a buscar primero')
                    ->color('gray')
                    ->action(function (): void {
                        $this->mountAction('cambioNombre');
                    }),
            ])
            ->form([
                Forms\Components\Placeholder::make('aviso')
                    ->label('')
                    ->content(new HtmlString(
                        '<div style="padding:14px 16px;border:2px solid #f59e0b;border-radius:10px;background:#fffbeb;">'
                        .'<div style="font-weight:800;color:#92400e;font-size:15px;">¿Seguro es un cliente nuevo?</div>'
                        .'<div style="margin-top:6px;color:#78350f;font-size:14px;line-height:1.45;">'
                        .'Si ya está en el sistema crearás un <strong>cliente duplicado</strong>. '
                        .'Solo continúa si estás segura de que no existe.</div>'
                        .'</div>'
                    )),
                Forms\Components\Hidden::make('first_names')->required(),
                Forms\Components\Hidden::make('last_names')->required(),
                Forms\Components\Hidden::make('dni')->required(),
                Forms\Components\Placeholder::make('resumen')
                    ->label('Datos del nuevo cliente')
                    ->content(fn (Get $get): string => trim(
                        ($get('first_names') ?? '').' '.($get('last_names') ?? '')
                        .' · DNI '.($get('dni') ?? '')
                    )),
            ])
            ->action(function (array $data): void {
                /** @var Venta $venta */
                $venta = $this->record;
                $service = app(VentaReassignCustomerService::class);

                try {
                    $customer = $service->createAndAssign(
                        $venta,
                        (string) $data['first_names'],
                        (string) $data['last_names'],
                        (string) $data['dni'],
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo crear el cliente')
                        ->body(collect($e->errors())->flatten()->first() ?: 'Revisa los datos.')
                        ->persistent()
                        ->send();

                    throw $e;
                }

                $this->refreshFormAfterCustomerReassign($customer->id);

                Notification::make()
                    ->success()
                    ->title('Cliente nuevo asignado')
                    ->body("Contrato reasignado al cliente #{$customer->id}.")
                    ->send();
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function cambioNombreFormSchema(): array
    {
        return [
            Forms\Components\Radio::make('opcion')
                ->label('¿Qué quieres hacer?')
                ->options([
                    'buscar' => '1. Buscar si es cliente (recomendado)',
                    'nuevo' => '2. Es cliente nuevo',
                ])
                ->descriptions([
                    'buscar' => 'Busca por nombre y/o DNI y asigna este contrato a un cliente existente.',
                    'nuevo' => 'Crea una ficha nueva solo para este contrato (con confirmación anti-duplicado).',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $this->cambioNombreOpcion = $state === 'nuevo' ? 'nuevo' : 'buscar';
                    $this->cambioNombreMatches = [];
                    $this->cambioNombreSearchDone = false;
                    $this->cambioNombreSelectedId = null;
                    $this->cambioNombreGoNew = false;
                    $set('selected_customer_id', null);
                    $set('go_new_from_empty', false);
                }),

            Forms\Components\Group::make([
                Forms\Components\Placeholder::make('search_hint')
                    ->label('')
                    ->content(new HtmlString(
                        '<p class="text-sm text-gray-500 dark:text-gray-400">'
                        .'Puedes buscar <strong>solo por nombre y apellidos</strong>, <strong>solo por DNI</strong>, o por ambos.'
                        .'</p>'
                    )),
                Forms\Components\TextInput::make('search_name')
                    ->label('Nombre de cliente')
                    ->placeholder('Nombre y apellidos (opcional si pones DNI)')
                    ->extraInputAttributes(['style' => 'font-weight:700;']),
                Forms\Components\TextInput::make('search_dni')
                    ->label('DNI')
                    ->placeholder('DNI (opcional si pones nombre)')
                    ->extraInputAttributes(['style' => 'font-weight:700;']),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('buscarEnOhana')
                        ->label('Buscar en Ohana')
                        ->color('primary')
                        ->action(function (Get $get, Set $set): void {
                            $name = trim((string) ($get('search_name') ?? ''));
                            $dni = trim((string) ($get('search_dni') ?? ''));

                            if ($name === '' && $dni === '') {
                                Notification::make()
                                    ->warning()
                                    ->title('Indica un dato para buscar')
                                    ->body('Escribe nombre y apellidos, o un DNI (uno de los dos basta).')
                                    ->send();

                                return;
                            }

                            $matches = app(VentaReassignCustomerService::class)
                                ->search($name, $dni);

                            $this->cambioNombreMatches = $matches
                                ->map(fn (array $row): array => [
                                    'id' => $row['id'],
                                    'label' => $row['label'],
                                    'name' => $row['name'] ?? '',
                                    'nro_cliente' => $row['nro_cliente'] ?? '—',
                                    'dni' => $row['dni'] ?? '',
                                    'phone' => $row['phone'] ?? '',
                                    'address' => $row['address'] ?? '—',
                                    'contratos' => $row['contratos'] ?? 0,
                                ])
                                ->all();
                            $this->cambioNombreSearchDone = true;
                            $this->cambioNombreSelectedId = null;
                            $this->cambioNombreGoNew = false;
                            $set('selected_customer_id', null);
                            $set('go_new_from_empty', false);

                            if ($matches->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('No aparece ninguno')
                                    ->body('Puedes cancelar esta ventana o activar «Asignar a un nuevo cliente».')
                                    ->send();
                            }
                        }),
                ]),
                Forms\Components\Radio::make('selected_customer_id')
                    ->label('Selecciona el cliente al que asignar esta venta')
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->cambioNombreSelectedId = filled($state) ? (string) $state : null;
                    })
                    ->options(fn (): array => collect($this->cambioNombreMatches)
                        ->mapWithKeys(function (array $row): array {
                            $id = (string) (int) $row['id'];
                            $nro = e((string) ($row['nro_cliente'] ?? '—'));
                            $name = e((string) ($row['name'] ?? ''));
                            $dni = e((string) ($row['dni'] ?? '—'));

                            return [
                                $id => new HtmlString(
                                    '<span style="display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;flex-wrap:wrap;">'
                                    .'<span>'
                                    .'Nº Cliente <strong>'.$nro.'</strong>'
                                    .' · <strong style="font-weight:700;">'.$name.'</strong>'
                                    .' · DNI '.$dni
                                    .'</span>'
                                    .'<span style="display:inline-flex;align-items:center;gap:6px;margin-left:auto;white-space:nowrap;">'
                                    .'ID/Sist '
                                    .'<span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;background:#6b7280;color:#fff;font-size:12px;font-weight:700;">'
                                    .$id
                                    .'</span>'
                                    .'</span>'
                                    .'</span>'
                                ),
                            ];
                        })
                        ->all())
                    ->descriptions(fn (): array => collect($this->cambioNombreMatches)
                        ->mapWithKeys(function (array $row): array {
                            $id = (string) (int) $row['id'];
                            $phone = e((string) ($row['phone'] ?? '—'));
                            $contratos = (int) ($row['contratos'] ?? 0);
                            $address = e((string) ($row['address'] ?? '—'));

                            return [
                                $id => new HtmlString(
                                    'Tel '.$phone
                                    .' · '.$contratos.' contrato(s)'
                                    .' · <strong style="font-weight:700;">'.$address.'</strong>'
                                ),
                            ];
                        })
                        ->all())
                    ->visible(fn (): bool => $this->cambioNombreMatches !== [])
                    ->required(fn (Get $get): bool => $get('opcion') === 'buscar'
                        && $this->cambioNombreMatches !== []
                        && ! $get('go_new_from_empty')),
                Forms\Components\Placeholder::make('sin_resultados')
                    ->label('')
                    ->visible(fn (): bool => $this->cambioNombreSearchDone && $this->cambioNombreMatches === [])
                    ->content(new HtmlString(
                        <<<'HTML'
                        <style>
                          @keyframes oh-cambio-nombre-flecha {
                            0%, 100% { opacity: 1; transform: translateY(0); }
                            50% { opacity: 0.25; transform: translateY(6px); }
                          }
                          .oh-cambio-nombre-sin-res {
                            padding: 14px 16px;
                            border: 1px solid #f59e0b;
                            border-radius: 8px;
                            background: #fffbeb;
                            color: #92400e;
                            text-align: center;
                          }
                          .oh-cambio-nombre-sin-res .oh-flecha {
                            display: block;
                            margin: 10px auto 0;
                            color: #16a34a;
                            font-size: 28px;
                            line-height: 1;
                            font-weight: 800;
                            animation: oh-cambio-nombre-flecha 1s ease-in-out infinite;
                          }
                          .oh-cambio-nombre-toggle-wrap {
                            display: flex !important;
                            justify-content: center !important;
                            width: 100% !important;
                            text-align: center !important;
                          }
                          .oh-cambio-nombre-toggle-wrap .fi-fo-field-wrp,
                          .oh-cambio-nombre-toggle-wrap .fi-fo-toggle {
                            justify-content: center !important;
                            text-align: center !important;
                            margin-left: auto !important;
                            margin-right: auto !important;
                          }
                        </style>
                        <div class="oh-cambio-nombre-sin-res">
                          <div>Puedes cancelar esta ventana o activar &quot;Asignar a un nuevo cliente&quot;</div>
                          <span class="oh-flecha" aria-hidden="true">↓</span>
                        </div>
                        HTML
                    )),
                Forms\Components\Group::make([
                    Forms\Components\Toggle::make('go_new_from_empty')
                        ->label('Asignar a un Nuevo Cliente')
                        ->inline(false)
                        ->live()
                        ->afterStateUpdated(function (?bool $state): void {
                            $this->cambioNombreGoNew = (bool) $state;
                        }),
                ])
                    ->visible(fn (): bool => $this->cambioNombreSearchDone && $this->cambioNombreMatches === [])
                    ->extraAttributes([
                        'class' => 'oh-cambio-nombre-toggle-wrap',
                        'style' => 'display:flex;justify-content:center;width:100%;text-align:center;',
                    ]),
            ])
                ->visible(fn (Get $get): bool => $get('opcion') === 'buscar')
                ->columns(1),

            Forms\Components\Group::make([
                Forms\Components\TextInput::make('new_first_names')
                    ->label('Nombres')
                    ->extraInputAttributes(['style' => 'font-weight:700;'])
                    ->required(fn (Get $get): bool => $get('opcion') === 'nuevo' || (bool) $get('go_new_from_empty')),
                Forms\Components\TextInput::make('new_last_names')
                    ->label('Apellidos')
                    ->extraInputAttributes(['style' => 'font-weight:700;'])
                    ->required(fn (Get $get): bool => $get('opcion') === 'nuevo' || (bool) $get('go_new_from_empty')),
                Forms\Components\TextInput::make('new_dni')
                    ->label('DNI')
                    ->extraInputAttributes(['style' => 'font-weight:700;'])
                    ->required(fn (Get $get): bool => $get('opcion') === 'nuevo' || (bool) $get('go_new_from_empty')),
            ])
                ->visible(fn (Get $get): bool => $get('opcion') === 'nuevo' || (bool) $get('go_new_from_empty'))
                ->columns(1),
        ];
    }

    protected function handleCambioNombreSubmit(array $data): void
    {
        /** @var Venta $venta */
        $venta = $this->record;
        $service = app(VentaReassignCustomerService::class);

        $opcion = $data['opcion'] ?? 'buscar';
        $goNew = (bool) ($data['go_new_from_empty'] ?? false);

        if ($opcion === 'buscar' && ! $goNew) {
            $selectedId = (int) ($data['selected_customer_id'] ?? 0);
            if ($selectedId <= 0) {
                if (! $this->cambioNombreSearchDone) {
                    Notification::make()
                        ->warning()
                        ->title('Busca primero')
                        ->body('Pulsa «Buscar en Ohana» y selecciona un cliente, o elige «Es cliente nuevo».')
                        ->send();

                    return;
                }

                Notification::make()
                    ->warning()
                    ->title('Selecciona un cliente')
                    ->body('Elige un resultado o marca «Asignar a un Nuevo Cliente».')
                    ->send();

                return;
            }

            $customer = $service->assignToExisting($venta, $selectedId);
            $this->refreshFormAfterCustomerReassign($customer->id);

            Notification::make()
                ->success()
                ->title('Contrato reasignado')
                ->body("Asignado al cliente #{$customer->id} · ".trim($customer->first_names.' '.$customer->last_names))
                ->send();

            return;
        }

        // Nuevo (opción 2 o desde sin resultados)
        $first = trim((string) ($data['new_first_names'] ?? ''));
        $last = trim((string) ($data['new_last_names'] ?? ''));
        $dni = trim((string) ($data['new_dni'] ?? ''));

        if ($first === '' || $last === '' || $dni === '') {
            Notification::make()
                ->danger()
                ->title('Datos incompletos')
                ->body('Indica nombres, apellidos y DNI del cliente nuevo.')
                ->send();

            return;
        }

        if ($existing = $service->findByDni($dni)) {
            Notification::make()
                ->warning()
                ->title('Ese DNI ya existe')
                ->body("Cliente #{$existing->id} · ".trim($existing->first_names.' '.$existing->last_names).'. Usa la búsqueda.')
                ->persistent()
                ->send();

            $this->mountAction('cambioNombre', [
                'opcion' => 'buscar',
                'search_dni' => VentaReassignCustomerService::normalizeDni($dni),
                'search_name' => trim($first.' '.$last),
            ]);

            return;
        }

        $this->mountAction('confirmarClienteNuevo', [
            'first_names' => $first,
            'last_names' => $last,
            'dni' => VentaReassignCustomerService::normalizeDni($dni),
        ]);
    }

    protected function refreshFormAfterCustomerReassign(int $customerId): void
    {
        $this->record->refresh()->load('customer');
        $this->fillForm();
    }
}
