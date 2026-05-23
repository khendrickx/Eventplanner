<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
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
            'event' => $event->only('id', 'name', 'description'),
            'collaborators' => $collaborators,
            'pendingInvitations' => $pendingInvitations,
        ]);
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

        $copy = $event->replicate(['user_id']);
        $copy->user_id = auth()->id();
        $copy->name = $event->name . ' (copy)';
        $copy->save();

        // Copy plans, building a map from old plan ID → new plan ID
        $planIdMap = [];
        foreach ($event->plans as $plan) {
            $newPlan = $copy->plans()->create([
                'name' => $plan->name,
                'sort_order' => $plan->sort_order,
            ]);
            $planIdMap[$plan->id] = $newPlan->id;

            // Copy plan-scoped elements
            foreach ($plan->elements as $element) {
                $newEl = $element->replicate(['event_id', 'event_plan_id']);
                $newEl->event_id = $copy->id;
                $newEl->event_plan_id = $newPlan->id;
                $newEl->save();
            }
        }

        // Copy shared elements (event_plan_id = null)
        foreach ($event->elements()->whereNull('event_plan_id')->get() as $element) {
            $newEl = $element->replicate(['event_id', 'event_plan_id']);
            $newEl->event_id = $copy->id;
            $newEl->event_plan_id = null;
            $newEl->save();
        }

        // Copy overlays (physically copy the image files too)
        foreach ($event->overlays as $overlay) {
            $ext = pathinfo($overlay->image_path, PATHINFO_EXTENSION);
            $newPath = 'overlays/' . \Illuminate\Support\Str::uuid() . ($ext ? '.' . $ext : '');
            \Illuminate\Support\Facades\Storage::disk('public')->copy($overlay->image_path, $newPath);

            $copy->overlays()->create([
                'event_plan_id' => $overlay->event_plan_id ? $planIdMap[$overlay->event_plan_id] : null,
                'name' => $overlay->name,
                'image_path' => $newPath,
                'bounds' => $overlay->bounds,
                'opacity' => $overlay->opacity,
                'sort_order' => $overlay->sort_order,
            ]);
        }

        return redirect()->route('events.show', $copy);
    }
}
