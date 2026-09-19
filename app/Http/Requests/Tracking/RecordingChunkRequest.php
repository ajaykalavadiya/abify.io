<?php

namespace App\Http\Requests\Tracking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordingChunkRequest extends FormRequest
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
            'site_id' => ['required', 'string'],
            'session_id' => ['required', 'uuid'],
            'visitor_uuid' => ['required', 'uuid'],

            // Chunk ordering is the client's, not the server's: chunks can
            // arrive out of order over sendBeacon, and playback needs them
            // back in capture order.
            'seq' => ['required', 'integer', 'min:0', 'max:100000'],

            'events' => ['required', 'array', 'min:1'],

            // Signals the recorder noticed while capturing. Denormalised onto
            // the recording so the list can be filtered without opening every
            // payload.
            'rage_click' => ['nullable', 'boolean'],
            'error' => ['nullable', 'boolean'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
