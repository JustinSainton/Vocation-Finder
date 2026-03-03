<?php

namespace Database\Seeders;

use App\Models\VocationalCategory;
use Illuminate\Database\Seeder;

class VocationalCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Healing & Care',
                'slug' => 'healing-care',
                'description' => 'Called to restore wholeness — physical, emotional, or spiritual — through direct care and compassion.',
                'ministry_connection' => 'Healthcare, counseling, and caregiving are acts of healing that reflect Christ\'s ministry to the sick and suffering. Every patient encounter, every therapy session, every compassionate act of care is ministry.',
                'career_pathways' => ['Medicine', 'Nursing', 'Counseling/Therapy', 'Social Work', 'Physical Therapy', 'Occupational Therapy', 'Chaplaincy', 'Public Health'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Teaching & Formation',
                'slug' => 'teaching-formation',
                'description' => 'Called to shape understanding, develop potential, and form character through education and mentorship.',
                'ministry_connection' => 'Teaching is one of the most ancient forms of ministry. Whether in a classroom, a mentoring relationship, or a training program, forming minds and character is deeply spiritual work.',
                'career_pathways' => ['K-12 Education', 'Higher Education', 'Curriculum Development', 'Training & Development', 'Tutoring', 'Educational Technology', 'Special Education'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Leadership & Management',
                'slug' => 'leadership-management',
                'description' => 'Called to organize people and resources toward shared vision, stewarding responsibility for collective outcomes.',
                'ministry_connection' => 'Stewarding people and resources well is an act of service. Leading teams, managing organizations, and casting vision are expressions of the calling to shepherd and serve.',
                'career_pathways' => ['Executive Leadership', 'Operations Management', 'Project Management', 'Human Resources', 'Nonprofit Leadership', 'Team Leadership', 'Consulting'],
                'sort_order' => 3,
            ],
            [
                'name' => 'Law & Policy',
                'slug' => 'law-policy',
                'description' => 'Called to establish and maintain justice through legal frameworks, advocacy, and governance.',
                'ministry_connection' => 'Pursuing justice is central to biblical calling. Working to create fair laws, defend the vulnerable, and govern well is ministry that reflects God\'s concern for righteousness.',
                'career_pathways' => ['Law (Litigation, Corporate, Public Interest)', 'Policy Analysis', 'Government Service', 'Legislative Work', 'Mediation', 'Compliance'],
                'sort_order' => 4,
            ],
            [
                'name' => 'Protecting & Defending',
                'slug' => 'protecting-defending',
                'description' => 'Called to safeguard people, communities, and institutions from harm.',
                'ministry_connection' => 'Protection of the vulnerable is a deeply biblical calling. Whether through military service, law enforcement, cybersecurity, or advocacy, defending others is an act of sacrificial love.',
                'career_pathways' => ['Military', 'Law Enforcement', 'Firefighting/EMT', 'Cybersecurity', 'National Security', 'Child Advocacy', 'Environmental Protection'],
                'sort_order' => 5,
            ],
            [
                'name' => 'Creating & Building',
                'slug' => 'creating-building',
                'description' => 'Called to bring new things into existence — structures, systems, products, or environments that serve human flourishing.',
                'ministry_connection' => 'Creating and building reflects the image of God as Creator. Architecture, engineering, and construction are acts of cultivating creation and building environments where people can flourish.',
                'career_pathways' => ['Architecture', 'Engineering (Civil, Mechanical, Electrical)', 'Construction Management', 'Urban Planning', 'Product Design', 'Industrial Design'],
                'sort_order' => 6,
            ],
            [
                'name' => 'Maintaining & Repairing',
                'slug' => 'maintaining-repairing',
                'description' => 'Called to preserve, restore, and keep things working — the essential work of stewardship.',
                'ministry_connection' => 'Faithful maintenance is a form of stewardship that keeps the world running. Repairing what is broken and maintaining what is good is ministry that sustains communities.',
                'career_pathways' => ['Skilled Trades (Electrical, Plumbing, HVAC)', 'IT Infrastructure', 'Facilities Management', 'Conservation', 'Automotive Technology', 'Restoration'],
                'sort_order' => 7,
            ],
            [
                'name' => 'Arts & Beauty',
                'slug' => 'arts-beauty',
                'description' => 'Called to create beauty, tell stories, and express truth through artistic expression.',
                'ministry_connection' => 'Beauty is not a luxury but a necessity for the human soul. Creating art, music, literature, and film is ministry that reveals truth, offers hope, and nurtures the spirit.',
                'career_pathways' => ['Visual Arts', 'Music', 'Film & Video', 'Writing & Literature', 'Graphic Design', 'Photography', 'Theater', 'Dance'],
                'sort_order' => 8,
            ],
            [
                'name' => 'Discovering & Innovating',
                'slug' => 'discovering-innovating',
                'description' => 'Called to push the boundaries of human knowledge and create new solutions through research and innovation.',
                'ministry_connection' => 'Discovering truth about the natural world is exploring God\'s creation. Scientific research and technological innovation serve humanity and steward the resources of creation.',
                'career_pathways' => ['Scientific Research', 'Technology Development', 'R&D', 'Academic Research', 'Biotech', 'Environmental Science', 'Data Science'],
                'sort_order' => 9,
            ],
            [
                'name' => 'Nourishing & Hospitality',
                'slug' => 'nourishing-hospitality',
                'description' => 'Called to nourish bodies and create welcoming spaces where people feel cared for and connected.',
                'ministry_connection' => 'Hospitality is one of the most consistently emphasized virtues in Scripture. Feeding people, creating gathering spaces, and making others feel welcome is deeply spiritual work.',
                'career_pathways' => ['Culinary Arts', 'Restaurant Management', 'Hospitality Management', 'Event Planning', 'Food Science', 'Agriculture', 'Nutrition'],
                'sort_order' => 10,
            ],
            [
                'name' => 'Commerce & Enterprise',
                'slug' => 'commerce-enterprise',
                'description' => 'Called to create economic value, build businesses, and facilitate exchange that serves communities.',
                'ministry_connection' => 'Business is a vehicle for serving communities through job creation, meeting needs, and stewarding resources. Entrepreneurship at its best is an act of creation and service.',
                'career_pathways' => ['Entrepreneurship', 'Business Development', 'Sales', 'Marketing', 'Supply Chain', 'Real Estate', 'Retail Management'],
                'sort_order' => 11,
            ],
            [
                'name' => 'Finance & Economics',
                'slug' => 'finance-economics',
                'description' => 'Called to steward financial resources and understand economic systems for the common good.',
                'ministry_connection' => 'Financial stewardship is a biblical responsibility. Managing money well — for individuals, organizations, or societies — is ministry that enables flourishing and prevents exploitation.',
                'career_pathways' => ['Accounting', 'Financial Planning', 'Investment Management', 'Banking', 'Economics Research', 'Insurance', 'Financial Counseling'],
                'sort_order' => 12,
            ],
            [
                'name' => 'Communication & Media',
                'slug' => 'communication-media',
                'description' => 'Called to inform, connect, and tell stories that shape how people understand the world.',
                'ministry_connection' => 'Truth-telling and storytelling are powerful forms of ministry. Journalism, media, and communications shape culture and can bring light to darkness.',
                'career_pathways' => ['Journalism', 'Public Relations', 'Broadcasting', 'Content Creation', 'Digital Media', 'Advertising', 'Corporate Communications'],
                'sort_order' => 13,
            ],
            [
                'name' => 'Advocating & Supporting',
                'slug' => 'advocating-supporting',
                'description' => 'Called to speak on behalf of others, support those in need, and ensure marginalized voices are heard.',
                'ministry_connection' => 'Advocacy for the vulnerable and voiceless is central to biblical justice. Supporting others through nonprofit work, community organizing, or direct service is deeply ministerial.',
                'career_pathways' => ['Nonprofit Management', 'Community Organizing', 'Social Services', 'Refugee/Immigration Services', 'Disability Advocacy', 'Human Rights', 'International Development'],
                'sort_order' => 14,
            ],
            [
                'name' => 'Knowledge & Information',
                'slug' => 'knowledge-information',
                'description' => 'Called to organize, preserve, and make knowledge accessible for the benefit of all.',
                'ministry_connection' => 'Stewarding knowledge is a sacred responsibility. Libraries, archives, and information systems serve truth and make wisdom accessible to all people.',
                'career_pathways' => ['Library Science', 'Information Management', 'Archival Work', 'Knowledge Management', 'Technical Writing', 'Translation', 'Publishing'],
                'sort_order' => 15,
            ],
            [
                'name' => 'Administration & Systems',
                'slug' => 'administration-systems',
                'description' => 'Called to design and maintain the systems that allow organizations and communities to function effectively.',
                'ministry_connection' => 'Administration is a spiritual gift (1 Cor 12:28). Well-designed systems free people to do their best work. This is the invisible ministry that makes all other ministry possible.',
                'career_pathways' => ['Systems Administration', 'Software Engineering', 'Database Management', 'Office Management', 'Process Improvement', 'Quality Assurance'],
                'sort_order' => 16,
            ],
            [
                'name' => 'Pastoral & Missionary Work',
                'slug' => 'pastoral-missionary',
                'description' => 'Called to vocational ministry — shepherding communities of faith, missions, and church leadership.',
                'ministry_connection' => 'Vocational ministry is a specific calling to shepherd, teach, and lead within the church. This does not mean other callings are less ministerial — but some are called to serve the church directly.',
                'career_pathways' => ['Pastoral Ministry', 'Missions', 'Church Planting', 'Youth Ministry', 'Campus Ministry', 'Worship Leadership', 'Theological Education', 'Parachurch Organizations'],
                'sort_order' => 17,
            ],
        ];

        foreach ($categories as $category) {
            VocationalCategory::create($category);
        }
    }
}
