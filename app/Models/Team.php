<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property int $sales_manager_id
 * @property int $team_leader_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\User $salesManager
 * @property-read \App\Models\User $teamLeader
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereSalesManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereTeamLeaderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team withoutTrashed()
 * @mixin \Eloquent
 */
class Team extends Model
{

    protected $fillable = [
        'name',
        'description',
        'sales_manager_id',
        'team_leader_id',
        'foto',
        'deleted',
        'history',
    ];

    protected $casts = [
        'deleted' => 'boolean',
        'history' => 'array',
    ];

    public function delete(): bool
    {
        // 1) Capturar estado actual
        $leader = $this->teamLeader;                  // modelo User antes de nullear
        $leaderData = $leader?->toArray();
        $membersData = $this->members->map(fn($u) => $u->toArray())->all();

        // 2) Rellenar history
        $this->history = [
            'deleted_at' => now()->toDateTimeString(),
            'team_leader' => $leaderData,
            'members' => $membersData,
        ];

        // 3) Desvincular miembros
        $this->members()->detach();

        // 4) Marcar borrado y eliminar relación de líder
        $this->deleted = true;
        $this->team_leader_id = null;

        // 5) Guardar cambios
        $ok = $this->save();

        // 6) Si guardó correctamente, retirar rol de team_leader al usuario
        if ($ok && $leader) {
            // Comprobar si aún lidera algún otro equipo NO borrado
            $stillLeads = self::query()
                ->where('team_leader_id', $leader->id)
                ->where('deleted', false)
                ->exists();

            if (!$stillLeads && $leader->hasRole('team_leader')) {
                $leader->removeRole('team_leader');
            }
        }

        return $ok;
    }

    public function salesManager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_team')
            ->withTimestamps()
            ->withPivot(['joined_at', 'is_active']);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    /**
     * Borrado lógico con la misma lógica “inteligente” que usas en Edit:
     * - marca deleted = true
     * - desasocia miembros
     * - si el líder ya no lidera ningún equipo activo, le quita el rol team_leader
     */
    public function safeDelete(): void
    {
        DB::transaction(function () {
            $leader = $this->team_leader_id
                ? User::find($this->team_leader_id)
                : null;

            // 1) marcar borrado lógico
            $this->forceFill(['deleted' => true])->save();

            // 2) desasociar miembros del equipo (puedes usar detach o marcar inactivos)
            $this->members()->detach();
            // Si prefieres mantener histórico: $this->members()->updateExistingPivot($this->members()->pluck('users.id'), ['is_active' => false]);

            // 3) si el líder ya no lidera otro equipo “no borrado”, retirar el rol
            if ($leader) {
                $sigueSiendoLider = static::query()
                    ->where('deleted', false)
                    ->where('team_leader_id', $leader->id)
                    ->exists();

                if (!$sigueSiendoLider && $leader->hasRole('team_leader')) {
                    $leader->removeRole('team_leader');
                }
            }
        });
    }
}