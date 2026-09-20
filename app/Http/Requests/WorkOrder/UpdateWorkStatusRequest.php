<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'work_status' => ['required', Rule::in(['antre', 'proses', 'selesai'])],
        ];
    }
}
