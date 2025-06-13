<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       return [
            'user_id' => ['sometimes', 'exists:users,id'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'active', 'blocked'])],

            'license_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'license_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'car_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'car_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
