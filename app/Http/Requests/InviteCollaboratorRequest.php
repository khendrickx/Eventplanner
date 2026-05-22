<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteCollaboratorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'in:editor,viewer'],
        ];
    }
}
