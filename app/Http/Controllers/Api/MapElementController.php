<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMapElementRequest;
use App\Http\Requests\UpdateMapElementRequest;
use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapElement;
use Illuminate\Http\JsonResponse;

class MapElementController extends Controller
{
    public function indexForPlan(EventPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan->event);

        $elements = $plan->event->elementsForPlan($plan->id);
        return response()->json(['data' => $elements]);
    }

    public function indexForEvent(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $elements = $event->elements()->orderBy('sort_order')->get();
        return response()->json(['data' => $elements]);
    }

    public function storeForPlan(StoreMapElementRequest $request, EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);

        $sortOrder = $plan->event->elements()->max('sort_order') + 1;

        $element = $plan->event->elements()->create(array_merge(
            $request->validated(),
            ['event_plan_id' => $plan->id, 'sort_order' => $sortOrder]
        ));

        return response()->json($element, 201);
    }

    public function storeShared(StoreMapElementRequest $request, Event $event): JsonResponse
    {
        $this->authorize('editContent', $event);

        $sortOrder = $event->elements()->max('sort_order') + 1;

        $element = $event->elements()->create(array_merge(
            $request->validated(),
            ['event_plan_id' => null, 'sort_order' => $sortOrder]
        ));

        return response()->json($element, 201);
    }

    public function update(UpdateMapElementRequest $request, MapElement $element): JsonResponse
    {
        $this->authorize('editContent', $element->event);

        $element->update($request->validated());
        return response()->json($element);
    }

    public function destroy(MapElement $element): JsonResponse
    {
        $this->authorize('editContent', $element->event);

        $element->delete();
        return response()->json(null, 204);
    }
}
