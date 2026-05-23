<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapOverlay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MapOverlayController extends Controller
{
    public function indexForPlan(EventPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan->event);
        $overlays = $plan->event->overlaysForPlan($plan->id)->map(fn ($o) => [
            ...$o->toArray(),
            'image_url' => asset('storage/' . $o->image_path),
        ]);
        return response()->json(['data' => $overlays]);
    }

    public function storeForPlan(Request $request, EventPlan $plan): JsonResponse
    {
        $this->authorize('editContent', $plan->event);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'file', 'image', 'max:10240'],
            'bounds' => ['required', 'array'],
        ]);

        $path = $request->file('image')->store('overlays', 'public');
        $sortOrder = $plan->event->overlays()->max('sort_order') + 1;

        $overlay = $plan->event->overlays()->create([
            'event_plan_id' => $plan->id,
            'name' => $request->name,
            'image_path' => $path,
            'bounds' => $request->bounds,
            'opacity' => 1.0,
            'sort_order' => $sortOrder,
        ]);

        return response()->json($overlay, 201);
    }

    public function storeShared(Request $request, Event $event): JsonResponse
    {
        $this->authorize('editContent', $event);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'file', 'image', 'max:10240'],
            'bounds' => ['required', 'array'],
        ]);

        $path = $request->file('image')->store('overlays', 'public');
        $sortOrder = $event->overlays()->max('sort_order') + 1;

        $overlay = $event->overlays()->create([
            'event_plan_id' => null,
            'name' => $request->name,
            'image_path' => $path,
            'bounds' => $request->bounds,
            'opacity' => 1.0,
            'sort_order' => $sortOrder,
        ]);

        return response()->json($overlay, 201);
    }

    public function update(Request $request, MapOverlay $overlay): JsonResponse
    {
        $this->authorize('editContent', $overlay->event);

        $request->validate([
            'bounds' => ['sometimes', 'array'],
            'opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $overlay->update($request->only(['bounds', 'opacity', 'name']));
        return response()->json($overlay);
    }

    public function destroy(MapOverlay $overlay): JsonResponse
    {
        $this->authorize('editContent', $overlay->event);

        Storage::disk('public')->delete($overlay->image_path);
        $overlay->delete();

        return response()->json(null, 204);
    }
}
