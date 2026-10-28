<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

class PayForExtraPlayersRequest extends FormRequest
{
    /**
     * Fields that must never be flashed back to the session.
     */
    public const SENSITIVE = ['card_number', 'security_code', 'expiry_date'];

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
            'billing_name_first' => ['required', 'string', 'max:100'],
            'billing_name_last' => ['required', 'string', 'max:100'],
            'billing_address_street' => ['required', 'string', 'max:150'],
            'billing_address_locality' => ['nullable', 'string', 'max:150'],
            'billing_address_town' => ['required', 'string', 'max:100'],
            'billing_address_region' => ['required', 'string', 'max:100'],
            'billing_address_country' => ['required', 'string', 'max:100'],
            'billing_address_postcode' => ['required', 'string', 'max:20'],
            'card_holder_name' => ['required', 'string', 'max:100'],
            'card_number' => ['required', 'regex:/^\d{12,19}$/', $this->luhn(...)],
            'expiry_date' => ['required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', $this->notExpired(...)],
            'security_code' => ['required', 'regex:/^\d{3,4}$/'],
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
            'billing_name_first.required' => 'Please add billing first name',
            'billing_name_last.required' => 'Please add billing last name',
            'billing_address_street.required' => 'Please add billing Address 1',
            'billing_address_town.required' => 'Please add billing City',
            'billing_address_region.required' => 'Please add billing Region',
            'billing_address_country.required' => 'Please add billing Country',
            'billing_address_postcode.required' => 'Please add billing postcode',
            'card_holder_name.required' => 'Please add card holder name',
            'card_number.required' => 'Please add card number',
            'card_number.regex' => 'Please enter a valid card number',
            'expiry_date.required' => 'Please add expiry date',
            'expiry_date.regex' => 'The expiry date format should be mm/yy',
            'security_code.required' => 'Please add security code',
            'security_code.regex' => 'Please enter the 3 or 4 digit security code',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => preg_replace('/[\s-]+/', '', (string) $this->input('card_number')),
            'expiry_date' => preg_replace('/\s+/', '', (string) $this->input('expiry_date')),
            'security_code' => trim((string) $this->input('security_code')),
        ]);
    }

    private function luhn(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = strrev((string) $value);
        $sum = 0;

        for ($i = 0, $length = strlen($digits); $i < $length; $i++) {
            $digit = (int) $digits[$i];
            $sum += $i % 2 === 1 ? ($digit * 2 > 9 ? $digit * 2 - 9 : $digit * 2) : $digit;
        }

        if ($sum % 10 !== 0) {
            $fail('Please enter a valid card number');
        }
    }

    private function notExpired(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^(\d{2})\/(\d{2})$/', (string) $value, $match)) {
            return;
        }

        $expiresAfter = Date::createFromDate(2000 + (int) $match[2], (int) $match[1], 1)->endOfMonth();

        if ($expiresAfter->isPast()) {
            $fail('This card has expired');
        }
    }
}
