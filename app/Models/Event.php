<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'public_token', 'public_password_hash'];

    protected $hidden = ['public_password_hash'];

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

    public function elements(): HasMany
    {
        return $this->hasMany(MapElement::class);
    }

    public function elementsForPlan(int $planId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->elements()
            ->where(fn ($q) => $q
                ->where('event_plan_id', $planId)
                ->orWhereNull('event_plan_id')
            )
            ->orderBy('sort_order')
            ->get();
    }

    public function generatePublicToken(): void
    {
        $this->update(['public_token' => (string) Str::uuid()]);
    }

    public function clearPublicToken(): void
    {
        $this->update(['public_token' => null, 'public_password_hash' => null]);
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
