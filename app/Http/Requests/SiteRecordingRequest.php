<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteRecordingRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],

            // Capped rather than unbounded: this is the number storage cost
            // and the customer's bill are driven by, so a stray extra zero
            // should be a validation error, not an invoice.
            'daily_limit' => ['required', 'integer', 'min:0', 'max:10000'],

            'sample_rate' => ['required', 'integer', 'min:1', 'max:100'],

            // Analytics fan-out, saved on the same form because they are the
            // same kind of per-site switch.
            'gtm' => ['required', 'boolean'],
            'ga4' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'daily_limit.max' => 'A site can record at most 10,000 sessions a day.',
            'sample_rate.min' => 'Sample rate must be at least 1% — use the toggle to stop recording entirely.',
        ];
    }
}
