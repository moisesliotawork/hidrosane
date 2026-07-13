<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\EstadoTerminal;
use App\Filament\SuperAdmin\Resources\SuperAsignarResource\Pages;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Support\TeleoperatorCustomerNoteGuard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SuperAsignarResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Super_Asignar';

    protected static ?string $modelLabel = 'Asignación';

    protected static ?string $pluralModelLabel = 'Super_Asignar';

    protected static ?string $slug = 'super-asignar';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function normalizeNroNota(string $value): string
    {
        $value = trim($value);
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits !== '') {
            return str_pad($digits, 5, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    public static function formatNroNota(?string $value): string
    {
        $value = (string) $value;

        if (strlen($value) === 5) {
            return substr($value, 0, 3) . ' ' . substr($value, 3, 2);
        }

        return $value;
    }

    public static function formatPhoneDisplay(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) !== 9) {
            return trim((string) $phone);
        }

        return implode(' ', str_split($digits, 3));
    }

    /**
     * @return array{
     *     notes: Collection<int, Note>,
     *     customers: Collection<int, Customer>,
     *     message: ?string
     * }
     */
    public static function findNotesByPhone(string $phone): array
    {
        $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($phone);

        if ($digits === null) {
            return [
                'notes' => collect(),
                'customers' => collect(),
                'message' => 'Introduce un teléfono válido de 9 dígitos.',
            ];
        }

        $customers = app(TeleoperatorCustomerNoteGuard::class)
            ->resolveCustomersForPhone($digits)
            ->unique('id')
            ->values();

        if ($customers->isEmpty()) {
            return [
                'notes' => collect(),
                'customers' => collect(),
                'message' => "No se encontró ningún cliente con el teléfono {$digits}.",
            ];
        }

        $notes = Note::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->with([
                'customer:id,first_names,last_names,phone,phone1_commercial,phone2_commercial,postal_code',
                'comercial:id,name,last_name,empleado_id',
                'user:id,empleado_id,name,last_name',
            ])
            ->orderByDesc('created_at')
            ->get();

        if ($notes->isEmpty()) {
            return [
                'notes' => collect(),
                'customers' => $customers,
                'message' => 'El cliente existe pero no tiene notas registradas.',
            ];
        }

        return [
            'notes' => $notes,
            'customers' => $customers,
            'message' => null,
        ];
    }

    public static function noteEagerLoads(): array
    {
        return [
            'customer:id,first_names,last_names,phone,phone1_commercial,phone2_commercial,postal_code',
            'comercial:id,name,last_name,empleado_id',
            'user:id,empleado_id,name,last_name',
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function assignmentFormSchema(): array
    {
        return [
            Forms\Components\Select::make('comercial_id')
                ->label('Asignar a')
                ->options(fn (): array => [
                    '__RETEN__' => 'COMERCIAL RETÉN',
                    null => 'Sin asignar',
                ] + self::assignableUserOptions(labeled: true))
                ->searchable()
                ->native(false)
                ->placeholder('Selecciona comercial, jefe de equipo o retén'),

            Forms\Components\DatePicker::make('assignment_date')
                ->label('Fecha de asignación')
                ->hint('Si se deja vacío, se usará la fecha actual al asignar un comercial')
                ->native(false)
                ->displayFormat('d/m/Y'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public static function assignableUserOptions(bool $labeled = false): array
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.last_name', 'users.empleado_id')
            ->with(['roles:id,name'])
            ->role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja')
            ->orderBy('empleado_id')
            ->get()
            ->unique('id')
            ->mapWithKeys(function (User $user) use ($labeled): array {
                if (! $labeled) {
                    return [
                        $user->id => "{$user->empleado_id} {$user->name} {$user->last_name}",
                    ];
                }

                $hasTl = $user->roles->contains('name', 'team_leader');
                $hasCom = $user->roles->contains('name', 'commercial');
                $hasJv = $user->roles->contains('name', 'sales_manager');

                $tag = match (true) {
                    $hasJv && $hasTl => 'JV/TL',
                    $hasTl && $hasCom => 'TL/COM',
                    $hasTl => 'TL',
                    $hasJv => 'JV',
                    default => 'COM',
                };

                return [
                    $user->id => "{$user->empleado_id} {$user->name} {$user->last_name} ({$tag})",
                ];
            })
            ->all();
    }

    public static function applyAssignment(Note $record, array $data, bool $notify = true): void
    {
        try {
            $choice = $data['comercial_id'] ?? null;

            if ($choice === '__RETEN__') {
                $record->update(['reten' => true]);

                if ($notify) {
                    Notification::make()
                        ->title("Nota #{$record->nro_nota} enviada a COMERCIAL RETÉN")
                        ->success()
                        ->send();
                }

                return;
            }

            if (blank($choice)) {
                $record->update([
                    'comercial_id' => null,
                    'assignment_date' => null,
                    'reten' => false,
                ]);

                if ($notify) {
                    Notification::make()
                        ->title("Asignación removida de la nota #{$record->nro_nota}")
                        ->success()
                        ->send();
                }

                return;
            }

            $isValid = User::query()
                ->where('id', $choice)
                ->whereNull('baja')
                ->whereHas('roles', fn (Builder $r) => $r->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
                ->exists();

            if (! $isValid) {
                throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
            }

            $assignmentDate = Note::normalizeCommercialAssignmentDate($data['assignment_date'] ?? null);

            $updates = [
                'comercial_id' => (int) $choice,
                'assignment_date' => $assignmentDate,
                'reten' => false,
            ];

            if ($record->estado_terminal === EstadoTerminal::SALA) {
                $updates['estado_terminal'] = EstadoTerminal::SIN_ESTADO->value;
                $updates['fecha_declaracion'] = null;
                $updates['sent_to_sala_at'] = null;
            }

            $record->update($updates);

            if ($notify) {
                $user = User::find($choice);

                Notification::make()
                    ->title("Nota #{$record->nro_nota} asignada correctamente")
                    ->body($user
                        ? trim("{$user->empleado_id} {$user->name} {$user->last_name}") . ' · ' . $assignmentDate->format('d/m/Y')
                        : null)
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Error al asignar la nota')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            throw $e;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSuperAsignar::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
