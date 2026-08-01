<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Models\User;
use App\Services\ContractRecovery\ContractFromImageRecovery;
use App\Services\ContractRecovery\ContractImageExtractor;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Recuperación de contratos extraviados desde imagen (aislado del flujo comercial).
 */
class RecuperarContratoImagen extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Recuperar por imagen';

    protected static ?string $title = 'Recuperar contrato por imagen';

    protected static ?string $slug = 'recuperar-contrato-imagen';

    protected static string $view = 'filament.superAdmin.pages.recuperar-contrato-imagen';

    protected static ?int $navigationSort = 97;

    /** @var array<string, mixed>|null */
    public ?array $uploadData = [];

    /** @var array<string, mixed>|null */
    public ?array $reviewData = [];

    public string $step = 'upload'; // upload | review

    /** @var list<array{type: string, path: string, label?: string|null}> */
    public array $pendingDocuments = [];

    public ?int $reviewComercialId = null;

    public bool $updateCustomerIban = false;

    public function mount(): void
    {
        $this->uploadForm->fill();
        $this->reviewForm->fill($this->emptyReview());
    }

    protected function getForms(): array
    {
        return [
            'uploadForm',
            'reviewForm',
        ];
    }

    public function uploadForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Documentos (máximo 3)')
                    ->description('Puedes subir contrato de app, albarán y/u otro documento. Al menos uno es obligatorio. No modifica el flujo comercial.')
                    ->schema([
                        Forms\Components\FileUpload::make('doc_app')
                            ->label('1. Contrato app (foto/PDF)')
                            ->disk('local')
                            ->directory('contract-recovery/tmp')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                        Forms\Components\FileUpload::make('doc_albaran')
                            ->label('2. Albarán manuscrito')
                            ->disk('local')
                            ->directory('contract-recovery/tmp')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                        Forms\Components\FileUpload::make('doc_other')
                            ->label('3. Otro documento')
                            ->disk('local')
                            ->directory('contract-recovery/tmp')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                        Forms\Components\TextInput::make('doc_other_label')
                            ->label('Etiqueta del otro documento')
                            ->placeholder('Ej. página 2, DNI, anexo')
                            ->maxLength(80),
                    ])
                    ->columns(2),
            ])
            ->statePath('uploadData');
    }

    public function reviewForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos recuperados (revisar antes de Aceptar)')
                    ->description('Aceptar solo guarda en la tabla de pendientes. El contrato en la app se crea después con «Agregar Contrato».')
                    ->schema([
                        Forms\Components\TextInput::make('dni')->label('DNI')->required(),
                        Forms\Components\TextInput::make('nro_contr_adm')->label('# Contrato admin')->required(),
                        Forms\Components\TextInput::make('cliente_nombre')->label('Nombre (extraído)'),
                        Forms\Components\TextInput::make('nro_albaran')->label('Nº albarán'),
                        Forms\Components\DatePicker::make('fecha_venta')->label('Fecha venta / promo')->native(false),
                        Forms\Components\DatePicker::make('fecha_entrega')->label('Fecha entrega')->native(false),
                        Forms\Components\TextInput::make('horario_entrega')->label('Horario entrega'),
                        Forms\Components\TextInput::make('comercial_codes')->label('Códigos comercial (del documento)'),
                        Forms\Components\Select::make('comercial_id')
                            ->label('Comercial (obligatorio para Agregar)')
                            ->options(fn () => User::query()
                                ->orderBy('empleado_id')
                                ->limit(500)
                                ->get()
                                ->mapWithKeys(fn (User $u) => [
                                    $u->id => trim(($u->empleado_id ? $u->empleado_id.' - ' : '').$u->name.' '.($u->last_name ?? '')),
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('importe_total')->label('Importe total')->numeric(),
                        Forms\Components\TextInput::make('entrada')->label('Entrada')->numeric(),
                        Forms\Components\TextInput::make('cuota_mensual')->label('Cuota mensual')->numeric(),
                        Forms\Components\TextInput::make('num_cuotas')->label('Nº cuotas')->numeric()->integer(),
                        Forms\Components\TextInput::make('iban')->label('IBAN'),
                        Forms\Components\Textarea::make('productos_texto')->label('Productos')->rows(3)->columnSpanFull(),
                        Forms\Components\Textarea::make('direccion')->label('Dirección')->rows(2),
                        Forms\Components\TextInput::make('telefonos')->label('Teléfonos'),
                        Forms\Components\Textarea::make('observaciones')->label('Observaciones')->rows(2)->columnSpanFull(),
                        Forms\Components\Placeholder::make('customer_match')
                            ->label('Cliente en app')
                            ->content(fn (): string => $this->customerMatchLabel())
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('conflicts')
                            ->label('Conflictos de merge')
                            ->content(fn (): string => filled($this->reviewData['_conflicts'] ?? null)
                                ? implode(', ', (array) $this->reviewData['_conflicts'])
                                : 'Ninguno')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('reviewData');
    }

    public function analyzeDocuments(): void
    {
        $state = $this->uploadForm->getState();
        $docs = [];

        foreach ([
            'doc_app' => ContractImageExtractor::TYPE_APP,
            'doc_albaran' => ContractImageExtractor::TYPE_ALBARAN,
            'doc_other' => ContractImageExtractor::TYPE_OTHER,
        ] as $field => $type) {
            $path = $state[$field] ?? null;
            if (is_array($path)) {
                $path = $path[0] ?? null;
            }
            if (filled($path)) {
                $docs[] = [
                    'type' => $type,
                    'path' => (string) $path,
                    'label' => $type === ContractImageExtractor::TYPE_OTHER
                        ? ($state['doc_other_label'] ?? null)
                        : null,
                ];
            }
        }

        if ($docs === []) {
            Notification::make()->title('Sube al menos un documento')->warning()->send();

            return;
        }

        $this->pendingDocuments = $docs;

        $result = app(ContractImageExtractor::class)->extractAndMerge($docs);
        $merged = $result['merged'];

        if ($result['errors'] !== []) {
            Notification::make()
                ->title('Extracción parcial')
                ->body(implode(' | ', $result['errors']).' Puedes completar a mano.')
                ->warning()
                ->send();
        } else {
            Notification::make()->title('Datos extraídos')->success()->send();
        }

        $comercialId = filled($merged['comercial_codes'] ?? null)
            ? $this->guessComercialId((string) $merged['comercial_codes'])
            : null;

        $this->reviewForm->fill(array_merge($this->emptyReview(), $merged, [
            'comercial_id' => $comercialId,
            'fecha_venta' => $this->normalizeDateForPicker($merged['fecha_venta'] ?? null),
            'fecha_entrega' => $this->normalizeDateForPicker($merged['fecha_entrega'] ?? null),
        ]));

        $this->step = 'review';
    }

    public function acceptRecovered(): void
    {
        if (! Schema::hasTable('contrato_recovery_items')) {
            Notification::make()->title('Ejecuta migraciones')->danger()->send();

            return;
        }

        $data = $this->reviewForm->getState();
        $dni = mb_strtoupper(trim((string) ($data['dni'] ?? '')));
        $nro = trim((string) ($data['nro_contr_adm'] ?? ''));

        if ($dni === '' || $nro === '') {
            Notification::make()->title('DNI y nº contrato admin son obligatorios')->warning()->send();

            return;
        }

        if ($this->pendingDocuments === []) {
            Notification::make()->title('No hay documentos en esta revisión')->warning()->send();

            return;
        }

        // Mover tmp → carpeta estable del item
        $stableDocs = [];
        foreach ($this->pendingDocuments as $doc) {
            $from = $doc['path'];
            $ext = pathinfo($from, PATHINFO_EXTENSION) ?: 'bin';
            $to = 'contract-recovery/accepted/'.now()->format('YmdHis').'_'.uniqid().'.'.$ext;
            if (Storage::disk('local')->exists($from)) {
                Storage::disk('local')->copy($from, $to);
                $stableDocs[] = [
                    'type' => $doc['type'],
                    'path' => $to,
                    'label' => $doc['label'] ?? null,
                ];
            }
        }

        $customer = Customer::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
            ->orderBy('id')
            ->first();

        ContratoRecoveryItem::query()->create([
            'status' => ContratoRecoveryItem::STATUS_PENDING_ADD,
            'documents' => $stableDocs,
            'extracted_json' => $data,
            'reviewed_json' => $data,
            'dni' => $dni,
            'nro_contr_adm' => $nro,
            'cliente_nombre' => $data['cliente_nombre'] ?? null,
            'customer_id' => $customer?->id,
            'comercial_id' => $data['comercial_id'] ?? null,
            'created_by_user_id' => auth()->id(),
        ]);

        Notification::make()
            ->title('Aceptado')
            ->body('Quedó en la tabla pendiente. Usa «Agregar Contrato» para crear la venta.')
            ->success()
            ->send();

        $this->resetReview();
        $this->resetTable();
    }

    public function cancelReview(): void
    {
        $this->resetReview();
    }

    protected function resetReview(): void
    {
        $this->step = 'upload';
        $this->pendingDocuments = [];
        $this->uploadForm->fill();
        $this->reviewForm->fill($this->emptyReview());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Schema::hasTable('contrato_recovery_items')
                    ? ContratoRecoveryItem::query()->latest('id')
                    : ContratoRecoveryItem::query()->whereRaw('1=0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('dni')->label('DNI')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('nro_contr_adm')->label('# Contrato_admin')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('cliente_nombre')->label('Nombre')->wrap()->limit(40),
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('Cliente app')
                    ->formatStateUsing(fn ($state, ContratoRecoveryItem $record) => $state
                        ? "#{$state}".($record->customer ? ' '.$record->customer->first_names : '')
                        : '— sin match'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state, ContratoRecoveryItem $r) => $r->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        ContratoRecoveryItem::STATUS_PENDING_ADD => 'warning',
                        ContratoRecoveryItem::STATUS_ADDED => 'success',
                        ContratoRecoveryItem::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('documents')
                    ->label('Docs')
                    ->formatStateUsing(fn ($state) => is_array($state) ? (string) count($state) : '0'),
                Tables\Columns\TextColumn::make('venta_id')->label('Venta')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Aceptado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('agregarContrato')
                    ->label('Agregar Contrato')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Agregar contrato a la app')
                    ->modalDescription('Se creará (o restaurará) la venta enlazada al cliente con ese DNI. No modifica otros procedimientos comerciales.')
                    ->visible(fn (ContratoRecoveryItem $record) => in_array($record->status, [
                        ContratoRecoveryItem::STATUS_PENDING_ADD,
                        ContratoRecoveryItem::STATUS_FAILED,
                    ], true))
                    ->form([
                        Forms\Components\Toggle::make('update_iban')
                            ->label('Actualizar IBAN del cliente con el extraído')
                            ->default(false),
                    ])
                    ->action(function (ContratoRecoveryItem $record, array $data): void {
                        $result = app(ContractFromImageRecovery::class)->addContract(
                            $record,
                            (bool) ($data['update_iban'] ?? false),
                        );

                        if ($result['ok']) {
                            Notification::make()
                                ->title('Contrato agregado')
                                ->body($result['message'])
                                ->success()
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('ver')
                                        ->label('Ver contrato')
                                        ->url(VentaResource::getUrl('edit', ['record' => $result['venta_id']]))
                                        ->openUrlInNewTab(),
                                ])
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No se pudo agregar')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }

                        $this->resetTable();
                    }),
                Tables\Actions\Action::make('verVenta')
                    ->label('Ver contrato')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ContratoRecoveryItem $record) => $record->venta_id
                        ? VentaResource::getUrl('edit', ['record' => $record->venta_id])
                        : null)
                    ->visible(fn (ContratoRecoveryItem $record) => filled($record->venta_id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyReview(): array
    {
        return app(ContractImageExtractor::class)->emptyPayload() + [
            'comercial_id' => null,
            '_conflicts' => [],
        ];
    }

    protected function customerMatchLabel(): string
    {
        $dni = mb_strtoupper(trim((string) ($this->reviewData['dni'] ?? '')));
        if ($dni === '') {
            return 'Indica un DNI para buscar cliente.';
        }

        $c = Customer::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
            ->first();

        if (! $c) {
            return "No hay cliente activo con DNI {$dni}. No se creará cliente nuevo.";
        }

        return "#{$c->id} — {$c->first_names} {$c->last_names} (DNI {$c->dni})";
    }

    protected function guessComercialId(string $codes): ?int
    {
        $parts = preg_split('/[,\s\-]+/', $codes) ?: [];
        foreach ($parts as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            $padded = str_pad(ltrim($code, '0') ?: '0', 3, '0', STR_PAD_LEFT);
            $user = User::query()
                ->where('empleado_id', $code)
                ->orWhere('empleado_id', $padded)
                ->first();
            if ($user) {
                return (int) $user->id;
            }
        }

        return null;
    }

    protected function normalizeDateForPicker(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
