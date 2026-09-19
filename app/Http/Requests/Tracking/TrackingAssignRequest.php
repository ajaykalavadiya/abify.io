<?php

namespace App\Http\Requests\Tracking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackingAssignRequest extends FormRequest
{
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
            'site_id' => ['required', 'string'],
            'visitor_uuid' => ['required', 'uuid'],
            'experiment_id' => ['required', 'integer'],
        ];
    }
}
