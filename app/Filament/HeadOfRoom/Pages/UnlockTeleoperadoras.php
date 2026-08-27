<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource;
use App\Support\HeadOfRoom\TeleoperadorasAccess;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class UnlockTeleoperadoras extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Acceso Teleoperadoras';

    protected static ?string $title = 'Acceso a Teleoperadoras';

    protected static ?string $slug = 'teleoperadoras-acceso';

    protected static string $view = 'filament.head-of-room.pages.unlock-teleoperadoras';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array{clave?: string}|null */
    public ?array $data = [];

    public function mount(): void
    {
        if (TeleoperadorasAccess::isUnlocked()) {
            $this->redirect(TeleoperadoraResource::getUrl('index'));
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('clave')
                    ->label('Clave de acceso')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('off')
                    ->autofocus()
                    ->helperText('Introduce la clave para ver Teleoperadoras.'),
            ])
            ->statePath('data');
    }

    public function unlock(): void
    {
        $clave = trim((string) ($this->form->getState()['clave'] ?? ''));

        if ($clave !== TeleoperadorasAccess::PIN) {
            Notification::make()
                ->title('Clave incorrecta')
                ->body('No se puede acceder a Teleoperadoras.')
                ->danger()
                ->send();

            $this->data['clave'] = '';

            return;
        }

        TeleoperadorasAccess::unlock();

        Notification::make()
            ->title('Acceso concedido')
            ->success()
            ->send();

        $this->redirect(TeleoperadoraResource::getUrl('index'));
    }
}
