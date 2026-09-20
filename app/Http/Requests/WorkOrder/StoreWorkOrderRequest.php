<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kasir & owner/super_admin yang mengelola antrean.
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:20'],
            'complaint' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
