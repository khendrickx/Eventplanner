<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMapElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type          = $this->input('type');
        $validSubtypes = config("map_elements.subtypes.{$type}", []);

        return [
            'type'     => ['required', Rule::in(config('map_elements.types'))],
            'subtype'  => [
                'nullable', 'string',
                function ($attribute, $value, $fail) use ($validSubtypes) {
                    if ($value && !in_array($value, $validSubtypes)) {
                        $fail("Invalid subtype '{$value}' for type '{$this->input('type')}'.");
                    }
                },
            ],
            'name'                    => ['nullable', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string'],
            'geometry'                => ['required', 'array'],
            'geometry.type'           => ['required', 'string'],
            'geometry.coordinates'    => ['required'],
            'properties'              => ['nullable', 'array'],
            'is_locked'               => ['boolean'],
            'is_hidden'               => ['boolean'],
            'sort_order'              => ['integer'],
            'event_plan_id'           => ['nullable', 'integer', 'exists:event_plans,id'],
            'parent_id'               => [
                'nullable', 'integer', 'exists:map_elements,id',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $this->input('type') === 'group') {
                        $fail('Groups cannot be nested inside other groups.');
                    }
                },
            ],
        ];
    }
}
