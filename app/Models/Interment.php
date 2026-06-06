<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cemetery_id',
        'last_name',
        'first_name',
        'age_at_death',
        'age_raw',
        'interment_date',
        'interment_date_raw',
        'lot',
        'lot_number',
        'lot_qualifier',
        'block',
        'block_number',
        'block_suffix',
        'is_veteran',
        'is_cremation',
        'cremation_placement',
        'is_infant',
        'is_disinterment',
        'notes',
        'source_notes_raw',
        'import_source',
        'import_row',
        'deed_id',
        'plot_coordinates',
    ];

    protected $casts = [
        'interment_date'   => 'date',
        'is_veteran'       => 'boolean',
        'is_cremation'     => 'boolean',
        'is_infant'        => 'boolean',
        'is_disinterment'  => 'boolean',
        'plot_coordinates' => 'array',
    ];

    public function cemetery(): BelongsTo
    {
        return $this->belongsTo(Cemetery::class);
    }

    public function deed(): BelongsTo
    {
        return $this->belongsTo(Deed::class);
    }

    // Scopes for common filters

    public function scopeVeteran($query)
    {
        return $query->where('is_veteran', true);
    }

    public function scopeCremation($query)
    {
        return $query->where('is_cremation', true);
    }

    public function scopeInfant($query)
    {
        return $query->where('is_infant', true);
    }

    public function scopeDisinterment($query)
    {
        return $query->where('is_disinterment', true);
    }

    public function scopeForCemetery($query, Cemetery|int $cemetery)
    {
        $id = $cemetery instanceof Cemetery ? $cemetery->id : $cemetery;
        return $query->where('cemetery_id', $id);
    }
}
