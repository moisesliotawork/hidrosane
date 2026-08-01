<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $nro_contr_adm
 * @property int|null $created_by_user_id
 */
class ContratoRecuperado extends Model
{
    protected $table = 'contratos_recuperados';

    protected $fillable = [
        'nro_contr_adm',
        'created_by_user_id',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
