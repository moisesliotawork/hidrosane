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
use Illuminate\Support\Facades\DB;

class SuperAsignarResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Super_Asignar';

    protected static ?string $modelLabel = 'Asignación';

    protected static ?string $pluralModelLabel = 'Super_Asignar';

    protected static ?string $slug = 'super-asignar';

    protected static ?int $navigationSort = 4;

    public const MAX_SELECTED_NOTES = 10;

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

    /**
     * @return list<string>
     */
    public static function parseNroNotaInputs(string $value): array
    {
        $parts = preg_split('/[\s,;]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $normalized = [];

        foreach ($parts as $part) {
            $nro = self::normalizeNroNota($part);

            if ($nro !== '') {
                $normalized[] = $nro;
            }
        }

        return array_values(array_unique($normalized));
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

        $notes = self::notesForCustomers($customers);

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

    /**
     * @return array{
     *     notes: Collection<int, Note>,
     *     customers: Collection<int, Customer>,
     *     message: ?string
     * }
     */
    public static function findNotesByCustomerName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return [
                'notes' => collect(),
                'customers' => collect(),
                'message' => 'Introduce el nombre del cliente.',
            ];
        }

        if (mb_strlen($name) < 3) {
            return [
                'notes' => collect(),
                'customers' => collect(),
                'message' => 'Introduce al menos 3 caracteres del nombre del cliente.',
            ];
        }

        $parts = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        $customers = Customer::query()
            ->where(function (Builder $query) use ($firstName, $lastName, $name): void {
                if ($firstName !== '') {
                    $query->where('first_names', 'like', "%{$firstName}%");
                }

                if ($lastName !== '') {
                    $query->orWhere('last_names', 'like', "%{$lastName}%");
                }

                $query->orWhereRaw(
                    "CONCAT(COALESCE(first_names, ''), ' ', COALESCE(last_names, '')) LIKE ?",
                    ["%{$name}%"],
                );
            })
            ->orderBy('first_names')
            ->orderBy('last_names')
            ->limit(50)
            ->get()
            ->unique('id')
            ->values();

        if ($customers->isEmpty()) {
            return [
                'notes' => collect(),
                'customers' => collect(),
                'message' => "No se encontró ningún cliente con el nombre \"{$name}\".",
            ];
        }

        $notes = self::notesForCustomers($customers);

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

    /**
     * @param  Collection<int, Customer>  $customers
     * @return Collection<int, Note>
     */
    public static function notesForCustomers(Collection $customers): Collection
    {
        if ($customers->isEmpty()) {
            return collect();
        }

        return Note::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->with(self::noteEagerLoads())
            ->orderByDesc('created_at')
            ->get();
    }

    public static function formatCustomerName(?Customer $customer): string
    {
        if (! $customer) {
            return '—';
        }

        return strtoupper(trim("{$customer->first_names} {$customer->last_names}"));
    }

    /**
     * @param  Collection<int, Customer>  $customers
     */
    public static function customersLabel(Collection $customers): string
    {
        return $customers
            ->map(fn (Customer $customer): string => self::formatCustomerName($customer))
            ->unique()
            ->values()
            ->implode(' · ');
    }

    /**
     * @param  Collection<int, Customer>  $customers
     */
    public static function customersPhonesLabel(Collection $customers): string
    {
        return $customers
            ->flatMap(function (Customer $customer): array {
                $phones = array_filter([
                    $customer->phone1_commercial,
                    $customer->phone,
                    $customer->phone2_commercial,
                ]);

                return array_map(
                    fn (?string $phone): string => self::formatPhoneDisplay($phone),
                    $phones,
                );
            })
            ->unique()
            ->values()
            ->implode(' · ');
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
                ->hint('Obligatoria para RETÉN. Si se deja vacía, se usará la fecha actual.')
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
                $assignmentDate = Note::normalizeCommercialAssignmentDate($data['assignment_date'] ?? null);

                $updates = [
                    'reten' => true,
                    'assignment_date' => $assignmentDate,
                ];

                if ($record->estado_terminal === EstadoTerminal::SALA) {
                    $updates['estado_terminal'] = EstadoTerminal::SIN_ESTADO->value;
                    $updates['fecha_declaracion'] = null;
                    $updates['sent_to_sala_at'] = null;
                }

                $record->update($updates);

                if ($notify) {
                    Notification::make()
                        ->title("Nota #{$record->nro_nota} enviada a COMERCIAL RETÉN")
                        ->body('Fecha de asignación: ' . $assignmentDate->format('d/m/Y'))
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

    /**
     * @param  Collection<int, Note>  $notes
     */
    public static function applyBulkAssignment(Collection $notes, array $data): int
    {
        if ($notes->isEmpty()) {
            return 0;
        }

        $count = 0;

        DB::transaction(function () use ($notes, $data, &$count): void {
            foreach ($notes as $note) {
                self::applyAssignment($note, $data, notify: false);
                $count++;
            }
        });

        $destination = match ($data['comercial_id'] ?? null) {
            '__RETEN__' => 'COMERCIAL RETÉN',
            null, '' => 'sin asignar',
            default => 'comercial seleccionado',
        };

        Notification::make()
            ->title("{$count} nota(s) reasignada(s) correctamente")
            ->body("Destino: {$destination}")
            ->success()
            ->send();

        return $count;
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
