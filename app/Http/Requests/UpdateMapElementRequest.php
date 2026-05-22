<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMapElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'geometry' => ['sometimes', 'required', 'array'],
            'geometry.type' => ['required_with:geometry', 'string'],
            'geometry.coordinates' => ['required_with:geometry'],
            'properties' => ['sometimes', 'nullable', 'array'],
            'is_locked' => ['sometimes', 'boolean'],
            'is_hidden' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'event_plan_id' => ['sometimes', 'nullable', 'integer', 'exists:event_plans,id'],
        ];
    }
}
