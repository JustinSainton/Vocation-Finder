<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Organization extends Model
{
    use Billable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'settings',
        'logo_path',
        'stripe_id',
        'subscription_status',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'current_price_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Whether staff may read the portrait of a student in this organization.
     *
     * On by default: a counsellor who cannot see the result cannot do the job
     * the school bought the tool for, and the student's own guidance office
     * is not a stranger. It is a flag rather than a constant because a
     * diocese, a clinic-adjacent programme or a district with its own policy
     * must be able to close it without us shipping a release.
     *
     * Note what it does *not* open. This governs the portrait — the profile
     * written from an assessment. Coaching conversations and the vocational
     * brain are not reachable through it, by design and by test.
     */
    public const SETTING_STAFF_MAY_READ_PORTRAITS = 'staff_may_read_portraits';

    public function staffMayReadPortraits(): bool
    {
        return (bool) ($this->settings[self::SETTING_STAFF_MAY_READ_PORTRAITS] ?? true);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', OrganizationRole::Admin->value)
            ->withTimestamps();
    }

    public function mentors(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', OrganizationRole::Mentor->value)
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', OrganizationRole::Member->value)
            ->withTimestamps();
    }

    public function mentorAssignments(): HasMany
    {
        return $this->hasMany(MentorAssignment::class);
    }

    public function mentorNotes(): HasMany
    {
        return $this->hasMany(MentorNote::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function memberLimit(): int
    {
        $plan = $this->currentPlan();

        return $plan['member_limit'] ?? 25;
    }

    public function assessmentsPerPeriod(): int
    {
        $plan = $this->currentPlan();

        return $plan['assessments_per_period'] ?? 50;
    }

    public function assessmentsUsedThisPeriod(): int
    {
        return $this->assessments()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function hasAssessmentQuotaRemaining(): bool
    {
        return $this->assessmentsUsedThisPeriod() < $this->assessmentsPerPeriod();
    }

    private function currentPlan(): array
    {
        $plans = config('billing.plans');

        foreach ($plans as $plan) {
            if (($plan['price_id'] ?? null) === $this->current_price_id) {
                return $plan;
            }
        }

        return [];
    }
}
