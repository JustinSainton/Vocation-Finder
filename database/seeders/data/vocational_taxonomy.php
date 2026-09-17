<?php

declare(strict_types=1);

/**
 * The 17 vocational categories, transcribed from Appendix 10.6 of the
 * Vocational Finder Intelligence System Blueprint.
 *
 * This file is GOVERNED CONTENT. The blueprint is the constitution for this
 * product, and the taxonomy is what governs the AI's interpretation. Three rules:
 *
 * 1. `summary_sentence` is the Q10 sentence and is PRE-APPROVED VERBATIM.
 *    It is surfaced to students exactly as written. Never paraphrase,
 *    regenerate, or let a model rewrite it.
 * 2. `distortions` are DIAGNOSTIC, not disqualifying. A narrative matching a
 *    pathway's distortion is still evidence for that pathway — the
 *    interpretation notes the immature expression rather than scoring it down.
 * 3. `adjacent_categories.differentiating_questions` are what the engine asks
 *    itself when two pathways compete. They are not user-facing.
 *
 * Changes here require founder review (blueprint section 10, Founder-Only Decisions).
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug' => 'healing-care',
        'name' => 'Healing & Care',
        'sort_order' => 1,
        'core_function' => 'An orientation toward what is wounded, with the instinct to restore it.',
        'summary_sentence' => 'You are drawn to what is wounded because you carry the instinct to make it whole.',
        'signal_fingerprint' => [
            'desires' => [
                'To relieve pain, to see someone whole again, to be trusted with what is fragile',
            ],
            'burdens' => [
                'Illness, trauma, and neglect feel personally unbearable to witness',
            ],
            'strengths' => [
                'Empathy joined with perception',
                'patience',
                'calm presence',
                'deep listening',
            ],
            'environments' => [
                'Clinical, counseling, and caregiving settings',
                'one-on-one or small-group',
                'structured but person-centered',
            ],
            'literal_phrases' => [
                'I couldn\'t stand seeing them like that,',
                'I wanted to make it better,',
                'I wished I knew how to fix what was wrong with them.',
            ],
        ],
        'distortions' => [
            'The savior complex',
            'codependency',
            'martyrdom burnout',
            'needing others to be broken in order to feel useful',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'nourishing-hospitality',
                'advocating-supporting',
                'pastoral-missionary',
            ],
            'differentiating_questions' => [
                'Is the drive to *comfort and welcome*(Hospitality) or to *diagnose and restore* (Healing)?',
                'To *speak for* someone (Advocating) or to *treat* them (Healing)?',
                'To shepherd their *relationship with God* (Pastoral) or their *psychological and physical wholeness* (Healing)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward what is wounded, with the instinct to restore it.',
                'expanded' => 'The person does not merely notice suffering — they move toward it. The meaning of this pathway is restoration: bringing a body, mind, or heart back to wholeness. In narratives, look for movement *toward* pain rather than sympathy expressed from a distance.',
            ],
            'q2' => [
                'short' => 'Medicine, nursing, counseling, therapy, mental health, rehabilitation, veterinary care, hospice.',
                'expanded' => 'Any work where the core transaction is diagnosis and restoration of a living being. The setting varies from ER to therapy office to animal clinic, but the function is constant: something is hurting, and this person addresses it directly.',
            ],
            'q3' => [
                'short' => 'The attentive one — steady, perceptive, the first to notice when someone is "off."',
                'expanded' => 'People confide in them without being asked. Suffering does not repel them; it summons them. In narratives, they often describe being the person friends came to in crisis, or moments where they could not walk away from someone in pain.',
            ],
            'q4' => [
                'short' => 'To relieve pain, to see someone whole again, to be trusted with what is fragile.',
                'expanded' => 'The desire is specifically restorative — not to comfort (Hospitality) or represent (Advocating) but to *heal*. Signal phrases: "I wanted to make it better," "I couldn\'t stand seeing them like that," "I wished I knew how to fix what was wrong with them."',
            ],
            'q5' => [
                'short' => 'Illness, trauma, and neglect feel personally unbearable to witness.',
                'expanded' => 'Unaddressed pain in a room feels like their responsibility. Narratives often include a formative encounter with suffering — a sick relative, a friend\'s breakdown, an injured animal — that the student describes as a turning point rather than a passing event.',
            ],
            'q6' => [
                'short' => 'Empathy joined with perception; patience; calm presence; deep listening.',
                'expanded' => 'The key composite is empathy *plus* diagnosis — the ability to not only feel with someone but discern what is actually wrong. Distinguish from pure warmth: a healer asks questions and looks beneath symptoms.',
            ],
            'q7' => [
                'short' => 'Clinical, counseling, and caregiving settings; one-on-one or small-group; structured but person-centered.',
                'expanded' => 'They need proximity to individual persons over time. Large-scale, abstract, or transactional environments drain them. Look for narratives where their best moments happened in intimate, sustained contact with someone recovering.',
            ],
            'q8' => [
                'short' => 'The savior complex; codependency; martyrdom burnout; needing others to be broken in order to feel useful.',
                'expanded' => 'The healthy healer serves restoration; the distorted healer serves their own identity as rescuer. Watch for narratives where the student\'s worth is fused to being needed — where they enable rather than heal, give without boundary, or subtly resist others\' recovery because recovery ends the role. Theologically: the gift of mercy bent into an idol of indispensability.',
            ],
            'q9' => [
                'short' => 'Nourishing & Hospitality; Advocating & Supporting; Pastoral & Missionary Work.',
                'expanded' => 'Differentiating questions — Is the drive to *comfort and welcome*(Hospitality) or to *diagnose and restore* (Healing)? To *speak for* someone (Advocating) or to *treat* them (Healing)? To shepherd their *relationship with God* (Pastoral) or their *psychological and physical wholeness* (Healing)?',
            ],
            'q10' => [
                'sentence' => 'You are drawn to what is wounded because you carry the instinct to make it whole.',
            ],
        ],
    ],
    [
        'slug' => 'teaching-formation',
        'name' => 'Teaching & Formation',
        'sort_order' => 2,
        'core_function' => 'An orientation toward growth in others through understanding.',
        'summary_sentence' => 'You come alive at the moment understanding dawns in someone else\'s eyes.',
        'signal_fingerprint' => [
            'desires' => [
                'To see understanding dawn',
                'to shape thinking',
                'to invest in specific people over time',
            ],
            'burdens' => [
                'Confusion, ignorance, shallow thinking, and wasted potential are hard to leave alone',
            ],
            'strengths' => [
                'Simplification, sequencing, questioning, and patience with the learning process',
            ],
            'environments' => [
                'Classrooms, mentoring relationships, coaching contexts — anywhere long-term growth is observable',
            ],
            'literal_phrases' => [
                'I could see them growing.',
                'I love when it clicks for someone,',
                'I wanted to help them understand,',
                'get it.',
            ],
        ],
        'distortions' => [
            'Teaching to be admired',
            'creating dependence',
            'knowledge as pride',
            'instruction as control',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'communication-media',
                'healing-care',
                'pastoral-missionary',
                'leadership-management',
            ],
            'differentiating_questions' => [
                'Named learners over time (Teaching) or audiences at scale (Communication)?',
                'Developing capacity (Teaching) or restoring wholeness (Healing)?',
                'Shaping thinking (Teaching) or shepherding faith (Pastoral)?',
                'Developing people (Teaching) or directing them toward a goal (Leadership)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward growth in others through understanding.',
                'expanded' => 'The person is drawn to the moment clarity dawns in someone else — when confusion breaks and a mind expands. The meaning is formation: not transferring information, but shaping how another person thinks and matures over time.',
            ],
            'q2' => [
                'short' => 'Teaching, professorship, coaching, mentoring, tutoring, curriculum design, training and development.',
                'expanded' => 'Any work whose success is measured in another person\'s growth. The classroom is the archetype but not the boundary — the athletic coach, the corporate trainer, and the mentor all share the function of long-arc development of specific people.',
            ],
            'q3' => [
                'short' => 'The patient explainer — the one others seek out for advice, who delights more in others\' progress than in their own performance.',
                'expanded' => 'In narratives, they describe tutoring friends, being asked to explain things, or the satisfaction of watching someone finally "get it." The tell is where the joy lands: on the learner\'s breakthrough, not the explainer\'s display.',
            ],
            'q4' => [
                'short' => 'To see understanding dawn; to shape thinking; to invest in specific people over time.',
                'expanded' => 'Signal phrases: "I love when it clicks for someone," "I wanted to help them understand," "I could see them growing." The desire is developmental and personal — distinguishable from the desire to reach audiences (Communication) by its focus on named individuals over long arcs.',
            ],
            'q5' => [
                'short' => 'Confusion, ignorance, shallow thinking, and wasted potential are hard to leave alone.',
                'expanded' => 'They are bothered when people are underdeveloped — when a capable person is stuck because no one ever explained, guided, or invested. Narratives often feature frustration with bad teaching they received, paired with the impulse to do it better for someone else.',
            ],
            'q6' => [
                'short' => 'Simplification, sequencing, questioning, and patience with the learning process.',
                'expanded' => 'The core skill is decomposition — breaking a complex idea into an ordered path a novice can walk. Secondary signal: they naturally ask questions that lead others to conclusions rather than announcing conclusions themselves.',
            ],
            'q7' => [
                'short' => 'Classrooms, mentoring relationships, coaching contexts — anywhere long-term growth is observable.',
                'expanded' => 'They need repeated contact with the same learners; one-off interactions frustrate the formation instinct. Look for narratives where the student stayed with someone through a learning process rather than delivering a single explanation.',
            ],
            'q8' => [
                'short' => 'Teaching to be admired; creating dependence; knowledge as pride; instruction as control.',
                'expanded' => 'The healthy teacher works toward their own obsolescence — the student surpasses them and leaves. The distorted teacher builds orbits: learners who must keep returning, an identity fed by being the smartest presence in the room. Watch for narratives where correction of others carries relish, or where the student\'s authority matters more than the learner\'s growth. Theologically: knowledge that puffs up rather than love that builds up.',
            ],
            'q9' => [
                'short' => 'Communication & Media; Healing & Care; Pastoral & Missionary Work; Leadership & Management.',
                'expanded' => 'Differentiating questions — Named learners over time (Teaching) or audiences at scale (Communication)? Developing capacity (Teaching) or restoring wholeness (Healing)? Shaping thinking (Teaching) or shepherding faith (Pastoral)? Developing people (Teaching) or directing them toward a goal (Leadership)?',
            ],
            'q10' => [
                'sentence' => 'You come alive at the moment understanding dawns in someone else\'s eyes.',
            ],
        ],
    ],
    [
        'slug' => 'leadership-management',
        'name' => 'Leadership & Management',
        'sort_order' => 3,
        'core_function' => 'An orientation toward direction — taking responsibility for moving people and resources toward a goal.',
        'summary_sentence' => 'Where others see a directionless room, you feel the weight of what it could become — and you move.',
        'signal_fingerprint' => [
            'desires' => [
                'To take ownership, to bring order to drift, to achieve outcomes through a team',
            ],
            'burdens' => [
                'Directionless teams, indecision, poor coordination, and unaccountable drift',
            ],
            'strengths' => [
                'Decision-making under pressure, delegation, alignment, ownership, accountability',
            ],
            'environments' => [
                'Teams, organizations, and projects — dynamic, responsibility-heavy contexts where results visibly matter',
            ],
            'literal_phrases' => [
                'I get frustrated when nobody decides.',
                'I like being responsible for the result,',
                'Someone had to step up,',
            ],
        ],
        'distortions' => [
            'Control and domination',
            'identity fused to position',
            'people used as instruments',
            'inability to follow',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'administration-systems',
                'commerce-enterprise',
                'teaching-formation',
            ],
            'differentiating_questions' => [
                'Directing people toward a goal (Leadership) or tuning processes behind the scenes (Administration)?',
                'Owning and building a venture (Commerce) or directing an existing organization (Leadership)?',
                'Developing individuals (Teaching) or aligning a collective (Leadership)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward direction — taking responsibility for moving people and resources toward a goal.',
                'expanded' => 'The person feels the weight of a directionless room and instinctively moves to organize it. The meaning is stewardship of collective effort: decisions made, ownership taken, outcomes delivered through others.',
            ],
            'q2' => [
                'short' => 'Team leadership, management, project leadership, executive roles, directing organizations and initiatives.',
                'expanded' => 'Any work where the person\'s primary output is other people\'s coordinated output. The leader\'s product is alignment, decision, and execution — measured in what the team accomplishes, not what the leader personally produces.',
            ],
            'q3' => [
                'short' => 'The one who naturally takes charge — comfortable with responsibility, decisive under pressure, frustrated by drift.',
                'expanded' => 'In narratives, they describe stepping up when no one else would: organizing the group project, captaining the team, becoming the de facto decision-maker. The tell is that others *look to them* — leadership was often conferred before it was sought.',
            ],
            'q4' => [
                'short' => 'To take ownership, to bring order to drift, to achieve outcomes through a team.',
                'expanded' => 'Signal phrases: "Someone had to step up," "I like being responsible for the result," "I get frustrated when nobody decides." Distinguish from the desire to *start* things (Commerce) — the leader\'s desire is to *direct and deliver*, including what others built.',
            ],
            'q5' => [
                'short' => 'Directionless teams, indecision, poor coordination, and unaccountable drift.',
                'expanded' => 'They experience lack of leadership almost physically — a vacuum that pulls them in. Narratives frequently feature frustration with a failing group and the moment they took over, reorganized roles, and drove the thing home.',
            ],
            'q6' => [
                'short' => 'Decision-making under pressure, delegation, alignment, ownership, accountability.',
                'expanded' => 'The composite skill is judgment plus nerve — the ability to choose among imperfect options and absorb the consequences. Secondary signal: they think in terms of who should do what, naturally distributing work rather than hoarding it.',
            ],
            'q7' => [
                'short' => 'Teams, organizations, and projects — dynamic, responsibility-heavy contexts where results visibly matter.',
                'expanded' => 'They wilt in environments where no one owns anything and decisions dissolve into committees. Look for narratives where they thrived precisely when stakes and accountability were highest.',
            ],
            'q8' => [
                'short' => 'Control and domination; identity fused to position; people used as instruments; inability to follow.',
                'expanded' => 'The healthy leader serves the mission and develops the team; the distorted leader serves the position and consumes the team. Watch for narratives where taking charge was about being seen rather than serving the outcome, where the student cannot describe ever following well, or where "leadership" reads as needing others to comply. Theologically: authority sought as throne rather than carried as towel.',
            ],
            'q9' => [
                'short' => 'Administration & Systems; Commerce & Enterprise; Teaching & Formation.',
                'expanded' => 'Differentiating questions — Directing people toward a goal (Leadership) or tuning processes behind the scenes (Administration)? Owning and building a venture (Commerce) or directing an existing organization (Leadership)? Developing individuals (Teaching) or aligning a collective (Leadership)?',
            ],
            'q10' => [
                'sentence' => 'Where others see a directionless room, you feel the weight of what it could become — and you move.',
            ],
        ],
    ],
    [
        'slug' => 'law-policy',
        'name' => 'Law & Policy',
        'sort_order' => 4,
        'core_function' => 'An orientation toward justice as structure — the belief that fairness must be designed, interpreted, and enforced.',
        'summary_sentence' => 'You believe fairness is never an accident — it must be designed, defended, and enforced.',
        'signal_fingerprint' => [
            'desires' => [
                'To influence rules and systems',
                'to argue well',
                'to see accountability and due process hold',
            ],
            'burdens' => [
                'Injustice, arbitrary treatment, unaccountable power, and badly designed or unenforced rules',
            ],
            'strengths' => [
                'Analytical reasoning, logical argument, interpretation of rules and precedents, precision',
            ],
            'environments' => [
                'Courts, legislatures, regulatory bodies, public institutions — structured, rule-based, precise',
            ],
            'literal_phrases' => [
                'I like understanding how the system works.',
                'I want to change the law,',
                'That\'s not fair — and here\'s why,',
            ],
        ],
        'distortions' => [
            'Legalism — loving rules more than people',
            'self-righteousness',
            'argument as ego',
            'procedure weaponized',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'advocating-supporting',
                'protecting-defending',
                'administration-systems',
            ],
            'differentiating_questions' => [
                'Shaping the rules (Law & Policy) or standing with a specific person against them (Advocating)?',
                'Designing frameworks of safety (Law & Policy) or physically responding to threats (Protecting)?',
                'Justice systems (Law & Policy) or operational efficiency (Administration)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward justice as structure — the belief that fairness must be designed, interpreted, and enforced.',
                'expanded' => 'The person is drawn to the frameworks beneath society: rules, rights, precedents, and institutions. The meaning is systemic justice — not helping one person through a hardship (Advocating), but shaping the structures that determine everyone\'s treatment.',
            ],
            'q2' => [
                'short' => 'Law, public policy, government, regulation, compliance, judiciary, legislative work, public administration.',
                'expanded' => 'Any work whose material is rules and whose product is order and equity at institutional scale. The attorney, the policy analyst, and the regulator share a common function: interpreting and applying frameworks of justice.',
            ],
            'q3' => [
                'short' => 'The analytical arguer — precise, structured, animated by fairness and by how systems work.',
                'expanded' => 'In narratives, they enjoy debate for its logic rather than its heat, notice when rules are applied inconsistently, and are bothered by unfairness even when it does not affect them personally. The tell is abstraction: their justice instinct scales beyond individuals to categories and systems.',
            ],
            'q4' => [
                'short' => 'To influence rules and systems; to argue well; to see accountability and due process hold.',
                'expanded' => 'Signal phrases: "That\'s not fair — and here\'s why," "I want to change the law," "I like understanding how the system works." Distinguish from Advocating by the object of desire: the rule itself versus the person under the rule.',
            ],
            'q5' => [
                'short' => 'Injustice, arbitrary treatment, unaccountable power, and badly designed or unenforced rules.',
                'expanded' => 'They are provoked by structural wrong — the policy that punishes the wrong people, the process that protects the powerful. Narratives often include a formative encounter with institutional unfairness they could analyze but not yet change.',
            ],
            'q6' => [
                'short' => 'Analytical reasoning, logical argument, interpretation of rules and precedents, precision.',
                'expanded' => 'The core skill is applying general frameworks to particular cases — and arguing that application persuasively. Secondary signal: comfort with adversarial reasoning; they can argue a position while separating it from personal animosity.',
            ],
            'q7' => [
                'short' => 'Courts, legislatures, regulatory bodies, public institutions — structured, rule-based, precise.',
                'expanded' => 'They function best where authority is procedural and argument is the currency. Chaotic, rule-less environments frustrate them not because they crave control but because nothing there can be made fair.',
            ],
            'q8' => [
                'short' => 'Legalism — loving rules more than people; self-righteousness; argument as ego; procedure weaponized.',
                'expanded' => 'The healthy jurist uses structure to protect persons; the distorted one uses persons to vindicate structure — or self. Watch for narratives where being right eclipses making right, where winning arguments is the pleasure and justice the pretext, or where rule-keeping becomes moral superiority. Theologically: the Pharisee\'s error — tithing the letter while the weightier matters starve.',
            ],
            'q9' => [
                'short' => 'Advocating & Supporting; Protecting & Defending; Administration & Systems.',
                'expanded' => 'Differentiating questions — Shaping the rules (Law & Policy) or standing with a specific person against them (Advocating)? Designing frameworks of safety (Law & Policy) or physically responding to threats (Protecting)? Justice systems (Law & Policy) or operational efficiency (Administration)?',
            ],
            'q10' => [
                'sentence' => 'You believe fairness is never an accident — it must be designed, defended, and enforced.',
            ],
        ],
    ],
    [
        'slug' => 'protecting-defending',
        'name' => 'Protecting & Defending',
        'sort_order' => 5,
        'core_function' => 'An orientation toward safety — standing between harm and the vulnerable.',
        'summary_sentence' => 'You were built to stand between harm and the people who cannot stand alone.',
        'signal_fingerprint' => [
            'desires' => [
                'To keep people safe',
                'to carry responsibility in danger',
                'to be the one who shows up when it matters',
            ],
            'burdens' => [
                'Immediate danger, active injustice, and the vulnerability of people who cannot defend themselves',
            ],
            'strengths' => [
                'Courage, situational awareness, rapid decision-making under pressure, sense of duty',
            ],
            'environments' => [
                'High-stakes, action-oriented contexts — emergency response, security, defense, crisis operations',
            ],
            'literal_phrases' => [
                'I couldn\'t just watch it happen.',
                'I feel responsible for protecting them,',
                'I stay calm when things go wrong,',
            ],
        ],
        'distortions' => [
            'Adrenaline addiction',
            'aggression dressed as protection',
            'the warrior identity that needs an enemy',
            'hypervigilance',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'law-policy',
                'healing-care',
                'leadership-management',
            ],
            'differentiating_questions' => [
                'Responding to threats bodily and immediately (Protecting) or shaping frameworks of justice (Law & Policy)?',
                'Preventing the wound (Protecting) or healing it afterward (Healing)?',
                'Guarding people (Protecting) or directing them toward goals (Leadership)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward safety — standing between harm and the vulnerable.',
                'expanded' => 'The person feels a duty to shield others that activates precisely when danger appears. The meaning is guardianship: not designing justice (Law & Policy) or treating wounds (Healing), but preventing the wound in the first place and responding when threat becomes real.',
            ],
            'q2' => [
                'short' => 'Law enforcement, military service, firefighting, emergency response, security, safety operations.',
                'expanded' => 'Any work where the core function is vigilance and intervention — where the professional\'s presence is what stands between a community and harm. The unifying element is risk absorbed on behalf of others.',
            ],
            'q3' => [
                'short' => 'The calm one in crisis — courageous, vigilant, steady when everyone else is panicking.',
                'expanded' => 'In narratives, they describe running toward the emergency, physically inserting themselves between a bully and a target, or being the person others instinctively stood behind. The tell is inverted arousal: high-stakes situations clarify them rather than scatter them.',
            ],
            'q4' => [
                'short' => 'To keep people safe; to carry responsibility in danger; to be the one who shows up when it matters.',
                'expanded' => 'Signal phrases: "I feel responsible for protecting them," "I stay calm when things go wrong," "I couldn\'t just watch it happen." The desire is physical and immediate — distinguishable from Law & Policy\'s abstract justice by its bodily readiness to act.',
            ],
            'q5' => [
                'short' => 'Immediate danger, active injustice, and the vulnerability of people who cannot defend themselves.',
                'expanded' => 'They are provoked by threat in real time — the fight breaking out, the person being targeted, the emergency unfolding. Narratives often feature a moment of intervention the student describes with clarity and no regret, even when it cost them.',
            ],
            'q6' => [
                'short' => 'Courage, situational awareness, rapid decision-making under pressure, sense of duty.',
                'expanded' => 'The composite skill is vigilance plus poise — scanning for threat without paranoia, then acting decisively when it materializes. Secondary signal: physical confidence and a tolerance for personal risk that does not read as recklessness.',
            ],
            'q7' => [
                'short' => 'High-stakes, action-oriented contexts — emergency response, security, defense, crisis operations.',
                'expanded' => 'They atrophy in environments where nothing is ever at stake. Look for narratives where routine bored them but crisis brought out their best self — the inverse of most students\' pattern.',
            ],
            'q8' => [
                'short' => 'Adrenaline addiction; aggression dressed as protection; the warrior identity that needs an enemy; hypervigilance.',
                'expanded' => 'The healthy protector serves peace and stands down when threat passes; the distorted one needs the threat — seeking conflict to feel purposeful, escalating where de-escalation was possible, or using "protection" to justify domination. Watch for narratives where the fight itself is the pleasure, or where authority over others is the underlying draw. Theologically: the sword carried as identity rather than borne as burden.',
            ],
            'q9' => [
                'short' => 'Law & Policy; Healing & Care; Leadership & Management.',
                'expanded' => 'Differentiating questions — Responding to threats bodily and immediately (Protecting) or shaping frameworks of justice (Law & Policy)? Preventing the wound (Protecting) or healing it afterward (Healing)? Guarding people (Protecting) or directing them toward goals (Leadership)?',
            ],
            'q10' => [
                'sentence' => 'You were built to stand between harm and the people who cannot stand alone.',
            ],
        ],
    ],
    [
        'slug' => 'creating-building',
        'name' => 'Creating & Building',
        'sort_order' => 6,
        'core_function' => 'An orientation toward bringing new things into existence — vision converted into concrete reality.',
        'summary_sentence' => 'You look at empty space and see what should exist there — and you cannot rest until it does.',
        'signal_fingerprint' => [
            'desires' => [
                'To turn ideas into real things',
                'to solve problems by making',
                'to see and touch the result',
            ],
            'burdens' => [
                'The gap where something should exist',
                'badly built things',
                'ideas dying unexecuted',
            ],
            'strengths' => [
                'Vision joined with execution',
                'resourcefulness',
                'iteration',
                'construction and problem-solving through making',
            ],
            'environments' => [
                'Project-based, hands-on, builder-oriented contexts — workshops, studios, startups, engineering teams',
            ],
            'literal_phrases' => [
                'I couldn\'t stop until it worked.',
                'I like making things from scratch,',
                'I wanted to build it myself,',
            ],
        ],
        'distortions' => [
            'Chronic starting without finishing',
            'worth fused to output',
            'building for validation',
            'the idol of novelty',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'arts-beauty',
                'discovering-innovating',
                'commerce-enterprise',
                'maintaining-repairing',
            ],
            'differentiating_questions' => [
                'Building for function (Creating) or for expression and meaning (Arts)?',
                'Generating the insight (Discovering) or shipping the artifact (Creating)?',
                'Building the product (Creating) or scaling the venture around it (Commerce)?',
                'Making the new (Creating) or sustaining the existing (Maintaining)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward bringing new things into existence — vision converted into concrete reality.',
                'expanded' => 'The person looks at empty space and sees what should exist there. The meaning is materialization: ideas are not enough (Discovering) and beauty is not the point (Arts) — the drive is to make a real, functioning thing where nothing was before.',
            ],
            'q2' => [
                'short' => 'Engineering, software development, architecture, construction, product design, making and building of every kind.',
                'expanded' => 'Any work whose output is a new tangible or digital thing that works. The carpenter, the coder, and the product builder share one function: they start from zero and ship something real.',
            ],
            'q3' => [
                'short' => 'The maker — resourceful, hands-on, happiest mid-project with visible progress.',
                'expanded' => 'In narratives, they describe building things unprompted: the app, the treehouse, the modded game, the rebuilt engine. The tell is follow-through — they don\'t just imagine projects, they finish versions of them, however rough.',
            ],
            'q4' => [
                'short' => 'To turn ideas into real things; to solve problems by making; to see and touch the result.',
                'expanded' => 'Signal phrases: "I wanted to build it myself," "I like making things from scratch," "I couldn\'t stop until it worked." Distinguish from Discovering by the endpoint: the discoverer is satisfied by the insight; the builder is only satisfied by the working artifact.',
            ],
            'q5' => [
                'short' => 'The gap where something should exist; badly built things; ideas dying unexecuted.',
                'expanded' => 'They are irritated by vaporware — plans that never materialize, problems everyone discusses and no one solves. Narratives often include frustration with a tool or system that didn\'t exist or didn\'t work, followed by the student attempting to build it themselves.',
            ],
            'q6' => [
                'short' => 'Vision joined with execution; resourcefulness; iteration; construction and problem-solving through making.',
                'expanded' => 'The core composite is imagination plus stamina — seeing the finished thing and grinding through the unglamorous middle to reach it. Secondary signal: comfort with rough first versions; they prototype rather than perfect on paper.',
            ],
            'q7' => [
                'short' => 'Project-based, hands-on, builder-oriented contexts — workshops, studios, startups, engineering teams.',
                'expanded' => 'They need visible output. Environments where work dissolves into meetings and abstractions starve them. Look for narratives where their proudest moments end with a thing that exists.',
            ],
            'q8' => [
                'short' => 'Chronic starting without finishing; worth fused to output; building for validation; the idol of novelty.',
                'expanded' => 'The healthy builder serves the need the thing meets; the distorted builder serves the identity of being a maker — a trail of abandoned projects chasing the high of the new start, or relentless shipping because self-worth is measured in output. Watch for narratives where rest feels like failure or where the student cannot name *why* anything they built mattered. Theologically: creation without sabbath — the maker who forgot the making was for someone.',
            ],
            'q9' => [
                'short' => 'Arts & Beauty; Discovering & Innovating; Commerce & Enterprise; Maintaining & Repairing.',
                'expanded' => 'Differentiating questions — Building for function (Creating) or for expression and meaning (Arts)? Generating the insight (Discovering) or shipping the artifact (Creating)? Building the product (Creating) or scaling the venture around it (Commerce)? Making the new (Creating) or sustaining the existing (Maintaining)?',
            ],
            'q10' => [
                'sentence' => 'You look at empty space and see what should exist there — and you cannot rest until it does.',
            ],
        ],
    ],
    [
        'slug' => 'maintaining-repairing',
        'name' => 'Maintaining & Repairing',
        'sort_order' => 7,
        'core_function' => 'An orientation toward preservation — keeping the built world working.',
        'summary_sentence' => 'You are the quiet reason things keep working in a world that constantly breaks.',
        'signal_fingerprint' => [
            'desires' => [
                'To fix what is broken',
                'to work with their hands',
                'to see immediate, tangible resolution',
            ],
            'burdens' => [
                'Things breaking down, running badly, or being neglected when they could be restored',
            ],
            'strengths' => [
                'Diagnostic troubleshooting, technical aptitude, attention to detail, reliability',
            ],
            'environments' => [
                'Operational, technical, mechanical settings with clear tasks and tangible outcomes',
            ],
            'literal_phrases' => [
                'I enjoy working with my hands,',
                'I fixed it myself.',
                'I like figuring out why it stopped working,',
            ],
        ],
        'distortions' => [
            'Fixing things to avoid people',
            'preserving what should be retired',
            'invisibility hardened into resentment',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'creating-building',
                'administration-systems',
                'discovering-innovating',
            ],
            'differentiating_questions' => [
                'Restoring the existing (Maintaining) or making the new (Creating)?',
                'Fixing physical and technical systems (Maintaining) or designing organizational processes (Administration)?',
                'Making it work again (Maintaining) or understanding why it works at all (Discovering)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward preservation — keeping the built world working.',
                'expanded' => 'The person notices what is broken, worn, or drifting out of tune, and feels the pull to restore function. The meaning is stewardship of the existing: a quiet conviction that keeping things working is as valuable as making them, though the world rarely says so.',
            ],
            'q2' => [
                'short' => 'Skilled trades, mechanics, IT support and systems maintenance, facilities, equipment repair, infrastructure upkeep.',
                'expanded' => 'Any work whose function is diagnosing malfunction and restoring operation. The electrician, the mechanic, the sysadmin, and the technician share the same core act: something stopped working, and they make it work again.',
            ],
            'q3' => [
                'short' => 'The practical fixer — detail-attentive, reliable, drawn to hands-on problems with definite answers.',
                'expanded' => 'In narratives, they describe taking things apart to understand them, being the family member who fixes everything, or the deep satisfaction of a repair completed. The tell is preference for the concrete: they trust problems they can touch over problems they can only discuss.',
            ],
            'q4' => [
                'short' => 'To fix what is broken; to work with their hands; to see immediate, tangible resolution.',
                'expanded' => 'Signal phrases: "I like figuring out why it stopped working," "I enjoy working with my hands," "I fixed it myself." Distinguish from Creating by the starting point: the builder starts from nothing; the repairer starts from something broken — and finds the second more satisfying.',
            ],
            'q5' => [
                'short' => 'Things breaking down, running badly, or being neglected when they could be restored.',
                'expanded' => 'They are bothered by the squeaky door everyone ignores, the machine limping along at half capacity, the waste of throwing away what could be fixed. Narratives often feature an early history of repair — bikes, electronics, engines — pursued for its own satisfaction.',
            ],
            'q6' => [
                'short' => 'Diagnostic troubleshooting, technical aptitude, attention to detail, reliability.',
                'expanded' => 'The core skill is causal tracing — following a malfunction back through a system to its source. Secondary signal: consistency; these students describe showing up and following through in ways others around them did not.',
            ],
            'q7' => [
                'short' => 'Operational, technical, mechanical settings with clear tasks and tangible outcomes.',
                'expanded' => 'They thrive where the work is definite: broken in the morning, fixed by evening. Ambiguous, open-ended, abstraction-heavy environments frustrate them — not from lack of intelligence but from a different relationship to problems.',
            ],
            'q8' => [
                'short' => 'Fixing things to avoid people; preserving what should be retired; invisibility hardened into resentment.',
                'expanded' => 'The healthy maintainer stewards what serves people; the distorted one hides in the workshop — competence with objects becoming an escape from the harder maintenance of relationships. A second distortion: resistance to necessary change, defending legacy systems because their mastery lives there. Watch for narratives where being unappreciated has curdled into bitterness. Theologically: faithfulness in small things, forgotten that the small things were always for people.',
            ],
            'q9' => [
                'short' => 'Creating & Building; Administration & Systems; Discovering & Innovating.',
                'expanded' => 'Differentiating questions — Restoring the existing (Maintaining) or making the new (Creating)? Fixing physical and technical systems (Maintaining) or designing organizational processes (Administration)? Making it work again (Maintaining) or understanding why it works at all (Discovering)?',
            ],
            'q10' => [
                'sentence' => 'You are the quiet reason things keep working in a world that constantly breaks.',
            ],
        ],
    ],
    [
        'slug' => 'arts-beauty',
        'name' => 'Arts & Beauty',
        'sort_order' => 8,
        'core_function' => 'An orientation toward beauty and meaning — the compulsion to give inner experience an outer form.',
        'summary_sentence' => 'You cannot help but translate what you feel into something others can finally see.',
        'signal_fingerprint' => [
            'desires' => [
                'To create something beautiful',
                'to express what words alone cannot carry',
                'to move people',
            ],
            'burdens' => [
                'Ugliness, lifelessness, emotional numbness, and truths that go unexpressed',
            ],
            'strengths' => [
                'Imagination, aesthetic judgment, emotional sensitivity, the craft of a chosen medium',
            ],
            'environments' => [
                'Creative, flexible, expressive contexts — studios, stages, workshops of the imagination',
            ],
            'literal_phrases' => [
                'I have to get it out somehow,',
                'I see it differently than everyone else.',
                'I want to make something that makes people feel,',
            ],
        ],
        'distortions' => [
            'Art as ego',
            'worth measured in applause',
            'the tortured-artist identity',
            'authenticity used to excuse absent craft',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'creating-building',
                'communication-media',
                'discovering-innovating',
            ],
            'differentiating_questions' => [
                'Made to be experienced (Arts) or made to function (Creating)?',
                'Resonance and meaning (Arts) or clarity and reach (Communication)?',
                'Expression of the felt (Arts) or exploration of the unknown (Discovering)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward beauty and meaning — the compulsion to give inner experience an outer form.',
                'expanded' => 'The person perceives the world differently and cannot rest until that perception is expressed — in image, sound, movement, or design. The meaning is translation: making the invisible visible, so others can feel what would otherwise stay locked inside one person.',
            ],
            'q2' => [
                'short' => 'Visual art, music, design, writing as craft, performance, film, photography, architecture as aesthetic.',
                'expanded' => 'Any work whose primary value is aesthetic and expressive rather than functional. The test is what the work is *for*: if its purpose is fulfilled by being experienced — moving, arresting, revealing — it belongs here.',
            ],
            'q3' => [
                'short' => 'The perceiver — emotionally sensitive, imaginative, unable to not notice beauty and its absence.',
                'expanded' => 'In narratives, they describe seeing differently: the detail no one else caught, the song that rearranged them, the compulsion to draw or write or compose without being asked. The tell is necessity — expression reads as need, not hobby.',
            ],
            'q4' => [
                'short' => 'To create something beautiful; to express what words alone cannot carry; to move people.',
                'expanded' => 'Signal phrases: "I have to get it out somehow," "I want to make something that makes people feel," "I see it differently than everyone else." Distinguish from Communication by the currency: the communicator trades in clarity and reach; the artist trades in resonance.',
            ],
            'q5' => [
                'short' => 'Ugliness, lifelessness, emotional numbness, and truths that go unexpressed.',
                'expanded' => 'They are oppressed by sterile environments and shallow culture in a way others find dramatic. Narratives often include an encounter with a work of art described as formative — the moment they realized beauty could do something to a person, and wanted to do that for others.',
            ],
            'q6' => [
                'short' => 'Imagination, aesthetic judgment, emotional sensitivity, the craft of a chosen medium.',
                'expanded' => 'The composite skill is perception plus translation — feeling deeply *and* possessing (or hungering to develop) the technical craft to give the feeling form. Weight narratives that mention sustained practice of a medium over those that mention only appreciation.',
            ],
            'q7' => [
                'short' => 'Creative, flexible, expressive contexts — studios, stages, workshops of the imagination.',
                'expanded' => 'They need latitude. Rigid, metric-driven environments suffocate the work; but total structurelessness can too. The best fit is protected creative space with real deadlines — freedom inside a frame.',
            ],
            'q8' => [
                'short' => 'Art as ego; worth measured in applause; the tortured-artist identity; authenticity used to excuse absent craft.',
                'expanded' => 'The healthy artist serves the work and the audience it will reach; the distorted artist serves the image of being an artist. Watch for narratives where suffering is romanticized as credential, where criticism is received as annihilation, or where discipline is rejected as inauthentic. Theologically: the gift turned mirror — beauty made to reflect the maker instead of pointing beyond them.',
            ],
            'q9' => [
                'short' => 'Creating & Building; Communication & Media; Discovering & Innovating.',
                'expanded' => 'Differentiating questions — Made to be experienced (Arts) or made to function (Creating)? Resonance and meaning (Arts) or clarity and reach (Communication)? Expression of the felt (Arts) or exploration of the unknown (Discovering)?',
            ],
            'q10' => [
                'sentence' => 'You cannot help but translate what you feel into something others can finally see.',
            ],
        ],
    ],
    [
        'slug' => 'discovering-innovating',
        'name' => 'Discovering & Innovating',
        'sort_order' => 9,
        'core_function' => 'An orientation toward the unknown — the pull of the unanswered question.',
        'summary_sentence' => 'The unanswered question pulls you the way gravity pulls water.',
        'signal_fingerprint' => [
            'desires' => [
                'To understand deeply',
                'to explore what no one has explained',
                'to generate genuinely new ideas',
            ],
            'burdens' => [
                'Unsolved problems, unexplained phenomena, and questions everyone else has stopped asking',
            ],
            'strengths' => [
                'Probing questions, pattern recognition, abstraction, experimentation, comfort with uncertainty',
            ],
            'environments' => [
                'Research and exploratory contexts — labs, R\\&D teams, academic settings, idea-driven cultures',
            ],
            'literal_phrases' => [
                'I stayed up all night reading about it,',
                'I\'m always asking why,',
                'What if it worked differently?',
                'why',
            ],
        ],
        'distortions' => [
            'Perpetual exploration without contribution',
            'curiosity as escape from commitment',
            'intellectual pride',
            'idea hoarding',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'creating-building',
                'knowledge-information',
                'teaching-formation',
            ],
            'differentiating_questions' => [
                'Generating the insight (Discovering) or shipping the artifact (Creating)?',
                'Producing new knowledge (Discovering) or organizing existing knowledge (Knowledge & Information)?',
                'Understanding for its own sake (Discovering) or understanding transferred to develop learners (Teaching)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward the unknown — the pull of the unanswered question.',
                'expanded' => 'The person is constitutionally curious: driven to understand how things work, why they work, and what could exist that doesn\'t yet. The meaning is frontier — they live at the edge of what is known and feel the edge as invitation.',
            ],
            'q2' => [
                'short' => 'Research, science, R\\&D, invention, data science, academic inquiry, exploratory and experimental work.',
                'expanded' => 'Any work whose product is new understanding or new possibility. The scientist, the inventor, and the researcher share one function: converting the unknown into the known — insight first, application sometimes.',
            ],
            'q3' => [
                'short' => 'The questioner — endlessly curious, pattern-seeking, energized by complexity others avoid.',
                'expanded' => 'In narratives, they describe rabbit holes: the topic they couldn\'t stop researching, the experiment they ran on their own, the "why" they kept asking past everyone\'s patience. The tell is that understanding itself is the reward — they light up at the insight, not its uses.',
            ],
            'q4' => [
                'short' => 'To understand deeply; to explore what no one has explained; to generate genuinely new ideas.',
                'expanded' => 'Signal phrases: "I\'m always asking why," "I stayed up all night reading about it," "What if it worked differently?" Distinguish from Knowledge & Information by direction: the discoverer pushes past the edge of organized knowledge; the archivist organizes what\'s inside it.',
            ],
            'q5' => [
                'short' => 'Unsolved problems, unexplained phenomena, and questions everyone else has stopped asking.',
                'expanded' => 'They are haunted by open questions the way others are haunted by unfinished tasks. Narratives often feature dissatisfaction with received answers — the teacher\'s explanation that didn\'t hold, the accepted method that made no sense — and independent digging that followed.',
            ],
            'q6' => [
                'short' => 'Probing questions, pattern recognition, abstraction, experimentation, comfort with uncertainty.',
                'expanded' => 'The composite skill is disciplined curiosity — not just wondering but structuring the wondering: forming hypotheses, testing, iterating. Weight narratives showing method over narratives showing only enthusiasm.',
            ],
            'q7' => [
                'short' => 'Research and exploratory contexts — labs, R\\&D teams, academic settings, idea-driven cultures.',
                'expanded' => 'They need freedom to pursue questions that may not pay off, and colleagues who treat ideas as serious objects. Environments demanding immediate practical output on every effort suffocate the exploratory instinct.',
            ],
            'q8' => [
                'short' => 'Perpetual exploration without contribution; curiosity as escape from commitment; intellectual pride; idea hoarding.',
                'expanded' => 'The healthy discoverer explores in service of shared understanding; the distorted one explores to avoid landing — endless research as a sophisticated form of hiding, or intelligence wielded as superiority. Watch for narratives with many fascinations and no completions, or where knowing more than others is the visible pleasure. Theologically: the talent studied endlessly and never invested.',
            ],
            'q9' => [
                'short' => 'Creating & Building; Knowledge & Information; Teaching & Formation.',
                'expanded' => 'Differentiating questions — Generating the insight (Discovering) or shipping the artifact (Creating)? Producing new knowledge (Discovering) or organizing existing knowledge (Knowledge & Information)? Understanding for its own sake (Discovering) or understanding transferred to develop learners (Teaching)?',
            ],
            'q10' => [
                'sentence' => 'The unanswered question pulls you the way gravity pulls water.',
            ],
        ],
    ],
    [
        'slug' => 'nourishing-hospitality',
        'name' => 'Nourishing & Hospitality',
        'sort_order' => 10,
        'core_function' => 'An orientation toward belonging — creating environments where people feel welcomed, provided for, and seen.',
        'summary_sentence' => 'You make rooms where people remember they belong.',
        'signal_fingerprint' => [
            'desires' => [
                'To make people feel welcomed and valued',
                'to provide',
                'to create spaces of belonging',
            ],
            'burdens' => [
                'People feeling unseen, unwelcome, or uncared for',
                'cold spaces',
                'needs going unnoticed',
            ],
            'strengths' => [
                'Warmth, attentiveness, generosity, anticipation of needs, environment-making',
            ],
            'environments' => [
                'Homes, gatherings, community spaces, service settings — relational, people-dense contexts',
            ],
            'literal_phrases' => [
                'I love taking care of people,',
                'I notice when someone\'s left out.',
                'I want everyone to feel included,',
            ],
        ],
        'distortions' => [
            'People-pleasing',
            'serving to be needed',
            'inability to receive',
            'the self hidden behind the hosting',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'healing-care',
                'advocating-supporting',
                'leadership-management',
            ],
            'differentiating_questions' => [
                'Creating belonging (Hospitality) or restoring wholeness (Healing)?',
                'Welcoming people in (Hospitality) or representing them against obstacles (Advocating)?',
                'Making the gathering good (Hospitality) or directing the team toward goals (Leadership)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward belonging — creating environments where people feel welcomed, provided for, and seen.',
                'expanded' => 'The person instinctively builds warmth around others: the meal, the gathering, the space where guards come down. The meaning is provision and welcome — meeting the human need not to be healed or taught, but simply to belong somewhere.',
            ],
            'q2' => [
                'short' => 'Hospitality, culinary work, event creation, community building, service industries, guest experience.',
                'expanded' => 'Any work whose product is a human environment — where the value delivered is comfort, welcome, and care made tangible. The chef, the host, the community director, and the caregiver-of-spaces share the same function.',
            ],
            'q3' => [
                'short' => 'The welcomer — warm, attentive, generous, the one whose presence makes rooms feel safer.',
                'expanded' => 'In narratives, they describe hosting instinctively: cooking for friends, organizing the gathering, noticing the person standing alone and folding them in. The tell is anticipatory attention — they see needs before they\'re voiced.',
            ],
            'q4' => [
                'short' => 'To make people feel welcomed and valued; to provide; to create spaces of belonging.',
                'expanded' => 'Signal phrases: "I love taking care of people," "I want everyone to feel included," "I notice when someone\'s left out." Distinguish from Healing by the object: hospitality serves the well and the wounded alike — its gift is belonging, not restoration.',
            ],
            'q5' => [
                'short' => 'People feeling unseen, unwelcome, or uncared for; cold spaces; needs going unnoticed.',
                'expanded' => 'They are pained by exclusion others don\'t register — the new kid eating alone, the guest no one greeted. Narratives often feature childhood roles as the family\'s includer or the friend group\'s gatherer.',
            ],
            'q6' => [
                'short' => 'Warmth, attentiveness, generosity, anticipation of needs, environment-making.',
                'expanded' => 'The core skill is reading a room as a set of unmet needs and quietly meeting them. Secondary signal: practical service — food, space, logistics of care — done without being asked and without needing credit.',
            ],
            'q7' => [
                'short' => 'Homes, gatherings, community spaces, service settings — relational, people-dense contexts.',
                'expanded' => 'They thrive where people come together and someone must make the together good. Isolated or purely transactional environments starve the gift; look for narratives where their happiest memories involve a full table.',
            ],
            'q8' => [
                'short' => 'People-pleasing; serving to be needed; inability to receive; the self hidden behind the hosting.',
                'expanded' => 'The healthy host serves the guest\'s belonging; the distorted host serves their own — earning a place through provision because they don\'t believe they\'d be welcome without it. Watch for narratives of exhaustion without boundaries, resentment when service goes unnoticed, or discomfort ever being the one cared for. Theologically: Martha\'s distraction — service so anxious it forgets the point of the table.',
            ],
            'q9' => [
                'short' => 'Healing & Care; Advocating & Supporting; Leadership & Management.',
                'expanded' => 'Differentiating questions — Creating belonging (Hospitality) or restoring wholeness (Healing)? Welcoming people in (Hospitality) or representing them against obstacles (Advocating)? Making the gathering good (Hospitality) or directing the team toward goals (Leadership)?',
            ],
            'q10' => [
                'sentence' => 'You make rooms where people remember they belong.',
            ],
        ],
    ],
    [
        'slug' => 'commerce-enterprise',
        'name' => 'Commerce & Enterprise',
        'sort_order' => 11,
        'core_function' => 'An orientation toward opportunity — seeing unmet needs and building ventures that meet them at scale.',
        'summary_sentence' => 'Where others see risk, you see an opportunity with your name on it.',
        'signal_fingerprint' => [
            'desires' => [
                'To start something of their own',
                'to create and capture value',
                'to grow what they build',
            ],
            'burdens' => [
                'Untapped opportunities, badly served markets, and value left on the table',
            ],
            'strengths' => [
                'Opportunity recognition, persuasion and selling, negotiation, initiative, risk tolerance',
            ],
            'environments' => [
                'Dynamic, autonomous, upside-driven contexts — startups, sales floors, ventures of their own',
            ],
            'literal_phrases' => [
                'I like the idea of building a business.',
                'I see opportunities everywhere,',
                'I want to start something,',
            ],
        ],
        'distortions' => [
            'Greed dressed as ambition',
            'extraction instead of creation',
            'opportunity addiction',
            'the win fused to identity',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'leadership-management',
                'creating-building',
                'finance-economics',
            ],
            'differentiating_questions' => [
                'Starting and owning the venture (Commerce) or directing an existing organization (Leadership)?',
                'Loving the product (Creating) or the enterprise around it (Commerce)?',
                'Building ventures (Commerce) or stewarding capital and financial systems (Finance)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward opportunity — seeing unmet needs and building ventures that meet them at scale.',
                'expanded' => 'The person perceives the world as a field of gaps between what people need and what exists, and feels compelled to close those gaps profitably. The meaning is value creation through exchange: enterprise as service that sustains itself.',
            ],
            'q2' => [
                'short' => 'Entrepreneurship, sales, business development, venture building, franchising, commercial strategy.',
                'expanded' => 'Any work whose engine is opportunity recognition and whose measure is value exchanged in a market. The founder, the salesperson, and the dealmaker share one function: matching need to solution and capturing the value of the match.',
            ],
            'q3' => [
                'short' => 'The opportunist in the noble sense — initiative-taking, risk-tolerant, energized by ownership and upside.',
                'expanded' => 'In narratives, they describe early ventures: the sneaker resale, the lawn-care operation, the thing they sold at school. The tell is unprompted initiative with real stakes — they risked their own time or money before anyone asked them to.',
            ],
            'q4' => [
                'short' => 'To start something of their own; to create and capture value; to grow what they build.',
                'expanded' => 'Signal phrases: "I want to start something," "I see opportunities everywhere," "I like the idea of building a business." Distinguish from Creating & Building by the object of love: the builder loves the product; the entrepreneur loves the venture — market, model, and growth included.',
            ],
            'q5' => [
                'short' => 'Untapped opportunities, badly served markets, and value left on the table.',
                'expanded' => 'They are agitated by inefficiency framed commercially: the bad service everyone tolerates, the obvious need no one is meeting. Narratives often include frustration at watching an opportunity go unclaimed — and sometimes claiming it themselves.',
            ],
            'q6' => [
                'short' => 'Opportunity recognition, persuasion and selling, negotiation, initiative, risk tolerance.',
                'expanded' => 'The composite skill is judgment about value — sensing what people will pay for before evidence is complete — plus the nerve to act on it. Secondary signal: comfort with rejection; they describe selling and hearing "no" without it landing as identity verdict.',
            ],
            'q7' => [
                'short' => 'Dynamic, autonomous, upside-driven contexts — startups, sales floors, ventures of their own.',
                'expanded' => 'They wilt under fixed ceilings and rigid process. Look for narratives where autonomy plus accountability produced their best performance, and where security mattered less to them than possibility.',
            ],
            'q8' => [
                'short' => 'Greed dressed as ambition; extraction instead of creation; opportunity addiction; the win fused to identity.',
                'expanded' => 'The healthy entrepreneur creates value for others and captures a fair share; the distorted one captures value without creating it — hype over substance, the customer as mark rather than neighbor. A second distortion: perpetual pivoting, chasing each new opportunity to escape the discipline of the current one. Watch for narratives where money is the scoreboard of personal worth. Theologically: gain pursued as god rather than stewarded as means — profit unmoored from service.',
            ],
            'q9' => [
                'short' => 'Leadership & Management; Creating & Building; Finance & Economics.',
                'expanded' => 'Differentiating questions — Starting and owning the venture (Commerce) or directing an existing organization (Leadership)? Loving the product (Creating) or the enterprise around it (Commerce)? Building ventures (Commerce) or stewarding capital and financial systems (Finance)?',
            ],
            'q10' => [
                'sentence' => 'Where others see risk, you see an opportunity with your name on it.',
            ],
        ],
    ],
    [
        'slug' => 'finance-economics',
        'name' => 'Finance & Economics',
        'sort_order' => 12,
        'core_function' => 'An orientation toward stewardship of resources — managing, allocating, and growing capital wisely.',
        'summary_sentence' => 'You know that resources are never just numbers — they are futures waiting to be stewarded.',
        'signal_fingerprint' => [
            'desires' => [
                'To optimize resources',
                'to build long-term security and growth',
                'to bring rigor to financial decisions',
            ],
            'burdens' => [
                'Waste, poor allocation, financial shortsightedness, and preventable financial ruin',
            ],
            'strengths' => [
                'Quantitative reasoning, risk assessment, strategic planning, precision, discipline',
            ],
            'environments' => [
                'Structured, data-driven contexts — financial institutions, investment settings, corporate finance, planning roles',
            ],
            'literal_phrases' => [
                'I like thinking about how money grows,',
                'I made a budget for it.',
                'I\'m good with numbers,',
            ],
        ],
        'distortions' => [
            'Money as security idol',
            'hoarding',
            'risk aversion masquerading as prudence',
            'people reduced to numbers',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'commerce-enterprise',
                'administration-systems',
                'knowledge-information',
            ],
            'differentiating_questions' => [
                'Stewarding capital (Finance) or building ventures (Commerce)?',
                'Managing financial systems (Finance) or organizational operations (Administration)?',
                'Analysis serving allocation decisions (Finance) or research serving knowledge itself (Knowledge & Information)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward stewardship of resources — managing, allocating, and growing capital wisely.',
                'expanded' => 'The person thinks naturally in terms of allocation, risk, and return: where resources should go, what they will yield, what could go wrong. The meaning is stewardship at the level of systems — the conviction that money well managed is futures well served.',
            ],
            'q2' => [
                'short' => 'Finance, investing, accounting, banking, financial planning, economics, corporate finance, actuarial work.',
                'expanded' => 'Any work whose material is capital and whose product is its wise deployment. The analyst, the accountant, the planner, and the economist share a common function: making resources do the most good over time.',
            ],
            'q3' => [
                'short' => 'The quantitative strategist — analytical, precise, naturally long-term in their thinking about resources.',
                'expanded' => 'In narratives, they describe an early relationship with money as a system: budgeting their own earnings, tracking investments, running the numbers on decisions friends made by feel. The tell is time horizon — they instinctively think in years where peers think in weeks.',
            ],
            'q4' => [
                'short' => 'To optimize resources; to build long-term security and growth; to bring rigor to financial decisions.',
                'expanded' => 'Signal phrases: "I\'m good with numbers," "I like thinking about how money grows," "I made a budget for it." Distinguish from Commerce by temperament: the entrepreneur is drawn to the venture\'s upside; the financier is drawn to the system\'s soundness.',
            ],
            'q5' => [
                'short' => 'Waste, poor allocation, financial shortsightedness, and preventable financial ruin.',
                'expanded' => 'They are bothered by resources squandered — the family that never planned, the organization bleeding money invisibly. Narratives sometimes include a formative encounter with financial hardship that they responded to analytically rather than only emotionally.',
            ],
            'q6' => [
                'short' => 'Quantitative reasoning, risk assessment, strategic planning, precision, discipline.',
                'expanded' => 'The composite skill is modeling the future — translating uncertainty into structured decisions. Secondary signal: personal financial discipline appearing early and unprompted, treated as obvious rather than impressive.',
            ],
            'q7' => [
                'short' => 'Structured, data-driven contexts — financial institutions, investment settings, corporate finance, planning roles.',
                'expanded' => 'They function best where precision is honored and time horizons are long. Chaotic, improvisational environments frustrate them — not from timidity but because good stewardship requires structure.',
            ],
            'q8' => [
                'short' => 'Money as security idol; hoarding; risk aversion masquerading as prudence; people reduced to numbers.',
                'expanded' => 'The healthy steward manages resources for human flourishing; the distorted one manages them for the feeling of control — accumulating as anesthetic against fear, or optimizing spreadsheets while forgetting the people inside the cells. Watch for narratives where scarcity anxiety, not service, drives the fascination with money. Theologically: treasure stored where the heart then lives — mammon served rather than managed.',
            ],
            'q9' => [
                'short' => 'Commerce & Enterprise; Administration & Systems; Knowledge & Information.',
                'expanded' => 'Differentiating questions — Stewarding capital (Finance) or building ventures (Commerce)? Managing financial systems (Finance) or organizational operations (Administration)? Analysis serving allocation decisions (Finance) or research serving knowledge itself (Knowledge & Information)?',
            ],
            'q10' => [
                'sentence' => 'You know that resources are never just numbers — they are futures waiting to be stewarded.',
            ],
        ],
    ],
    [
        'slug' => 'communication-media',
        'name' => 'Communication & Media',
        'sort_order' => 13,
        'core_function' => 'An orientation toward the message — carrying ideas across the distance between minds, at scale.',
        'summary_sentence' => 'You carry ideas across the distance between minds — and you make them land.',
        'signal_fingerprint' => [
            'desires' => [
                'To be heard',
                'to make important ideas land',
                'to reach and move many people',
            ],
            'burdens' => [
                'Important truths going unheard',
                'confusion at scale',
                'bad messaging burying good ideas',
            ],
            'strengths' => [
                'Writing, speaking, storytelling, framing, audience empathy',
            ],
            'environments' => [
                'Media, marketing, content, public-facing contexts — fast, audience-driven, communication-dense',
            ],
            'literal_phrases' => [
                'I love communicating ideas,',
                'I think about how to say it so people get it,',
                'I want to reach people.',
            ],
        ],
        'distortions' => [
            'Influence as ego',
            'audience capture',
            'manipulation replacing persuasion',
            'the platform fused to identity',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'teaching-formation',
                'arts-beauty',
                'advocating-supporting',
            ],
            'differentiating_questions' => [
                'Audiences at scale (Communication) or named learners over time (Teaching)?',
                'Clarity and reach (Communication) or beauty and resonance (Arts)?',
                'Broadcasting the cause (Communication) or standing with the specific person (Advocating)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward the message — carrying ideas across the distance between minds, at scale.',
                'expanded' => 'The person is compelled by how ideas travel: what makes a message land, spread, and move people. The meaning is translation at scale — not forming individual learners (Teaching), but reaching audiences with clarity and force.',
            ],
            'q2' => [
                'short' => 'Journalism, marketing, public relations, content creation, broadcasting, speechwriting, brand and media work.',
                'expanded' => 'Any work whose product is a message engineered for an audience. The writer, the marketer, the podcaster, and the publicist share one function: making ideas clear, compelling, and heard.',
            ],
            'q3' => [
                'short' => 'The natural storyteller — articulate, audience-aware, always thinking about how to frame the thing.',
                'expanded' => 'In narratives, they describe the school announcement they rewrote to be better, the account they grew, the speech that worked. The tell is audience instinct — they don\'t just express; they calibrate for reception.',
            ],
            'q4' => [
                'short' => 'To be heard; to make important ideas land; to reach and move many people.',
                'expanded' => 'Signal phrases: "I love communicating ideas," "I think about how to say it so people get it," "I want to reach people." Distinguish from Arts by the goal: the communicator wants comprehension and response; the artist wants resonance whether or not it\'s understood.',
            ],
            'q5' => [
                'short' => 'Important truths going unheard; confusion at scale; bad messaging burying good ideas.',
                'expanded' => 'They are frustrated by the gap between what deserves attention and what gets it. Narratives often include irritation at a message botched — the good cause with the terrible pitch — and the itch to fix the framing.',
            ],
            'q6' => [
                'short' => 'Writing, speaking, storytelling, framing, audience empathy.',
                'expanded' => 'The composite skill is compression — taking something complex and making it portable without making it false. Secondary signal: instinctive attention to medium and moment: they think about *where* and *when* a message lands, not just what it says.',
            ],
            'q7' => [
                'short' => 'Media, marketing, content, public-facing contexts — fast, audience-driven, communication-dense.',
                'expanded' => 'They thrive on feedback loops: the message goes out, the response comes back, the craft improves. Environments where their words disappear into silence starve them.',
            ],
            'q8' => [
                'short' => 'Influence as ego; audience capture; manipulation replacing persuasion; the platform fused to identity.',
                'expanded' => 'The healthy communicator serves the truth of the message and the good of the audience; the distorted one serves the metrics — saying what wins applause rather than what is true, or bending framing until persuasion becomes manipulation. Watch for narratives where attention itself is the hunger, or where the student measures worth in reach. Theologically: the voice made throne — speech serving the speaker\'s glory rather than the hearer\'s good.',
            ],
            'q9' => [
                'short' => 'Teaching & Formation; Arts & Beauty; Advocating & Supporting.',
                'expanded' => 'Differentiating questions — Audiences at scale (Communication) or named learners over time (Teaching)? Clarity and reach (Communication) or beauty and resonance (Arts)? Broadcasting the cause (Communication) or standing with the specific person (Advocating)?',
            ],
            'q10' => [
                'sentence' => 'You carry ideas across the distance between minds — and you make them land.',
            ],
        ],
    ],
    [
        'slug' => 'advocating-supporting',
        'name' => 'Advocating & Supporting',
        'sort_order' => 14,
        'core_function' => 'An orientation toward the overlooked — standing with specific people and ensuring their needs and voices are addressed.',
        'summary_sentence' => 'You cannot walk past someone the system walked over.',
        'signal_fingerprint' => [
            'desires' => [
                'To stand up for people',
                'to help the overlooked get access',
                'to be someone\'s ally in a hard system',
            ],
            'burdens' => [
                'Specific people overlooked, unheard, or blocked from what they need',
            ],
            'strengths' => [
                'Empathy joined with action',
                'system navigation',
                'representation',
                'relational persistence',
            ],
            'environments' => [
                'Nonprofits, social services, casework, community organizations — relational, person-centered contexts',
            ],
            'literal_phrases' => [
                'I can\'t stand seeing people treated that way,',
                'I helped them figure it out,',
                'Someone had to be on their side.',
            ],
        ],
        'distortions' => [
            'Rescuing that removes agency',
            'outrage as identity',
            'martyr burnout',
            'others\' pain made into a platform',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'healing-care',
                'law-policy',
                'nourishing-hospitality',
            ],
            'differentiating_questions' => [
                'Representing and navigating (Advocating) or diagnosing and treating (Healing)?',
                'Standing with the person under the rule (Advocating) or reshaping the rule itself (Law & Policy)?',
                'Ensuring access and voice (Advocating) or creating warmth and welcome (Hospitality)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward the overlooked — standing with specific people and ensuring their needs and voices are addressed.',
                'expanded' => 'The person cannot walk past someone the system walked over. The meaning is representation and accompaniment: not designing the rules (Law & Policy) or healing the wound (Healing), but standing beside a particular person and helping them get what they need.',
            ],
            'q2' => [
                'short' => 'Social work, casework, nonprofit service, community advocacy, counseling-adjacent support roles, human services.',
                'expanded' => 'Any work whose function is personal-scale justice — navigating systems on behalf of individuals, ensuring access, voicing needs that would otherwise go unheard. The caseworker, the advocate, and the support coordinator share the same core act.',
            ],
            'q3' => [
                'short' => 'The defender of the specific — empathetic but active, loyal, persistent on behalf of others.',
                'expanded' => 'In narratives, they describe standing up for the excluded kid, helping a family member navigate a bureaucracy, or refusing to drop someone else\'s problem until it was solved. The tell is empathy that converts into logistics: they don\'t just feel for people, they *do the paperwork*.',
            ],
            'q4' => [
                'short' => 'To stand up for people; to help the overlooked get access; to be someone\'s ally in a hard system.',
                'expanded' => 'Signal phrases: "I can\'t stand seeing people treated that way," "I helped them figure it out," "Someone had to be on their side." Distinguish from Law & Policy by scale of desire: the advocate wants *this person* to win; the jurist wants the *rule* to be right.',
            ],
            'q5' => [
                'short' => 'Specific people overlooked, unheard, or blocked from what they need.',
                'expanded' => 'They are provoked by faces, not statistics — the neighbor denied services, the classmate no one defended. Narratives often center a particular person whose mistreatment the student still carries, sometimes years later.',
            ],
            'q6' => [
                'short' => 'Empathy joined with action; system navigation; representation; relational persistence.',
                'expanded' => 'The composite skill is stubbornness on behalf of others — the willingness to make the fifth phone call, appeal the denial, sit in the waiting room with someone. Secondary signal: comfort speaking for others when appropriate, and knowing when to hand the microphone back.',
            ],
            'q7' => [
                'short' => 'Nonprofits, social services, casework, community organizations — relational, person-centered contexts.',
                'expanded' => 'They need proximity to the people they serve; policy-only roles at abstract distance frustrate the gift. Look for narratives where their satisfaction came from one person\'s situation concretely improving.',
            ],
            'q8' => [
                'short' => 'Rescuing that removes agency; outrage as identity; martyr burnout; others\' pain made into a platform.',
                'expanded' => 'The healthy advocate empowers people toward their own voice; the distorted one keeps people dependent because being the rescuer is the identity. A second distortion: chronic outrage — the cause becomes a personality, and opposition becomes the fuel. Watch for narratives where the student needs victims to remain victims, or where their own depletion is worn as a badge. Theologically: compassion detached from wisdom — mercy that serves the giver\'s need to be merciful.',
            ],
            'q9' => [
                'short' => 'Healing & Care; Law & Policy; Nourishing & Hospitality.',
                'expanded' => 'Differentiating questions — Representing and navigating (Advocating) or diagnosing and treating (Healing)? Standing with the person under the rule (Advocating) or reshaping the rule itself (Law & Policy)? Ensuring access and voice (Advocating) or creating warmth and welcome (Hospitality)?',
            ],
            'q10' => [
                'sentence' => 'You cannot walk past someone the system walked over.',
            ],
        ],
    ],
    [
        'slug' => 'knowledge-information',
        'name' => 'Knowledge & Information',
        'sort_order' => 15,
        'core_function' => 'An orientation toward order in knowledge — collecting, organizing, and preserving what is known so it can be used.',
        'summary_sentence' => 'You bring order to what the world knows so that none of it is lost.',
        'signal_fingerprint' => [
            'desires' => [
                'To bring order to information',
                'to research deeply',
                'to make knowledge clear and accessible',
            ],
            'burdens' => [
                'Lost, disorganized, or inaccurate information',
                'poor documentation',
                'knowledge no one can find',
            ],
            'strengths' => [
                'Categorization, research, documentation, accuracy, systematic and archival thinking',
            ],
            'environments' => [
                'Libraries, archives, research institutions, data-heavy organizations — structured, accuracy-honoring contexts',
            ],
            'literal_phrases' => [
                'I keep everything documented,',
                'I like organizing information,',
                'I want it to be accurate and findable.',
            ],
        ],
        'distortions' => [
            'Perfectionism that paralyzes',
            'hoarding knowledge instead of serving with it',
            'order as control',
            'detail without purpose',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'discovering-innovating',
                'teaching-formation',
                'administration-systems',
            ],
            'differentiating_questions' => [
                'Organizing existing knowledge (Knowledge & Information) or generating new knowledge (Discovering)?',
                'Making knowledge accessible (Knowledge & Information) or forming learners through it (Teaching)?',
                'Structuring information (Knowledge & Information) or structuring operations (Administration)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward order in knowledge — collecting, organizing, and preserving what is known so it can be used.',
                'expanded' => 'The person is drawn to the architecture of information: how knowledge is structured, stored, found, and kept accurate. The meaning is preservation and access — the conviction that knowledge disorganized is knowledge lost.',
            ],
            'q2' => [
                'short' => 'Library and archival science, research support, data management, documentation, records, knowledge systems.',
                'expanded' => 'Any work whose function is making knowledge findable, accurate, and durable. The librarian, the archivist, the data curator, and the documentation specialist share the same core act: standing between human knowledge and entropy.',
            ],
            'q3' => [
                'short' => 'The organizer of the known — detail-precise, thorough, quietly bothered by inaccuracy and disorder.',
                'expanded' => 'In narratives, they describe organizing collections unprompted — the meticulous notes classmates borrowed, the personal database, the family archive they appointed themselves to keep. The tell is care for accuracy as almost a moral matter.',
            ],
            'q4' => [
                'short' => 'To bring order to information; to research deeply; to make knowledge clear and accessible.',
                'expanded' => 'Signal phrases: "I like organizing information," "I keep everything documented," "I want it to be accurate and findable." Distinguish from Discovering by direction: this desire curates what is known rather than pushing past its edge.',
            ],
            'q5' => [
                'short' => 'Lost, disorganized, or inaccurate information; poor documentation; knowledge no one can find.',
                'expanded' => 'They are genuinely pained by misinformation, broken records, and institutional forgetting. Narratives often include frustration at needed information that existed but couldn\'t be found — and the systems they built in response.',
            ],
            'q6' => [
                'short' => 'Categorization, research, documentation, accuracy, systematic and archival thinking.',
                'expanded' => 'The composite skill is taxonomy — perceiving the natural structure inside a mass of information and building the system that reflects it. Secondary signal: patience for detail work others find tedious, experienced instead as satisfying.',
            ],
            'q7' => [
                'short' => 'Libraries, archives, research institutions, data-heavy organizations — structured, accuracy-honoring contexts.',
                'expanded' => 'They thrive where precision is valued and time is granted for thoroughness. Fast-and-loose environments that reward speed over accuracy violate something in them.',
            ],
            'q8' => [
                'short' => 'Perfectionism that paralyzes; hoarding knowledge instead of serving with it; order as control; detail without purpose.',
                'expanded' => 'The healthy curator organizes knowledge so others can use it; the distorted one organizes to possess — systems polished endlessly and shared never, or precision wielded as gatekeeping. Watch for narratives where the organizing is refuge from the human world rather than service to it, or where nothing can ship because nothing is ever complete enough. Theologically: the lamp perfected and kept under the basket.',
            ],
            'q9' => [
                'short' => 'Discovering & Innovating; Teaching & Formation; Administration & Systems.',
                'expanded' => 'Differentiating questions — Organizing existing knowledge (Knowledge & Information) or generating new knowledge (Discovering)? Making knowledge accessible (Knowledge & Information) or forming learners through it (Teaching)? Structuring information (Knowledge & Information) or structuring operations (Administration)?',
            ],
            'q10' => [
                'sentence' => 'You bring order to what the world knows so that none of it is lost.',
            ],
        ],
    ],
    [
        'slug' => 'administration-systems',
        'name' => 'Administration & Systems',
        'sort_order' => 16,
        'core_function' => 'An orientation toward operational order — designing and optimizing the processes that make things run.',
        'summary_sentence' => 'You are the architecture beneath the achievement — nothing runs without what you build.',
        'signal_fingerprint' => [
            'desires' => [
                'To make things run smoothly',
                'to build reliable systems',
                'to eliminate chaos and waste',
            ],
            'burdens' => [
                'Chaos, inefficiency, unclear responsibilities, and preventable failures of coordination',
            ],
            'strengths' => [
                'Planning, process design, organization, follow-through, logistical thinking',
            ],
            'environments' => [
                'Structured organizational contexts — operations, logistics, coordination-heavy roles',
            ],
            'literal_phrases' => [
                'I get frustrated when things are disorganized,',
                'I like making things run smoothly,',
                'I set up a system for it.',
            ],
        ],
        'distortions' => [
            'Process worshiped over people',
            'control through structure',
            'rigidity',
            'invisibility curdled into resentment',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'leadership-management',
                'maintaining-repairing',
                'finance-economics',
                'knowledge-information',
            ],
            'differentiating_questions' => [
                'Perfecting process (Administration) or directing people toward vision (Leadership)?',
                'Organizational workflows (Administration) or physical and technical systems (Maintaining)?',
                'Operations generally (Administration) or capital specifically (Finance)?',
                'Structuring work (Administration) or structuring information (Knowledge & Information)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward operational order — designing and optimizing the processes that make things run.',
                'expanded' => 'The person sees organizations as machines of coordination and feels compelled to make the machine run smoothly. The meaning is invisible excellence: the systems, workflows, and processes that everything else depends on and no one notices until they fail.',
            ],
            'q2' => [
                'short' => 'Operations, logistics, project coordination, office administration, process design, systems management.',
                'expanded' => 'Any work whose product is smooth execution — the right things happening in the right order without drama. The operations manager, the coordinator, and the process designer share the same function: converting chaos into reliability.',
            ],
            'q3' => [
                'short' => 'The organizer of execution — structured, dependable, quietly allergic to chaos and dropped balls.',
                'expanded' => 'In narratives, they describe becoming the de facto coordinator: the group project\'s actual scheduler, the club\'s logistics brain, the person who made the event run. The tell is that they organized the *process*, not commanded the *people* — the clipboard, not the podium.',
            ],
            'q4' => [
                'short' => 'To make things run smoothly; to build reliable systems; to eliminate chaos and waste.',
                'expanded' => 'Signal phrases: "I like making things run smoothly," "I get frustrated when things are disorganized," "I set up a system for it." Distinguish from Leadership by the object: the leader is drawn to directing people toward vision; the administrator is drawn to perfecting the process beneath them.',
            ],
            'q5' => [
                'short' => 'Chaos, inefficiency, unclear responsibilities, and preventable failures of coordination.',
                'expanded' => 'They are irritated by disorder that costs people time and outcomes — the meeting without an agenda, the plan without an owner. Narratives often show them quietly fixing operational messes no one else even diagnosed.',
            ],
            'q6' => [
                'short' => 'Planning, process design, organization, follow-through, logistical thinking.',
                'expanded' => 'The composite skill is orchestration — holding many moving parts in mind and sequencing them so nothing collides or drops. Secondary signal: reliability so consistent that others build their plans on it.',
            ],
            'q7' => [
                'short' => 'Structured organizational contexts — operations, logistics, coordination-heavy roles.',
                'expanded' => 'They thrive where excellence of process is recognized as real work. Environments that romanticize improvisation and treat structure as bureaucracy waste and frustrate the gift.',
            ],
            'q8' => [
                'short' => 'Process worshiped over people; control through structure; rigidity; invisibility curdled into resentment.',
                'expanded' => 'The healthy administrator builds systems that serve people; the distorted one builds systems people must serve — the process defended even when it harms the purpose, or structure used to control what others may do. A second distortion: bitterness at being unseen, since good systems make their maker invisible. Watch for narratives where flexibility reads to the student as moral failure. Theologically: the sabbath made for man, inverted — the system enthroned above those it was built for.',
            ],
            'q9' => [
                'short' => 'Leadership & Management; Maintaining & Repairing; Finance & Economics; Knowledge & Information.',
                'expanded' => 'Differentiating questions — Perfecting process (Administration) or directing people toward vision (Leadership)? Organizational workflows (Administration) or physical and technical systems (Maintaining)? Operations generally (Administration) or capital specifically (Finance)? Structuring work (Administration) or structuring information (Knowledge & Information)?',
            ],
            'q10' => [
                'sentence' => 'You are the architecture beneath the achievement — nothing runs without what you build.',
            ],
        ],
    ],
    [
        'slug' => 'pastoral-missionary',
        'name' => 'Pastoral & Missionary Work',
        'sort_order' => 17,
        'core_function' => 'An orientation toward the soul — shepherding people in faith, truth, and their relationship with God.',
        'summary_sentence' => 'You feel the weight of souls the way others feel the weight of deadlines.',
        'signal_fingerprint' => [
            'desires' => [
                'To disciple and shepherd others',
                'to see people reconciled to God',
                'to serve a community or mission',
            ],
            'burdens' => [
                'Spiritual lostness, disconnection from God, and the absence of discipleship and spiritual care',
            ],
            'strengths' => [
                'Spiritual discernment, relational depth, teaching truth with pastoral sensitivity, servant leadership',
            ],
            'environments' => [
                'Churches, ministries, mission contexts — communities of faith, worship, and spiritual formation',
            ],
            'literal_phrases' => [
                'I care about where they are with God,',
                'I feel called to ministry,',
                'I want to walk with people spiritually.',
            ],
        ],
        'distortions' => [
            'The messiah complex',
            'ministry fused to identity',
            'spiritual authority misused',
            'needing to be spiritually needed',
            'performance faith',
        ],
        'adjacent_categories' => [
            'slugs' => [
                'teaching-formation',
                'healing-care',
                'leadership-management',
                'nourishing-hospitality',
            ],
            'differentiating_questions' => [
                'Tending the soul (Pastoral) or forming the mind (Teaching)?',
                'Spiritual restoration (Pastoral) or psychological and physical restoration (Healing)?',
                'Shepherding a flock (Pastoral) or directing an organization (Leadership)?',
                'Spiritual community (Pastoral) or general welcome and belonging (Hospitality)?',
            ],
        ],
        'taxonomy_profile' => [
            'q1' => [
                'short' => 'An orientation toward the soul — shepherding people in faith, truth, and their relationship with God.',
                'expanded' => 'The person feels responsible for others\' spiritual lives the way others feel responsible for deadlines or dependents. The meaning is shepherding: guiding people toward God, walking with them through doubt and formation, often with a felt calling to particular communities or places.',
            ],
            'q2' => [
                'short' => 'Pastoral ministry, missions, chaplaincy, discipleship, spiritual direction, ministry leadership.',
                'expanded' => 'Any work whose core function is spiritual formation and care — where the material is faith and the product is people growing in relationship with God. The pastor, the missionary, the chaplain, and the spiritual director share the same shepherding act in different fields.',
            ],
            'q3' => [
                'short' => 'The shepherd — spiritually sensitive, relationally deep, carrying a felt sense of calling.',
                'expanded' => 'In narratives, they describe praying for friends unprompted, being the one others brought spiritual questions to, or a moment of calling they can narrate with specificity. The tell is weight: they speak of others\' spiritual condition as a burden they carry, not a topic they enjoy.',
            ],
            'q4' => [
                'short' => 'To disciple and shepherd others; to see people reconciled to God; to serve a community or mission.',
                'expanded' => 'Signal phrases: "I feel called to ministry," "I care about where they are with God," "I want to walk with people spiritually." Distinguish from Teaching by the dimension: the teacher forms the mind; the shepherd tends the soul — and the shepherd\'s satisfaction is spiritual transformation, not comprehension.',
            ],
            'q5' => [
                'short' => 'Spiritual lostness, disconnection from God, and the absence of discipleship and spiritual care.',
                'expanded' => 'They ache over conditions others don\'t perceive — the friend drifting from faith, the community with no one tending it. Narratives often include grief over someone\'s spiritual state, carried personally and prayed over long after others moved on.',
            ],
            'q6' => [
                'short' => 'Spiritual discernment, relational depth, teaching truth with pastoral sensitivity, servant leadership.',
                'expanded' => 'The composite skill is presence with discernment — the ability to sit with a person\'s soul honestly, sensing what is actually happening beneath what is said. Secondary signal: others already treat them pastorally, seeking them out for prayer, confession, and counsel before any title exists.',
            ],
            'q7' => [
                'short' => 'Churches, ministries, mission contexts — communities of faith, worship, and spiritual formation.',
                'expanded' => 'They need contexts where spiritual formation is the explicit work, not a private extra. Look for narratives where their most alive moments occurred in ministry settings — the retreat, the mission trip, the small group they ended up leading.',
            ],
            'q8' => [
                'short' => 'The messiah complex; ministry fused to identity; spiritual authority misused; needing to be spiritually needed; performance faith.',
                'expanded' => 'The healthy shepherd points people to God and gets out of the way; the distorted one becomes the destination — indispensable, unaccountable, feeding on being the spiritual center. Other distortions: calling claimed as immunity from criticism, public devotion masking private emptiness, and ministry pursued as escape from a secular world that felt too hard. Watch for narratives where the student needs the role more than the people need the shepherd. Theologically: the shepherd who feeds on the flock — or the temple built to the builder.',
            ],
            'q9' => [
                'short' => 'Teaching & Formation; Healing & Care; Leadership & Management; Nourishing & Hospitality.',
                'expanded' => 'Differentiating questions — Tending the soul (Pastoral) or forming the mind (Teaching)? Spiritual restoration (Pastoral) or psychological and physical restoration (Healing)? Shepherding a flock (Pastoral) or directing an organization (Leadership)? Spiritual community (Pastoral) or general welcome and belonging (Hospitality)?',
            ],
            'q10' => [
                'sentence' => 'You feel the weight of souls the way others feel the weight of deadlines.',
            ],
        ],
    ],
];
