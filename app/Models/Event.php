<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_collaborators')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(EventPlan::class)->orderBy('sort_order');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->isOwnedBy($user)
            || $this->collaborators()->where('user_id', $user->id)->exists();
    }

    public function roleFor(User $user): ?string
    {
        if ($this->isOwnedBy($user)) {
            return 'owner';
        }
        $collaborator = $this->collaborators()->where('user_id', $user->id)->first();
        return $collaborator?->pivot?->role;
    }
}
