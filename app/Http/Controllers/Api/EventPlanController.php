<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventPlanRequest;
use App\Models\Event;
use App\Models\EventPlan;
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

        $sortOrder = $event->plans()->max('sort_order') + 1;
        $plan = $event->plans()->create([
            'name' => $request->name,
            'sort_order' => $sortOrder,
        ]);

        return response()->json($plan, 201);
    }

    public function update(Request $request, EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $plan->update(['name' => $request->name]);
        return response()->json($plan);
    }

    public function duplicate(EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);

        $event = $plan->event;
        $copy = $event->plans()->create([
            'name' => $plan->name . ' (copy)',
            'sort_order' => $event->plans()->max('sort_order') + 1,
        ]);

        foreach ($plan->elements as $element) {
            $newElement = $element->replicate(['event_plan_id']);
            $newElement->event_plan_id = $copy->id;
            $newElement->save();
        }

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
