<?php

namespace App\Filament\Repartidor\Resources\HistoricoRepartosResource\Pages;

use App\Filament\Repartidor\Resources\HistoricoRepartosResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\{Group, FileUpload, Placeholder, Section};
use Filament\Notifications\Notification;
use App\Models\{Reparto, Venta};
use Illuminate\Support\HtmlString;
use Filament\Forms\Get;

class GestionDocumentos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = HistoricoRepartosResource::class;
    protected static string $view = 'filament.repartidor.historico-repartos.docs';

    /** Reparto actual (inyectado por la ruta /{record}/docs) */
    public Reparto $record;

    /** Venta asociada al reparto */
    public ?Venta $venta = null;

    /** Estado del formulario */
    public ?array $data = [];

    public function getTitle(): string
    {
        return 'Gestión de Documentos';
    }

    public function mount(Reparto $record): void
    {
        $this->record = $record;
        $this->venta = $record->venta;

        abort_unless($this->venta, 404, 'El reparto no tiene una venta asociada.');

        // Precarga del formulario con los valores actuales
        $this->form->fill([
            'precontractual' => $this->venta->precontractual,
            'dni_anverso' => $this->venta->dni_anverso,
            'dni_reverso' => $this->venta->dni_reverso,
            'documento_titularidad' => $this->venta->documento_titularidad,
            'nomina' => $this->venta->nomina,
            'pension' => $this->venta->pension,
            'contrato_firmado' => $this->venta->contrato_firmado,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            // Si quieres aplicar policies de modelo en FileUpload, puedes descomentar:
            // ->model($this->venta)
            ->statePath('data')  // todos los campos viven en $this->data[...]
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

    /** Bloques reutilizables de documentos (idénticos a EntregaSimple) */
    protected static function docCard(string $field, string $label, bool $required = false): Group
    {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),

            // ↓ Aquí usamos HtmlString para que el <strong> se renderice
            Placeholder::make("{$field}_desc")
                ->content(new HtmlString(
                    "Este espacio está diseñado para que puedas actualizar y modificar el archivo de " .
                    "<strong>{$label}</strong>. Es necesario actualizarlo para mantener tus datos al día."
                ))
                ->label(""),

            // ↓ También en el aviso rojo
            Placeholder::make("{$field}_required_notice")
                ->label('')
                ->content(new HtmlString(
                    '<div class="text-red-500 text-l font-bold leading-6">
            ❗ El documento <strong>' . e($label) . '</strong> es <strong>obligatorio</strong>.
        </div>'
                ))
                ->visible(fn(Get $get) => $required && blank($get($field))),

            FileUpload::make($field)
                ->label("")
                ->disk('public')
                ->directory('ventas')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->required($required)
                ->validationMessages([
                    'required' => "El documento {$label} es obligatorio.",
                ])
                ->extraAttributes(['class' => 'border-2 border-dashed py-16'])
                ->columnSpanFull(),
        ])->columns(1);
    }

    /** Guardado de documentos */
    public function save(): void
    {
        $data = $this->form->getState(); // = $this->data

        // Actualiza solo los campos presentes (mantiene los existentes si no se sube nada)
        $this->venta->fill([
            'precontractual' => $data['precontractual'] ?? $this->venta->precontractual,
            'dni_anverso' => $data['dni_anverso'] ?? $this->venta->dni_anverso,
            'dni_reverso' => $data['dni_reverso'] ?? $this->venta->dni_reverso,
            'documento_titularidad' => $data['documento_titularidad'] ?? $this->venta->documento_titularidad,
            'nomina' => $data['nomina'] ?? $this->venta->nomina,
            'pension' => $data['pension'] ?? $this->venta->pension,
            'contrato_firmado' => $data['contrato_firmado'] ?? $this->venta->contrato_firmado,
        ])->save();

        Notification::make()
            ->title('Documentos actualizados')
            ->success()
            ->send();
    }
}
