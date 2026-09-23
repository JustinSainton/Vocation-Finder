<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key' => 'job_discovery',
                'name' => 'Job Discovery',
                'description' => 'Job listing aggregation, search, and vocational pathway matching.',
            ],
            [
                'key' => 'career_profile',
                'name' => 'Career Profile & LinkedIn Import',
                'description' => 'Structured career data from LinkedIn PDF upload or manual entry.',
            ],
            [
                'key' => 'resume_builder',
                'name' => 'AI Resume Builder',
                'description' => 'One-click resume generation and conversational resume coach.',
            ],
            [
                'key' => 'cover_letter_builder',
                'name' => 'AI Cover Letter Builder',
                'description' => 'Personalized cover letter generation with 3-touch method.',
            ],
            [
                'key' => 'application_tracking',
                'name' => 'Application Tracking',
                'description' => 'Track job applications through the full hiring pipeline.',
            ],
            [
                'key' => 'voice_profile',
                'name' => 'Voice Profile',
                'description' => 'Writing style analysis for anti-AI-slop resume generation.',
            ],
            [
                'key' => 'job_alerts',
                'name' => 'Job Alert Notifications',
                'description' => 'Push notifications when new jobs match the user\'s vocational profile.',
            ],
            [
                'key' => 'career_coach',
                'name' => 'AI Career Coaching Conversation',
                'description' => 'Multi-turn AI conversation for career exploration and job narrowing.',
            ],
            [
                'key' => 'courses',
                'name' => 'Courses & Learning Pathways',
                'description' => 'Course catalogue, enrollment, personalized content and AI-curated curriculum pathways. Legacy: V1 ships no published courses, so this stays off until there are some.',
            ],
            [
                'key' => 'pathway_coach',
                'name' => 'Pathway Coach (students)',
                'description' => 'The student-facing coach and vocational brain. Separate from career_coach, which is the adult product — the two must be able to ship independently. On by default: it is where the assessment hands off. Kept as a kill switch for the one surface that calls a model on every turn.',
                'enabled_by_default' => true,
            ],
        ];

        foreach ($flags as $definition) {
            $flag = FeatureFlag::updateOrCreate(
                ['key' => $definition['key']],
                Arr::except($definition, 'enabled_by_default'),
            );

            /*
             * Only when the row is new. Re-seeding must never overrule an
             * operator who switched a flag off on purpose.
             */
            if ($flag->wasRecentlyCreated && ($definition['enabled_by_default'] ?? false)) {
                $flag->update(['is_enabled' => true]);
            }
        }
    }
}
