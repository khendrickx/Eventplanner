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

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $plan->event->name . ' - ' . $plan->name . '.csv"',
        ];

        $csv = implode(',', ['id', 'type', 'subtype', 'name', 'notes', 'geometry_type', 'coordinates', 'width', 'length', 'rotation', 'fill_color', 'stroke_color', 'plan']) . "\n";

        foreach ($elements as $el) {
            $props = $el->properties ?? [];
            $styling = $props['styling'] ?? [];
            $csv .= implode(',', [
                $el->id,
                $el->type,
                $el->subtype ?? '',
                $this->escapeCsv($el->name ?? ''),
                $this->escapeCsv($el->notes ?? ''),
                $el->geometry['type'] ?? '',
                $this->escapeCsv(json_encode($el->geometry['coordinates'] ?? [])),
                $props['width'] ?? '',
                $props['length'] ?? '',
                $props['rotation'] ?? '',
                $styling['fill_color'] ?? '',
                $styling['stroke_color'] ?? '',
                $el->event_plan_id ? $plan->name : 'shared',
            ]) . "\n";
        }

        return response($csv, 200, $headers);
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
