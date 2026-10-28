<?php

namespace App\Http\Requests;

use App\Portal\ConsentRules;
use App\Portal\PortalContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
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
        $rules = [
            'name_first' => ['required', 'string', 'max:100'],
            'name_last' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'mobile' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:190'],
            'postcode' => ['required', 'string', 'max:20'],
        ];

        if ($this->needsGuardian()) {
            $rules['guardian'] = ['required', 'string', 'max:100'];
            $rules['guardian_permission'] = ['accepted'];
        }

        return $rules + $this->consentRules()->validationRules($this->portal()->booking(), $this->age());
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_first.required' => 'Please enter your first name',
            'name_last.required' => 'Please enter your last name',
            'email.required' => 'Please enter your email address',
            'mobile.required' => 'Please enter your mobile number',
            'date_of_birth.required' => 'Please enter your date of birth',
            'date_of_birth.before' => 'Please enter a valid date of birth',
            'postcode.required' => 'Please enter your postcode',
            'guardian.required' => "Please enter the Guardian's name",
            'guardian_permission.accepted' => __('consents.errors.guardian_permission'),
            'safety_brief.accepted' => __('consents.errors.safety_brief'),
            '*.accepted' => __('consents.errors.default'),
        ];
    }

    /**
     * The player's age in whole years, in the site's timezone.
     */
    public function age(): ?int
    {
        return ConsentRules::ageFromDateOfBirth($this->input('date_of_birth'), $this->portal()->booking()->timezone());
    }

    /**
     * Accepted / declined state of every declaration HAPI stores. Ones that
     * did not apply to this player are recorded as declined.
     *
     * @return array<string, bool>
     */
    public function declarations(): array
    {
        return $this->consentRules()->declarations($this->portal()->booking(), $this->age(), $this->all());
    }

    private function needsGuardian(): bool
    {
        $age = $this->age();

        return $age !== null && $age < (int) config('regos.registration.guardian_age');
    }

    private function consentRules(): ConsentRules
    {
        return app(ConsentRules::class);
    }

    private function portal(): PortalContext
    {
        return app(PortalContext::class);
    }
}
