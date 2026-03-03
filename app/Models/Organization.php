<?php

namespace App\Models;

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
            ->wherePivot('role', 'admin')
            ->withTimestamps();
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
