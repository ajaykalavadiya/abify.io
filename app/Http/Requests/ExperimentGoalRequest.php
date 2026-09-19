<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExperimentGoalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:pageview,click,custom_event'],
            'match_type' => ['nullable', 'in:exact,contains,regex'],
            'target' => ['nullable', 'string', 'max:255'],
        ];
    }
}
