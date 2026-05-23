<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPlan extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(MapElement::class, 'event_plan_id');
    }

    public function overlays(): HasMany
    {
        return $this->hasMany(MapOverlay::class, 'event_plan_id');
    }
}
