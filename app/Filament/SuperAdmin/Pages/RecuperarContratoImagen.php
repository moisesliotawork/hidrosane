<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Exports\RecoveredContractsExport;
use App\Enums\EstadoVenta;
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
use App\Support\RecoveredContractsQuery;
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
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?string $title = 'Paso 1 · Recuperar contrato';

    protected static ?string $slug = 'recuperar-contrato-imagen';

    protected static string $view = 'filament.superAdmin.pages.recuperar-contrato-imagen';

    /** Justo debajo del recurso Contratos en el menú */
    protected static ?int $navigationSort = -7;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasTable('contrato_recovery_items')) {
            return null;
        }

        return (string) ContratoRecoveryItem::query()->count();
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'success';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPdf')
                ->label('Previsualizar PDF')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->url(fn (): string => $this->recuperadosPdfUrl())
                ->openUrlInNewTab()
                ->tooltip('PDF de recuperados aceptados (filtro de mes actual)'),

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

    /** Mes seleccionado (Y-m). Null + showAllMonths = Todos. */
    public ?string $selectedYearMonth = null;

    public bool $showAllMonths = true;

    public int $selectedYear = 2025;

    public function mount(): void
    {
        $this->uploadForm->fill();
        $this->voiceForm->fill();
        $this->reviewForm->fill($this->emptyReview());

        $this->selectedYear = (int) (session('recuperados.selectedYear') ?: now()->year);
        $this->selectedYearMonth = session('recuperados.selectedYearMonth');
        $this->showAllMonths = (bool) session('recuperados.showAllMonths', true);

        if ($this->showAllMonths) {
            $this->selectedYearMonth = null;
        }
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
                    ->collapsible()
                    ->collapsed()
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
                    ->collapsible()
                    ->collapsed()
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
                        Forms\Components\DatePicker::make('fecha_venta')
                            ->label('Fec.Promo. (fecha contrato admin)')
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->format('Y-m-d'),
                        Forms\Components\DatePicker::make('fecha_entrega')
                            ->label('Fec.Entr. (fecha entrega)')
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->format('Y-m-d'),
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
                        Forms\Components\TextInput::make('codigo_postal')->label('CP / Código Postal'),
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

        $data = app(ContractFromImageRecovery::class)->ensureRecoveryDefaults(
            $this->reviewForm->getState()
        );
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

        // Chequeo temprano: si ya hay una venta ACTIVA con ese nº, no tiene sentido
        // dejarlo como "pendiente de agregar" — va a la tabla de rechazados.
        $existingVenta = app(ContractFromImageRecovery::class)->findActiveVentaByNro($nro);

        ContratoRecoveryItem::query()->create([
            'status' => $existingVenta
                ? ContratoRecoveryItem::STATUS_REJECTED_EXISTS
                : ContratoRecoveryItem::STATUS_PENDING_ADD,
            'documents' => $stableDocs,
            'extracted_json' => $data,
            'reviewed_json' => $data,
            'dni' => $dni,
            'nro_contr_adm' => $nro,
            'cliente_nombre' => $data['cliente_nombre'] ?? null,
            'customer_id' => $customer?->id,
            'venta_id' => $existingVenta?->id,
            'comercial_id' => $data['comercial_id'] ?? null,
            'created_by_user_id' => auth()->id(),
            'last_error' => $existingVenta
                ? "YA EXISTE UN CONTRATO con ese número (venta #{$existingVenta->id})."
                : null,
        ]);

        if ($existingVenta) {
            Notification::make()
                ->title('YA EXISTE UN CONTRATO con ese número')
                ->body("Venta #{$existingVenta->id} ya está activa en la app. Se movió a «Contratos rechazados por estar ya en app».")
                ->danger()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Aceptado')
                ->body('Quedó en la tabla pendiente. Usa «Agregar Contrato» para crear la venta.')
                ->success()
                ->send();
        }

        $this->resetReview();
        $this->resetTable();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContratoRecoveryItem>
     */
    public function rejectedItems(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('contrato_recovery_items')) {
            return collect();
        }

        return ContratoRecoveryItem::query()
            ->where('status', ContratoRecoveryItem::STATUS_REJECTED_EXISTS)
            ->with('venta')
            ->latest('id')
            ->limit(200)
            ->get();
    }

    public function deleteRejectedItem(int $id): void
    {
        $item = ContratoRecoveryItem::query()
            ->where('status', ContratoRecoveryItem::STATUS_REJECTED_EXISTS)
            ->find($id);

        $item?->delete();

        Notification::make()
            ->title($item ? 'Registro eliminado' : 'No encontrado')
            ->send();
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
            // Closure: se reevalúa al render (tras el click del tab). Un Builder
            // fijo se arma en boot con el mes anterior y «Todos» queda vacío.
            ->query(fn (): Builder => Schema::hasTable('contrato_recovery_items')
                ? $this->filteredRecoveryQuery()
                : ContratoRecoveryItem::query()->whereRaw('1 = 0'))
            ->columns([
                Tables\Columns\TextColumn::make('nro_contr_adm')
                    ->label('#Contr.')
                    ->state(fn (ContratoRecoveryItem $record): ?string => $record->displayNroContrAdm())
                    ->searchable(
                        query: function (Builder $query, string $search): void {
                            RecoveredContractsQuery::applySearchFilter($query, $search);
                        },
                    )
                    ->forceSearchCaseInsensitive()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->state(fn (ContratoRecoveryItem $record): ?string => $record->displayClienteNombre())
                    ->searchable(isGlobal: false)
                    ->forceSearchCaseInsensitive()
                    ->weight('bold')
                    ->limit(48)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->extraAttributes([
                        'class' => 'whitespace-nowrap',
                        'style' => 'white-space: nowrap; max-width: 18rem; overflow: hidden; text-overflow: ellipsis; font-weight: 800;',
                    ]),
                Tables\Columns\TextColumn::make('dni')
                    ->label('DNI')
                    ->state(fn (ContratoRecoveryItem $record): ?string => $record->displayDni())
                    ->searchable(isGlobal: false)
                    ->forceSearchCaseInsensitive()
                    ->badge()
                    ->color('warning')
                    ->weight('bold')
                    ->formatStateUsing(fn (?string $state): string => $this->formatDniGroupedEvery4($state)),
                Tables\Columns\TextColumn::make('contrato_pdf')
                    ->label('Contrato/PDF')
                    ->state(fn (ContratoRecoveryItem $record): string => filled($record->documents) ? 'Ver PDF' : '—')
                    ->color(fn (ContratoRecoveryItem $record): string => filled($record->documents) ? 'primary' : 'gray')
                    ->weight('bold')
                    ->url(fn (ContratoRecoveryItem $record): ?string => filled($record->documents)
                        ? route('recovery-items.pdf', $record)
                        : null)
                    ->openUrlInNewTab()
                    ->tooltip('Foto(s) originales con las que se extrajeron los datos del contrato'),
                Tables\Columns\TextColumn::make('domicilio')
                    ->label('Domicilio')
                    ->state(fn (ContratoRecoveryItem $record): ?string => $record->displayDireccion())
                    ->formatStateUsing(function (?string $state): string {
                        if (blank($state)) {
                            return '—';
                        }

                        return mb_strlen($state) > 14 ? mb_substr($state, 0, 14).'...' : $state;
                    })
                    ->tooltip(fn (ContratoRecoveryItem $record): ?string => $record->displayDireccion()),
                Tables\Columns\TextColumn::make('fecha_contrato')
                    ->label('Fecha/Contrato')
                    ->state(fn (ContratoRecoveryItem $record): string => $this->fechaContratoFormatted($record) ?? '—')
                    ->badge()
                    ->color(fn (ContratoRecoveryItem $record) => $this->mesContratoColor($record))
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(reviewed_json, '$.fecha_venta')), '') {$direction}"
                        );
                    }),
                Tables\Columns\TextColumn::make('mes_contrato')
                    ->label('Mes')
                    ->state(fn (ContratoRecoveryItem $record): string => $this->mesContratoLabel($record) ?? '—')
                    ->badge()
                    ->color(fn (ContratoRecoveryItem $record) => $this->mesContratoColor($record))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('Cliente app')
                    ->state(fn (ContratoRecoveryItem $record): ?int => $record->displayCustomerId())
                    ->searchable(isGlobal: false)
                    ->formatStateUsing(function ($state, ContratoRecoveryItem $record): string {
                        $id = $record->displayCustomerId();
                        if (! $id) {
                            return '— sin match';
                        }
                        $record->loadMissing(['venta.customer', 'customer']);
                        $name = $record->venta?->customer?->first_names
                            ?? $record->customer?->first_names
                            ?? '';

                        return '#'.$id.($name !== '' ? ' '.$name : '');
                    }),
                Tables\Columns\TextColumn::make('estado_venta_col')
                    ->label('Estado de la venta')
                    ->state(function (ContratoRecoveryItem $record): string {
                        $estado = $record->venta?->estado_venta
                            ?? EstadoVenta::tryFrom((string) data_get($record->reviewedData(), 'estado_venta', ''))
                            ?? EstadoVenta::POR_ASIGNAR;

                        return $estado->label();
                    })
                    ->badge()
                    ->color(function (ContratoRecoveryItem $record): string {
                        $estado = $record->venta?->estado_venta
                            ?? EstadoVenta::tryFrom((string) data_get($record->reviewedData(), 'estado_venta', ''))
                            ?? EstadoVenta::POR_ASIGNAR;

                        return $estado->color();
                    }),
                Tables\Columns\TextColumn::make('ofertas_de_la_venta')
                    ->label('OfertasDeLaVenta')
                    ->html()
                    ->state(fn (ContratoRecoveryItem $record): HtmlString => $this->formatOfertasDeLaVentaHtml($record))
                    ->wrap()
                    ->extraAttributes([
                        'style' => 'font-size:8px;line-height:1.2;max-width:11rem;',
                    ]),
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
                    ->state(fn (ContratoRecoveryItem $record): string => (string) $record->displayDocsCount())
                    ->badge()
                    ->color(fn (ContratoRecoveryItem $record): string => $record->displayDocsCount() > 0 ? 'success' : 'gray')
                    ->tooltip(fn (ContratoRecoveryItem $record): string => $record->venta_id
                        ? 'Documentos en la venta (BD)'
                        : 'Documentos del recovery (aún sin venta)'),
                Tables\Columns\TextColumn::make('venta_id')
                    ->label('ID_Vta')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aceptado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('reference_photos')
                    ->label('Referencias')
                    ->getStateUsing(function (ContratoRecoveryItem $record): array {
                        $base = rtrim(request()->getSchemeAndHttpHost().request()->getBasePath(), '/');

                        return collect($record->reference_photos ?? [])
                            ->filter(fn ($p) => filled($p) && is_string($p))
                            ->map(function (string $path) use ($base): string {
                                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                                    return $path;
                                }
                                $path = ltrim(str_replace('\\', '/', $path), '/');
                                if (str_starts_with($path, 'storage/')) {
                                    $path = substr($path, strlen('storage/'));
                                }

                                return $base.'/storage/'.$path;
                            })
                            ->values()
                            ->all();
                    })
                    ->square()
                    ->height(36)
                    ->stacked()
                    ->limit(4)
                    ->limitedRemainingText()
                    ->defaultImageUrl(null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('verReferencias')
                    ->label('VER REFERENCIAS')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->modalHeading(fn (ContratoRecoveryItem $record): string => 'Referencias — '.$record->nro_contr_adm)
                    ->modalDescription('Hasta 4 fotos. Puedes previsualizar, reordenar y eliminar.')
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->modalSubmitActionLabel('Guardar fotos')
                    ->modalCancelActionLabel('Cerrar')
                    ->fillForm(fn (ContratoRecoveryItem $record): array => [
                        'reference_photos' => array_values($record->reference_photos ?? []),
                    ])
                    ->form(fn (ContratoRecoveryItem $record): array => [
                        Forms\Components\Section::make('Ver fotos')
                            ->description('Clic en una imagen para ampliarla y leer el manuscrito')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Forms\Components\View::make('filament.superAdmin.components.reference-photos-lightbox')
                                    ->viewData([
                                        'photos' => array_values($record->reference_photos ?? []),
                                    ]),
                            ]),
                        Forms\Components\Section::make('Fotos de referencia')
                            ->description('JPG/PNG/WebP · máximo 4 · subir aquí y Guardar fotos')
                            ->icon('heroicon-o-camera')
                            ->collapsible()
                            ->collapsed(false)
                            ->schema([
                                Forms\Components\FileUpload::make('reference_photos')
                                    ->label('Subir / gestionar fotos')
                                    ->image()
                                    ->multiple()
                                    ->reorderable()
                                    ->maxFiles(4)
                                    ->maxSize(15360) // 15 MB — fotos de manuscrito
                                    ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('contract-recovery/references/'.$record->id)
                                    ->fetchFileInformation(false)
                                    ->imagePreviewHeight('140')
                                    ->openable()
                                    ->downloadable()
                                    ->deletable()
                                    ->panelLayout('grid')
                                    ->helperText('1) Sube o arrastra las fotos  2) Pulsa «Guardar fotos»  3) Vuelve a abrir VER REFERENCIAS para ampliarlas arriba.'),
                            ]),
                    ])
                    ->action(function (ContratoRecoveryItem $record, array $data): void {
                        $photos = array_values(array_filter(
                            Arr::wrap($data['reference_photos'] ?? []),
                            function ($path) {
                                if (! filled($path) || ! is_string($path)) {
                                    return false;
                                }
                                // Descarta temporales Livewire no movidos al disco public
                                if (str_contains($path, 'livewire-tmp')) {
                                    return false;
                                }

                                return true;
                            }
                        ));

                        if (count($photos) > 4) {
                            $photos = array_slice($photos, 0, 4);
                        }

                        $record->forceFill([
                            'reference_photos' => $photos === [] ? null : $photos,
                        ])->save();

                        Notification::make()
                            ->title('Referencias guardadas')
                            ->body(count($photos).' foto(s) en el registro. Vuelve a abrir el modal para verlas ampliadas.')
                            ->success()
                            ->send();

                        $this->flushCachedTableRecords();
                    }),

                Tables\Actions\Action::make('verDatos')
                    ->label('VER DATOS')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->modalHeading(fn (ContratoRecoveryItem $record): string => 'Editar datos — '.$record->nro_contr_adm)
                    ->modalWidth(MaxWidth::FiveExtraLarge)
                    ->modalSubmitActionLabel('Guardar cambios')
                    ->modalCancelActionLabel('Cerrar')
                    ->fillForm(function (ContratoRecoveryItem $record): array {
                        $fechaVenta = $this->normalizeDateForPicker(
                            data_get($record->reviewedData(), 'fecha_venta')
                        );

                        return array_merge(
                            $this->emptyReview(),
                            $record->reviewedData(),
                            [
                                'dni' => $this->formatDniGrouped(
                                    data_get($record->reviewedData(), 'dni') ?: $record->dni
                                ),
                                'nro_contr_adm' => data_get($record->reviewedData(), 'nro_contr_adm')
                                    ?: $record->nro_contr_adm,
                                'cliente_nombre' => mb_strtoupper(trim((string) (
                                    data_get($record->reviewedData(), 'cliente_nombre')
                                    ?: $record->cliente_nombre
                                    ?: ''
                                ))),
                                'comercial_id' => $record->comercial_id
                                    ?? data_get($record->reviewedData(), 'comercial_id'),
                                'repartidor_id' => data_get($record->reviewedData(), 'repartidor_id'),
                                'fecha_venta' => $fechaVenta,
                                'fecha_promo' => $fechaVenta,
                                'fecha_entrega' => $this->normalizeDateForPicker(
                                    data_get($record->reviewedData(), 'fecha_entrega')
                                ),
                            ],
                        );
                    })
                    ->form($this->recoveredDataFormSchema())
                    ->action(function (ContratoRecoveryItem $record, array $data): void {
                        $dni = $this->normalizeDniInput($data['dni'] ?? '');
                        $nro = trim((string) ($data['nro_contr_adm'] ?? ''));

                        if ($dni === '' || $nro === '') {
                            Notification::make()
                                ->title('DNI y nº contrato admin son obligatorios')
                                ->warning()
                                ->send();

                            return;
                        }

                        $data['dni'] = $dni;
                        $data['cliente_nombre'] = mb_strtoupper(trim((string) ($data['cliente_nombre'] ?? ''))) ?: null;
                        $data['fecha_venta'] = $this->normalizeDateForPicker($data['fecha_venta'] ?? null);
                        $data['fecha_entrega'] = $this->normalizeDateForPicker($data['fecha_entrega'] ?? null);
                        unset($data['fecha_promo']);

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
                            ->title('Datos guardados')
                            ->body('Se usarán al Agregar Contrato.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

                Tables\Actions\Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(false)
                    ->modalHeading(fn (ContratoRecoveryItem $record): string => 'Editar datos — '.$record->nro_contr_adm)
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->fillForm(function (ContratoRecoveryItem $record): array {
                        $fechaVenta = $this->normalizeDateForPicker(
                            data_get($record->reviewedData(), 'fecha_venta')
                        );

                        return array_merge(
                            $this->emptyReview(),
                            $record->reviewedData(),
                            [
                                'comercial_id' => $record->comercial_id
                                    ?? data_get($record->reviewedData(), 'comercial_id'),
                                'repartidor_id' => data_get($record->reviewedData(), 'repartidor_id'),
                                'fecha_venta' => $fechaVenta,
                                'fecha_promo' => $fechaVenta,
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

                        $data['fecha_venta'] = $this->normalizeDateForPicker($data['fecha_venta'] ?? null);
                        $data['fecha_entrega'] = $this->normalizeDateForPicker($data['fecha_entrega'] ?? null);
                        unset($data['fecha_promo']);

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
                            $yaExiste = str_contains($result['message'], 'ya existe un contrato ACTIVO')
                                || str_contains($result['message'], 'colisiona con venta');

                            Notification::make()
                                ->title($yaExiste ? 'YA EXISTE UN CONTRATO con ese número' : 'No se pudo agregar')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
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

                Tables\Actions\Action::make('syncFromVenta')
                    ->label('Sync venta')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->tooltip('Copia al snapshot los datos actuales de la venta (cliente, nº, fecha, ofertas…)')
                    ->visible(fn (ContratoRecoveryItem $record) => $record->canSyncFromVenta())
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar desde venta')
                    ->modalDescription('Se actualizará el registro de recuperados con los datos actuales de la venta enlazada. La vista ya muestra datos en vivo; esto congela el snapshot.')
                    ->action(function (ContratoRecoveryItem $record): void {
                        $result = (new ContractFromImageRecovery)->syncFromVenta($record);
                        if ($result['ok']) {
                            Notification::make()
                                ->title('Sincronizado')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No se pudo sincronizar')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                        $this->flushCachedTableRecords();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar registro')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading('Eliminar registro de recuperación')
                    ->modalDescription('Se elimina solo este registro de la tabla de recuperación (staging). No borra ni afecta ninguna venta/cliente ya creado.')
                    ->using(fn (ContratoRecoveryItem $record) => $record->delete())
                    ->successNotificationTitle('Registro eliminado'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('syncFromVentaBulk')
                    ->label('Sincronizar desde venta')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records): void {
                        $ok = 0;
                        $fail = 0;
                        $svc = new ContractFromImageRecovery;
                        foreach ($records as $record) {
                            if (! $record instanceof ContratoRecoveryItem || ! $record->canSyncFromVenta()) {
                                $fail++;
                                continue;
                            }
                            $result = $svc->syncFromVenta($record);
                            if ($result['ok']) {
                                $ok++;
                            } else {
                                $fail++;
                            }
                        }
                        Notification::make()
                            ->title('Sincronización')
                            ->body("OK: {$ok}".($fail > 0 ? " · Fallidos/omitidos: {$fail}" : ''))
                            ->color($fail > 0 ? 'warning' : 'success')
                            ->send();
                        $this->flushCachedTableRecords();
                    }),
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
            Forms\Components\Section::make()
                ->compact()
                ->columns(4)
                ->extraAttributes(['class' => 'recovery-datos-highlight-form recovery-datos-form-4col'])
                ->schema([
                    Forms\Components\TextInput::make('nro_contr_adm')
                        ->label('Nº CONTRATO')
                        ->required()
                        ->inlineLabel()
                        ->extraInputAttributes(['class' => 'recovery-nro-contrato-input'])
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('cliente_nombre')
                        ->label('CLIENTE')
                        ->inlineLabel()
                        ->formatStateUsing(fn (?string $state): string => mb_strtoupper(trim((string) $state)))
                        ->dehydrateStateUsing(fn (?string $state): ?string => ($t = trim((string) $state)) !== '' ? mb_strtoupper($t) : null)
                        ->extraInputAttributes(['class' => 'recovery-cliente-nombre-input'])
                        ->columnSpan(2),
                    Forms\Components\DatePicker::make('fecha_venta')
                        ->label('FEC. PROMO')
                        ->inlineLabel()
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->format('Y-m-d')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set('fecha_promo', $state);
                        })
                        ->extraInputAttributes(['class' => 'recovery-fecha-verde-input'])
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('dni')
                        ->label('DNI')
                        ->required()
                        ->inlineLabel()
                        ->formatStateUsing(fn (?string $state): string => $this->formatDniGrouped($state))
                        ->dehydrateStateUsing(fn (?string $state): string => $this->normalizeDniInput($state))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set('dni', $this->formatDniGrouped(is_string($state) ? $state : null));
                        })
                        ->extraInputAttributes(['class' => 'recovery-dni-input'])
                        ->columnSpan(1),
                    Forms\Components\DatePicker::make('fecha_promo')
                        ->label('FECHA CONTRATO')
                        ->inlineLabel()
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->format('Y-m-d')
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set('fecha_venta', $state);
                        })
                        ->extraInputAttributes(['class' => 'recovery-fecha-verde-input'])
                        ->columnSpan(1),
                    Forms\Components\DatePicker::make('fecha_entrega')
                        ->label('FECHA ENTREGA')
                        ->inlineLabel()
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->format('Y-m-d')
                        ->extraInputAttributes(['class' => 'recovery-fecha-bold-input'])
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('nro_albaran')
                        ->label('ALBARÁN')
                        ->inlineLabel()
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make('Resto de datos')
                ->compact()
                ->columns(4)
                ->extraAttributes(['class' => 'recovery-datos-form-4col'])
                ->schema([
                    Forms\Components\TextInput::make('horario_entrega')
                        ->label('Hora Entr.')
                        ->inlineLabel(),
                    Forms\Components\TextInput::make('comercial_codes')
                        ->label('Com. (códigos)')
                        ->inlineLabel(),
                    Forms\Components\Select::make('comercial_id')
                        ->label('Comercial')
                        ->inlineLabel()
                        ->options(fn () => $this->empleadoOptions())
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('repartidor_code')
                        ->label('Rep. código')
                        ->inlineLabel(),
                    Forms\Components\Select::make('repartidor_id')
                        ->label('Repartidor')
                        ->inlineLabel()
                        ->options(fn () => $this->empleadoOptions())
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('importe_total')
                        ->label('Total')
                        ->inlineLabel()
                        ->numeric(),
                    Forms\Components\TextInput::make('entrada')
                        ->label('Entrada')
                        ->inlineLabel()
                        ->numeric(),
                    Forms\Components\TextInput::make('cuota_mensual')
                        ->label('Cuota')
                        ->inlineLabel()
                        ->numeric(),
                    Forms\Components\TextInput::make('num_cuotas')
                        ->label('Nº cuotas')
                        ->inlineLabel()
                        ->numeric()
                        ->integer(),
                    Forms\Components\TextInput::make('iban')
                        ->label('IBAN')
                        ->inlineLabel()
                        ->extraInputAttributes(['class' => 'recovery-iban-input'])
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('telefonos')
                        ->label('Teléfonos')
                        ->inlineLabel()
                        ->columnSpan(2),
                    Forms\Components\Textarea::make('direccion')
                        ->label('Dirección')
                        ->rows(2)
                        ->columnSpan(2),
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->rows(2)
                        ->columnSpan(2),
                    Forms\Components\Textarea::make('productos_texto')
                        ->label('Texto OCR / manuscrito (pista)')
                        ->helperText('Úsalo para mapear al catálogo. Oferta + productos son obligatorios al Agregar Contrato.')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            ...$this->ofertaProductosFormSchema(),
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
                                    ->options(function () {
                                        return Oferta::query()
                                            ->orderBy('nombre')
                                            ->get()
                                            ->mapWithKeys(function (Oferta $oferta) {
                                                if ($oferta->nombre === ContractFromImageRecovery::OFERTA_POR_ASIGNAR_NOMBRE) {
                                                    return [
                                                        $oferta->id => new HtmlString(
                                                            '<span style="color:#dc2626;font-weight:800;">'
                                                            .e($oferta->nombre)
                                                            .'</span>'
                                                        ),
                                                    ];
                                                }

                                                return [$oferta->id => $oferta->nombre];
                                            })
                                            ->all();
                                    })
                                    ->allowHtml()
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
    /**
     * @return array<int, Infolists\Components\Component>
     */
    protected function recoveredDataInfolistSchema(bool $withHighlight = true): array
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

        $valDate = static function (string $key) use ($val): \Closure {
            return static function (ContratoRecoveryItem $r) use ($key, $val): string {
                $raw = $val($key)($r);
                if ($raw === '—' || ! filled($raw)) {
                    return '—';
                }
                try {
                    return \Carbon\Carbon::parse($raw)->format('d-m-Y');
                } catch (\Throwable) {
                    return (string) $raw;
                }
            };
        };

        $entry = function (string $name, string $label, callable $state, array $opts = []) {
            $highlight = (bool) ($opts['highlight'] ?? false);

            if ($highlight && $state instanceof \Closure) {
                $inner = $state;
                $state = static function (ContratoRecoveryItem $r) use ($inner): string {
                    $raw = $inner($r);
                    $text = filled($raw) && (string) $raw !== '—'
                        ? (string) $raw
                        : '—';

                    return mb_strtoupper($text);
                };
            }

            $e = Infolists\Components\TextEntry::make($name)
                ->label($highlight
                    ? new HtmlString(
                        '<span class="recovery-datos-highlight-label">'.e(mb_strtoupper($label)).'</span>'
                    )
                    : $this->boldUnderlinedLabel($label))
                ->state($state)
                ->inlineLabel()
                ->weight('bold')
                ->extraAttributes([
                    'class' => $highlight
                        ? 'recovery-datos-entry recovery-datos-highlight'
                        : 'recovery-datos-entry',
                ])
                ->extraEntryWrapperAttributes([
                    'class' => $highlight
                        ? 'recovery-datos-entry-wrp recovery-datos-highlight-wrp'
                        : 'recovery-datos-entry-wrp',
                ]);

            if (! $highlight && ($opts['badge'] ?? false)) {
                $e->badge()->color($opts['color'] ?? 'gray');
            }
            if (isset($opts['span'])) {
                $e->columnSpan($opts['span']);
            }

            return $e;
        };

        $sections = [];

        if ($withHighlight) {
            $fechaContrato = $valDate('fecha_venta');
            $sections[] = Infolists\Components\Section::make()
                ->compact()
                ->columns(1)
                ->extraAttributes(['class' => 'recovery-datos-section recovery-datos-highlight-section'])
                ->schema([
                    $entry(
                        'cliente_nombre_hl',
                        'Nombre de cliente',
                        $val('cliente_nombre', fn (ContratoRecoveryItem $r) => $r->cliente_nombre),
                        ['highlight' => true],
                    ),
                    $entry(
                        'dni_hl',
                        'DNI',
                        $val('dni', fn (ContratoRecoveryItem $r) => $r->dni),
                        ['highlight' => true],
                    ),
                    $entry('fecha_contrato_hl', 'Fecha de contrato', $fechaContrato, ['highlight' => true]),
                    $entry('fecha_promo_hl', 'Fecha promo', $fechaContrato, ['highlight' => true]),
                    $entry('fecha_entrega_hl', 'Fecha entrega', $valDate('fecha_entrega'), ['highlight' => true]),
                ]);
        }

        $sections[] = Infolists\Components\Section::make()
            ->compact()
            ->columns(4)
            ->extraAttributes(['class' => 'recovery-datos-section'])
            ->schema([
                $entry('nro_contr_adm', 'Cod.Contrato', $val('nro_contr_adm', fn (ContratoRecoveryItem $r) => $r->nro_contr_adm), ['badge' => true, 'color' => 'success']),
                $entry('status', 'Estado', fn (ContratoRecoveryItem $r) => $r->statusLabel(), ['badge' => true, 'color' => 'warning']),
                $entry('nro_albaran', 'Albarán', $val('nro_albaran'), ['badge' => true, 'color' => 'gray']),
                $entry('customer_match', 'Cliente app', function (ContratoRecoveryItem $r): string {
                    if ($r->customer) {
                        return "#{$r->customer->id} {$r->customer->first_names} {$r->customer->last_names}";
                    }

                    return $r->customer_id ? "#{$r->customer_id}" : 'sin match';
                }, ['badge' => true, 'color' => 'info']),

                $entry('fecha_venta_ro', 'Fec.Promo.', $valDate('fecha_venta'), ['badge' => true, 'color' => 'warning']),
                $entry('fecha_entrega_ro', 'Fec.Entr.', $valDate('fecha_entrega'), ['badge' => true, 'color' => 'warning']),
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
            ]);

        return $sections;
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
        $defaults = app(ContractFromImageRecovery::class)->ensureRecoveryDefaults([]);

        return app(ContractImageExtractor::class)->emptyPayload() + [
            'comercial_id' => null,
            'repartidor_id' => null,
            'estado_venta' => $defaults['estado_venta'] ?? EstadoVenta::POR_ASIGNAR->value,
            'ventaOfertas' => $defaults['ventaOfertas'] ?? [],
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

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\ContratoRecoveryItem>
     */
    protected function filteredRecoveryQuery()
    {
        return RecoveredContractsQuery::forList(
            $this->selectedYearMonth,
            $this->showAllMonths || blank($this->selectedYearMonth),
            null, // la búsqueda la aplica Filament vía columnas searchable
        )->with(['customer', 'venta.customer', 'venta.ventaOfertas.oferta']);
    }

    protected function recoveryFechaSqlExpression(): string
    {
        return RecoveredContractsQuery::fechaSqlExpression();
    }

    /**
     * @return list<int>
     */
    public function tabYears(): array
    {
        return [2025, 2026];
    }

    public function clienteSearchQuery(): string
    {
        return trim((string) ($this->tableSearch ?? ''));
    }

    /**
     * Meses (1-12) con recuperados que coinciden con la búsqueda, por año.
     *
     * @return array<int, list<int>>
     */
    public function clienteActivityByYear(): array
    {
        $q = $this->clienteSearchQuery();
        if ($q === '' || ! Schema::hasTable('contrato_recovery_items')) {
            return [];
        }

        $years = array_map('intval', $this->tabYears());

        $items = RecoveredContractsQuery::applySearchFilter(
            ContratoRecoveryItem::query()->with(['venta:id,fecha_venta']),
            $q,
        )
            ->limit(500)
            ->get(['id', 'reviewed_json', 'venta_id']);

        $map = [];
        foreach ($items as $item) {
            $fecha = $this->fechaContratoCarbon($item);
            if (! $fecha) {
                continue;
            }

            $year = (int) $fecha->year;
            $month = (int) $fecha->month;
            if (! in_array($year, $years, true) || $month < 1 || $month > 12) {
                continue;
            }

            if (! in_array($month, $map[$year] ?? [], true)) {
                $map[$year][] = $month;
            }
        }

        ksort($map);
        foreach ($map as &$months) {
            sort($months);
        }
        unset($months);

        return $map;
    }

    /**
     * @return array<int, array{label: string, full: string, bg: string, border: string, text: string}>
     */
    public function monthBadges(): array
    {
        return [
            1 => ['label' => 'ENE', 'full' => 'Enero', 'bg' => '#fde8e8', 'border' => '#f5c2c2', 'text' => '#9f1239'],
            2 => ['label' => 'FEB', 'full' => 'Febrero', 'bg' => '#fce7f3', 'border' => '#f0abcf', 'text' => '#9d174d'],
            3 => ['label' => 'MAR', 'full' => 'Marzo', 'bg' => '#f3e8ff', 'border' => '#d8b4fe', 'text' => '#6b21a8'],
            4 => ['label' => 'ABR', 'full' => 'Abril', 'bg' => '#ede9fe', 'border' => '#c4b5fd', 'text' => '#5b21b6'],
            5 => ['label' => 'MAY', 'full' => 'Mayo', 'bg' => '#e0e7ff', 'border' => '#a5b4fc', 'text' => '#3730a3'],
            6 => ['label' => 'JUN', 'full' => 'Junio', 'bg' => '#e0f2fe', 'border' => '#7dd3fc', 'text' => '#075985'],
            7 => ['label' => 'JUL', 'full' => 'Julio', 'bg' => '#ccfbf1', 'border' => '#5eead4', 'text' => '#115e59'],
            8 => ['label' => 'AGO', 'full' => 'Agosto', 'bg' => '#d1fae5', 'border' => '#6ee7b7', 'text' => '#065f46'],
            9 => ['label' => 'SEP', 'full' => 'Septiembre', 'bg' => '#ecfccb', 'border' => '#bef264', 'text' => '#3f6212'],
            10 => ['label' => 'OCT', 'full' => 'Octubre', 'bg' => '#fef9c3', 'border' => '#fde047', 'text' => '#854d0e'],
            11 => ['label' => 'NOV', 'full' => 'Noviembre', 'bg' => '#ffedd5', 'border' => '#fdba74', 'text' => '#9a3412'],
            12 => ['label' => 'DIC', 'full' => 'Diciembre', 'bg' => '#fee2e2', 'border' => '#fca5a5', 'text' => '#991b1b'],
        ];
    }

    public function selectedBadgeMonth(): ?int
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return null;
        }

        try {
            return (int) explode('-', $this->selectedYearMonth)[1];
        } catch (\Throwable) {
            return null;
        }
    }

    public function selectedBadgeYear(): ?int
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return null;
        }

        try {
            return (int) explode('-', $this->selectedYearMonth)[0];
        } catch (\Throwable) {
            return null;
        }
    }

    public function selectCalendarMonth(int $year, int $month): void
    {
        $month = max(1, min(12, $month));
        $this->selectedYear = $year;
        $this->selectedYearMonth = sprintf('%04d-%02d', $year, $month);
        $this->showAllMonths = false;
        $this->persistRecoveryMonthSelection();
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function showAllPayments(): void
    {
        $this->showAllMonths = true;
        $this->selectedYearMonth = null;
        $this->persistRecoveryMonthSelection();
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    protected function persistRecoveryMonthSelection(): void
    {
        session([
            'recuperados.selectedYear' => $this->selectedYear,
            'recuperados.selectedYearMonth' => $this->selectedYearMonth,
            'recuperados.showAllMonths' => $this->showAllMonths,
        ]);
    }

    public function selectedPeriodLabel(): string
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return 'Todos los registros';
        }

        $badges = $this->monthBadges();
        $month = $this->selectedBadgeMonth();
        $year = $this->selectedBadgeYear() ?? $this->selectedYear;
        $label = $badges[$month]['full'] ?? ($badges[$month]['label'] ?? 'MES');

        return $label.' '.$year;
    }

    public function recuperadosPdfUrl(bool $download = false): string
    {
        $params = [];
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            $params['todos'] = 1;
        } else {
            $params['mes'] = $this->selectedYearMonth;
        }

        $q = $this->clienteSearchQuery();
        if ($q !== '') {
            $params['q'] = $q;
        }

        if ($download) {
            $params['download'] = 1;
        }

        return route('recuperados-aceptados.pdf', $params);
    }

    protected function fechaContratoCarbon(ContratoRecoveryItem $record): ?Carbon
    {
        $raw = $record->displayFechaVentaRaw();
        if (blank($raw)) {
            return null;
        }

        try {
            return $raw instanceof Carbon
                ? $raw->copy()->timezone('Europe/Madrid')
                : Carbon::parse((string) $raw, 'Europe/Madrid');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function fechaContratoFormatted(ContratoRecoveryItem $record): ?string
    {
        return $this->fechaContratoCarbon($record)?->format('d/m/Y');
    }

    protected function mesContratoLabel(ContratoRecoveryItem $record): ?string
    {
        $fecha = $this->fechaContratoCarbon($record);
        if ($fecha === null) {
            return null;
        }

        $labels = [
            1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
            5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
            9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
        ];

        $mes = $labels[(int) $fecha->month] ?? null;
        if ($mes === null) {
            return null;
        }

        return $mes.' '.$fecha->format('y');
    }

    /**
     * Colores alineados con los tabs de mes de ListaAmano.
     *
     * @return string|array{50: string, 100: string, 200: string, 300: string, 400: string, 500: string, 600: string, 700: string, 800: string, 900: string, 950: string}
     */
    protected function mesContratoColor(ContratoRecoveryItem $record): string|array
    {
        $fecha = $this->fechaContratoCarbon($record);
        if ($fecha === null) {
            return 'gray';
        }

        $hex = match ((int) $fecha->month) {
            1 => '#9f1239',
            2 => '#9d174d',
            3 => '#6b21a8',
            4 => '#5b21b6',
            5 => '#3730a3',
            6 => '#075985',
            7 => '#115e59',
            8 => '#065f46',
            9 => '#3f6212',
            10 => '#854d0e',
            11 => '#9a3412',
            12 => '#991b1b',
            default => '#6b7280',
        };

        return Color::hex($hex);
    }

    protected function formatOfertasDeLaVentaHtml(ContratoRecoveryItem $record): HtmlString
    {
        $names = $record->displayOfertaNombres();

        if ($names === []) {
            return new HtmlString('<span style="font-size:8px;color:#9ca3af;">—</span>');
        }

        $lines = array_map(function (string $nombre): string {
            $isPorAsignar = $nombre === ContractFromImageRecovery::OFERTA_POR_ASIGNAR_NOMBRE;
            $color = $isPorAsignar ? '#dc2626' : '#111827';
            $weight = $isPorAsignar ? '700' : '500';

            return '<div style="font-size:8px;line-height:1.25;color:'.$color.';font-weight:'.$weight.';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
                .e($nombre)
                .'</div>';
        }, $names);

        return new HtmlString(implode('', $lines));
    }

    protected function formatDniGrouped(?string $dni): string
    {
        $raw = mb_strtoupper(preg_replace('/[^0-9A-Z]/', '', (string) $dni) ?? '');
        if ($raw === '') {
            return '';
        }

        $letter = '';
        if (preg_match('/[A-Z]$/', $raw) === 1) {
            $letter = substr($raw, -1);
            $raw = substr($raw, 0, -1);
        }

        $chunks = $raw === '' ? [] : str_split($raw, 3);
        $grouped = implode(' ', $chunks);

        return trim($grouped.($letter !== '' ? ' '.$letter : ''));
    }

    /** Agrupa DNI cada 4 cifras (tabla recuperados). */
    protected function formatDniGroupedEvery4(?string $dni): string
    {
        $raw = mb_strtoupper(preg_replace('/[^0-9A-Z]/', '', (string) $dni) ?? '');
        if ($raw === '') {
            return '—';
        }

        $letter = '';
        if (preg_match('/[A-Z]$/', $raw) === 1) {
            $letter = substr($raw, -1);
            $raw = substr($raw, 0, -1);
        }

        $chunks = $raw === '' ? [] : str_split($raw, 4);
        $grouped = implode(' ', $chunks);

        return trim($grouped.($letter !== '' ? ' '.$letter : ''));
    }

    protected function normalizeDniInput(?string $dni): string
    {
        return mb_strtoupper(preg_replace('/[^0-9A-Z]/', '', (string) $dni) ?? '');
    }

    protected function normalizeDateForPicker(mixed $value): ?string
    {
        return app(ContractImageExtractor::class)->normalizeDate($value);
    }
}
