<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PublicEventController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $event = Event::where('public_token', $token)->firstOrFail();

        if ($event->public_password_hash && !session("public_event_{$event->id}")) {
            return Inertia::render('Public/Password', [
                'token'     => $token,
                'eventName' => $event->name,
            ]);
        }

        return Inertia::render('Public/Show', [
            'event' => [
                'id'           => $event->id,
                'name'         => $event->name,
                'role'         => 'viewer',
                'public_token' => $token,
            ],
            'plans' => $event->plans->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'sort_order' => $p->sort_order,
            ]),
        ]);
    }

    public function enter(Request $request, string $token): RedirectResponse
    {
        $event = Event::where('public_token', $token)->firstOrFail();

        if (!$event->public_password_hash) {
            return redirect()->route('public.show', $token);
        }

        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->password, $event->public_password_hash)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        session(["public_event_{$event->id}" => true]);

        return redirect()->route('public.show', $token);
    }
}
