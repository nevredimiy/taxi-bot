<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,active,blocked'],

            'license_number' => ['nullable', 'string', 'max:255'],
            'license_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'], // до 2MB
            'car_model' => ['nullable', 'string', 'max:255'],
            'car_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'], // до 2MB

            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }
}
