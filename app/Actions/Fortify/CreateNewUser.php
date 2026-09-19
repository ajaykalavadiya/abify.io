<?php

namespace App\Actions\Fortify;

use App\Actions\Tenancy\CreateCompanyWithOwner;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user, along with the company
     * ("account") they own and its tenant database.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        [, $user] = app(CreateCompanyWithOwner::class)->handle(
            ['name' => "{$input['name']}'s Company"],
            ['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']],
        );

        return $user;
    }
}
