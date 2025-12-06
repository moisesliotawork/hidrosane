<?php

namespace App\Filament\Commercial\Resources\NoteResource\Pages;

use App\Filament\Commercial\Resources\NoteResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Models\Note;
use App\Models\CreamDailyControl;
use App\Models\CreamTransfer;
use App\Notifications\CreamTransferRequested;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PedirCrema extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = NoteResource::class;

    // Vista Blade de esta página (la creamos en el siguiente paso)
    protected static string $view = 'filament.commercial.pages.pedir-crema';

    // No la mostramos en el menú lateral
    protected static bool $shouldRegisterNavigation = false;

    public Note $record;
    public ?array $data = [];

    public function mount(Note $record): void
    {
        $this->record = $record;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('to_comercial_id')
                    ->label('¿A qué comercial le pides crema?')
                    ->options(function () {
                        $today = Carbon::today()->toDateString();
                        $user = Auth::user();

                        return CreamDailyControl::query()
                            ->whereDate('date', $today)
                            ->where('remaining', '>', 0)
                            ->whereHas('comercial', function ($q) use ($user) {
                                $q->where('id', '!=', $user->id);
                            })
                            ->with('comercial')
                            ->orderByDesc('remaining')
                            ->get()
                            ->unique('comercial_id')
                            ->mapWithKeys(function (CreamDailyControl $control) {
                                return [
                                    $control->comercial_id =>
                                        $control->comercial->name . " ({$control->remaining} cremas)",
                                ];
                            });
                    })
                    ->required()
                    ->native(false),

                Forms\Components\TextInput::make('amount')
                    ->label('Cantidad de cremas')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $fromUser = Auth::user();
        $today = Carbon::today()->toDateString();

        $toControl = CreamDailyControl::where('comercial_id', $data['to_comercial_id'])
            ->whereDate('date', $today)
            ->firstOrFail();

        $amount = (int) $data['amount'];

        if ($amount <= 0 || $amount > $toControl->remaining) {
            Notification::make()
                ->title('Cantidad inválida')
                ->body('El comercial seleccionado no tiene suficientes cremas disponibles.')
                ->danger()
                ->send();

            return;
        }

        $transfer = CreamTransfer::create([
            'from_comercial_id' => $fromUser->id,
            'to_comercial_id'   => $toControl->comercial_id,
            'date'              => $today,
            'amount'            => $amount,
        ]);

        $toControl->comercial->notify(new CreamTransferRequested($transfer));

        Notification::make()
            ->title('Solicitud enviada')
            ->body('Se envió la solicitud de crema al comercial seleccionado. Debes esperar a que la acepte.')
            ->success()
            ->send();

        // Volver a editar la nota
        $url = NoteResource::getUrl('edit', [
            'record' => $this->record,
        ], panel: 'comercial');

        $this->redirect($url);
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(fn () => NoteResource::getUrl('edit', [
                    'record' => $this->record,
                ], panel: 'comercial')),

            Forms\Components\Actions\Action::make('submit')
                ->label('Enviar solicitud')
                ->submit('submit')
                ->color('primary'),
        ];
    }
}
