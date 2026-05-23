<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'subtype', 'name', 'notes',
        'geometry', 'properties',
        'is_locked', 'is_hidden', 'sort_order',
        'event_plan_id', 'parent_id',
    ];

    protected $casts = [
        'geometry'   => 'array',
        'properties' => 'array',
        'is_locked'  => 'boolean',
        'is_hidden'  => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EventPlan::class, 'event_plan_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MapElement::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MapElement::class, 'parent_id');
    }

    /**
     * Copy a collection of elements to a new event/plan, remapping parent_id references.
     *
     * Pass 1: copy every element with parent_id = null, record old→new ID map.
     * Pass 2: restore parent_id for elements that had one.
     */
    public static function copyCollection(Collection $elements, int $newEventId, ?int $newPlanId): void
    {
        $idMap = [];

        foreach ($elements as $element) {
            $copy = $element->replicate(['event_id', 'event_plan_id', 'parent_id']);
            $copy->event_id     = $newEventId;
            $copy->event_plan_id = $newPlanId;
            $copy->parent_id    = null;
            $copy->save();
            $idMap[$element->id] = $copy->id;
        }

        foreach ($elements->whereNotNull('parent_id') as $element) {
            $newId       = $idMap[$element->id] ?? null;
            $newParentId = $idMap[$element->parent_id] ?? null;
            if ($newId && $newParentId) {
                self::where('id', $newId)->update(['parent_id' => $newParentId]);
            }
        }
    }
}
