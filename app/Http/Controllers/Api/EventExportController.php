<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventPlan;
use Illuminate\Http\Response;

class EventExportController extends Controller
{
    public function csv(EventPlan $plan): Response
    {
        $this->authorize('view', $plan->event);

        $elements = $plan->event->elementsForPlan($plan->id);

        $safeName = str_replace(['"', "\r", "\n"], '', $plan->event->name . ' - ' . $plan->name);
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '.csv"',
        ];

        $buffer = fopen('php://temp', 'r+');

        fputcsv($buffer, ['id', 'type', 'subtype', 'name', 'notes', 'geometry_type', 'coordinates', 'width', 'length', 'rotation', 'fill_color', 'stroke_color', 'plan']);

        foreach ($elements as $el) {
            $props = $el->properties ?? [];
            $styling = $props['styling'] ?? [];
            fputcsv($buffer, [
                $el->id,
                $el->type,
                $el->subtype ?? '',
                $el->name ?? '',
                $el->notes ?? '',
                $el->geometry['type'] ?? '',
                json_encode($el->geometry['coordinates'] ?? []),
                $props['width'] ?? '',
                $props['length'] ?? '',
                $props['rotation'] ?? '',
                $styling['fill_color'] ?? '',
                $styling['stroke_color'] ?? '',
                $el->event_plan_id ? $plan->name : 'shared',
            ]);
        }

        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return response($csv, 200, $headers);
    }
}
