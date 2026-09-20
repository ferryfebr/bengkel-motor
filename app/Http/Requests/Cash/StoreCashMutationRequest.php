<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
