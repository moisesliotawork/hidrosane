<?php

namespace App\Filament\Admin\Pages;

use App\Models\City;
use App\Models\PostalCode;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UbicacionWizard extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Ubicación (Prov/Ciudad/CP)';
    protected static ?string $title = 'Seleccionar o crear Ubicación';
    protected static ?string $slug = 'ubicacion-wizard';
    protected static string $view = 'filament.admin.pages.ubicacion-wizard';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // PROVINCIA
                Forms\Components\Select::make('state_id')
                    ->label('Provincia')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn() => State::query()->orderBy('title')->pluck('title', 'id')),

                // CIUDAD (texto)
                Forms\Components\TextInput::make('city_title')
                    ->label('Ciudad')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ej: A Coruña')
                    ->dehydrateStateUsing(fn($state) => trim((string) $state))
                    ->dehydrated(true),

                // CÓDIGO POSTAL (texto)
                Forms\Components\TextInput::make('postal_code_code')
                    ->label('Código postal')
                    ->required()
                    ->maxLength(20)
                    ->placeholder('Ej: 15008')
                    ->dehydrateStateUsing(fn($state) => trim((string) $state))
                    ->dehydrated(true),
            ])
            ->statePath('data')
            ->model(State::class)
            ->columns(1);
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Guardar')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $stateId = data_get($this->data, 'state_id');
        $cityRaw = (string) data_get($this->data, 'city_title');
        $codeRaw = (string) data_get($this->data, 'postal_code_code');

        $cityTitle = trim($cityRaw);
        $code = trim($codeRaw);

        if (blank($stateId) || $cityTitle === '' || $code === '') {
            Notification::make()
                ->title('Faltan datos')
                ->body('Selecciona provincia y escribe ciudad y código postal.')
                ->danger()->send();
            return;
        }

        DB::transaction(function () use ($stateId, $cityTitle, $code) {
            // 1) Buscar/crear ciudad por (state_id, title) case-insensitive
            $city = City::query()
                ->where('state_id', $stateId)
                ->whereRaw('LOWER(title) = ?', [Str::lower($cityTitle)])
                ->first();

            if (!$city) {
                $city = City::create([
                    'title' => $cityTitle,
                    'state_id' => $stateId,
                ]);
            }

            // 2) Verificar si ya existe el CP para esa ciudad
            $exists = PostalCode::query()
                ->where('city_id', $city->id)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                Notification::make()
                    ->title('Ya existe')
                    ->body("La ciudad «{$city->title}» ya tiene el código postal {$code}.")
                    ->warning()->send();
                return;
            }

            // 3) Crear CP asociado a la ciudad
            $pc = PostalCode::create([
                'code' => $code,
                'city_id' => $city->id,
            ]);

            Notification::make()
                ->title('Creado correctamente')
                ->body("Ciudad: {$city->title} • CP: {$pc->code}")
                ->success()->send();
        });

        $this->form->fill([]);
    }
}
