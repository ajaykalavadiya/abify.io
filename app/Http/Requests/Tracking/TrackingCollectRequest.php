<?php

namespace App\Http\Requests\Tracking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackingCollectRequest extends FormRequest
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
            'session_id' => ['nullable', 'uuid'],
            // GA4's client id, as read from the _ga cookie.
            'ga_client_id' => ['nullable', 'string', 'max:64'],
            'events' => ['required', 'array', 'min:1', 'max:200'],
            'events.*.type' => ['required', 'in:pageview,custom,heatmap_click,heatmap_move,heatmap_scroll,goal_conversion'],
            'events.*.url' => ['nullable', 'string', 'max:2048'],
            'events.*.name' => ['nullable', 'string', 'max:255'],
            'events.*.props' => ['nullable', 'array'],
            'events.*.x_pct' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'events.*.y_pct' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'events.*.scroll_depth_pct' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'events.*.viewport_width' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'events.*.viewport_height' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'events.*.doc_width' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'events.*.doc_height' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'events.*.referrer' => ['nullable', 'string', 'max:2048'],
            'events.*.experiment_id' => ['nullable', 'integer'],
            'events.*.goal_id' => ['nullable', 'integer'],
            'events.*.occurred_at' => ['nullable', 'date'],
        ];
    }
}
