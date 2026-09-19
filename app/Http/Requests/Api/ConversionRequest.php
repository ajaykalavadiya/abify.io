<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // AuthenticateSiteWriteKey has already resolved the site.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visitor_uuid' => ['required', 'uuid'],
            'goal' => ['required', 'string', 'max:255'],

            // The platform's order/transaction id. Optional, but without it
            // a retried webhook is counted twice — the integrations always
            // send it.
            'external_id' => ['nullable', 'string', 'max:255'],

            'revenue' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'occurred_at' => ['nullable', 'date'],
            'props' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.size' => 'Currency must be a 3-letter ISO 4217 code, e.g. USD.',
        ];
    }
}
