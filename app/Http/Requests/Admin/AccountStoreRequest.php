<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountStoreRequest extends FormRequest
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
            // Not required to be unique — the invited email may already
            // belong to a platform user, who'll simply gain ownership of
            // this new company too (see CreateCompanyWithInvitedOwner).
            'owner_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
