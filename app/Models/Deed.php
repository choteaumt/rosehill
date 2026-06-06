<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deed extends Model
{
    use HasFactory;

    protected $fillable = [
        'cemetery_id',
        'lot',
        'block',
        'grantor_name',
        'grantee_name',
        'deed_date',
        'notes',
    ];

    protected $casts = [
        'deed_date' => 'date',
    ];

    public function cemetery(): BelongsTo
    {
        return $this->belongsTo(Cemetery::class);
    }

    public function interments(): HasMany
    {
        return $this->hasMany(Interment::class);
    }
}
