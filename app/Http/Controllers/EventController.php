<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MapElement;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class EventController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $events = Event::where('user_id', $user->id)
            ->orWhereHas('collaborators', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('collaborators')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'description' => $e->description,
                'role' => $e->roleFor($user),
                'collaborators_count' => $e->collaborators_count,
                'created_at' => $e->created_at->toDateString(),
            ]);

        return Inertia::render('Dashboard', compact('events'));
    }

    public function create(): Response
    {
        return Inertia::render('Events/Create');
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create($request->validated());
        $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        return redirect()->route('events.show', $event);
    }

    public function show(Event $event): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'role' => $event->roleFor(auth()->user()),
            ],
            'plans' => $event->plans->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sort_order' => $p->sort_order,
            ]),
        ]);
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);

        $collaborators = $event->collaborators->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
        ]);

        $pendingInvitations = $event->invitations()
            ->where('expires_at', '>', now())
            ->get(['id', 'email', 'role', 'expires_at']);

        return Inertia::render('Events/Edit', [
            'event' => [
                ...$event->only('id', 'name', 'description'),
                'public_token'    => $event->public_token,
                'has_password'    => !is_null($event->public_password_hash),
            ],
            'collaborators' => $collaborators,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function updateShare(\Illuminate\Http\Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $request->validate([
            'enabled'  => 'required|boolean',
            'password' => 'nullable|string|max:255',
        ]);

        if (!$request->boolean('enabled')) {
            $event->clearPublicToken();
        } else {
            if (!$event->public_token) {
                $event->generatePublicToken();
            }
            if ($request->filled('password')) {
                $event->update(['public_password_hash' => Hash::make($request->password)]);
            } elseif ($request->has('password') && $request->input('password') === null) {
                $event->update(['public_password_hash' => null]);
            }
        }

        return back();
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $event->update($request->validated());
        return redirect()->route('events.edit', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->delete();
        return redirect()->route('dashboard');
    }

    public function duplicate(Event $event): RedirectResponse
    {
        $this->authorize('duplicate', $event);

        $copy           = $event->replicate(['user_id']);
        $copy->user_id  = auth()->id();
        $copy->name     = $event->name . ' (copy)';
        $copy->save();

        $planIdMap = [];
        foreach ($event->plans as $plan) {
            $newPlan = $copy->plans()->create([
                'name'       => $plan->name,
                'sort_order' => $plan->sort_order,
                'properties' => $plan->properties,
            ]);
            $planIdMap[$plan->id] = $newPlan->id;

            MapElement::copyCollection($plan->elements, $copy->id, $newPlan->id);
        }

        // Shared elements (event_plan_id = null)
        MapElement::copyCollection(
            $event->elements()->whereNull('event_plan_id')->get(),
            $copy->id,
            null
        );

        return redirect()->route('events.show', $copy);
    }
}
