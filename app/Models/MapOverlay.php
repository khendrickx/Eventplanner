<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapOverlay extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'image_path', 'bounds', 'opacity', 'sort_order', 'event_plan_id'];

    protected $casts = ['bounds' => 'array'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EventPlan::class, 'event_plan_id');
    }
}
