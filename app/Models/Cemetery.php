<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cemetery extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'county',
        'state',
        'address',
        'notes',
    ];

    public function interments(): HasMany
    {
        return $this->hasMany(Interment::class);
    }

    public function deeds(): HasMany
    {
        return $this->hasMany(Deed::class);
    }
}
