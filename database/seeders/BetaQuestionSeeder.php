<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Database\Seeder;

class BetaQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $categories = QuestionCategory::orderBy('sort_order')->get()->keyBy('slug');

        $questions = [
            [
                'category' => 'context-direction',
                'sort_order' => 1,
                'is_beta' => true,
                'question_text' => 'In the past 6–12 months, what sense of direction or calling has become clearer in your life? Describe specific moments, thoughts, or experiences that shaped this. (If you are a person of faith, include anything you believe God is speaking to you.)',
                'conversation_prompt' => "Let's start with your sense of direction. In the past 6 to 12 months, what's become clearer to you about where you're headed — your calling or purpose? Tell me about specific moments, thoughts, or experiences that shaped this. If faith is part of your story, include anything you feel God has been speaking to you.",
                'follow_up_prompts' => [
                    'Was there a specific moment or conversation that crystallized this for you?',
                    'How has that sense of direction changed or sharpened over time?',
                    'What would it mean for you to act on this direction?',
                ],
            ],
            [
                'category' => 'service-orientation',
                'sort_order' => 2,
                'is_beta' => true,
                'question_text' => 'What do people consistently come to you for help with? What have mentors, leaders, or friends affirmed in you repeatedly?',
                'conversation_prompt' => 'Think about the people in your life — mentors, friends, colleagues, family. What do they consistently come to you for? What have they affirmed in you, maybe so often that you almost take it for granted?',
                'follow_up_prompts' => [
                    'Does that affirmation surprise you, or does it match how you see yourself?',
                    'Is there a pattern in what people come to you for — a type of problem or a kind of support?',
                    'How does being needed in that way make you feel?',
                ],
            ],
            [
                'category' => 'energy-engagement',
                'sort_order' => 3,
                'is_beta' => true,
                'question_text' => 'When do you feel most effective and "in your element"? Describe the kind of work, environment, and problems where you naturally excel.',
                'conversation_prompt' => 'When are you most in your element — doing your best work, feeling fully alive and effective? Describe the kind of work, the environment, and the kinds of problems where you naturally excel.',
                'follow_up_prompts' => [
                    'What is it about that context that draws out your best?',
                    'Is it the type of work, the people around you, or the problem itself that energizes you most?',
                    'How often do you find yourself in that kind of environment right now?',
                ],
            ],
            [
                'category' => 'problem-solving-draw',
                'sort_order' => 4,
                'is_beta' => true,
                'question_text' => 'What problems in the world frustrate or move you so deeply that you feel compelled to act? Be specific about the kind of people or situations involved.',
                'conversation_prompt' => 'What problems in the world frustrate or move you so deeply that you feel compelled to do something about them? Be as specific as you can — what kind of people are involved, what kind of situations, and why does it get to you the way it does?',
                'follow_up_prompts' => [
                    'When did you first start noticing this problem or feeling this way about it?',
                    'Is this more of a personal burden — something that weighs on you — or more of an intellectual conviction?',
                    'Have you ever tried to act on it? What happened?',
                ],
            ],
            [
                'category' => 'values-under-pressure',
                'sort_order' => 5,
                'is_beta' => true,
                'question_text' => 'What opportunities, responsibilities, or doors are already in front of you right now that require your faithfulness?',
                'conversation_prompt' => "Let's close by looking at where you are right now. What opportunities, responsibilities, or open doors are already in front of you that require your faithfulness — things that may not be glamorous or final, but are clearly yours to steward?",
                'follow_up_prompts' => [
                    'Do you feel ready for those responsibilities, or do they feel bigger than you?',
                    'Is there anything you\'ve been putting off or avoiding that you sense you should lean into?',
                    'How do you think being faithful in the small things connects to where you\'re ultimately headed?',
                ],
            ],
        ];

        foreach ($questions as $q) {
            $category = $categories[$q['category']];

            Question::create([
                'category_id' => $category->id,
                'question_text' => $q['question_text'],
                'conversation_prompt' => $q['conversation_prompt'],
                'follow_up_prompts' => $q['follow_up_prompts'],
                'sort_order' => $q['sort_order'],
                'is_beta' => true,
            ]);
        }
    }
}
