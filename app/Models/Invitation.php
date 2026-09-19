<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $invited_by
 * @property string $email
 * @property string $role
 * @property string $token
 * @property Carbon|null $accepted_at
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'role'])]
class Invitation extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            if (! $invitation->token) {
                $invitation->token = Str::random(48);
            }

            if (! $invitation->expires_at) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }
}
