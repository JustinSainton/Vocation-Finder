<?php

namespace App\Models;

use App\Enums\CollegeControl;
use App\Enums\CollegeKind;
use App\Support\CollegeApplication;
use App\Support\CollegeCost;
use App\Support\CollegeStanding;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An institution, carrying only what it publishes about itself.
 *
 * The model has no opinion methods on purpose. Everything the student is told
 * about a college relative to *them* — where they stand, what it would cost
 * them, what applying involves — is computed in {@see CollegeStanding},
 * {@see CollegeCost} and {@see CollegeApplication},
 * because those three are the judgements and judgements belong somewhere a
 * test can hold them still.
 */
class College extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'city', 'state', 'control', 'kind',
        'acceptance_rate', 'gpa_25th', 'gpa_75th', 'test_optional',
        'tuition_in_state', 'tuition_out_of_state', 'room_and_board',
        'net_price_by_income', 'application_system', 'application_url',
        'deadline_month', 'deadline_day', 'application_fee', 'fee_waiver_available',
    ];

    protected function casts(): array
    {
        return [
            'control' => CollegeControl::class,
            'kind' => CollegeKind::class,
            'acceptance_rate' => 'float',
            'gpa_25th' => 'float',
            'gpa_75th' => 'float',
            'test_optional' => 'boolean',
            'fee_waiver_available' => 'boolean',
            'net_price_by_income' => 'array',
        ];
    }

    public function vocationalCategories(): BelongsToMany
    {
        return $this->belongsToMany(VocationalCategory::class)
            ->withPivot(['program_name', 'program_url'])
            ->withTimestamps();
    }

    /**
     * Institution-specific links for the services every campus has.
     *
     * @return HasMany<CollegeResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(CollegeResource::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'college_interests')
            ->using(CollegeInterest::class)
            ->withTimestamps();
    }

    /**
     * Whether a published admitted-student range exists to compare against.
     */
    public function publishesAdmissionRange(): bool
    {
        return $this->gpa_25th !== null && $this->gpa_75th !== null;
    }

    /**
     * Deliberately not named `where()`: that is Eloquent's query builder, and
     * an instance method of that name shadows it on every model instance.
     */
    public function location(): string
    {
        return $this->city.', '.$this->state;
    }
}
