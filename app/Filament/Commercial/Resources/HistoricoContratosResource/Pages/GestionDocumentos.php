<?php

namespace App\Filament\Commercial\Resources\HistoricoContratosResource\Pages;

use App\Filament\Commercial\Resources\HistoricoContratosResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\{Section, Group, FileUpload};
use Filament\Notifications\Notification;
use App\Models\Venta;
use App\Support\Filament\VentaDocumentUpload;


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
            'foto_sorteo' => $record->foto_sorteo,
            'dni_anverso' => $record->dni_anverso,
            'dni_reverso' => $record->dni_reverso,
            'documento_titularidad' => $record->documento_titularidad,
            'nomina' => $record->nomina,
            'pension' => $record->pension,
            //'contrato_firmado' => $record->contrato_firmado,
            'otros_documentos' => $record->otros_documentos
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
                        //SOLO FOTOTECA (sin capture, solo accept)
                        self::docCard('albaran', 'Albarán', false, false),

                        //RESTO: CÁMARA
                        self::docCard('precontractual', 'Precontractual', true, true),
                        self::docCard('foto_sorteo', 'Foto Sorteo', false, true),
                        self::docCard('dni_anverso', 'DNI – Anverso', false, true),
                        self::docCard('dni_reverso', 'DNI – Reverso', false, true),
                        self::docCard('documento_titularidad', 'Documento de titularidad', false, true),
                        self::docCard('nomina', 'Nómina', false, true),
                        self::docCard('pension', 'Pensión', false, true),
                        //self::docCard('contrato_firmado', 'Contrato Firmado', false, true),
                        self::docCard('otros_documentos', 'Otros Documentos', false, true),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    protected static function docCard(
        string $field,
        string $label,
        bool $required = false,
        bool $soloCamara = true,
    ): Group {
        return VentaDocumentUpload::card(
            $field,
            $label,
            VentaDocumentUpload::configure(
                FileUpload::make($field),
                $field,
                $required,
                null,
                $soloCamara,
            )
                ->validationMessages([
                    'required' => "El documento {$label} es obligatorio.",
                ])
                ->columnSpanFull(),
        );
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->record->fill([
            'precontractual' => $data['precontractual'] ?? $this->record->precontractual,
            'foto_sorteo' => $data['foto_sorteo'] ?? $this->record->foto_sorteo,
            'dni_anverso' => $data['dni_anverso'] ?? $this->record->dni_anverso,
            'dni_reverso' => $data['dni_reverso'] ?? $this->record->dni_reverso,
            'documento_titularidad' => $data['documento_titularidad'] ?? $this->record->documento_titularidad,
            'nomina' => $data['nomina'] ?? $this->record->nomina,
            'pension' => $data['pension'] ?? $this->record->pension,
            //'contrato_firmado' => $data['contrato_firmado'] ?? $this->record->contrato_firmado,
            'otros_documentos' => $data['otros_documentos'] ?? $this->record->otros_documentos,
        ])->save();

        Notification::make()
            ->title('Documentos del contrato actualizados')
            ->success()
            ->send();

        $this->redirect(HistoricoContratosResource::getUrl('index', panel: 'comercial'));
    }
}
