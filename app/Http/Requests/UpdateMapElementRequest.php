<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMapElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'                   => ['sometimes', 'nullable', 'string'],
            'geometry'                => ['sometimes', 'required', 'array'],
            'geometry.type'           => ['required_with:geometry', 'string'],
            'geometry.coordinates'    => ['required_with:geometry'],
            'properties'              => ['sometimes', 'nullable', 'array'],
            'is_locked'               => ['sometimes', 'boolean'],
            'is_hidden'               => ['sometimes', 'boolean'],
            'sort_order'              => ['sometimes', 'integer'],
            'event_plan_id'           => ['sometimes', 'nullable', 'integer', 'exists:event_plans,id'],
            'parent_id'               => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('map_elements', 'id')->where('event_id', $this->route('element')?->event_id),
                function ($attribute, $value, $fail) {
                    if ($value !== null) {
                        $element = $this->route('element');
                        if ($element && $element->type === 'group') {
                            $fail('Groups cannot be nested inside other groups.');
                        }
                    }
                },
            ],
        ];
    }
}
