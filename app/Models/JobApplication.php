<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUSES = [
        'saved', 'applied', 'phone_screen', 'interviewing',
        'offered', 'accepted', 'rejected', 'declined', 'withdrawn', 'ghosted',
    ];

    public const TERMINAL_STATUSES = ['accepted', 'rejected', 'declined', 'withdrawn'];

    public const VALID_TRANSITIONS = [
        'saved' => ['applied', 'withdrawn'],
        'applied' => ['phone_screen', 'interviewing', 'rejected', 'withdrawn', 'ghosted'],
        'phone_screen' => ['interviewing', 'rejected', 'withdrawn', 'ghosted'],
        'interviewing' => ['offered', 'rejected', 'withdrawn', 'ghosted'],
        'offered' => ['accepted', 'declined', 'withdrawn'],
        'ghosted' => ['interviewing'],
    ];

    protected $fillable = [
        'user_id',
        'job_listing_id',
        'resume_version_id',
        'cover_letter_id',
        'status',
        'company_name',
        'job_title',
        'job_url',
        'applied_at',
        'salary_offered',
        'notes',
        'priority',
        'source',
        'contact_name',
        'contact_email',
        'next_action',
        'next_action_date',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'next_action_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class);
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class);
    }

    public function coverLetter(): BelongsTo
    {
        return $this->belongsTo(CoverLetter::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->orderByDesc('occurred_at');
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowed);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }
}
