<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExperimentVariationRequest extends FormRequest
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
            'traffic_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'redirect_url' => ['nullable', 'url', 'max:2048'],
            'payload' => ['nullable', 'array'],
            'payload.css' => ['nullable', 'string'],
            'payload.js' => ['nullable', 'string'],
            'changes' => ['nullable', 'array'],
            'changes.*.selector' => ['required_with:changes.*.action', 'nullable', 'string', 'max:255'],
            'changes.*.action' => ['nullable', 'in:text,html,hide'],
            'changes.*.value' => ['nullable', 'string', 'required_if:changes.*.action,text,html'],
        ];
    }
}
