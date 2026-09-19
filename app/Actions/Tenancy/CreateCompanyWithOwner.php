<?php

namespace App\Actions\Tenancy;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Shared by self-serve registration (Actions\Fortify\CreateNewUser) and
 * super-admin-initiated account creation (Admin\AccountController): create a
 * Company, its owning User, and provision the company's tenant database.
 */
class CreateCompanyWithOwner
{
    public function __construct(protected ProvisionTenantDatabase $provisioner) {}

    /**
     * @param  array{name: string}  $companyAttributes
     * @param  array{name: string, email: string, password: string}  $ownerAttributes
     * @return array{0: Company, 1: User}
     */
    public function handle(array $companyAttributes, array $ownerAttributes): array
    {
        [$company, $user] = DB::transaction(function () use ($companyAttributes, $ownerAttributes) {
            $company = Company::create([
                'name' => $companyAttributes['name'],
                'slug' => Str::slug($companyAttributes['name']).'-'.Str::random(6),
            ]);

            $company->forceFill([
                'trial_ends_at' => now()->addDays(config('billing.trial_days')),
            ])->save();

            // A user can belong to more than one company — if this email is
            // already registered (e.g. a super-admin adding another company
            // for an existing platform user), attach them as owner of the
            // new company rather than erroring on a duplicate email.
            $user = User::firstOrCreate(
                ['email' => $ownerAttributes['email']],
                ['name' => $ownerAttributes['name'], 'password' => $ownerAttributes['password']],
            );

            $company->users()->attach($user->id, ['role' => 'owner']);

            return [$company, $user];
        });

        // CREATE DATABASE / migrations aren't transactional, so this runs
        // after the central rows are committed. If it fails, we compensate
        // by deleting the just-created rows rather than leaving a user with
        // no usable workspace.
        try {
            $this->provisioner->handle($company);
        } catch (Throwable $e) {
            report($e);

            $company->users()->detach($user->id);
            $company->delete();

            // Only remove the user if this action created them — an
            // existing user attached to a new (now-failed) company should
            // keep their account and other memberships intact.
            if ($user->wasRecentlyCreated) {
                $user->delete();
            }

            throw ValidationException::withMessages([
                'email' => 'We could not set up the workspace. Please try again.',
            ]);
        }

        return [$company, $user];
    }
}
