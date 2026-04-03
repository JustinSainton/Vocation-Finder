<?php

namespace Database\Seeders;

use App\Models\VocationalCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocVocationalMappingSeeder extends Seeder
{
    public function run(): void
    {
        $categories = VocationalCategory::all()->keyBy('slug');

        $mappings = [
            // Healthcare
            ['29-0000', 'Healthcare Practitioners & Technical', 'healing-care', 0.95],
            ['31-0000', 'Healthcare Support', 'healing-care', 0.90],

            // Education
            ['25-0000', 'Postsecondary Teachers', 'teaching-formation', 0.90],
            ['25-2000', 'Preschool, Elementary, Middle, Secondary Teachers', 'teaching-formation', 0.95],
            ['25-3000', 'Other Teachers & Instructors', 'teaching-formation', 0.85],
            ['25-9000', 'Other Education, Training, Library', 'teaching-formation', 0.80],

            // Management
            ['11-0000', 'Management Occupations', 'leadership-management', 0.85],

            // Legal
            ['23-0000', 'Legal Occupations', 'law-policy', 0.90],

            // Protective Services
            ['33-0000', 'Protective Service Occupations', 'protecting-defending', 0.90],
            ['55-0000', 'Military Specific', 'protecting-defending', 0.85],

            // Construction / Trades
            ['47-0000', 'Construction & Extraction', 'creating-building', 0.85],
            ['51-0000', 'Production Occupations', 'creating-building', 0.75],

            // Installation / Maintenance
            ['49-0000', 'Installation, Maintenance & Repair', 'maintaining-repairing', 0.85],

            // Arts / Design
            ['27-1000', 'Art & Design Workers', 'arts-beauty', 0.90],
            ['27-2000', 'Entertainers & Performers', 'arts-beauty', 0.80],
            ['39-5000', 'Personal Appearance Workers', 'arts-beauty', 0.85],

            // Science / Research
            ['19-0000', 'Life, Physical & Social Science', 'discovering-innovating', 0.90],

            // Food / Hospitality
            ['35-0000', 'Food Preparation & Serving', 'nourishing-hospitality', 0.85],
            ['39-0000', 'Personal Care & Service (Hospitality)', 'nourishing-hospitality', 0.75],

            // Sales / Commerce
            ['41-0000', 'Sales & Related', 'commerce-enterprise', 0.80],

            // Business / Finance
            ['13-0000', 'Business & Financial Operations', 'finance-economics', 0.85],

            // Media / Communication
            ['27-3000', 'Media & Communication Workers', 'communication-media', 0.85],
            ['27-4000', 'Media & Communication Equipment Workers', 'communication-media', 0.75],

            // Community / Social Service
            ['21-0000', 'Community & Social Service', 'advocating-supporting', 0.90],

            // Library / Archival
            ['25-4000', 'Librarians, Curators & Archivists', 'knowledge-information', 0.85],

            // Computer / Math / Systems
            ['15-0000', 'Computer & Mathematical', 'administration-systems', 0.80],
            ['43-0000', 'Office & Administrative Support', 'administration-systems', 0.70],

            // Cross-cutting: Management also maps to Leadership
            ['11-1000', 'Top Executives', 'leadership-management', 0.90],
            ['11-3000', 'Operations Specialties Managers', 'leadership-management', 0.85],
            ['11-9000', 'Other Management Occupations', 'leadership-management', 0.80],

            // Transportation (maps to multiple)
            ['53-0000', 'Transportation & Material Moving', 'administration-systems', 0.60],

            // Farming (maps to multiple)
            ['45-0000', 'Farming, Fishing & Forestry', 'creating-building', 0.70],
        ];

        foreach ($mappings as [$socGroup, $socName, $categorySlug, $relevance]) {
            $category = $categories->get($categorySlug);

            if (! $category) {
                continue;
            }

            DB::table('soc_vocational_mappings')->updateOrInsert(
                [
                    'soc_major_group' => $socGroup,
                    'vocational_category_id' => $category->id,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'soc_group_name' => $socName,
                    'default_relevance' => $relevance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
