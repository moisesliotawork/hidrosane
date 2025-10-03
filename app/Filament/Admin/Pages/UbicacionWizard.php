<?php

namespace App\Filament\Admin\Pages;

use App\Models\City;
use App\Models\Country;
use App\Models\PostalCode;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                // ====== PROVINCIA (STATE) ======
                Forms\Components\Select::make('state_id')
                    ->label('Provincia')
                    ->required()
                    ->live()
                    ->searchable()
                    ->preload()
                    ->options(fn() => State::query()
                        ->orderBy('title')
                        ->pluck('title', 'id'))
                    // reset de dependientes
                    ->afterStateUpdated(function (Set $set) {
                        $set('city_id', null);
                        $set('postal_code_id', null);
                    })
                    // Crear provincia en línea: se asocia a España automáticamente
                    ->createOptionForm([
                        Forms\Components\TextInput::make('title')
                            ->label('Nombre de la provincia')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('iso')
                            ->label('ISO (opcional)')
                            ->default("")
                            ->maxLength(10),
                    ])
                    ->createOptionUsing(function (array $data) {
                        // Busca o crea España por ISO o por título
                        $spain = Country::query()
                            ->where('iso', 'ES')
                            ->orWhere('title', 'España')
                            ->first();

                        if (!$spain) {
                            // si por algún motivo no existe, la creamos
                            $spain = Country::create([
                                'title' => 'España',
                                'iso' => 'ES',
                            ]);
                        }

                        return State::create([
                            'title' => $data['title'],
                            'iso' => $data['iso'] ?? null,
                            'country_id' => $spain->id,
                        ])->getKey();
                    }),

                // ====== CIUDAD ======
                Forms\Components\Select::make('city_id')
                    ->label('Ciudad')
                    ->required()
                    ->live()
                    ->disabled(fn(Get $get) => blank($get('state_id')))
                    ->searchable()
                    ->preload()
                    ->options(function (Get $get) {
                        $stateId = $get('state_id');
                        if (blank($stateId)) {
                            return [];
                        }
                        // Filtra ciudades por provincia seleccionada
                        return City::query()
                            ->where('state_id', $stateId)
                            ->orderBy('title')
                            ->pluck('title', 'id');
                    })
                    ->afterStateUpdated(function (Set $set) {
                        $set('postal_code_id', null);
                    })
                    // Crear ciudad en línea: se asocia a la provincia seleccionada
                    ->createOptionForm([
                        Forms\Components\TextInput::make('title')
                            ->label('Nombre de la ciudad')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data, Get $get) {
                        $stateId = $get('state_id');
                        if (blank($stateId)) {
                            throw new ModelNotFoundException('Debe seleccionar una provincia antes de crear una ciudad.');
                        }

                        return City::create([
                            'title' => $data['title'],
                            'state_id' => $stateId,
                        ])->getKey();
                    }),

                // ====== CÓDIGO POSTAL (TextInput) ======
                Forms\Components\TextInput::make('postal_code_code')
                    ->label('Código postal')
                    ->required()
                    ->maxLength(20)
                    ->placeholder('Ej: 28001')
                    ->helperText('Se creará (o reutilizará) para la ciudad seleccionada.')
                    // normaliza y GARANTIZA que el valor se mande al estado del form
                    ->dehydrateStateUsing(fn($state) => trim((string) $state))
                    ->dehydrated(true),
            ])
            ->statePath('data')
            ->model(State::class) // no se persiste nada aquí, es para tener un modelo base
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
        $cityId = data_get($this->data, 'city_id');
        $code = trim((string) data_get($this->data, 'postal_code_code'));

        if (blank($stateId) || blank($cityId) || blank($code)) {
            Notification::make()
                ->title('Faltan datos')
                ->body('Selecciona provincia, ciudad y escribe el código postal.')
                ->danger()->send();
            return;
        }

        try {
            DB::beginTransaction();

            $state = State::findOrFail($stateId);
            $city = City::findOrFail($cityId);

            // Garantiza ciudad → provincia
            if ($city->state_id !== (int) $state->id) {
                $city->state_id = $state->id;
                $city->save();
            }

            // ¿Existe (code, city_id) para ESTA ciudad?
            $pc = $city->postalCodes()      // <- usa la relación hasMany de City
                ->where('code', $code)
                ->first();

            if (!$pc) {
                // Crea el CP colgando de la ciudad (Eloquent pone city_id solo)
                $pc = $city->postalCodes()->create([
                    'code' => $code,
                    // 'uid' no hace falta: tu boot() lo autogenera
                ]);
            }

            Log::debug('DEBUG CP', [
                'city_id' => $city->id,
                'code' => $code,
                'pc_id' => $pc?->id,
            ]);

            DB::commit();

            Notification::make()
                ->title('Ubicación guardada')
                ->body("Provincia: {$state->title} • Ciudad: {$city->title} • CP: {$pc->code} (ID {$pc->id})")
                ->success()->send();

            // Limpia el form
            $this->form->fill([]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creando CP', [
                'state_id' => $stateId,
                'city_id' => $cityId,
                'code' => $code,
                'msg' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error al guardar')
                ->body($e->getMessage())
                ->danger()->send();
        }
    }



}
