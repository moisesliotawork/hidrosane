<?php

namespace App\Filament\Commercial\Resources\HistoricoContratosResource\Pages;

use App\Filament\Commercial\Resources\HistoricoContratosResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\{Section, Group, FileUpload, Placeholder};
use Filament\Notifications\Notification;
use App\Models\Venta;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;


class GestionDocumentos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = HistoricoContratosResource::class;
    protected static string $view = 'filament.commercial.historico-contratos.docs';

    /** Venta actual (viene como {record}) */
    public Venta $record;

    /** Estado del form */
    public ?array $data = [];

    public function getTitle(): string
    {
        return 'Gestión de Documentos del Contrato';
    }

    public function mount(Venta $record): void
    {
        // seguridad: solo su propia venta
        abort_unless($record->comercial_id === auth()->id(), 403);

        $this->record = $record;

        $this->form->fill([
            'precontractual' => $record->precontractual,
            'dni_anverso' => $record->dni_anverso,
            'dni_reverso' => $record->dni_reverso,
            'documento_titularidad' => $record->documento_titularidad,
            'nomina' => $record->nomina,
            'pension' => $record->pension,
            'contrato_firmado' => $record->contrato_firmado,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->statePath('data')
            ->schema([
                Section::make('Gestión Documentos')
                    ->schema([
                        self::docCard('precontractual', 'Precontractual', true),
                        self::docCard('dni_anverso', 'DNI – Anverso'),
                        self::docCard('dni_reverso', 'DNI – Reverso'),
                        self::docCard('documento_titularidad', 'Documento de titularidad'),
                        self::docCard('nomina', 'Nómina'),
                        self::docCard('pension', 'Pensión'),
                        self::docCard('contrato_firmado', 'Contrato firmado'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    protected static function docCard(string $field, string $label, bool $required = false): Group
    {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),

            Placeholder::make("{$field}_desc")
                ->content("Sube o actualiza el archivo de <strong>{$label}</strong>.")
                ->label(""),

            FileUpload::make($field)
                ->label("")
                ->disk('public')
                ->directory('ventas')

                ->openable()
                ->downloadable()
                ->required($required)
                ->getUploadedFileNameForStorageUsing(
                    function (TemporaryUploadedFile $file) use ($field): string {
                        $user = auth()->user();

                        $timestamp = now()->format('Ymd_His');
                        $empleadoId = $user?->empleado_id ?? 'sin-id';
                        $fullName = $user
                            ? Str::slug($user->name . ' ' . $user->last_name, '_')
                            : 'sin-usuario';

                        // opcional: normalizar el nombre del field
                        $fieldSlug = Str::slug($field, '_'); // precontractual, dni_anverso, etc.
            
                        $extension = $file->getClientOriginalExtension();

                        return "{$timestamp}_{$empleadoId}_{$fullName}_{$fieldSlug}.{$extension}";
                    }
                )

                ->validationMessages([
                    'required' => "El documento {$label} es obligatorio.",
                ])
                ->extraAttributes(['class' => 'border-2 border-dashed py-16'])
                ->columnSpanFull(),
        ])->columns(1);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->record->fill([
            'precontractual' => $data['precontractual'] ?? $this->record->precontractual,
            'dni_anverso' => $data['dni_anverso'] ?? $this->record->dni_anverso,
            'dni_reverso' => $data['dni_reverso'] ?? $this->record->dni_reverso,
            'documento_titularidad' => $data['documento_titularidad'] ?? $this->record->documento_titularidad,
            'nomina' => $data['nomina'] ?? $this->record->nomina,
            'pension' => $data['pension'] ?? $this->record->pension,
            'contrato_firmado' => $data['contrato_firmado'] ?? $this->record->contrato_firmado,
        ])->save();

        Notification::make()
            ->title('Documentos del contrato actualizados')
            ->success()
            ->send();
    }
}
