<?php

namespace Database\Factories;

use App\Enums\VettingStatus;
use App\Enums\WorkKind;
use App\Models\JobListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobListing>
 */
class JobListingFactory extends Factory
{
    /**
     * The default is deliberately an *unvetted* listing with no stated minimum
     * age — the shape a posting actually arrives in from an aggregator. A
     * factory whose default is already safe would let a test pass while the
     * gate it is testing does nothing.
     */
    public function definition(): array
    {
        return [
            'title' => 'Veterinary Assistant',
            'work_kind' => WorkKind::Job,
            'company_name' => 'Bell Road Animal Clinic',
            'company_url' => 'https://example.com/careers',
            'location' => 'Dayton, OH',
            'is_remote' => false,
            'salary_min' => 15,
            'salary_max' => 18,
            'salary_currency' => 'USD',
            'description' => 'Help with intake, cleaning and animal handling on weekend shifts.',
            'description_plain' => 'Help with intake, cleaning and animal handling on weekend shifts.',
            'source' => 'adzuna',
            'source_id' => Str::uuid()->toString(),
            'source_url' => 'https://example.com/posting',
            'classification_status' => 'classified',
            'minimum_age' => null,
            'supervised' => null,
            'vetting_status' => VettingStatus::Pending,
            'posted_at' => now()->subDays(3),
        ];
    }

    /**
     * Through the vetting pass, and open to sixteen-year-olds.
     */
    public function openToTeens(int $minimumAge = 16): static
    {
        return $this->state(fn () => [
            'vetting_status' => VettingStatus::Passed,
            'minimum_age' => $minimumAge,
            'supervised' => true,
        ]);
    }

    public function apprenticeship(): static
    {
        return $this->state(fn () => [
            'work_kind' => WorkKind::Apprenticeship,
            'title' => 'Electrical Apprentice',
        ]);
    }

    /**
     * Advance-fee fraud, in the words these postings actually use.
     */
    public function scam(): static
    {
        return $this->state(fn () => [
            'title' => 'Work from home admin assistant',
            'description_plain' => 'No experience necessary earn $500 a day. Small investment required for your starter kit. Interview via telegram.',
            'description' => 'No experience necessary earn $500 a day. Small investment required for your starter kit. Interview via telegram.',
        ]);
    }
}
