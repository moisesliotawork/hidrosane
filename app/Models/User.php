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
class User extends Authenticatable implements FilamentUser
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
            'comercial' => $this->hasRole('commercial') || $this->hasRole('team_leader'),
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
        return $this->hasRole('head_of_room') || $this->empleado_id === '020';
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

}
