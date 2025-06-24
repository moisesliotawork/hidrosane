<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_names',
        'last_names',
        'phone',
        'secondary_phone',
        'email',
        'age',
        'postal_code_id',
        'primary_address',
        'secondary_address',
        'parish',
    ];

    public function name(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->first_names . ' ' . $this->last_names,
        );
    }

    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class);
    }
}
