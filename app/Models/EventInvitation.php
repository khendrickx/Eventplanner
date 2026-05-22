<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvitation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['event_id', 'email', 'role', 'token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
