<?php

namespace App\Support;

use App\Models\Question;

/**
 * One student, answering every question, for demo mode.
 *
 * Maya is a seventeen-year-old senior in Columbus whose younger brother has
 * dyslexia. The answers are written to agree with each other — the same
 * brother, the same library homework club, the same weekend job — because the
 * portrait is built from patterns across answers, and a demo persona whose
 * answers contradict one another produces a portrait nobody would believe.
 *
 * Keyed by sort order within each question set rather than by id, so the
 * persona survives a reseed. English only: a translated question still gets
 * the English answer, which is fine for a demo and wrong for anything else.
 *
 * @see DemoMode
 */
class DemoPersona
{
    public const NAME = 'Maya, 17';

    /**
     * The full twenty-question assessment, by `sort_order`.
     *
     * @var array<int, string>
     */
    public const STANDARD = [
        1 => "Last spring my little brother Eli, who's eleven and has dyslexia, came home crying because a sub made him read out loud in front of the class. That night I sat with him and we tried reading the same chapter with the audiobook playing while he traced the lines with his finger. It clicked. He read two pages by himself and looked at me like he'd gotten away with something. Nobody asked me to do that. I just couldn't stand that he'd decided he was stupid when really nobody had shown him how his brain works. We still do it most nights.",
        2 => "If a friend came to me because they were falling behind in a class and starting to believe they just weren't smart, I would want to help with that more than anything. I'd want to sit down with their notes, figure out where it stopped making sense, and try a few different ways of explaining it until one landed. I'm not the friend you come to for relationship drama. I'm the one you come to when you're overwhelmed and need someone to break a big thing into small steps with you.",
        3 => "Making a difference looks like a kid who used to hide during reading time raising their hand. At the library homework club I volunteer at on Tuesdays and Thursdays, there's a third grader named Josiah who wouldn't open a book in September. By December he was picking the books. I didn't do anything magic. I noticed he liked trucks, found him easy books about trucks, and let him read to me instead of the other way around. That's what I want to do: notice what a kid needs and build the path from there.",
        4 => "It bothers me how many kids get labeled lazy or bad at school when really they learn differently and nobody caught it. Eli went almost three years before anyone tested him for dyslexia, and only because my mom kept pushing. Families like mine don't always know what to ask for, and schools are stretched thin. So the kids whose parents can pay for tutors or evaluations get help, and everyone else gets told to try harder. That feels deeply unfair to me, because it's decided so early and it follows you.",
        5 => "I would make sure every kid got screened for reading and learning differences early, like vision and hearing tests, and that the help that follows is free. Right now it depends on whether your parents know the system and have time to fight it. My mom works as a home health aide and my dad works nights at a warehouse. They love us, but they didn't know what an IEP was. The fix is not complicated. It just has to not depend on luck.",
        6 => "Most people accept that some kids are just 'not readers.' I can't let that go. When I hear a younger kid say 'I hate reading,' what I usually hear underneath is 'reading makes me feel dumb.' Adults nod and move on. I keep thinking about how many of those kids are actually like Eli, bright and funny and quick, but stuck behind one skill nobody taught them in the way they needed. It stays with me because I watched it almost happen in my own house.",
        7 => "Over winter break I built a tracking sheet for the reading buddies program I started at my church. It has every pair, what book they're on, and little notes after each session, plus a set of phonics flashcards I designed and printed. I sat down after dinner and when I looked up it was almost 1 a.m. It was absorbing because it was a puzzle with real people in it. Every column I added was a way to notice something about a kid a little sooner.",
        8 => "AP Psychology, especially the unit on learning and memory. We learned about working memory and how the brain processes language, and I kept raising my hand because it was like someone was explaining my brother to me. I did my project on how multisensory reading instruction works, and I actually used it with Eli. Chemistry drains me even though I get good grades in it, because it doesn't connect to anyone. Psych felt different because it was about why people struggle and what actually helps.",
        9 => "I'm proudest of Reading Buddies. Last fall I asked our youth pastor, Dana, if I could pair high schoolers from youth group with third graders from the church's after-school program. We started with four pairs and now we have eight. I recruited the volunteers, made the schedule, trained everyone on how to let the kid do the reading, and kept the tracking sheet. One mom told me her daughter now reads to her little sister at night. It mattered because it kept going even on the weeks I couldn't be there.",
        10 => "Honestly I'd spend it at the library making new materials for Reading Buddies. I've wanted to make a set of decodable mini-books with stories the kids would actually care about, like soccer and Minecraft and little brothers who are annoying. I'd write them, lay them out, print them, and then test one with Eli that night to see where he got stuck. Then I'd probably go for a long walk and call my best friend Priya, because I think better when I'm talking out loud.",
        11 => 'Senior year I had to choose between varsity volleyball and keeping my library tutoring shifts plus my weekend job at Harvest Market. I love volleyball and my friends were all on the team. But practices were Tuesday and Thursday, the same days as homework club, and I need the job for gas and to save for college. I chose tutoring and work. It was sad for a week. But every Tuesday when Josiah runs up to show me his book, I know I chose right.',
        12 => "Quitting volleyball. My dad played sports and thought it would help me get a scholarship, and my teammates thought I was being dramatic. I tried to explain that I'm not going to play in college, but I might actually work with kids who struggle to read, and the homework club was where I was learning how. My dad came around after he visited the library once and saw the kids. He didn't say much, but he drove me the next week.",
        13 => "Everyone expected me to go pre-med because I'm good at science and my mom works in health care. For a while I said I wanted to be a nurse because it made people nod. But when I actually pictured it, it didn't feel like me. What I'm drawn to is the moment when a kid who thought they couldn't learn realizes they can. I told my mom that in the car one night. She was quiet for a second and then said, 'You've been doing that for Eli for years.'",
        14 => "I applied to a summer research program at Ohio State for high schoolers and didn't get in. I'd spent weeks on the essay and I was really hurt. For a couple days I felt like it proved I wasn't the kind of person who does 'real' academic stuff. Then the library asked if I'd help run their summer reading camp, and I said yes. I ended up planning activities for twenty kids. I learned more about teaching reading that summer than any program would have taught me.",
        15 => "Money, mostly. My family can't pay for a four-year school out of pocket, so I'm looking at in-state schools and maybe starting at Columbus State and transferring. I'm also needed at home, because I drive Eli to things when my parents are working. Sometimes it feels like a wall. But it also tells me something. I don't want a path where I disappear for years. I want something I can start now, close to home, working with kids in communities like mine.",
        16 => "I hope I'd say, 'I help kids who think they're bad at school find out their brains just work differently, and then I help them learn in the way they actually learn.' Maybe as a reading specialist or running a learning center that's free for families who can't pay. It would matter because every kid I work with is one less kid who spends years believing they're stupid. That belief costs so much, and it's usually wrong.",
        17 => "Kids with dyslexia and other learning differences in low-income schools getting identified late or never. I'd want to be really good at spotting it early and knowing exactly which kind of instruction helps. It's that one because I've seen the difference it made for Eli once someone finally named it, and I've seen how many kids at the homework club are probably in the same place with nobody to push for them.",
        18 => "I want people to say I made them feel capable. That I was patient, that I noticed things other people missed, and that I didn't give up on them. I'd love for a grown-up to tell me someday, 'You were the first person who made reading not scary.' And I'd want the parents to say I helped them understand how to advocate for their kid, because my mom had to figure that out alone.",
        19 => "People come to me to explain things. My friends ask me to go over notes before tests because I break things into steps and make little diagrams. At work, my manager put me in charge of training new cashiers because I'm patient and don't make people feel dumb for asking twice. My English teacher, Mr. Alvarez, wrote on my essay that I notice what people need before they say it. And Pastor Dana says I'm the most organized seventeen-year-old she's ever met, which is probably because of the spreadsheet.",
        20 => "Right now I'm thinking about special education, speech-language pathology, or educational psychology. Special ed draws me because it's the most direct way to work with kids like Eli every day. Speech-language pathology is interesting because it's about language and how kids process it, but it needs a master's, which worries me with money. Educational psychology sounds like the person who actually figures out what's going on. I hesitate because teachers don't get paid much and I've watched my parents stress about money my whole life.",
    ];

