<?php

namespace App\Http\Requests\Mechanic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMechanicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'mechanic_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
