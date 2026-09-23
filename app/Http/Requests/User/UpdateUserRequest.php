<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('owner', 'super_admin');
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Password minimal 6 karakter.',
            'username.unique' => 'Username sudah dipakai.',
        ];
    }
}