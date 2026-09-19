<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExperimentUpdateRequest extends FormRequest
{
    /**
     * Same fields as creating a test, minus the type — see
     * ExperimentController::update() for why that one is fixed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url_match_type' => ['required', 'in:exact,contains,regex'],
            'url_pattern' => ['required', 'string', 'max:2048'],
            'traffic_allocation' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
