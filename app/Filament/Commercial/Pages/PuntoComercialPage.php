<?php

namespace App\Filament\Commercial\Pages;

use App\Events\PuntoComercialEnviado;
use App\Models\PuntoComercialReport;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class PuntoComercialPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Punto/Comercial';

    protected static ?string $title = 'Punto/Comercial';

    protected static ?string $slug = 'punto-comercial';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.commercial.pages.punto-comercial';

    public ?array $data = [];

    public bool $sending = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('team_leader') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'texto' => '',
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Textarea::make('texto')
                    ->label('Reporte del Punto Comercial:')
                    ->required()
                    ->rows(8)
                    ->maxLength(5000)
                    ->placeholder('Escribe aquí el reporte de punto comercial…')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function cancelar(): void
    {
        $this->form->fill([
            'texto' => '',
        ]);

        $this->sending = false;

        Notification::make()
            ->title('Cancelado')
            ->body('Se ha limpiado el formulario.')
            ->info()
            ->send();
    }

    public function enviar(?string $lat = null, ?string $lng = null): void
    {
        if (blank($lat) || blank($lng)) {
            Notification::make()
                ->title('Ubicación GPS requerida')
                ->body('Debes permitir el acceso a la ubicación para enviar el reporte.')
                ->danger()
                ->send();

            $this->sending = false;

            return;
        }

        $data = $this->form->getState();
        $texto = trim((string) ($data['texto'] ?? ''));

        if ($texto === '') {
            Notification::make()
                ->title('Texto requerido')
                ->body('Debes escribir un texto antes de enviar.')
                ->warning()
                ->send();

            $this->sending = false;

            return;
        }

        $report = PuntoComercialReport::create([
            'team_leader_id' => auth()->id(),
            'report_date' => today(),
            'texto' => $texto,
            'lat' => $lat,
            'lng' => $lng,
            'submitted_at' => now(),
        ]);

        PuntoComercialEnviado::dispatch($report);

        $this->form->fill([
            'texto' => '',
        ]);

        $this->sending = false;

        Notification::make()
            ->title('Enviado')
            ->body('Punto comercial registrado y notificado al gerente.')
            ->success()
            ->send();
    }

    public function getTodayLabelProperty(): string
    {
        return Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY');
    }
}
