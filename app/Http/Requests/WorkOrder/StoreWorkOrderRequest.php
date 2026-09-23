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
            'customer_name' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:20'],
            'motor_type' => ['nullable', 'string', 'max:100'],
            'complaint' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $plate = strtoupper(trim((string) $this->input('plate_number')));
            $customer = trim((string) $this->input('customer_name'));

            // Motor tanpa plat diwakili "XXXX"; nama customer wajib diisi.
            if ($plate === 'XXXX' && $customer === '') {
                $validator->errors()->add('customer_name', 'Nama customer wajib diisi bila motor tidak berplat nomor (XXXX).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'plate_number.required' => 'Plat nomor wajib diisi. Isi XXXX bila motor tidak berplat.',
            'customer_name.max' => 'Nama customer maksimal 100 karakter.',
        ];
    }
}
