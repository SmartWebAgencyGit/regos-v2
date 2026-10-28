<?php

namespace App\Http\Requests;

use App\Portal\PortalContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcceptDeclarationsRequest extends FormRequest
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
            'accept_terms' => ['accepted'],
            'safety_brief' => ['accepted'],
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
            'accept_terms.accepted' => __('consents.errors.default'),
            'safety_brief.accepted' => __('consents.errors.safety_brief'),
        ];
    }

    /**
     * On-site devices skip the video: the safety brief is given in person.
     */
    protected function prepareForValidation(): void
    {
        if (app(PortalContext::class)->isInternal()) {
            $this->merge(['safety_brief' => true]);
        }
    }
}
