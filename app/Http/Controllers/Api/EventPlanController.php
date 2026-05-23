<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventPlanRequest;
use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPlanController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        $this->authorize('view', $event);
        return response()->json(['data' => $event->plans]);
    }

    public function store(StoreEventPlanRequest $request, Event $event): JsonResponse
    {
        $this->authorize('editContent', $event);

        $plan = $event->plans()->create([
            'name'       => $request->name,
            'sort_order' => $event->plans()->max('sort_order') + 1,
        ]);

        return response()->json($plan, 201);
    }

    public function update(Request $request, EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);

        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'properties' => ['sometimes', 'nullable', 'array'],
        ]);

        $plan->update($validated);
        return response()->json($plan);
    }

    public function duplicate(EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);

        $copy = $plan->event->plans()->create([
            'name'       => $plan->name . ' (copy)',
            'sort_order' => $plan->event->plans()->max('sort_order') + 1,
            'properties' => $plan->properties,
        ]);

        MapElement::copyCollection($plan->elements, $plan->event_id, $copy->id);

        return response()->json($copy, 201);
    }

    public function destroy(EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);

        if ($plan->event->plans()->count() <= 1) {
            return response()->json(['message' => 'Cannot delete the last plan.'], 422);
        }

        $plan->delete();
        return response()->json(null, 204);
    }
}