    /**
     * The short five-question beta set, by `sort_order`.
     *
     * @var array<int, string>
     */
    public const BETA = [
        1 => "This year it's gotten clearer that I'm supposed to work with kids who struggle to learn, especially with reading. It started with my brother Eli and his dyslexia, but then it kept happening: the homework club at the library, starting Reading Buddies at church, running the summer reading camp after I didn't get into the program I wanted. Every door that closed pointed me back to the same place. I've prayed about it a lot, and I don't hear a voice, but I feel peace when I'm with those kids that I don't feel when I imagine being a nurse.",
        2 => "People come to me to explain things and to make them feel less overwhelmed. Friends ask me to go over notes before tests. My manager at Harvest Market had me train new cashiers because I'm patient. Mr. Alvarez, my English teacher, told me I notice what people need before they say it. Pastor Dana keeps asking me to lead things because I'm organized and follow through. The thing that gets said most is that I don't make people feel dumb for not getting something the first time.",
        3 => "I'm in my element sitting next to a kid with a book, trying a few ways of explaining something until one clicks. I love the problem-solving part, figuring out whether they're stuck on sounding out words, or remembering, or just scared. I also love building the stuff behind it, like my tracking spreadsheet and the flashcards I made. The environment is small and a little noisy, like the library after school. I do worse with big lectures or work that doesn't connect to an actual person.",
        4 => "Kids who get labeled lazy or 'not readers' when they actually learn differently and nobody caught it, especially in families like mine where parents are working hard and don't know the system. My brother went three years before he was tested. At the homework club I see kids every week who are bright and funny but shut down when it's time to read. It makes me angry that help depends on whether your parents can pay for it or know what to ask for.",
        5 => "Reading Buddies needs someone to keep it running, and I just recruited four new volunteers, so I'm responsible for training them. The library asked me back to help with summer reading camp. Eli still needs me for reading at night. I have my job at Harvest Market, and I'm applying to Ohio State and Columbus State, where I want to look at special education and speech-language pathology. Being faithful right now looks like showing up for those kids and doing the small things well.",
    ];

    public static function answerFor(Question $question): ?string
    {
        $answers = $question->is_beta ? self::BETA : self::STANDARD;

        return $answers[$question->sort_order] ?? null;
    }
}
