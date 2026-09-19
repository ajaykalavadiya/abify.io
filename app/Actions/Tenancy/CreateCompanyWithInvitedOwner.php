<?php

namespace App\Actions\Tenancy;

use App\Models\Company;
use App\Models\Invitation;
use App\Notifications\CompanyInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Super-admin-initiated account creation (Admin\AccountController): create a
 * Company and provision its tenant database, but — unlike self-serve
 * registration (Actions\Fortify\CreateNewUser → CreateCompanyWithOwner),
 * which sets the owner's password directly from the registration form — the
 * owner is invited by email and sets their own password by accepting it,
 * same as inviting an additional user to an existing account.
 */
class CreateCompanyWithInvitedOwner
{
    public function __construct(protected ProvisionTenantDatabase $provisioner) {}

    /**
     * @param  array{name: string}  $companyAttributes
     * @return array{0: Company, 1: Invitation}
     */
    public function handle(array $companyAttributes, string $ownerEmail, ?int $invitedBy): array
    {
        [$company, $invitation] = DB::transaction(function () use ($companyAttributes, $ownerEmail, $invitedBy) {
            $company = Company::create([
                'name' => $companyAttributes['name'],
                'slug' => Str::slug($companyAttributes['name']).'-'.Str::random(6),
            ]);

            $company->forceFill([
                'trial_ends_at' => now()->addDays(config('billing.trial_days')),
            ])->save();

            $invitation = $company->invitations()->create([
                'email' => $ownerEmail,
                'role' => 'owner',
                'invited_by' => $invitedBy,
            ]);

            return [$company, $invitation];
        });

        // CREATE DATABASE / migrations aren't transactional, so this runs
        // after the central rows are committed. If it fails, we compensate
        // by deleting the just-created rows rather than leaving a dangling
        // invitation to a workspace that doesn't work.
        try {
            $this->provisioner->handle($company);
        } catch (Throwable $e) {
            report($e);

            $invitation->delete();
            $company->delete();

            throw ValidationException::withMessages([
                'owner_email' => 'We could not set up the workspace. Please try again.',
            ]);
        }

        Notification::route('mail', $ownerEmail)->notify(new CompanyInvitation($invitation));

        return [$company, $invitation];
    }
}
