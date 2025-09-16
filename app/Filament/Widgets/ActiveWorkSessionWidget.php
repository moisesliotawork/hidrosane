<?php

namespace App\Filament\Widgets;

use App\Models\WorkSession;
use Filament\Forms\Components\Actions\Action;
use Filament\Widgets\Widget;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ActiveWorkSessionWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.active-work-session-widget';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $this->loadActiveSession();
    }

    protected function loadActiveSession(): void
    {
        $activeSession = WorkSession::with('user')
            ->where('user_id', Auth::id())
            ->active()
            ->where('panel_id', \Filament\Facades\Filament::getCurrentPanel()->getId())
            ->first();

        $this->data = $activeSession ? [
            'start_time' => $activeSession->start_time->format('Y-m-d H:i:s'),
            'duration' => $activeSession->start_time->diffForHumans(),
            'ip_address' => $activeSession->ip_address,
            'location' => $activeSession->latitude && $activeSession->longitude
                ? $activeSession->latitude . ', ' . $activeSession->longitude
                : 'Ubicación no disponible',
        ] : [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Section::make('Sesión de Trabajo Activa')
                    ->description($this->data ? 'Sesión iniciada el ' . $this->data['start_time'] : 'No hay sesión activa')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('duration')
                            ->label('Tiempo activa')
                            ->disabled()
                            ->placeholder(fn() => $this->data ? null : 'No hay sesión activa')
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('end_session')
                                ->label('Finalizar Sesión')
                                ->color('danger')
                                ->icon('heroicon-o-x-circle')
                                ->visible(fn() => !empty($this->data))
                                ->action(function () {
                                    $user = Auth::user();

                                    \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
                                        // Cerrar TODAS las sesiones activas del usuario en cualquier panel
                                        $closed = \App\Models\WorkSession::where('user_id', $user->id)
                                            ->whereNull('end_time')
                                            ->update(['end_time' => now()]);

                                        // Marcar al usuario como inactivo (sin importar en qué panel estaba)
                                        \App\Models\User::whereKey($user->id)->update(['is_active' => false]);
                                    });

                                    // Refrescar estado del usuario y del widget
                                    $user->refresh();

                                    \Filament\Notifications\Notification::make()
                                        ->title('Sesiones finalizadas')
                                        ->success()
                                        ->body('Se han cerrado todas tus sesiones activas y quedaste inactivo.')
                                        ->send();

                                    $this->loadActiveSession();
                                })


                                ->extraAttributes(['class' => 'w-full'])
                        ])
                            ->alignment('right') // Esto aplica al contenedor Actions
                            ->fullWidth() // Esto aplica al contenedor Actions
                    ])
            ])
            ->statePath('data');
    }

    public static function canView(): bool
    {
        return WorkSession::where('user_id', Auth::id())
            ->active()
            ->where('panel_id', \Filament\Facades\Filament::getCurrentPanel()->getId())
            ->exists();
    }
}
