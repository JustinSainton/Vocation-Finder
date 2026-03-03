<?php

namespace Database\Seeders;

use App\Models\QuestionCategory;
use Illuminate\Database\Seeder;

class QuestionCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Service Orientation',
                'slug' => 'service-orientation',
                'description' => 'How someone naturally serves others — direct care vs. building systems vs. creating beauty vs. teaching.',
                'theological_basis' => 'Reformed theology says all work is service to neighbor, but people serve in fundamentally different ways.',
                'what_it_reveals' => 'The mode of their calling — are they drawn to healing, teaching, creating, organizing, protecting, etc.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Problem-Solving Draw',
                'slug' => 'problem-solving-draw',
                'description' => 'What disorder or need compels them — what kind of brokenness draws their attention.',
                'theological_basis' => 'Cultural mandate = addressing disorder and cultivating creation. People are drawn to different kinds of brokenness.',
                'what_it_reveals' => 'The domain of their calling — physical suffering, systemic injustice, lack of beauty, intellectual confusion, environmental degradation, etc.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Energy & Engagement',
                'slug' => 'energy-engagement',
                'description' => 'Where their gifts actually lie, revealed through flow states and energy patterns.',
                'theological_basis' => 'Gifts are from God for service. Flow states reveal natural gifting. Energy vs. drain distinguishes calling from obligation.',
                'what_it_reveals' => 'The method of their work — do they work with people, systems, ideas, or things? Creating, organizing, discovering, caring?',
                'sort_order' => 3,
            ],
            [
                'name' => 'Values Under Pressure',
                'slug' => 'values-under-pressure',
                'description' => 'How they actually make decisions when competing goods collide.',
                'theological_basis' => 'Luther emphasized vocation includes your "station" (family, community responsibilities). Reformed theology recognizes calling isn\'t just about personal fulfillment.',
                'what_it_reveals' => 'Their theological maturity and how they navigate real constraints — do they honor family duty, take risks, seek security, follow conviction?',
                'sort_order' => 4,
            ],
            [
                'name' => 'Suffering & Limitation',
                'slug' => 'suffering-limitation',
                'description' => 'How they interpret obstacles, closed doors, and limitations.',
                'theological_basis' => 'Reformed theology has strong view of Providence. Closed doors can be guidance, not just obstacles.',
                'what_it_reveals' => 'Whether they see limitations as God\'s direction or just barriers. How they discern Providence. Adaptability vs. perseverance.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Legacy & Impact',
                'slug' => 'legacy-impact',
                'description' => 'What they want to ultimately contribute — the scale and vision of their calling.',
                'theological_basis' => 'Cultural mandate = long-term cultivation. Reveals scope of calling (individual vs. systemic, local vs. broad).',
                'what_it_reveals' => 'Whether they think about individuals helped, systems changed, beauty created, knowledge advanced, communities built.',
                'sort_order' => 6,
            ],
            [
                'name' => 'Context & Direction',
                'slug' => 'context-direction',
                'description' => 'Current practical skills, interests, and career considerations.',
                'theological_basis' => 'Discernment requires honest assessment of current gifts and opportunities alongside aspiration.',
                'what_it_reveals' => 'Practical grounding — what are they already good at, and what paths are they considering?',
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $category) {
            QuestionCategory::create($category);
        }
    }
}
