<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'subtype', 'name', 'notes',
        'geometry', 'properties',
        'is_locked', 'is_hidden', 'sort_order',
        'event_plan_id',
    ];

    protected $casts = [
        'geometry' => 'array',
        'properties' => 'array',
        'is_locked' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EventPlan::class, 'event_plan_id');
    }
}
