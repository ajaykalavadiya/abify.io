<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InvitationAcceptRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * For an email that already has an account, the submitted password
     * verifies that existing account (no confirmation, no complexity
     * check — it's not being set, just checked) and no name is collected.
     * For a brand-new email, the full "create an account" rules apply.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invitation = Invitation::where('token', $this->route('token'))->first();
        $existingUser = $invitation && User::where('email', $invitation->email)->exists();

        if ($existingUser) {
            return [
                'password' => ['required', 'string'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }
}
