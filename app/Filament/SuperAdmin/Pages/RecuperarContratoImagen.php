<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Exports\RecoveredContractsExport;
use App\Enums\VendidoPor;
use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\ContratoRecoveryItem;
use App\Models\Customer;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\User;
use App\Services\ContractRecovery\ContractFromImageRecovery;
use App\Services\ContractRecovery\ContractImageExtractor;
use App\Services\ContractRecovery\ContractVoiceExtractor;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Paso 1: recuperación de contratos extraviados desde imagen/voz.
 * El re-enganche de documentos huérfanos es un paso aparte
 * ({@see ReengancharDocumentosHuerfanos}).
 */
class RecuperarContratoImagen extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = '1. Recuperar contrato';

    protected static ?string $navigationGroup = 'Recuperación';

    protected static ?string $title = 'Paso 1 · Recuperar contrato';

    protected static ?string $slug = 'recuperar-contrato-imagen';

    protected static string $view = 'filament.superAdmin.pages.recuperar-contrato-imagen';

    /** Justo encima de Contratos/MES para que se vea en el menú */
    protected static ?int $navigationSort = 95;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('goToOrphanReattach')
                ->label('Paso 2 · Docs huérfanos')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->url(fn (): string => ReengancharDocumentosHuerfanos::getUrl())
                ->tooltip('Tras recuperar el contrato, re-engancha documentos huérfanos en un paso aparte'),
            Action::make('exportRecoveredExcel')
                ->label('Excel recuperados')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Registro de contratos recuperados (sin inventariar huérfanos)')
                ->action(function (): BinaryFileResponse {
                    $filename = 'contratos-recuperados-'.now()->format('Ymd-His').'.xlsx';

                    return Excel::download(new RecoveredContractsExport(includeOrphanHints: false), $filename);
                }),
        ];
    }

    /** @var array<string, mixed>|null */
    public ?array $uploadData = [];

    /** @var array<string, mixed>|null */
    public ?array $voiceData = [];

    /** @var array<string, mixed>|null */
    public ?array $reviewData = [];

    public string $step = 'upload'; // upload | review

    /** image | voice */
    public string $entrySource = 'image';

    /** @var list<array{type: string, path: string, label?: string|null}> */
    public array $pendingDocuments = [];

    public ?string $lastTranscript = null;

    public ?int $reviewComercialId = null;

    public bool $updateCustomerIban = false;

    public function mount(): void
    {
        $this->uploadForm->fill();
        $this->voiceForm->fill();
        $this->reviewForm->fill($this->emptyReview());
    }

    protected function getForms(): array
    {
        return [
            'uploadForm',
            'voiceForm',
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

    public function voiceForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('DATOS POR VOZ')
                    ->description('Dicta con el micrófono (Mac/navegador), pega texto o sube un audio. Revisa el escrito y luego Procesar dictado (OpenAI).')
                    ->schema([
                        Forms\Components\View::make('filament.superAdmin.pages.partials.voice-dictation')
                            ->viewData(fn (): array => [
                                'initialTranscript' => (string) ($this->voiceData['transcript_manual'] ?? ''),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('audio')
                            ->label('Opcional: subir archivo de audio')
                            ->disk('local')
                            ->directory('contract-recovery/tmp-audio')
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'audio/mpeg',
                                'audio/mp3',
                                'audio/mp4',
                                'audio/x-m4a',
                                'audio/wav',
                                'audio/webm',
                                'audio/ogg',
                                'video/webm',
                            ])
                            ->maxSize(25600)
                            ->helperText('Si dictas con el micrófono no hace falta. Whisper solo se usa si subes audio.'),
                        Forms\Components\Hidden::make('transcript_manual'),
                    ])
                    ->columns(1),
            ])
            ->statePath('voiceData');
    }

    public function reviewForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos recuperados (revisar antes de Aceptar)')
                    ->description('Aceptar solo guarda en la tabla de pendientes. El contrato en la app se crea después con «Agregar Contrato».')
                    ->schema([
                        Forms\Components\TextInput::make('dni')->label('DNI')->required(),
                        Forms\Components\TextInput::make('nro_contr_adm')->label('Cod.Contrato (nº contrato admin)')->required(),
                        Forms\Components\TextInput::make('cliente_nombre')->label('Nombre (extraído)'),
                        Forms\Components\TextInput::make('nro_albaran')->label('Nº albarán'),
                        Forms\Components\DatePicker::make('fecha_venta')->label('Fec.Promo. (fecha contrato admin)')->native(false),
                        Forms\Components\DatePicker::make('fecha_entrega')->label('Fec.Entr. (fecha entrega)')->native(false),
                        Forms\Components\TextInput::make('horario_entrega')->label('Hora Entr.'),
                        Forms\Components\TextInput::make('comercial_codes')->label('Com. (códigos comerciales, ej. 008,004)'),
                        Forms\Components\Select::make('comercial_id')
                            ->label('Comercial principal (obligatorio para Agregar)')
                            ->options(fn () => $this->empleadoOptions())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('repartidor_code')->label('Rep. (id empleado repartidor)'),
                        Forms\Components\Select::make('repartidor_id')
                            ->label('Repartidor')
                            ->options(fn () => $this->empleadoOptions())
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('importe_total')->label('Importe total')->numeric(),
                        Forms\Components\TextInput::make('entrada')->label('Entrada')->numeric(),
                        Forms\Components\TextInput::make('cuota_mensual')->label('Cuota mensual')->numeric(),
                        Forms\Components\TextInput::make('num_cuotas')->label('Nº cuotas')->numeric()->integer(),
                        Forms\Components\TextInput::make('iban')->label('IBAN'),
                        Forms\Components\Textarea::make('productos_texto')
                            ->label('Texto OCR / manuscrito (pista)')
                            ->helperText('Úsalo para mapear al catálogo en Oferta y productos (obligatorio al Agregar Contrato).')
                            ->rows(3)
                            ->columnSpanFull(),
                        ...$this->ofertaProductosFormSchema(),
                        Forms\Components\Textarea::make('direccion')->label('Dirección')->rows(2),
                        Forms\Components\TextInput::make('telefonos')->label('Teléfonos'),
                        Forms\Components\Textarea::make('observaciones')->label('Observaciones')->rows(2)->columnSpanFull(),
                        Forms\Components\Placeholder::make('customer_match')
                            ->label('Cliente en app')
                            ->content(fn (): string => $this->customerMatchLabel())
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('voice_transcript')
                            ->label('Transcripción (voz)')
                            ->content(fn (): string => filled($this->lastTranscript)
                                ? (string) $this->lastTranscript
                                : (filled($this->reviewData['_transcript'] ?? null)
                                    ? (string) $this->reviewData['_transcript']
                                    : '—'))
                            ->visible(fn (): bool => $this->entrySource === 'voice' || filled($this->lastTranscript))
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
        $this->entrySource = 'image';
        $this->lastTranscript = null;

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

        $this->fillReviewFromMerged($merged);
    }

    public function processVoiceDictation(): void
    {
        $state = $this->voiceForm->getState();
        $audio = $state['audio'] ?? null;
        if (is_array($audio)) {
            $audio = $audio[0] ?? null;
        }
        $manual = trim((string) ($state['transcript_manual'] ?? ''));

        if (! filled($audio) && $manual === '') {
                Notification::make()
                ->title('Sube un audio, dicta con el micrófono o escribe el texto')
                ->warning()
                ->send();

            return;
        }

        try {
            $extractor = app(ContractVoiceExtractor::class);
            if (filled($audio)) {
                $result = $extractor->extractFromAudioPath((string) $audio);
                $merged = $result['merged'];
                $this->lastTranscript = $result['transcript'];
            } else {
                $merged = $extractor->extractFromTranscript($manual);
                $this->lastTranscript = $manual;
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo procesar el dictado')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->pendingDocuments = [];
        $this->entrySource = 'voice';
        $this->fillReviewFromMerged($merged);

        Notification::make()
            ->title('Dictado procesado')
            ->body(filled($this->lastTranscript)
                ? 'Revisa los campos. Transcripción: '.mb_strimwidth($this->lastTranscript, 0, 120, '…')
                : 'Revisa los campos antes de Aceptar.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $merged
     */
    protected function fillReviewFromMerged(array $merged): void
    {
        $comercialId = filled($merged['comercial_codes'] ?? null)
            ? $this->guessEmpleadoId((string) $merged['comercial_codes'])
            : null;
        $repartidorId = filled($merged['repartidor_code'] ?? null)
            ? $this->guessEmpleadoId((string) $merged['repartidor_code'])
            : null;

        $this->reviewForm->fill(array_merge($this->emptyReview(), $merged, [
            'comercial_id' => $comercialId,
            'repartidor_id' => $repartidorId,
            'fecha_venta' => $this->normalizeDateForPicker($merged['fecha_venta'] ?? null),
            'fecha_entrega' => $this->normalizeDateForPicker($merged['fecha_entrega'] ?? null),
            '_transcript' => $this->lastTranscript ?? ($merged['_transcript'] ?? null),
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

        if ($this->entrySource === 'image' && $this->pendingDocuments === []) {
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

        if ($this->entrySource === 'voice' && filled($this->lastTranscript)) {
            $data['_transcript'] = $this->lastTranscript;
            $data['_entry_source'] = 'voice';
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
        $this->entrySource = 'image';
        $this->pendingDocuments = [];
        $this->lastTranscript = null;
        $this->uploadForm->fill();
        $this->voiceForm->fill();
        $this->reviewForm->fill($this->emptyReview());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Schema::hasTable('contrato_recovery_items')
                    ? ContratoRecoveryItem::query()->with(['customer'])->latest('id')
                    : ContratoRecoveryItem::query()->whereRaw('1=0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('nro_contr_adm')
                    ->label('# Contrato_admin')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('cliente_nombre')
                    ->label('Nombre')
                    ->limit(48)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->extraAttributes([
                        'class' => 'whitespace-nowrap',
                        'style' => 'white-space: nowrap; max-width: 18rem; overflow: hidden; text-overflow: ellipsis;',
                    ]),
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
                Tables\Columns\TextColumn::make('docs_count')
                    ->label('Docs')
                    ->state(fn (ContratoRecoveryItem $record): string => (string) count($record->documents ?? [])),
                Tables\Columns\TextColumn::make('venta_id')
                    ->label('ID_Vta')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aceptado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('verDatos')
                    ->label('VER DATOS')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (ContratoRecoveryItem $record): string => 'Datos — '.$record->nro_contr_adm)
                    ->modalWidth(MaxWidth::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist(fn (Infolist $infolist): Infolist => $infolist
                        ->columns(4)
                        ->extraAttributes(['class' => 'recovery-datos-infolist'])
                        ->schema($this->recoveredDataInfolistSchema())),

                Tables\Actions\Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (ContratoRecoveryItem $record) => in_array($record->status, [
                        ContratoRecoveryItem::STATUS_PENDING_ADD,
                        ContratoRecoveryItem::STATUS_FAILED,
                        ContratoRecoveryItem::STATUS_DRAFT,
                    ], true))
                    ->modalHeading(fn (ContratoRecoveryItem $record): string => 'Editar datos — '.$record->nro_contr_adm)
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->fillForm(function (ContratoRecoveryItem $record): array {
                        return array_merge(
                            $this->emptyReview(),
                            $record->reviewedData(),
                            [
                                'comercial_id' => $record->comercial_id
                                    ?? data_get($record->reviewedData(), 'comercial_id'),
                                'repartidor_id' => data_get($record->reviewedData(), 'repartidor_id'),
                                'fecha_venta' => $this->normalizeDateForPicker(
                                    data_get($record->reviewedData(), 'fecha_venta')
                                ),
                                'fecha_entrega' => $this->normalizeDateForPicker(
                                    data_get($record->reviewedData(), 'fecha_entrega')
                                ),
                            ],
                        );
                    })
                    ->form($this->recoveredDataFormSchema())
                    ->action(function (ContratoRecoveryItem $record, array $data): void {
                        $dni = mb_strtoupper(trim((string) ($data['dni'] ?? '')));
                        $nro = trim((string) ($data['nro_contr_adm'] ?? ''));

                        if ($dni === '' || $nro === '') {
                            Notification::make()
                                ->title('DNI y nº contrato admin son obligatorios')
                                ->warning()
                                ->send();

                            return;
                        }

                        $customer = Customer::query()
                            ->whereNull('deleted_at')
                            ->whereRaw('UPPER(TRIM(dni)) = ?', [$dni])
                            ->orderBy('id')
                            ->first();

                        $record->forceFill([
                            'reviewed_json' => $data,
                            'extracted_json' => $record->extracted_json ?: $data,
                            'dni' => $dni,
                            'nro_contr_adm' => $nro,
                            'cliente_nombre' => $data['cliente_nombre'] ?? null,
                            'customer_id' => $customer?->id,
                            'comercial_id' => $data['comercial_id'] ?? null,
                            'status' => $record->status === ContratoRecoveryItem::STATUS_FAILED
                                ? ContratoRecoveryItem::STATUS_PENDING_ADD
                                : $record->status,
                            'last_error' => null,
                        ])->save();

                        Notification::make()
                            ->title('Datos actualizados')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

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
                        $svc = app(ContractFromImageRecovery::class);
                        $ofertaError = $svc->validateOfertaProductos($record->reviewedData());
                        if ($ofertaError !== null) {
                            Notification::make()
                                ->title('Falta oferta / productos')
                                ->body($ofertaError)
                                ->warning()
                                ->send();

                            return;
                        }

                        $result = $svc->addContract(
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
                    ->label('Ir a venta')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ContratoRecoveryItem $record) => $record->venta_id
                        ? VentaResource::getUrl('edit', ['record' => $record->venta_id])
                        : null)
                    ->visible(fn (ContratoRecoveryItem $record) => filled($record->venta_id))
                    ->openUrlInNewTab(),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->striped()
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function recoveredDataFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('dni')->label('DNI')->required(),
            Forms\Components\TextInput::make('nro_contr_adm')->label('Cod.Contrato (nº contrato admin)')->required(),
            Forms\Components\TextInput::make('cliente_nombre')->label('Nombre (extraído)'),
            Forms\Components\TextInput::make('nro_albaran')->label('Nº albarán'),
            Forms\Components\DatePicker::make('fecha_venta')->label('Fec.Promo. (fecha contrato admin)')->native(false),
            Forms\Components\DatePicker::make('fecha_entrega')->label('Fec.Entr. (fecha entrega)')->native(false),
            Forms\Components\TextInput::make('horario_entrega')->label('Hora Entr.'),
            Forms\Components\TextInput::make('comercial_codes')->label('Com. (códigos comerciales, ej. 008,004)'),
            Forms\Components\Select::make('comercial_id')
                ->label('Comercial principal')
                ->options(fn () => $this->empleadoOptions())
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('repartidor_code')->label('Rep. (id empleado repartidor)'),
            Forms\Components\Select::make('repartidor_id')
                ->label('Repartidor')
                ->options(fn () => $this->empleadoOptions())
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('importe_total')->label('Importe total')->numeric(),
            Forms\Components\TextInput::make('entrada')->label('Entrada')->numeric(),
            Forms\Components\TextInput::make('cuota_mensual')->label('Cuota mensual')->numeric(),
            Forms\Components\TextInput::make('num_cuotas')->label('Nº cuotas')->numeric()->integer(),
            Forms\Components\TextInput::make('iban')->label('IBAN'),
            Forms\Components\Textarea::make('productos_texto')
                ->label('Texto OCR / manuscrito (pista)')
                ->helperText('Úsalo para mapear al catálogo. Oferta + productos son obligatorios al Agregar Contrato.')
                ->rows(3)
                ->columnSpanFull(),
            ...$this->ofertaProductosFormSchema(),
            Forms\Components\Textarea::make('direccion')->label('Dirección')->rows(2),
            Forms\Components\TextInput::make('telefonos')->label('Teléfonos'),
            Forms\Components\Textarea::make('observaciones')->label('Observaciones')->rows(2)->columnSpanFull(),
        ];
    }

    /**
     * Oferta + productos (sin relationship: aún no hay Venta).
     *
     * @return array<int, Forms\Components\Component>
     */
    protected function ofertaProductosFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Oferta y productos')
                ->description('Obligatorio antes de «Agregar Contrato». El texto OCR arriba es solo pista (a menudo manuscrito).')
                ->schema([
                    Forms\Components\Repeater::make('ventaOfertas')
                        ->label(false)
                        ->defaultItems(1)
                        ->addActionLabel('Agregar oferta')
                        ->collapsible()
                        ->itemLabel(function (array $state): string {
                            if (blank($state['oferta_id'] ?? null)) {
                                return 'Nueva oferta';
                            }

                            return (string) (Oferta::query()->whereKey($state['oferta_id'])->value('nombre') ?? 'Oferta');
                        })
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('oferta_id')
                                    ->label('Oferta')
                                    ->options(fn () => Oferta::query()
                                        ->orderBy('nombre')
                                        ->pluck('nombre', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                        $oferta = Oferta::query()->find($state);
                                        if (! $oferta) {
                                            return;
                                        }

                                        $set('puntos', (int) $oferta->puntos_base);

                                        $currentImporte = $get('../../importe_total');
                                        if (! filled($currentImporte) || (float) $currentImporte <= 0) {
                                            $set('../../importe_total', $oferta->precio_base);
                                        }
                                    }),
                                Forms\Components\TextInput::make('puntos')
                                    ->label('Puntos')
                                    ->numeric()
                                    ->dehydrated()
                                    ->default(0),
                            ]),
                            Forms\Components\Repeater::make('productos')
                                ->label('Productos')
                                ->defaultItems(1)
                                ->minItems(1)
                                ->addActionLabel('Agregar producto')
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $set(
                                        'puntos',
                                        collect($get('productos') ?? [])
                                            ->sum(fn ($l) => (int) ($l['puntos_linea'] ?? 0))
                                    );
                                })
                                ->schema([
                                    Forms\Components\Grid::make(4)->schema([
                                        Forms\Components\Select::make('producto_id')
                                            ->label('Producto')
                                            ->options(fn () => Producto::query()
                                                ->where('delete', false)
                                                ->orderBy('nombre')
                                                ->pluck('nombre', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                                $producto = Producto::query()->find($state);
                                                $cantidad = max(1, (int) ($get('cantidad') ?? 1));
                                                if ($producto && $producto->nombre === 'Producto Externo') {
                                                    $set('cantidad', 1);
                                                    $cantidad = 1;
                                                }
                                                if ($producto && $producto->nombre !== 'Producto Externo') {
                                                    $set('puntos_linea', $cantidad * (int) ($producto->puntos ?? 0));
                                                }
                                                $set(
                                                    '../../puntos',
                                                    collect($get('../../productos') ?? [])
                                                        ->sum(fn ($l) => (int) ($l['puntos_linea'] ?? 0))
                                                );
                                            }),
                                        Forms\Components\TextInput::make('cantidad')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                                $nombre = Producto::query()
                                                    ->whereKey($get('producto_id'))
                                                    ->value('nombre');
                                                $puntosUnidad = (int) Producto::query()
                                                    ->whereKey($get('producto_id'))
                                                    ->value('puntos');
                                                $cantidad = $nombre === 'Producto Externo'
                                                    ? 1
                                                    : max((int) $state, 1);
                                                $set('cantidad', $cantidad);
                                                if ($nombre !== 'Producto Externo') {
                                                    $set('puntos_linea', $cantidad * $puntosUnidad);
                                                }
                                                $set(
                                                    '../../puntos',
                                                    collect($get('../../productos') ?? [])
                                                        ->sum(fn ($l) => (int) ($l['puntos_linea'] ?? 0))
                                                );
                                            }),
                                        Forms\Components\TextInput::make('puntos_linea')
                                            ->label('Pts línea')
                                            ->numeric()
                                            ->required()
                                            ->dehydrated()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                $set(
                                                    '../../puntos',
                                                    collect($get('../../productos') ?? [])
                                                        ->sum(fn ($l) => (int) ($l['puntos_linea'] ?? 0))
                                                );
                                            }),
                                        Forms\Components\Select::make('vendido_por')
                                            ->label('Vendido por')
                                            ->options(VendidoPor::options())
                                            ->default(VendidoPor::Comercial->value)
                                            ->required(),
                                    ]),
                                ]),
                        ])
                        ->columnSpanFull(),
                    Forms\Components\Section::make('Productos externos')
                        ->visible(function (Get $get): bool {
                            $ids = collect($get('ventaOfertas') ?? [])
                                ->flatMap(fn ($oferta) => $oferta['productos'] ?? [])
                                ->pluck('producto_id')
                                ->filter()
                                ->all();

                            if ($ids === []) {
                                return false;
                            }

                            return Producto::query()
                                ->whereIn('id', $ids)
                                ->where('nombre', 'Producto Externo')
                                ->exists();
                        })
                        ->schema([
                            Forms\Components\Repeater::make('productos_externos')
                                ->label(false)
                                ->simple(
                                    Forms\Components\TextInput::make('value')
                                        ->label('Nombre producto externo')
                                        ->required()
                                )
                                ->minItems(1)
                                ->dehydrated(),
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Infolists\Components\Component>
     */
    protected function recoveredDataInfolistSchema(): array
    {
        $val = static fn (string $key, mixed $fallback = null) => function (ContratoRecoveryItem $r) use ($key, $fallback) {
            $v = data_get($r->reviewedData(), $key);
            if (filled($v)) {
                return is_scalar($v) ? (string) $v : json_encode($v);
            }
            if ($fallback !== null) {
                $f = is_callable($fallback) ? $fallback($r) : $fallback;

                return filled($f) ? (string) $f : '—';
            }

            return '—';
        };

        $entry = function (string $name, string $label, callable $state, array $opts = []) {
            $e = Infolists\Components\TextEntry::make($name)
                ->label($this->boldUnderlinedLabel($label))
                ->state($state)
                ->inlineLabel()
                ->weight('bold')
                ->extraAttributes(['class' => 'recovery-datos-entry'])
                ->extraEntryWrapperAttributes(['class' => 'recovery-datos-entry-wrp']);

            if ($opts['badge'] ?? false) {
                $e->badge()->color($opts['color'] ?? 'gray');
            }
            if (isset($opts['span'])) {
                $e->columnSpan($opts['span']);
            }

            return $e;
        };

        return [
            Infolists\Components\Section::make()
                ->compact()
                ->columns(4)
                ->extraAttributes(['class' => 'recovery-datos-section'])
                ->schema([
                    $entry('dni', 'DNI', $val('dni', fn (ContratoRecoveryItem $r) => $r->dni), ['badge' => true, 'color' => 'primary']),
                    $entry('nro_contr_adm', 'Cod.Contrato', $val('nro_contr_adm', fn (ContratoRecoveryItem $r) => $r->nro_contr_adm), ['badge' => true, 'color' => 'success']),
                    $entry('status', 'Estado', fn (ContratoRecoveryItem $r) => $r->statusLabel(), ['badge' => true, 'color' => 'warning']),
                    $entry('nro_albaran', 'Albarán', $val('nro_albaran'), ['badge' => true, 'color' => 'gray']),

                    $entry('cliente_nombre', 'Nombre', $val('cliente_nombre', fn (ContratoRecoveryItem $r) => $r->cliente_nombre), ['span' => 2]),
                    $entry('customer_match', 'Cliente app', function (ContratoRecoveryItem $r): string {
                        if ($r->customer) {
                            return "#{$r->customer->id} {$r->customer->first_names} {$r->customer->last_names}";
                        }

                        return $r->customer_id ? "#{$r->customer_id}" : 'sin match';
                    }, ['badge' => true, 'color' => 'info', 'span' => 2]),

                    $entry('fecha_venta', 'Fec.Promo.', $val('fecha_venta'), ['badge' => true, 'color' => 'warning']),
                    $entry('fecha_entrega', 'Fec.Entr.', $val('fecha_entrega'), ['badge' => true, 'color' => 'warning']),
                    $entry('horario_entrega', 'Hora Entr.', $val('horario_entrega')),
                    $entry('comercial', 'Com.', function (ContratoRecoveryItem $r): string {
                        $codes = data_get($r->reviewedData(), 'comercial_codes');
                        $id = $r->comercial_id ?: data_get($r->reviewedData(), 'comercial_id');
                        $name = null;
                        if ($id) {
                            $u = User::query()->find($id);
                            $name = $u
                                ? trim(($u->empleado_id ? $u->empleado_id.' - ' : '').$u->name)
                                : "#{$id}";
                        }

                        return trim(collect([$codes, $name])->filter()->implode(' · ')) ?: '—';
                    }, ['badge' => true, 'color' => 'info']),
                    $entry('repartidor', 'Rep.', function (ContratoRecoveryItem $r): string {
                        $code = data_get($r->reviewedData(), 'repartidor_code');
                        $id = data_get($r->reviewedData(), 'repartidor_id');
                        $name = null;
                        if ($id) {
                            $u = User::query()->find($id);
                            $name = $u
                                ? trim(($u->empleado_id ? $u->empleado_id.' - ' : '').$u->name)
                                : "#{$id}";
                        }

                        return trim(collect([$code, $name])->filter()->implode(' · ')) ?: '—';
                    }, ['badge' => true, 'color' => 'gray']),

                    $entry('importe_total', 'Total', $val('importe_total'), ['badge' => true, 'color' => 'success']),
                    $entry('entrada', 'Entrada', $val('entrada'), ['badge' => true, 'color' => 'gray']),
                    $entry('cuota_mensual', 'Cuota', $val('cuota_mensual'), ['badge' => true, 'color' => 'success']),
                    $entry('num_cuotas', 'Nº cuotas', $val('num_cuotas'), ['badge' => true, 'color' => 'gray']),

                    $entry('iban', 'IBAN', $val('iban'), ['span' => 2]),
                    $entry('telefonos', 'Teléfonos', $val('telefonos'), ['span' => 2]),

                    $entry('direccion', 'Dirección', $val('direccion'), ['span' => 2]),
                    $entry('docs', 'Docs', function (ContratoRecoveryItem $r): string {
                        $docs = $r->documents ?? [];
                        if ($docs === []) {
                            return '—';
                        }

                        return collect($docs)->map(fn ($d) => $d['type'] ?? '?')->implode(' · ');
                    }, ['badge' => true, 'color' => 'gray', 'span' => 2]),

                    $entry('productos_texto', 'OCR / manuscrito', $val('productos_texto'), ['span' => 'full']),
                    $entry('oferta_productos', 'Oferta / productos', function (ContratoRecoveryItem $r): string {
                        $rows = data_get($r->reviewedData(), 'ventaOfertas', []);
                        if (! is_array($rows) || $rows === []) {
                            return '— sin oferta (obligatoria al Agregar)';
                        }

                        return collect($rows)->map(function ($row) {
                            $ofertaId = (int) ($row['oferta_id'] ?? 0);
                            $nombre = $ofertaId
                                ? (Oferta::query()->whereKey($ofertaId)->value('nombre') ?? "#{$ofertaId}")
                                : 'sin oferta';
                            $prods = collect($row['productos'] ?? [])->map(function ($line) {
                                $pid = (int) ($line['producto_id'] ?? 0);
                                if ($pid <= 0) {
                                    return null;
                                }
                                $pn = Producto::query()->whereKey($pid)->value('nombre') ?? "#{$pid}";
                                $qty = (int) ($line['cantidad'] ?? 1);

                                return "{$pn} ×{$qty}";
                            })->filter()->implode(', ');

                            return $prods !== '' ? "{$nombre}: {$prods}" : $nombre;
                        })->filter()->implode(' | ') ?: '—';
                    }, ['span' => 'full']),
                    $entry('observaciones', 'Obs.', $val('observaciones'), ['span' => 'full']),
                ]),
        ];
    }

    protected function boldUnderlinedLabel(string $text): HtmlString
    {
        return new HtmlString(
            '<span class="recovery-field-label">'.e($text).'</span>'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyReview(): array
    {
        return app(ContractImageExtractor::class)->emptyPayload() + [
            'comercial_id' => null,
            'repartidor_id' => null,
            'ventaOfertas' => [],
            'productos_externos' => [],
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

    /**
     * @return array<int|string, string>
     */
    protected function empleadoOptions(): array
    {
        return User::query()
            ->orderBy('empleado_id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (User $u) => [
                $u->id => trim(($u->empleado_id ? $u->empleado_id.' - ' : '').$u->name.' '.($u->last_name ?? '')),
            ])
            ->all();
    }

    protected function guessEmpleadoId(string $codes): ?int
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
        return app(ContractImageExtractor::class)->normalizeDate($value);
    }
}
