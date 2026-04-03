<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes, Billable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'assessment_credits',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function mentorAssignments(): HasMany
    {
        return $this->hasMany(MentorAssignment::class, 'mentor_id');
    }

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(MentorAssignment::class, 'student_id');
    }

    public function mentorNotes(): HasMany
    {
        return $this->hasMany(MentorNote::class, 'mentor_id');
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function curriculumPathways(): HasMany
    {
        return $this->hasMany(CurriculumPathway::class);
    }

    public function latestCurriculumPathway(): HasOne
    {
        return $this->hasOne(CurriculumPathway::class)->latestOfMany();
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->subscribed()) {
            return true;
        }

        return $this->organizations()
            ->where('subscription_status', 'active')
            ->exists();
    }

    public function hasAssessmentAccess(): bool
    {
        if ($this->assessment_credits > 0) {
            return true;
        }

        return $this->organizations()
            ->where('subscription_status', 'active')
            ->get()
            ->contains(fn (Organization $org) => $org->hasAssessmentQuotaRemaining());
    }
}
