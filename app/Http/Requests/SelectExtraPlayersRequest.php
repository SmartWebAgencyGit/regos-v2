<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectExtraPlayersRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantities.required' => 'Add Players to Go Ahead!',
        ];
    }

    /**
     * @return array<int, int> booking package id => extra players
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ((array) $this->validated('quantities') as $packageId => $quantity) {
            $quantities[(int) $packageId] = (int) $quantity;
        }

        return $quantities;
    }
}
