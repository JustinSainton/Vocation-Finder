<?php

namespace App\Models;

use App\Enums\IncomeBand;
use App\Support\AccessPolicy;
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
    use Billable, HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'assessment_credits',
        'expo_push_token',
        'birthdate',
        'grade_level',
        'gpa',
        'household_income_band',
        'home_state',
        'calendar_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'grade_level' => 'integer',
            'gpa' => 'float',
            'household_income_band' => IncomeBand::class,
            // Cashier requires this cast for onTrial()/onGenericTrial() to
            // work at all; without it a generic trial throws rather than
            // returning false.
            'trial_ends_at' => 'datetime',
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

    /**
     * The colleges on this student's list.
     *
     * @return BelongsToMany<College, $this>
     */
    public function collegeInterests(): BelongsToMany
    {
        return $this->belongsToMany(College::class, 'college_interests')
            ->using(CollegeInterest::class)
            ->withPivot('note')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<Syllabus, $this>
     */
    public function syllabi(): HasMany
    {
        return $this->hasMany(Syllabus::class);
    }

    /**
     * @return HasMany<Gap, $this>
     */
    public function gaps(): HasMany
    {
        return $this->hasMany(Gap::class);
    }

    /**
     * Who Stripe bills and who gets the receipt.
     *
     * For a minor, that is the parent who consented — they are the one with
     * the card, and a fifteen-year-old should not be the name on a payment
     * dispute. The subscription itself still belongs to the student's record,
     * because the coach and the brain are theirs and must survive the parent
     * relationship changing.
     */
    public function stripeEmail(): ?string
    {
        if (! AccessPolicy::requiresParentCheckout($this)) {
            return $this->email;
        }

        return $this->parentConsents()->granted()->value('parent_email') ?? $this->email;
    }

    /**
     * @return HasMany<ReadinessSnapshot, $this>
     */
    public function readinessSnapshots(): HasMany
    {
        return $this->hasMany(ReadinessSnapshot::class);
    }

    /**
     * @return HasMany<BrainEntry, $this>
     */
    public function brainEntries(): HasMany
    {
        return $this->hasMany(BrainEntry::class);
    }

    /**
     * @return HasMany<Action, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    /**
     * @return HasMany<ParentConsent, $this>
     */
    public function parentConsents(): HasMany
    {
        return $this->hasMany(ParentConsent::class);
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

    public function careerProfile(): HasOne
    {
        return $this->hasOne(CareerProfile::class);
    }

    public function savedJobs(): BelongsToMany
    {
        return $this->belongsToMany(JobListing::class, 'saved_jobs')
            ->withTimestamps();
    }

    public function voiceProfile(): HasOne
    {
        return $this->hasOne(VoiceProfile::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function resumeVersions(): HasMany
    {
        return $this->hasMany(ResumeVersion::class);
    }

    public function coverLetters(): HasMany
    {
        return $this->hasMany(CoverLetter::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
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
