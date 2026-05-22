<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $event->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function manageCollaborators(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function duplicate(User $user, Event $event): bool
    {
        return $event->isAccessibleBy($user);
    }
}
