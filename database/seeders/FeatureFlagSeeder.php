<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

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
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(
                ['key' => $flag['key']],
                $flag
            );
        }
    }
}
