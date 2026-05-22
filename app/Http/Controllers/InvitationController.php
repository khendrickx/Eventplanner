<?php

namespace App\Http\Controllers;

use App\Models\EventInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = EventInvitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Expired');
        }

        if (auth()->check()) {
            $invitation->event->collaborators()->syncWithoutDetaching([
                auth()->id() => ['role' => $invitation->role],
            ]);
            $invitation->delete();
            return redirect()->route('dashboard');
        }

        return redirect("/register?invitation={$token}");
    }
}
