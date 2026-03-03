<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $categories = QuestionCategory::orderBy('sort_order')->get()->keyBy('slug');

        $questions = [
            // CATEGORY 1: SERVICE ORIENTATION (3 questions)
            [
                'category' => 'service-orientation',
                'sort_order' => 1,
                'question_text' => 'Think about a time when you helped someone and it actually felt meaningful—not just because you were supposed to, but because you genuinely wanted to. What did you do? What made you want to help in that way?',
                'conversation_prompt' => "I'd like to start by thinking about a time you helped someone — not because you had to, but because you genuinely wanted to. Can you tell me about that? What did you do, and what made you want to help in that way?",
                'follow_up_prompts' => [
                    'What was it about that particular situation that drew you in?',
                    'How did it feel while you were helping? Was there a moment that stood out?',
                    'Would you say that kind of helping is something you find yourself doing often?',
                ],
            ],
            [
                'category' => 'service-orientation',
                'sort_order' => 2,
                'question_text' => "Imagine a friend comes to you with a problem. What kind of problem would make you think, \"I actually want to help with this\"? Describe the situation and what you'd naturally want to do to help.",
                'conversation_prompt' => "Now imagine a friend comes to you with a problem. What kind of problem would genuinely make you think, 'I want to help with this'? Walk me through what that situation looks like and what you'd naturally do.",
                'follow_up_prompts' => [
                    'What is it about that type of problem that energizes you rather than drains you?',
                    'Do you tend to jump in with practical help, or do you lean more toward listening and understanding first?',
                ],
            ],
            [
                'category' => 'service-orientation',
                'sort_order' => 3,
                'question_text' => "When you think about making a difference in someone's life, what does that look like? Describe a specific way you'd want to help people—be as detailed as possible.",
                'conversation_prompt' => "When you picture making a real difference in someone's life, what does that actually look like to you? Be as specific and detailed as you can.",
                'follow_up_prompts' => [
                    'Is that more about a one-on-one impact, or do you see yourself helping many people at once?',
                    'What would tell you that you had actually made that difference?',
                ],
            ],

            // CATEGORY 2: PROBLEM-SOLVING DRAW (3 questions)
            [
                'category' => 'problem-solving-draw',
                'sort_order' => 4,
                'question_text' => "What's something in the world—big or small—that really bothers you or makes you frustrated? It could be something in your school, your community, or society in general. What is it, and why does it bother you so much?",
                'conversation_prompt' => "Let me ask you something different. What's something in the world — big or small — that really bothers you or frustrates you? It could be in your school, community, or the wider world. What is it, and why does it get to you?",
                'follow_up_prompts' => [
                    'When you think about that, do you find yourself wanting to fix it, or is it more that you just can\'t stop noticing it?',
                    'Has that frustration ever led you to actually try to do something about it?',
                ],
            ],
            [
                'category' => 'problem-solving-draw',
                'sort_order' => 5,
                'question_text' => "If you could fix one thing about how the world works, what would it be? Describe what's broken and what you wish was different.",
                'conversation_prompt' => "If you could fix one thing about how the world works, what would you choose? Tell me what feels broken, and what you wish it looked like instead.",
                'follow_up_prompts' => [
                    'What makes you feel like you\'re the kind of person who should work on that problem?',
                    'Is this something you\'ve felt for a long time, or is it more recent?',
                ],
            ],
            [
                'category' => 'problem-solving-draw',
                'sort_order' => 6,
                'question_text' => "Think about a problem you've noticed that most people seem to ignore or accept as normal, but you can't stop thinking about. What is it, and why can't you let it go?",
                'conversation_prompt' => "Now think about something most people seem to ignore or accept as just the way things are — but you can't stop thinking about it. What is it, and why can't you let it go?",
                'follow_up_prompts' => [
                    'Do you feel like you see that problem differently than most people do?',
                    'If you could spend your career working on that, would you want to?',
                ],
            ],

            // CATEGORY 3: ENERGY & ENGAGEMENT (4 questions)
            [
                'category' => 'energy-engagement',
                'sort_order' => 7,
                'question_text' => "Describe a time when you were working on something—a project, activity, hobby, anything—and you completely lost track of time. What were you doing? What made it so absorbing?",
                'conversation_prompt' => "I want to shift to thinking about energy. Can you describe a time when you were working on something — anything — and you completely lost track of time? What were you doing, and what made it so absorbing?",
                'follow_up_prompts' => [
                    'Was it the activity itself, or the people you were doing it with, or the result you were working toward?',
                    'Do you find that happens often, or was this more of a rare experience?',
                ],
            ],
            [
                'category' => 'energy-engagement',
                'sort_order' => 8,
                'question_text' => "Think about a class, project, or activity that actually energized you instead of draining you. What were you doing? What made it feel different from other things?",
                'conversation_prompt' => "Think about a class, project, or activity that actually energized you — where you came out of it with more energy than you went in. What were you doing, and what made it feel different?",
                'follow_up_prompts' => [
                    'What specifically about it gave you energy? Was it the creativity, the problem-solving, the people, the tangible result?',
                    'How does that compare to things that drain you?',
                ],
            ],
            [
                'category' => 'energy-engagement',
                'sort_order' => 9,
                'question_text' => "What's something you've made, built, organized, or accomplished that you're genuinely proud of? Describe what you did and why it mattered to you.",
                'conversation_prompt' => "What's something you've made, built, organized, or accomplished that you're genuinely proud of? Tell me about what you did and why it mattered to you.",
                'follow_up_prompts' => [
                    'Was the pride more about the process of making it, or the final result?',
                    'Did anyone else recognize it, or was it more of a private accomplishment?',
                ],
            ],
            [
                'category' => 'energy-engagement',
                'sort_order' => 10,
                'question_text' => 'If you had a completely free day with no obligations, and you could work on anything you wanted, what would you choose to do? Be specific about the activity, not just "relax" or "hang out."',
                'conversation_prompt' => "If you had a completely free day with no obligations, and you could work on anything you wanted — what would you choose? I'm looking for something specific, not just 'relax' or 'hang out.'",
                'follow_up_prompts' => [
                    'What draws you to that specific activity?',
                    'Do you think that says something about the kind of work you\'re meant to do?',
                ],
            ],

            // CATEGORY 4: VALUES UNDER PRESSURE (3 questions)
            [
                'category' => 'values-under-pressure',
                'sort_order' => 11,
                'question_text' => "Describe a time when you had to choose between two things you cared about—maybe between studying for a test and helping a friend, between what your parents wanted and what you felt was right, or between something safe and something risky. What did you choose and why?",
                'conversation_prompt' => "Now I want to ask about values. Describe a time you had to choose between two things you genuinely cared about — maybe between helping someone and meeting your own obligations, or between safety and risk. What did you choose, and why?",
                'follow_up_prompts' => [
                    'Looking back, do you feel like you made the right choice?',
                    'What does that decision tell you about what you value most?',
                ],
            ],
            [
                'category' => 'values-under-pressure',
                'sort_order' => 12,
                'question_text' => "Think about a decision you made that your friends or family didn't really understand or support. What was it, and why did you choose to do it anyway?",
                'conversation_prompt' => "Think about a decision you made that people around you — friends, family — didn't really understand or support. What was it, and why did you do it anyway?",
                'follow_up_prompts' => [
                    'Was it hard to go against their expectations?',
                    'What gave you the confidence to stick with your decision?',
                ],
            ],
            [
                'category' => 'values-under-pressure',
                'sort_order' => 13,
                'question_text' => "Have you ever had to choose between doing what people expected of you and doing what you actually felt drawn to do? Describe what happened and how you made the decision.",
                'conversation_prompt' => "Have you ever had to choose between what people expected of you and what you actually felt drawn to do? Tell me what happened and how you worked through it.",
                'follow_up_prompts' => [
                    'Do you tend to follow expectations or follow your own sense of direction?',
                    'How do you think about duty versus personal calling?',
                ],
            ],

            // CATEGORY 5: SUFFERING & LIMITATION (2 questions)
            [
                'category' => 'suffering-limitation',
                'sort_order' => 14,
                'question_text' => "Describe a time when something you really wanted didn't work out—maybe you didn't get accepted somewhere, didn't make a team, or failed at something important. How did you respond? What did you do next?",
                'conversation_prompt' => "I want to ask about something harder now. Describe a time when something you really wanted didn't work out — maybe you didn't get accepted somewhere, or failed at something that mattered. How did you respond, and what did you do next?",
                'follow_up_prompts' => [
                    'Looking back, do you see that experience differently now than you did at the time?',
                    'Did that closed door end up pointing you somewhere else?',
                ],
            ],
            [
                'category' => 'suffering-limitation',
                'sort_order' => 15,
                'question_text' => "What's something that limits what you can do right now—maybe money, location, grades, family situation, or something else? How do you think about that limitation? Does it feel like something you need to overcome, or does it help you understand what path you should take?",
                'conversation_prompt' => "What's something that limits what you can do right now — money, location, family, grades, anything? How do you think about that limitation? Is it something to overcome, or does it help you see your path more clearly?",
                'follow_up_prompts' => [
                    'Do you think God uses limitations to guide people?',
                    'Has this limitation shaped what you think you\'re meant to do?',
                ],
            ],

            // CATEGORY 6: LEGACY & IMPACT (3 questions)
            [
                'category' => 'legacy-impact',
                'sort_order' => 16,
                'question_text' => 'Imagine you\'re 40 years old and someone asks you, "What do you do?" How do you hope you\'d answer? What kind of work do you hope you\'ll be doing, and why would it matter?',
                'conversation_prompt' => "Let's think forward. Imagine you're 40 years old and someone asks, 'What do you do?' How do you hope you'd answer? What kind of work do you hope you'll be doing, and why would it matter?",
                'follow_up_prompts' => [
                    'Is the "why it matters" part about the people you help, the thing you build, or the life you live?',
                    'What would make that work feel like a calling rather than just a job?',
                ],
            ],
            [
                'category' => 'legacy-impact',
                'sort_order' => 17,
                'question_text' => "If you could be really good at solving one specific problem in the world—not just talk about it, but actually make progress on it through your career—what problem would you choose? Why that one?",
                'conversation_prompt' => "If you could spend your career actually making progress on one specific problem — not just talking about it, but doing something real — what problem would you choose, and why that one?",
                'follow_up_prompts' => [
                    'Is this related to something you\'ve experienced personally, or something you\'ve observed?',
                    'What kind of role do you see yourself playing in solving it?',
                ],
            ],
            [
                'category' => 'legacy-impact',
                'sort_order' => 18,
                'question_text' => "Think about the kind of impact you want your life to have. When you're older, what do you want people to say about how your work affected them or made things better?",
                'conversation_prompt' => "Think about the impact you want your life to have. When you're older, what do you want people to say about how your work affected them or made things better?",
                'follow_up_prompts' => [
                    'Is that about a wide impact on many people, or a deep impact on a few?',
                    'How does faith shape what that impact looks like for you?',
                ],
            ],

            // CATEGORY 7: CONTEXT & DIRECTION (2 questions)
            [
                'category' => 'context-direction',
                'sort_order' => 19,
                'question_text' => "What are you actually good at? Not what you wish you were good at, but what do people come to you for? What do teachers, friends, or family say you do well? Give specific examples.",
                'conversation_prompt' => "Almost done. What are you actually good at — not what you wish you were good at, but what do people come to you for? What do others say you do well? Give me specific examples.",
                'follow_up_prompts' => [
                    'Do you enjoy those things, or do they just come easily?',
                    'Is there a gap between what you\'re good at and what you want to do?',
                ],
            ],
            [
                'category' => 'context-direction',
                'sort_order' => 20,
                'question_text' => "Right now, what majors or career paths are you considering (even if you're not sure)? What draws you to those options, and what makes you hesitate about them?",
                'conversation_prompt' => "Last question. What majors or career paths are you considering right now, even if you're unsure? What draws you to those options, and what makes you hesitate?",
                'follow_up_prompts' => [
                    'Which of those feels most like "you" versus what others expect?',
                    'If money and expectations weren\'t a factor, would your answer change?',
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
            ]);
        }
    }
}
