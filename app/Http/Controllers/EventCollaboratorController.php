<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteCollaboratorRequest;
use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EventCollaboratorController extends Controller
{
    public function store(InviteCollaboratorRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('manageCollaborators', $event);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $event->collaborators()->syncWithoutDetaching([
                $user->id => ['role' => $request->role],
            ]);
        } else {
            $invitation = EventInvitation::updateOrCreate(
                ['event_id' => $event->id, 'email' => $request->email],
                [
                    'role' => $request->role,
                    'token' => Str::random(32),
                    'expires_at' => now()->addDays(7),
                ]
            );
            Mail::to($request->email)->send(new EventInvitationMail($invitation));
        }

        return redirect()->route('events.edit', $event);
    }

    public function update(Request $request, Event $event, User $user): RedirectResponse
    {
        $this->authorize('manageCollaborators', $event);
        $request->validate(['role' => ['required', 'in:editor,viewer']]);
        $event->collaborators()->updateExistingPivot($user->id, ['role' => $request->role]);
        return redirect()->route('events.edit', $event);
    }

    public function destroy(Event $event, User $user): RedirectResponse
    {
        $this->authorize('manageCollaborators', $event);
        $event->collaborators()->detach($user->id);
        return redirect()->route('events.edit', $event);
    }
}
