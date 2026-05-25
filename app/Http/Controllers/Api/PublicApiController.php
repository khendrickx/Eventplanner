<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPlan;
use Illuminate\Http\JsonResponse;

class PublicApiController extends Controller
{
    public function planElements(string $token, EventPlan $plan): JsonResponse
    {
        $event = Event::where('public_token', $token)->firstOrFail();

        // Verify the plan belongs to this event
        abort_if($plan->event_id !== $event->id, 404);

        // If password-protected, require prior session authorization
        if ($event->public_password_hash && !session("public_event_{$event->id}")) {
            return response()->json(['message' => 'Password required.'], 403);
        }

        $elements = $event->elementsForPlan($plan->id);

        return response()->json(['data' => $elements]);
    }
}
