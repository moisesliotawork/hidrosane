<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\CreamDailyControl;
use Carbon\Carbon;
use App\Enums\EstadoTerminal;
use Filament\Models\Contracts\HasName;


/**
 * @property int $id
 * @property string|null $empleado_id 
 * @property string $name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $alta_empleado
 * @property-read \App\Models\Team|null $currentTeam
 * @property-read bool $phones_visible
 * @property-read string|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $ledTeams
 * @property-read int|null $led_teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $managedSalesTeams
 * @property-read int|null $managed_sales_teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Note> $notes
 * @property-read int|null $notes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Note> $notesComercial
 * @property-read int|null $notes_comercial_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAltaEmpleado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmpleadoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'empleado_id',
        'last_name',
        'email',
        'alta_empleado',
        'password',
        'phone',
        'direccion',
        'baja',
        'dni',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'alta_empleado' => 'datetime',
        'baja' => 'datetime',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $panelId = $panel->getId();

        // TODOS pueden llegar al formulario (y a /admin).
        if ($panelId === 'admin') {
            return true;      // ó  $this->hasAnyRole([...roles...]);
        }

        // Para los demás paneles mantén el filtrado estricto
        return match ($panelId) {
            'comercial' => $this->hasRole('commercial') || $this->hasRole('team_leader') || $this->hasRole('sales_manager'),
            'teleoperador' => $this->hasRole('teleoperator'),
            'jefe-sala' => $this->hasRole('head_of_room'),
            'gerente' => $this->hasRole('gerente_general'),
            'repartidor' => $this->hasRole('delivery'),
            'superAdmin' => $this->hasRole('app_support'),
            default => false,
        };
    }


    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    // Agrega estas relaciones al modelo User
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'user_team')
            ->withTimestamps()
            ->withPivot(['joined_at', 'is_active']);
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'team_leader_id');
    }

    public function managedSalesTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'sales_manager_id');
    }

    public function getRoleAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    public function getPhonesVisibleAttribute(): bool
    {
        return $this->notes()->where('show_phone', true)->exists();
    }

    public function notesComercial(): HasMany
    {
        return $this->hasMany(Note::class, 'comercial_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->empleado_id} - {$this->name} {$this->last_name}";
    }

    public function canSeeVipSources(): bool
    {
        // Aseguramos que empleado_id sea string
        $empleadoId = (string) $this->empleado_id;

        return $this->hasRole('head_of_room')
            || in_array($empleadoId, ['020', '023', '027'], true);
    }


    public function workSessions(): HasMany
    {
        return $this->hasMany(\App\Models\WorkSession::class);
    }

    public function lastClosedWorkSession(): HasOne
    {
        return $this->hasOne(\App\Models\WorkSession::class)
            ->whereNotNull('end_time')
            ->latest('updated_at'); // la más reciente por updated_at
    }

    public function getLastClosedWorkSession()
    {
        return $this->workSessions()
            ->whereNotNull('end_time')
            ->orderByDesc('updated_at')
            ->first();
    }

    public function creamDailyControls(): HasMany
    {
        return $this->hasMany(CreamDailyControl::class, 'comercial_id');
    }

    /**
     * Máximo de cremas que se pueden asignar para una fecha concreta
     * según el día anterior: max = assigned(día anterior) - delivered(día anterior).
     *
     * Si no hay registro del día anterior, tú decides la política (aquí devuelvo null).
     */
    public function maxCremasAsignablesParaFecha(Carbon|string $fecha): ?int
    {
        $date = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
        $ayer = $date->copy()->subDay()->toDateString();

        $controlAyer = $this->creamDailyControls()
            ->where('date', $ayer)
            ->first();

        if (!$controlAyer) {
            return null; // o un número alto, según tu lógica de negocio
        }

        return max(0, (int) $controlAyer->remaining);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->last_name}");
    }

    public function notasDeclaradas()
    {
        return $this->hasMany(\App\Models\Note::class, 'comercial_id');
    }
    public function notasTeleoperadora()
    {
        return $this->hasMany(\App\Models\Note::class, 'user_id');
    }

    public function getNotasOficinaHoyAttribute(): string
    {
        $today = now()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'latestSalaObservation',
                'latestSalaEvent.sentBy',
            ])
            ->whereDate('fecha_declaracion', $today)
            ->where('estado_terminal', EstadoTerminal::SALA->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;
            $obs = $note->latestSalaObservation;
            $event = $note->latestSalaEvent;

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($obs?->observation) {
                $msg .= "Observación sala: {$obs->observation}\n";
            }

            if ($event) {
                $via = $event->via ?? 'N/D';
                $sent = $event->sent_at?->format('d/m/Y H:i') ?? 'N/D';
                $by = $event->sentBy?->display_name ?? 'N/D';

                $msg .= "Enviado por: {$by} vía {$via} el {$sent}\n";
            }

            return trim($msg);
        });

        // Un único string, con una línea en blanco entre cada nota
        return $mensajes->implode("\n\n");
    }

    public function getNotasNuloHoyAttribute(): string
    {
        $today = now()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with(['customer', 'comercial', 'nullReason', 'nullReason.companion'])
            ->whereDate('fecha_declaracion', $today)
            ->where('estado_terminal', EstadoTerminal::NUL->value)
            ->get();


        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;
            $nullReason = $note->nullReason;

            // Cabecera simplificada sin Markdown
            $msg = "NOTA NULA ⛔\n";
            $msg .= "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if ($customer->phone) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
                $companionLabel = $nullReason?->companion?->display_name ?? '—';
                $msg .= "Compañero: {$companionLabel}\n";

            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($nullReason?->reason) {
                $msg .= "Motivo: {$nullReason->reason}\n";
            }

            return trim($msg);
        });

        // Un solo string separado por dos saltos de línea
        return $mensajes->implode("\n\n");
    }

    public function getNotasAusenteHoyAttribute(): string
    {
        $today = now()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'ausencias.autor',
            ])
            ->whereDate('fecha_declaracion', $today)
            ->where('estado_terminal', EstadoTerminal::AUSENTE->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;

            // Tomamos la última AUSENCIA por fecha + hora
            $hist = $note->ausencias
                ->sortByDesc('fecha')
                ->sortByDesc('hora')
                ->first();

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($hist) {
                $fecha = $hist->fecha?->format('d/m/Y') ?? 'N/D';
                $hora = $hist->hora ?? 'N/D';
                $autor = $hist->autor?->display_name ?? 'N/D';

                $msg .= "Último registro ausente: {$fecha} {$hora}\n";

                if (!empty($hist->observacion)) {
                    $msg .= "Observación: {$hist->observacion}\n";
                }

                $msg .= "Registrado por: {$autor}\n";
            }

            return trim($msg);
        });

        // Un solo string, cada nota separada por una línea en blanco
        return $mensajes->implode("\n\n");
    }

    public function getNotasConfirmadoHoyAttribute(): string
    {
        $today = now()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'confirmations.author',
                'confirmations.companion',
            ])
            ->whereDate('fecha_declaracion', $today)
            ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;

            // última confirmación (por created_at)
            $conf = $note->confirmations
                ->sortByDesc('created_at')
                ->first();

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($conf) {
                $dioCrema = $conf->dio_crema ? 'Sí' : 'No';
                $autor = $conf->author?->display_name ?? 'N/D';

                $msg .= "Dio crema: {$dioCrema}\n";

                if (!empty($conf->observation)) {
                    $msg .= "Observación: {$conf->observation}\n";
                }

                $msg .= "Confirmado por: {$autor}\n";
                $companionLabel = $conf?->companion?->display_name ?? '—';
                $msg .= "Compañero: {$companionLabel}\n";

            }

            return trim($msg);
        });

        // Único string, separado por líneas en blanco entre notas
        return $mensajes->implode("\n\n");
    }

    public function getNotasVentaHoyAttribute(): string
    {
        $today = now()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'venta.customer',
                'venta.companion',
                'venta.ventaOfertas',
            ])
            ->whereDate('fecha_declaracion', $today)
            ->where('estado_terminal', EstadoTerminal::VENTA->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;
            $venta = $note->venta;

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($venta) {
                $msg .= "Compañero: {$venta->companion_label}\n";

                // Fecha de venta
                if ($venta->fecha_venta) {
                    $msg .= "Fecha venta: " . $venta->fecha_venta->format('d/m/Y H:i') . "\n";
                }

                // Resumen económico
                $importeTotalF = number_format((float) $venta->importe_total, 2, ',', '.');
                $numOfertas = $venta->ventaOfertas->count();
                $numCuotas = $venta->num_cuotas;
                $cuotaMensual = $venta->cuota_mensual;

                $msg .= "Importe: {$importeTotalF} €\n";
                $msg .= "Ofertas incluidas: {$numOfertas}\n";

                if ($numCuotas) {
                    $msg .= "Nº de cuotas: {$numCuotas}\n";
                }

                if (!is_null($cuotaMensual)) {
                    $cuotaMensualF = number_format((float) $cuotaMensual, 2, ',', '.');
                    $msg .= "Cuota mensual: {$cuotaMensualF} €\n";

                    if ($numCuotas) {
                        $msg .= "Operación: {$numCuotas} x {$cuotaMensualF} € = {$importeTotalF} €\n";
                    }
                }

                // Documentos subidos (sin bullets para hacerlo más compacto)
                $documentos = [
                    'precontractual' => 'Precontractual',
                    'dni_anverso' => 'DNI – Anverso',
                    'dni_reverso' => 'DNI – Reverso',
                    'documento_titularidad' => 'Documento titularidad',
                    'nomina' => 'Nómina',
                    'pension' => 'Pensión',
                    'contrato_firmado' => 'Contrato Firmado',
                    'otros_documentos' => 'Otros Documentod'
                ];

                $subidos = [];

                foreach ($documentos as $field => $label) {
                    if (!empty($venta->$field)) {
                        $subidos[] = $label;
                    }
                }

                $msg .= "Documentos subidos: ";

                if (!empty($subidos)) {
                    $msg .= implode(', ', $subidos) . "\n";
                } else {
                    $msg .= "Ninguno\n";
                }
            }

            return trim($msg);
        });

        // Único string, cada venta separada por una línea en blanco
        return $mensajes->implode("\n\n");
    }

    public function getNotasOficinaAyerAttribute(): string
    {
        $date = now()->subDay()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'latestSalaObservation',
                'latestSalaEvent.sentBy',
            ])
            ->whereDate('fecha_declaracion', $date)
            ->where('estado_terminal', EstadoTerminal::SALA->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;
            $obs = $note->latestSalaObservation;
            $event = $note->latestSalaEvent;

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($obs?->observation) {
                $msg .= "Observación sala: {$obs->observation}\n";
            }

            if ($event) {
                $via = $event->via ?? 'N/D';
                $sent = $event->sent_at?->format('d/m/Y H:i') ?? 'N/D';
                $by = $event->sentBy?->display_name ?? 'N/D';

                $msg .= "Enviado por: {$by} vía {$via} el {$sent}\n";
            }

            return trim($msg);
        });

        return $mensajes->implode("\n\n");
    }

    public function getNotasNuloAyerAttribute(): string
    {
        $date = now()->subDay()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with(['customer', 'comercial', 'nullReason', 'nullReason.companion'])
            ->whereDate('fecha_declaracion', $date)
            ->where('estado_terminal', EstadoTerminal::NUL->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;
            $nullReason = $note->nullReason;

            $msg = "NOTA NULA ⛔\n";
            $msg .= "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if ($customer->phone) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
                $companionLabel = $nullReason?->companion?->display_name ?? '—';
                $msg .= "Compañero: {$companionLabel}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($nullReason?->reason) {
                $msg .= "Motivo: {$nullReason->reason}\n";
            }

            return trim($msg);
        });

        return $mensajes->implode("\n\n");
    }

    public function getNotasAusenteAyerAttribute(): string
    {
        $date = now()->subDay()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'ausencias.autor',
            ])
            ->whereDate('fecha_declaracion', $date)
            ->where('estado_terminal', EstadoTerminal::AUSENTE->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;

            $hist = $note->ausencias
                ->sortByDesc('fecha')
                ->sortByDesc('hora')
                ->first();

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($hist) {
                $fecha = $hist->fecha?->format('d/m/Y') ?? 'N/D';
                $hora = $hist->hora ?? 'N/D';
                $autor = $hist->autor?->display_name ?? 'N/D';

                $msg .= "Último registro ausente: {$fecha} {$hora}\n";

                if (!empty($hist->observacion)) {
                    $msg .= "Observación: {$hist->observacion}\n";
                }

                $msg .= "Registrado por: {$autor}\n";
            }

            return trim($msg);
        });

        return $mensajes->implode("\n\n");
    }

    public function getNotasConfirmadoAyerAttribute(): string
    {
        $date = now()->subDay()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'confirmations.author',
                'confirmations.companion',
            ])
            ->whereDate('fecha_declaracion', $date)
            ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;

            $conf = $note->confirmations
                ->sortByDesc('created_at')
                ->first();

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($conf) {
                $dioCrema = $conf->dio_crema ? 'Sí' : 'No';
                $autor = $conf->author?->display_name ?? 'N/D';

                $msg .= "Dio crema: {$dioCrema}\n";

                if (!empty($conf->observation)) {
                    $msg .= "Observación: {$conf->observation}\n";
                }

                $msg .= "Confirmado por: {$autor}\n";
                $companionLabel = $conf?->companion?->display_name ?? '—';
                $msg .= "Compañero: {$companionLabel}\n";

            }

            return trim($msg);
        });

        return $mensajes->implode("\n\n");
    }
    public function getNotasVentaAyerAttribute(): string
    {
        $date = now()->subDay()->toDateString();

        $notas = $this->notasDeclaradas()
            ->with([
                'customer',
                'comercial',
                'venta.customer',
                'venta.companion',
                'venta.ventaOfertas',
            ])
            ->whereDate('fecha_declaracion', $date)
            ->where('estado_terminal', EstadoTerminal::VENTA->value)
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $mensajes = $notas->map(function (Note $note) {
            $customer = $note->customer;
            $com = $note->comercial;
            $venta = $note->venta;

            $msg = "Nota: #{$note->nro_nota}\n";

            if ($customer) {
                $msg .= "Cliente: {$customer->first_names} {$customer->last_names}\n";
                if (!empty($customer->phone)) {
                    $msg .= "Teléfono: {$customer->phone}\n";
                }
            }

            if ($com) {
                $msg .= "Comercial: {$com->display_name}\n";
            }

            if ($note->fecha_declaracion) {
                $msg .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
            }

            if ($venta) {
                $msg .= "Compañero: {$venta->companion_label}\n";

                if ($venta->fecha_venta) {
                    $msg .= "Fecha venta: " . $venta->fecha_venta->format('d/m/Y H:i') . "\n";
                }

                $importeTotalF = number_format((float) $venta->importe_total, 2, ',', '.');
                $numOfertas = $venta->ventaOfertas->count();
                $numCuotas = $venta->num_cuotas;
                $cuotaMensual = $venta->cuota_mensual;

                $msg .= "Importe: {$importeTotalF} €\n";
                $msg .= "Ofertas incluidas: {$numOfertas}\n";

                if ($numCuotas) {
                    $msg .= "Nº de cuotas: {$numCuotas}\n";
                }

                if (!is_null($cuotaMensual)) {
                    $cuotaMensualF = number_format((float) $cuotaMensual, 2, ',', '.');
                    $msg .= "Cuota mensual: {$cuotaMensualF} €\n";

                    if ($numCuotas) {
                        $msg .= "Operación: {$numCuotas} x {$cuotaMensualF} € = {$importeTotalF} €\n";
                    }
                }

                $documentos = [
                    'precontractual' => 'Precontractual',
                    'dni_anverso' => 'DNI – Anverso',
                    'dni_reverso' => 'DNI – Reverso',
                    'documento_titularidad' => 'Documento titularidad',
                    'nomina' => 'Nómina',
                    'pension' => 'Pensión',
                    'contrato_firmado' => 'Contrato Firmado',
                    'otros_documentos' => 'Otros Documentos'
                ];

                $subidos = [];

                foreach ($documentos as $field => $label) {
                    if (!empty($venta->$field)) {
                        $subidos[] = $label;
                    }
                }

                $msg .= "Documentos subidos: ";

                if (!empty($subidos)) {
                    $msg .= implode(', ', $subidos) . "\n";
                } else {
                    $msg .= "Ninguno\n";
                }
            }

            return trim($msg);
        });

        return $mensajes->implode("\n\n");
    }

    public function getFilamentName(): string
    {
        return $this->name; // usa tu accessor getDisplayNameAttribute()
    }


}
