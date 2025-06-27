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
        'phone'
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $panelId = $panel->getId();

        // Lógica para el panel de admin
        if ($panelId === 'admin') {
            return $this->hasRole('admin');
        }

        // Lógica para el panel de comercial
        if ($panelId === 'comercial') {
            return $this->hasRole('commercial');
        }

        // Lógica para el panel de teleoperador
        if ($panelId === 'teleoperador') {
            return $this->hasRole('teleoperator');
        }

        // Lógica para el panel de jefe de sala
        if ($panelId === 'jefe-sala') {
            return $this->hasRole('head_of_room');
        }

        // Retorna false por defecto si no coincide con ninguno de los paneles
        return false;
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

}
