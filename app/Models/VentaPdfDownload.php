<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Copia archivada (en segundo plano) de un PDF de contrato en el momento en que
 * se descarga desde el botón "Contrato PDF" / "Descargar Contr-B". Solo visible
 * en SuperAdmin (tabla "PDF DESCARGADOS"); el panel de Admin no expone este registro.
 *
 * @property int $id
 * @property int $venta_id
 * @property int|null $user_id
 * @property string|null $tipo
 * @property string $origen
 * @property string $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class VentaPdfDownload extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'venta_id',
        'user_id',
        'tipo',
        'origen',
        'file_path',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
