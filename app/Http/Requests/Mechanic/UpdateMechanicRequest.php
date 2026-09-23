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
            'bengkel_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mechanic = (float) $this->input('mechanic_percentage');
            $bengkel = (float) $this->input('bengkel_percentage');

            if (abs(($mechanic + $bengkel) - 100) > 0.01) {
                $validator->errors()->add('bengkel_percentage', 'Rasio mekanik + rasio bengkel harus berjumlah 100%.');
            }
        });
    }
}
