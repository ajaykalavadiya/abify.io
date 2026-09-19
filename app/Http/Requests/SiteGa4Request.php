<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteGa4Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // GA4 measurement ids are always G- prefixed; catching a typo here
            // is better than silently posting to a property that rejects
            // everything without ever telling us.
            'measurement_id' => ['nullable', 'string', 'max:32', 'regex:/^G-[A-Z0-9]+$/'],
            'api_secret' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'measurement_id.regex' => 'A GA4 measurement ID looks like G-XXXXXXXXXX.',
        ];
    }
}
