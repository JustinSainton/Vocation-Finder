# Vocational Finder — V1 Execution Roadmap

> **Source documents:** the *Vocational Finder Intelligence System Blueprint* (Lucas Cecilio, 85pp — governs how the engine interprets) and the *Vocational Finder Vision Document* (Lucas Cecilio, 12pp — governs what the product does with that interpretation).
>
> **Authored:** 2026-09-15. Audited against the codebase at commit `3e328f1`.

---

## The one-sentence goal

Build the pipeline described by the spine **assessment → gaps → readiness → actions → outcomes**, such that *no student finishes a first session without one concrete thing to do.*

---

## The MVP cut — "Coach MVP"

*Added 2026-09-15. The phases below are ordered by correctness. This section names what actually ships first, drawing items out of Phases 0–2 rather than completing them in order.*

**The shippable promise:** a junior or senior completes an assessment, reads a result the system is honest about its confidence in, talks to a coach that knows their profile, and leaves with **one concrete thing to do** and a reason to come back.

### What already exists and is reusable

`app/Ai/Agents/CareerCoachAgent.php` is a functioning conversational agent: `RemembersConversations` gives it persistent threads, `HasTools` lets it read the vocational profile via `GetVocationalProfileTool`, and it is already routed (`/career-coach` web page, `career-coach/start|message|history` API) behind the `career_coach` feature flag. `conversation_sessions` exists as a table.

**But it is the wrong coach.** Its instructions address adult career-changers ("helps people navigate their career path"), its third tool is `SearchJobsTool`, and its voice is unconstrained by the blueprint's allowlist/banlist. The MVP forks this into a student-facing `PathwayCoachAgent` — keeping the agent/tool/persistence architecture, replacing the instructions, the tools, and the guardrails.

### MVP scope

| From | # | Item | Why it is in the MVP |
|---|---|---|---|
| Phase 0 | 0.1 | ~~`signal_extractions`~~ **DONE** | The coach must reference specific things the student said. Without typed signals it invents. |
| Phase 0 | 0.2 | ~~Confidence levels~~ **DONE** | The coach has to be able to say "I'm not sure yet, here's how we find out." Structurally impossible today. |
| Phase 0 | 0.3 | ~~Red-team lint~~ **DONE** | Mostly a deterministic word-list check. Cheapest possible protection against the banned-language failure mode. |
| Phase 0 | 0.6 | ~~`response_quality_score`~~ **DONE** | Gates low-confidence mode; a thin assessment must not produce a confident coach. |
| Phase 0 | 0.7 | Taxonomy enrichment | Highest-leverage item in the whole roadmap — the 17 categories currently carry name + description, which is not enough to govern an LLM. |
| Phase 1 | 1.1 | ~~`PathwayCoachAgent`~~ **DONE** (agent + tools; wiring is 1.7) + persistent coach entity | The product. |
| Phase 1 | 1.2 | ~~Six gap types as records~~ **DONE** | What the coach converts conversation into. |
| Phase 1 | 1.4 | ~~Action queue — **one action, never a list**~~ **DONE** | The vision's entire success test. |
| Phase 1 | 1.6 | ~~Age gate + parent consent~~ **DONE** (+ parent checkout: policy done, Stripe session in 1.7) | Not optional. Minors are the primary user. |
| Phase 1 | 1.7 | First-run sequence wired end to end | Without this the parts do not constitute a product. |
| Phase 2 | 2.1–2.2 | Verbatim capture of coach turns | **Cannot be backfilled.** Conversations not stored on day one are gone forever, and the brain is the long-term moat. |

### Deliberately NOT in the MVP

- ~~**0.4 version columns, 0.5 evaluation logs**~~ — **pulled forward and shipped 2026-09-16.** The judgement below was wrong in one specific way: observability cannot be backfilled. The MVP exit test is "count how many juniors finish a first session with one action they can describe, and how many come back" — that is not a question you can ask retroactively of runs you did not instrument, and the first cohort is the cohort whose data matters most.
- **0.9 test-case library** — required before *scaling*, not before *launching*. It becomes mandatory the moment real students are on it; treat it as fast-follow, not never.
- **0.8 aspiration-vs-demonstrated dual track** — the coach can derive actions from conversation gaps first; the evidence gap is what later generates the full development plan.
- **1.3 Readiness to Change** — ~~ships as a *stated* reason to return~~ **now fully decomposed and scored internally** (2026-09-15); nothing numeric reaches the student.
- **1.5 habit tracker** — the action queue is the behavior loop for MVP; habits are the second iteration of it.
- **All of Phase 3, 4, 5.** Including the plan, the locker, parent reporting, SMS, college data, and job feeds.

### The MVP exit test

Pick 20 real juniors. Count how many finish a first session with exactly one assigned action they can describe in their own words, and how many return voluntarily within two weeks. That is the whole test. Interpretive accuracy is necessary but not sufficient — a portrait nobody acts on has proven nothing.

### The main risk

Shipping the coach before 0.7 (taxonomy enrichment) produces a fluent, confident, and subtly wrong guide. That failure is worse than no coach, because students believe it. **0.7 is the gate, not the polish.**

---

## Reconciling the two documents

The blueprint and the vision agree on substance and disagree on **sequencing**. This has to be resolved explicitly rather than by drift.

| | Blueprint §15 | Vision |
|---|---|---|
| V1 goal | "Prove that the engine works" | Full pipeline to college or employment |
| Deprioritized | Course marketplace, employer matching, college matching, mobile-native | College explorer, vetted job feed, syllabus mapping all named as build surface |
| Success test | Interpretive accuracy, humility, explainability | "If a student finishes their first session without one concrete thing to do, we haven't proven anything" |

**Resolution adopted in this roadmap:** the blueprint's caution is about *not building an ecosystem on an unproven engine*. The vision's insistence is that *an engine that ends in a portrait is not proven at all.* Both are satisfied by making **Phase 1 = engine + one action**, and deferring the pathway layers (college/jobs/college-success) until the coach loop demonstrably moves behavior.

This means: **the action loop is part of proving the engine, not an expansion of it.** The marketplace-style surfaces genuinely are expansion, and stay late.

---

## Open decisions blocking full execution

These are Justin's calls; the roadmap flags rather than assumes.

1. ~~**Design direction.**~~ **RESOLVED 2026-09-15 — "structure yes, expression from vision."** `DESIGN.md` is now the Vocational Finder design system: Clay's structural layer (4px spacing base, 96px section rhythm, token discipline, hairline elevation with no shadows, 44px touch targets, cream canvas) carrying the vision's expressive layer (editorial serif headlines *and* body, monospace eyebrow labels, grainy real photography, restraint, dark coach/brain surfaces). Claymation, mascots, the six-color palette and the generous radius scale were dropped. The document's own dual palette becomes semantic: light for assessment/results/marketing, dark for the coach and the brain. **Phase 3 is unblocked.** ~~Three implementation prerequisites remain: add IBM Plex Mono, introduce the dark token set, and replace the warm-stone `--color-accent` with the indigo `#3A3AA0` / `#8F8FF5` pair.~~ **All three landed 2026-09-16; see Phase 3 § Design system.**
2. **Scope tension above** — confirm the Phase 1/2 boundary is where you want it.
3. ~~**Existing surface area.**~~ **RESOLVED 2026-09-16 — "fold in what serves V1."** Mapping below.
4. ~~**Texting provider and cost model.**~~ **RESOLVED 2026-09-16 — "skip 3.6 for now."** No provider chosen, because nothing is being built that needs one. The return loop already exists without SMS: 2.4's brainstorm invitation adapts on evidence and never stops offering. SMS is reach, not mechanism, and it carries real per-message cost plus TCPA and minor-consent exposure — none of which is worth taking on before a cohort tells us in-app invitations are not landing. Reopens on that evidence.
5. **Readiness external mapping** — the vision says readiness "should eventually map to something externally recognized." Not required for V1, but the scoring design should not preclude it.

---

## Current state (verified, not assumed)

**Exists:** assessment flow, 20 questions, 17 seeded categories, `VocationalAnalysis` + `NarrativeSynthesis` agents, `ConversationSession`/`ConversationTurn`, Cashier billing, org/mentor structures, admin dashboards, courses, job listings, resume tooling, Expo app.

**Does not exist** (no migrations found): habits, coach persistence, vocational brain, readiness, nudges, action queue, milestones, plan, locker, parent consent/reporting, college data, signal extraction, red-team review, evaluation logs.

---

## Phase 0 — Engine integrity
*Closes the blueprint gaps that make everything downstream trustworthy. Nothing user-visible.*

| # | Work | Why it's first |
|---|---|---|
| 0.1 | ~~`signal_extractions` table~~ **DONE 2026-09-15** — `SignalType` (all 14 blueprint types, with `weight()` and `canEvidenceDemonstratedFit()`) + `SignalTrack` (aspiration vs demonstrated, §10.3) enums; `signal_extractions` keyed to assessment **and** answer; `SignalDetection` agent; `SignalExtractor` service runs as Layer 4 *before* taxonomy mapping and feeds verified signals into it. Every signal carries the `verbatim` span it came from, checked as a substring of the source answer — a quote the student never said is discarded deterministically, no model in the loop. 12 tests | Layer 4 has no home today; gaps in Phase 1 are derived from these signals |
| 0.2 | ~~Confidence levels (Strong/Moderate/Emerging/Weak/Insufficient) on category scores~~ **DONE 2026-09-15** — `ConfidenceLevel` enum + `ConfidenceCalculator`; per-category confidence on `category_scores`, plus `confidence_level`, `confidence_rationale` and `missing_evidence` on the profile (never lockable, §11.4). Derived deterministically from score × evidence ceiling × separation — never asked of the model. 24 tests | System structurally cannot admit uncertainty today — blueprint §13.3 |
| 0.3 | ~~Red-team review layer (Layer 8)~~ **DONE 2026-09-15** — `RedTeamLint` with severity: **blocking** (divine presumption, determinism, false certainty, mysticism, clinical/diagnostic, §10.5 dead-ends, percentages and match scores) refuses the narrative and drives a repair retry via `RedTeamViolation`; **warning** (flattery, hype, corporate-speak, objectifying) is logged and still ships. Word-boundary and regex matching so innocent lookalikes ("fateful", "heroic", "the subject matter") do not fire. 48 tests | Most of §10.4 is a deterministic word-list lint; run it before any LLM pass |
| 0.4 | ~~Version columns (`model_version`, `prompt_version`, `taxonomy_version`) on profiles~~ **DONE 2026-09-16** — `EngineVersion` **derives** all three rather than declaring them: `prompt()` is a sha256 over the six files that actually shape the prompts, `taxonomy()` over the nine governing columns of every category ordered by slug, `model()` from config. A hand-maintained constant is a promise someone has to keep on every edit, and the one time it is forgotten is the one time the data mattered — a forgotten bump silently misattributes every subsequent row to the previous engine. Written onto `vocational_profiles` and onto every `evaluation_logs` row. | Required for the "compare outputs across model versions" admin tool and drift tracking |
| 0.5 | ~~`evaluation_logs` table + user feedback capture~~ **DONE 2026-09-16** — two append-only tables. `evaluation_logs` records every engine run from inside `AnalyzeAssessmentJob` on **both** the success and failure paths: outcome, the three versions, confidence reached, signals kept, mean response quality, how many narrative drafts the red-team pass cost, the findings themselves, and wall-clock duration. `evaluation_feedback` captures the student's own verdict in three questions (`FeedbackQuestion`) answered in words, never a scale (`FeedbackStanding`), first answer wins, guests included. Both models throw on `updating` and `deleting`: a log you can edit is a log you cannot trust. 14 tests, six vacuity probes. | V1 *must* per blueprint §15 |
| 0.6 | ~~`response_quality_score` on `answers`~~ **DONE 2026-09-15** — `ResponseQuality` scores each answer 0-100 from three polish-blind bands: substance (30, saturating), evidence density (40, verified Layer 4 signals), behavioral grounding (30, demonstrated-track signals). Deliberately uses **no** literacy proxy — no vocabulary, spelling, punctuation or proper-noun signal, per §11's bias rule. Feeds `ConfidenceCalculator`'s evidence ceiling, replacing raw word counts; word counts remain the fallback for assessments scored before Layer 4 existed. 10 tests | Gates low-confidence mode |
| 0.7 | ~~Enrich `vocational_categories`~~ **DONE 2026-09-15** — `core_function`, `signal_fingerprint`, `adjacent_categories` + differentiating questions, `distortions`, `summary_sentence` (Q10), full `taxonomy_profile`. Plus `TaxonomyPrompt` rendering, two-pass disambiguation (`CompetingPathways` + `CategoryDisambiguation`), 32 tests | The taxonomy governs the AI; it was a bare list of 17 names in the prompt |
| 0.8 | ~~Aspiration vs demonstrated dual-track scoring; expose the **gap**~~ **DONE 2026-09-16** — the model cites, the code counts. Layer 4 runs before taxonomy mapping so a signal cannot know its category; the analysis pass is therefore asked for the one thing it is qualified to supply — *which signals bear on which pathway*, as `S3`-style references — and nothing else. `DualTrack` resolves those references against the real signals, discards inventions exactly as `SignalExtractor` discards invented spans, and computes both track weights from `SignalType::weight()` and `canEvidenceDemonstratedFit()`, which the model cannot influence. A desire filed on the demonstrated track still counts zero. `EvidenceStanding` is a closed four-case vocabulary (demonstrated / emerging / aspiration only / not yet evidenced) carrying `meaning()` and `nextMove()` written to be quoted to a sixteen-year-old, with no number anywhere. Only the leading pathway gets a gap — a student handed four distances has a report, one has something to do. Threshold is 1.5: one instance of anything is an anecdote and must not tell a student who they are. 17 tests, six vacuity probes. | The gap is what generates the development plan (§10.3) |
| 0.9 | ~~Test-case library as PHPUnit fixtures~~ **DONE 2026-09-16** — a golden corpus in `tests/Fixtures/Engine`, four whole assessments rather than constructed strings: the junior from the first live run, a student who answered in one to three words, an articulate student who wants surgery and has never touched it, and a narrative that must be refused. The model's output is recorded so the layers that are *ours* — provenance, red-team lint, response quality, confidence, dual track — are re-derived on every commit without an API key. `EngineFixtureLiveTest` drives the same corpus through a real model for what recorded output cannot catch: the model itself moving. A meta-test pins the shape of the library, so a corpus that has quietly become all-passing fails. | Highest-leverage defense against engine drift |

**Progress:** Phase 0 complete.

The corpus paid for itself on the first run. Prose shaped like a real model's
output slipped two blocking rules that every unit test had passed: the lint
knew "without a doubt" but not "there is no doubt", and had no rule at all for
telling a student *what they are* ("This is who you are"). Both are now caught,
with lookalike guards. It also surfaced that a pattern-based rule reported its
own regex as the offending `match`, so `repairInstruction()` would have handed
the model a string it could not find in its own draft — the same failure that
made the clinical rule unrepairable on the first live run, in a second place. Disambiguation runs as a conditional second pass — first pass scores all 17 from the Short anchors; when leaders cluster within 8 points above a floor of 55, a second call sends only those categories' Expanded layers and differentiating questions. Failure is non-fatal: an unresolved ambiguity keeps the first-pass scores.

**Exit criteria:** an assessment produces typed signals, confidence levels, a red-team-passed result, and a recorded aspiration/evidence gap — all versioned and replayable.

---

## Phase 1 — The core loop
*The vision's actual thesis. Ends the session with one action.*

| # | Work |
|---|---|
| 1.1 | **AGENT DONE 2026-09-15** — `PathwayCoachAgent` forked from `CareerCoachAgent`, keeping `RemembersConversations`; job-search tooling deliberately dropped. Two non-overlapping tools: `GetPathwayProfileTool` (profile **with** confidence + `may_name_a_direction` + `missing_evidence`) and `GetStudentSignalsTool` (verified Layer 4 verbatim, demonstrated vs aspiration). Instructions carry the six gap types, the §10.5 script, one-action-not-a-list, crisis-before-vocation, and the §10.4 prohibitions. 25 tests. **Still open:** routing, feature flag and coach-generation-on-completion — those land in 1.7 |
| 1.2 | ~~Six gap types as first-class records~~ **DONE 2026-09-15** — `GapType` (six, each with `description()` and a distinct `closingMove()`) + `GapStatus` (open/testing/closed, **no deleted state**); `gaps` keyed to the **user** not the assessment, so a retake cannot reset progress; provenance links to assessment and signal both `nullOnDelete`. `Gap::deleting` throws — closed, never deleted, per the brain persistence policy. Coach gains `GetGapsTool` + `RecordGapTool` (enum-constrained type, refuses a duplicate open gap). 20 tests |
| 1.3 | ~~**Readiness to Change**~~ **DONE 2026-09-15** — `ReadinessLevel` (Considering/Exploring/Testing/Moving — the floor is a statement about a week, never a verdict), `ReadinessFactor` ×5 each with its own distinct move, `FactorStanding`, `ReadinessCalculator`, append-only `readiness_snapshots`. **No model call**: every input is a count or an enum already in the database. **Naming an obstacle moves it UP** — scoring a student down for saying they cannot afford it teaches them not to say it, and the gaps are how the coach knows what to do next. Skipping a step never lowers it. "What moves it" is exactly one thing, from the thinnest factor. `GetReadinessTool` added to the coach; completing or skipping an action records a snapshot (**this is the action→readiness effect 1.4 deferred**); unchanged readings are not re-recorded, since a row per page load is a log not a history. Readiness is shown on the coach page rather than added to `FirstRunSequence` — it is something a student reads, not completes. 21 tests, three probes (penalise obstacles / surface a score / hand over the whole list) |
| 1.4 | ~~**Action queue**~~ **DONE 2026-09-15** — `Action` + `ActionStatus` (active/completed/skipped, **no deleted state**) + `ActionQueue`. "One at a time" enforced in the **database** via a portable `active_marker` trick (1 while active, NULL when settled, `unique(user_id, active_marker)`) — a partial index would not port to MySQL. "One thing" enforced on the text: narrow list-marker detection that rejects disguised lists without rejecting a single action with several clauses. Completion closes the gap it aimed at; skipping leaves it open. Coach gains `GetCurrentActionTool` + `AssignActionTool`. 26 tests. **Readiness effect deferred** — 1.3 scored readiness is out of the MVP cut |
| 1.5 | ~~**Habit tracker**~~ **DONE 2026-09-16** — `Habit` + `HabitCheckIn` + `HabitCadence`/`HabitStatus`/`HabitStanding` + `HabitTracker`. **"Not generic" is structural**: `habits.gap_id` is **not nullable** and `HabitTracker::prescribe()` takes a `Gap` in its signature, so a habit with no named gap behind it — a generic suggestion wearing a personalised label — has nowhere to exist. Keyed to the **user** like gaps and actions, so a retake cannot reset a month of work. **No model call**: every input is a date, a boolean or an enum, as with readiness. **No streak, ever** — a streak's mechanic is that breaking it destroys something, which teaches a teenager that the honest answer is the expensive one; the product needs the accurate report more than the adherence. **A miss the student explained leaves the denominator; silence does not** — the same rule that moves readiness *up* when a student names an obstacle. A stalled habit is made **smaller or set down**, never insisted upon. Nothing numeric reaches the student: the coach gets counts so it can tell a habit is the wrong size, the student gets a word and one move. Check-in is the student's act alone — no route takes another person's id, so no counsellor or parent can mark a habit done. `GetHabitsTool` + `PrescribeHabitTool`; check-in at `POST /habits/{habit}/check-in`. 20 tests, six probes |
| 1.6 | **DONE 2026-09-15** — `AgeTier` (freshman/sophomore · junior/senior · adult) + `ConsentStatus`; `birthdate` **and** `grade_level` on users (grade decides school bands, age decides adulthood — a 16-year-old may be either); `parent_consents` with token, granted_at/ip, revocable, **no cascade from billing**. `AccessPolicy` gates coach/brain; `PathwayCoachAgent` **throws in its constructor** for an unentitled student so no route can forget. `ParentVisibility` encodes the privacy invariant: milestones and a suggested conversation yes, reflections/evidence/transcripts never; no reporting at all for 18+. 25 tests. **Still open:** the parent Stripe checkout session itself — lands with routing in 1.7 |
| 1.7 | ~~**First-run sequence**~~ **DONE 2026-09-15** — `FirstRunStep` + `FirstRunSequence`, **computed, never stored**: a `current_step` column can disagree with the data it describes (revoked consent, failed analysis, a six-week gap) and every disagreement shows up as the product telling a student to redo something. Consent and payment are separate gates in that order — a parent who paid has not thereby consented. Freshman terminates at `Portrait` and that is **not** an incomplete funnel. `PathwayCoachController::message()` goes through `respondTo()` so every turn is captured. Public token-based parent consent (no account required), `pathway_coach` feature flag separate from `career_coach`, `checkoutStudent` + `User::stripeEmail()` (subscription belongs to the **student**, billing identity is the parent's). Also fixed a real Cashier gap: `trial_ends_at` had no date cast, so `onTrial()` threw. 21 tests. **Readiness (step 8) deliberately absent** — 1.3 is outside the MVP cut and slots in without reordering |

**Exit criteria:** a junior completes first session and leaves with exactly one assigned action, a readiness level, and a stated reason to return.

---

## Phase 2 — The brain
*Compounds in value over years; earliest start wins.*

| # | Work |
|---|---|
| 2.1 | ~~**Brain entries**~~ **DONE 2026-09-15** — `brain_entries` (verbatim `content`, `context` kept separate, `source`, `occurred_at`, `audio_storage_path`), `BrainEntry` with **two** guards: `deleting` refuses outright, and `updating` refuses a dirty `content` while still allowing context or audio to be added. **No `summary` column on purpose** — nowhere for generated prose to sit beside the student's words |
| 2.2 | ~~**Automatic capture**~~ **DONE 2026-09-15** — `BrainCapture` captures coach turns, action reflections and assessment answers. Capture lives on `PathwayCoachAgent::respondTo()`, the agent's only sanctioned entry point, so routing **cannot** forget it; it runs *before* the model call. `captureCoachTurn()` returns null for any role but `user` — the coach's sentences reach the table only as `context`. `MIN_WORDS = 6` filters acknowledgements (crude on purpose: a model deciding what is worth remembering about a teenager is the worse failure). Capture failure never blocks the thing it observed. 31 tests, three probes |
| 2.3 | ~~**Search/retrieval**~~ **DONE 2026-09-15** (landed with 2.1–2.2) — `BrainRetrieval` + `SearchBrainTool` + `SaveToBrainTool`. **No model and no entitlement check**: a retrieval that summarises hands back the system's sentence rather than the student's, and a lapse freezes the brain so export must always work. `SaveToBrainTool` reuses `SignalExtractor::spanAppearsIn()` against the current turn — a free-text save tool would let the model file a tidied version of a teenager's sentence under their name |
| 2.4 | ~~**Scheduled brainstorms**~~ **DONE 2026-09-16** — `BrainstormSchedule` (service + one row per student, so two invitations can never be outstanding). Monthly default; **adaptive on evidence, never on a marketing rhythm**: a student who settled an action is asked back at 21 days because they want it, and ignored invitations **double** the cadence — the information in a second ignored invitation is that the rhythm is wrong, not that we should ask again tomorrow. Capped at 90 days, and the cap is a **ceiling on contact, not a countdown to abandonment**: it never stops, because the brain is never deleted and neither is the standing offer to come back to it. Turning up counts as attending — a student who returned on their own has told the schedule everything it needed. **An invitation with nothing in it is a nag**, so it opens with whatever 2.5 has heard them repeat, in their own words. Shown on the coach page. 8 tests, four probes |
| 2.5 | ~~**Threshold surfacing**~~ **DONE 2026-09-16** — `ThresholdSurfacing` + `surfaced_patterns`. **No model, and it never names the pattern**: the "name" is the student's own repeated word lifted verbatim and the payload is their own dated sentences, so the system supplies the observation that they said it four times and nothing else. `surfaced_patterns` has no `label` or `summary` column, for the same reason `brain_entries` has none. **Repetition is not emphasis** — a term must appear in ≥4 *distinct* entries spanning ≥21 days, so saying it four times in one afternoon is one thought arriving loudly. One pattern, once: a 90-day cooldown, because said on every visit "you keep coming back to this" is nagging. ⚠️ **A pattern built from distress is never a vocational pattern** — it is flagged `needs_human` and excluded from the brainstorm topic, because crisis reaches a person before it reaches vocational meaning. 7 tests, three probes |
| 2.6 | ~~**Freeze-not-delete + export**~~ **DONE 2026-09-15** — `AccessPolicy::brainIsFrozen()` states the policy as one predicate about **capture only**, and `canExportBrain()` returns true unconditionally so the invariant has somewhere to live and something to test. `GET /brain/export` checks no subscription, no consent and no tier; default format is a readable document rather than JSON, because the actual use is a senior writing a college essay who needs what they said in the words they said it. Probe: gating export on entitlement — the plausible future 'hardening' — fails `export is reachable with no consent and no subscription` |

> **Invariant:** the brain *surfaces what the student already said; it does not write what they would have said.* Build as retrieval and clustering over stored verbatim capture, never generative summarization that puts new words in their mouth.
>
> **Invariant:** no cascade-delete path may reach brain data from any billing, org, or enrollment table. Verify on every migration touching it.

---

## Phase 3 — Surfaces and stakeholders
*Design decision resolved; see open decision 1. **Prerequisites landed 2026-09-16** — see "Design system" below.*

### Counsellor access to portraits — **RESOLVED 2026-09-16**

**Yes by default, with the organization able to change the flag.** `Organization::SETTING_STAFF_MAY_READ_PORTRAITS` defaults on, and `/org/{slug}/settings` lets an administrator close it with no release and no migration. The test `organisation_staff_cannot_read_a_members_portrait` that pinned this closed is now `..._may_read_..._by_default`, with the closed case kept as `an_organisation_can_close_it`.

What the decision forced into the open:

- **Reading is not acting.** `AssessmentAccess::authorize()` — *is this yours?* — still gates every write: answering, completing, filing feedback on the portrait, and mailing it to a typed address, which would otherwise have been an exfiltration path wearing a feature's clothes. `authorizeReading()` is the wider rule and has to be typed out on purpose, so a call site that forgets defaults closed.
- **The grant is a relationship, not a role.** Staff of another school are strangers; a classmate on the same roster is not staff. Both are pinned.
- **The hard invariant is untouched and now tested structurally.** The flag governs the portrait only. No coach or brain route takes another person as a parameter at all, which is the strongest form that guarantee can take — asserted against the route table rather than a response, with a vacuity guard so it cannot pass by the routes disappearing.
- **`OrganizationRole` exists.** The three role values were bare strings at a dozen call sites, so "which roles are staff?" was a `whereIn` written from memory. Adopted in `Organization` and the access rule.
- **The organization surface was 404ing entirely.** Every link in `OrgLayout` is `/org/{slug}` while implicit binding resolved on the UUID primary key, and no test covered any of it. Bound per-route as `{organization:slug}` rather than with `getRouteKeyName()` — the platform-admin surface addresses the same model by id, so the global fix trades one broken surface for the other. Both directions are asserted in one test so the next person to reach for it finds out immediately.

Probes: six mutations, each failing only its own test — dropping the flag check, calling members staff, dropping the shared-organization clause, widening `authorize()` to the reading rule, assigning to `settings` instead of merging, and binding by id.

### Design system — **DONE 2026-09-16**

All three prerequisites from open decision 1 are in, and `DESIGN.md` now has a test rather than a comment asserting it governs.

- **The mono face.** IBM Plex Mono loads from Google Fonts alongside Literata and Fontshare's Satoshi. `public/fonts/` stays empty — every face is a CDN face, which is why a "the font is declared but never loaded" regression was worth a test.
- **The indigo accent.** `--color-accent` *meant* muted: its value was `#78716C` and it was drawn on 161 label sites in 39 files. Renaming those to `--color-muted` was a visual no-op, which is the point — indigo then entered as a genuinely new token applied deliberately (`.link`, `.action-primary`, `:focus-visible`) rather than inheriting 161 sites that were never meant to be accented. `.action-primary` exists so "one accent per view" is countable: a component that fills with `bg-[var(--color-accent)]` directly fails a test. The first application demoted "Return home" on the results page from a second solid ink bar to an outline, because two equal-weight bars mean neither is the next move.
- **The dark room.** `AppLayout` takes `surface="light" | "dark"` and paints the canvas, so a dark page never bleeds past the layout to get there. `Coach/Index` is the first inhabitant; the brain is the second when 3.1 lands. A test fails if the coach leaves.
- **`DESIGN.md` is now enforced, not asserted.** `DesignSystemTest` parses the palette out of the document and compares every token to the stylesheet. It found real drift on its first run: `--color-text-secondary` was `#57534E`, a value the document never names, sitting between `body` and `muted`. Corrected to `#44403C`.

Probes: five mutations, each failing only its own test — moving the accent hex, swapping the Plex Mono link, hand-rolling an eyebrow out of `text-xs uppercase tracking-widest`, removing `surface="dark"` from the coach, and filling an element with the accent directly.

### 3.1 — The five places — **DONE 2026-09-16**

`StudentPlace` declares all five. Availability is asked of the **router**, not
of a hand-maintained boolean: `isOpen()` is `Route::has($this->value)`, so an
unbuilt place is absent from the interface without anyone deciding to hide it,
and 3.2 and 3.3 make theirs appear by registering a route rather than by
editing a component. `PlaceNav` contains no hrefs at all — a test asserts it
does not hard-code any place's path — and `AppLayout` is the only thing that
renders it, so a page cannot forget to be reachable from the rest of the
product.

Two places got built to fill the list.

- **The brain now has a front door**, and it sits **outside
  `feature:pathway_coach` and outside every entitlement check**, exactly as the
  export does. Letting a student download their own sentences while refusing to
  show them on a page is the same seizure with an extra step. `StudentPlace`
  carries this as `gate(): ?string`, null for the brain alone, so the exception
  is a line of code rather than a thing to remember. Nothing on the page is
  summarised, themed or titled: dated quotes, a source label, and the student's
  sentence untouched.
- **Habits got a place** instead of only a section on the coach page. The coach
  keeps its check-in, because the honest answer has to be givable in the moment
  the student is already there. Nothing numeric reaches either surface, and the
  test asserts the payload has no `kept`, `expected`, `streak`, `ratio` or
  `percent` key — the guarantee is about the data sent, not the pixels drawn.

The dark room is now **exclusive as well as inhabited**: `DesignSystemTest`
fails if any page outside `Pages/Coach` or `Pages/Brain` takes
`surface="dark"`. A reservation anyone may take up is not a reservation.

Probes: six mutations, each failing only its own test — a typo in a place's
path, gating the brain, truncating a brain entry, deleting `<PlaceNav />` from
the layout, moving habits into the dark room, and dropping the search term.
(Truncating an entry fails two tests; both are verbatim guards and that is the
point.)

### 3.2 — The plan — **DONE 2026-09-16**

The widest view the product offers, and therefore the one most likely to break
the product's own promise. Three things are enforced in `StudentPlan` rather
than in the page that draws them, because a rule living in a component is a
rule the next component does not have.

- **The plan is not a to-do list.** It carries exactly one action, read from
  the same `ActionQueue::current()` the coach reads, and milestones carry no
  "do it" affordance at all. A second list of next steps would be a second
  opinion about what matters most, and the student would have to arbitrate it.
- **Nothing is counted.** No totals, no completed-of-total, no percentage, no
  bar. The test walks the serialised payload and fails on a `total`,
  `completed`, `count`, `percent`, `progress`, `score` or `remaining` key
  anywhere in it — the guarantee is about the data, not the pixels, because
  the parent surface in 3.4 will inherit the data.
- **A closed window is a fact, not a verdict.** There is no `missed` status and
  no `failed` status, for the same reason there is no streak on a habit. The
  passed window is computed from today's date every time and arrives with a
  move attached: *"That date has gone by. Move it, or set it down on purpose."*

**Sections are computed, not authored.** `PlanSections` derives them from the
school calendar and `users.grade_level`: August to May is a year, June and July
are the summer between, and a junior in March is still a junior. Nobody types
"Junior year, August to May" into a form, because a value a human enters is a
value that is wrong every September.

**The plan stops at the end of school.** What comes after is one open section
with no end date. Naming four years of a college the student has not chosen
would be identity foreclosure with a calendar attached.

**`milestones.due_on` is NOT NULL** — the same structural move as
`habits.gap_id`. A milestone with no date is a wish, and a plan made of
sections has nowhere to put a wish; it belongs in the brain with everything
else the student has said.

**Passages are computed, not prescribed.** Finishing a year and graduating get
`MilestoneKind::Passage`, have no id, and carry no buttons — a student cannot
be behind on the passage of time. `passage` is deliberately absent from
`SetMilestoneTool`'s enum, so a model that wanted to could not hand a student a
task for turning seventeen.

Two real defects the tests caught before anything shipped:

1. **Carbon silently overflows an impossible date.** `createFromFormat('Y-m-d',
   '2026-13-45')` does not throw — it returns 14 February 2027. The tool now
   round-trips the parsed date back to a string and compares, because parsing
   succeeding proves nothing, and an invented deadline in a sixteen-year-old's
   plan is worse than no deadline: they will believe it.
2. The first draft of the school-year test asserted the wrong thing (`is_now`
   rather than `starts_on`), which would have passed while the year boundary
   was broken. Rewritten to pin the boundary itself.

Probes: nine mutations, each failing only its own test — rolling the school
year over in January, inventing an end date for life after school, making a
passage tickable, adding a count to the payload, chasing a milestone that was
set down on purpose, accepting an impossible date, letting the coach write a
passage, dropping the ownership check on the move route, and misfiling a
milestone's section.

3.1's claim held: **registering the `plan` route was the whole of making the
place appear.** The navigation test that asserted `plan` was absent now asserts
it is present, and no component was edited.

### 3.3 — The locker — **DONE 2026-09-16**

Where what the student made is kept. `Artifact`, `App\Support\Locker`,
`/locker`.

**The locker stores; it does not produce.** There is a `GetLockerTool` and
there is deliberately no writing counterpart — a test walks the coach's tool
list and fails if any tool other than that one has "Locker" in its name. A
locker the coach can fill is the coach's work with the student's name on it,
and "the tool never does the student's work for them" is the line the whole
product is built on. The read tool hands back titles and dates, never
contents, and its guidance says *do not offer to rewrite it*.

**Files are private, and that is the same defect as the results hole.** This
product has already shipped one surface where a minor's material was readable
by anyone holding a UUID. An uploaded essay is the same category of thing, so:
nothing goes to `public/`, the file returns only through `locker.download`
behind an owner check, and it comes back as `attachment` with
`X-Content-Type-Options: nosniff` and a generic content type — a document
containing markup cannot execute as a page inside this origin.

**An allowlist of types, not a denylist of extensions.** A denylist is a guess
about what is dangerous, and the guess is wrong the first time somebody
invents a format. SVG and HTML are both documents that execute; neither is on
the list. The browser's filename never reaches the filesystem — it is
attacker-controlled text, kept in a column where it is only ever printed.

**An artifact is a file or a link, exactly one.** No column can express "one of
these two", so the model refuses the save rather than silently correcting:
neither is an empty row the student will click on, both is two artifacts
wearing one title. `disk` is stored per row rather than read from config at
read time, so a file written in June does not become unreachable because
`FILESYSTEM_DISK` changed in September.

**The student may take their own work out, and only they may** — and the file
goes with the row. This is not the brain. The brain is what they *said*, and
the promise there is that it is never destroyed; this is a draft they thought
better of, and refusing to let a teenager delete one is the creepy reading of
that promise rather than the faithful one.

No locker route takes another person as a parameter, asserted against the
route table with a vacuity guard. The counsellor flag on an organisation
governs the **portrait** and nothing else.

Probes: ten mutations, each failing only its own test — allowing both a file
and a link, dropping either owner check, serving the file inline as its own
type, using the browser's filename as the path, allowing SVG, removing the
size limit, orphaning the file on delete, leaking contents through the coach
tool, and unregistering the locker route.

With this, **all five places named in the vision are built**, and
`StudentPlacesTest` flipped from asserting the unbuilt ones are absent to
asserting all five are present — the router answered, and `PlaceNav.tsx` was
never edited.

### 3.4 — The parent surface — **DONE 2026-09-16**

What a family sees, and the line it does not cross. `/family/{token}`,
`FamilyUpdateMail`, `family:send-updates`.

**`ParentVisibility` existed since 1.6 and was wired to nothing.** A boundary
class that nothing calls is not a boundary; it is a comment with a namespace.
3.4 is what makes it load-bearing: the page, the email and the command all
read `ParentVisibility::summaryFor()` and assemble nothing of their own. A
second place that decides what a parent may see is the second place that
leaks.

**Milestones cross; `why` does not.** The vision's phrase is "progress and
milestones", and 3.2 made milestones real — so titles, kinds, dates and
statuses go home. `why` stays, because it is the coach writing in the
student's terms about what this would change for them, and that is the
coaching conversation compressed into one sentence.

**Addressed by the consent token, not by a student.** A parent-facing URL that
takes a user id is one guess away from being everybody's report. The token is
the permission, so the token is the address — and revoking consent closes this
door with the same movement that closes the coach. A test walks the route
table and fails if any `family` route accepts `user` or `student`.

**The email ends in one thing to do, not a window to look through.** A parent
handed a transcript reads; a parent handed a question talks to their kid.
`suggested_conversation` is the whole call to action, and it is computed from
the live action or the open gap's closing move — never asked of a model.

**Monthly, and consent checked at send time.** A weekly progress email becomes
a report card, and a student starts working for the email rather than for
themselves. Consent is re-read as the command runs rather than baked into a
list, because a mailing list is a copy of a permission and copies go stale. A
student who has turned 18 since consent was given gets no letter at all — not
even one explaining why it is empty.

The load-bearing test asserts against **the serialised payload and the
rendered email body**, not the screen: distinctive strings are planted in the
student's reflection, a gap's evidence, a brain entry and a milestone's `why`,
and both surfaces are searched for them, plus every key in
`ParentVisibility::FORBIDDEN_KEYS` at any depth.

Probes: six mutations — leaking the reflection, letting `why` through, opening
the page without granted consent, ignoring consent status in the command,
mailing about an adult, and taking a student in the route.

### 3.5 — The cohort surface — **DONE 2026-09-16**

What a counsellor, pastor or programme director sees. `CohortSignal`,
`App\Support\CohortView`, `/org/{slug}/cohort`.

**The page answers "who needs a person this week", not "who is doing well".**
This is the surface where the product is most likely to betray itself: a
school dashboard naturally wants to rank students by readiness, and
`ReadinessLevel::rank()` already carries the sentence that has to hold —
ordering exists so a history can say "this changed", not so anyone can be
ranked against anyone else. So readiness does not appear on this page at all,
and a test asserts the payload carries no `readiness` key and no student
carries a `score`, `percent`, `rate`, `rank`, `level`, `progress` or
`position`.

**Every student lands in exactly one bucket, and every bucket has a move.** A
roster where one name appears under four headings is a list nobody reads, and
the first matching signal is the binding constraint anyway: waiting on a
parent beats not started beats blocked beats stalled beats working. Each
`CohortSignal` carries a `move()` addressed to the adult reading it — a flag
with no move attached is a way of feeling informed, which is not a way of
helping. "Working" sits last and carries no detail, because that is the right
amount of information about a student who is fine.

**Staff see the kind of gap, never the sentence it came from.** Only the two
types a school is positioned to remove (`Access`, `Finances`) raise the flag;
`FutureOutlook` does not, because a counsellor cannot fix a teenager's belief
about their own future by being told about it, and surfacing it turns a
private admission into a file note. The gap's `summary` stays behind too — it
is the engine describing a teenager to an adult.

**The boundary list is `ParentVisibility::FORBIDDEN_KEYS`, reused rather than
copied.** Staff are closer to a student than a parent in some ways and further
in others, but the line sits in the same place, and a second copy of a privacy
rule is the copy that drifts.

**Stalled is two weeks, pinned with literal dates.** The first version of the
test dated its fixtures off `STALLED_AFTER_DAYS ± 1`, so widening the window
to a whole term still passed — the test was asserting the code agreed with
itself. The probe caught it.

Seats, used and pending against `memberLimit()`, are on the same page: roster
management belongs where you notice you need it.

Probes: eleven mutations — listing a student twice, putting "working" first,
dropping the consent question, treating every gap type as the school's
business, widening the stalled window to a term, sending the gap's summary,
attaching a readiness standing to each name, stripping a move, spilling past
the organisation, and unregistering the route.

**Closed as debt:** `every_layout_takes_its_colour_from_the_tokens` now walks
`resources/js/Layouts/*.tsx` and fails on any raw palette utility or hex.
`OrgLayout` and `AdminLayout` were recorded as being outside the token system;
they were in fact already clean, so the note was stale and the test now pins
it rather than leaving the next person to re-read three files.

### 3.7 — The cohort invitation entry path — **DONE 2026-09-16**

How a student invited by their school actually gets in. `CohortInvitation`,
`InvitationController`, `/invitations/{token}`.

**The link in every invitation email was dead.**
`OrganizationInvitationNotification` pointed at `/invitations/{token}/accept`,
which existed only under `/api/v1` — no browser request ever reached it. The
entry path did not exist at all; 3.7 is building it, and the notification now
points at a page, which is the right thing anyway: somebody with no account
needs to be told what they are joining before they are asked to join it.

**Outside `auth`, for the obvious reason.** A login wall on an invitation
means the link only works for the people who did not need it. A signed-out
visitor reads the same page and is sent to registration with the token in the
session — not in a hidden form field, because a hidden input a student can
read in the page source is a hidden input a student can change, and the
invitation is the permission.

**Every condition is checked when the link is used, not when it was written.**
An invitation is a copy of a permission and copies go stale: it was created
when the roster had room, the token had not expired and nobody had used it.
None of that is still guaranteed by the time a fifteen-year-old opens the
email. Outstanding invitations count against the roster alongside members,
because counting only members lets a school hand out fifty links for twenty
seats and find out one disappointed student at a time.

**The refusals are separate sentences.** "Something went wrong" sends a student
to their counsellor with nothing to say; "Grace Academy has no seats left"
sends them with the sentence that fixes it.

**Accepting lands on `/next`, never a dashboard.** The point of an entry path
is that it drops somebody into the sequence. It goes there even when the
sequence is already over, so a freshman is told the portrait is theirs to keep
rather than being left on a home screen to work it out.

The page names the account that would join, rather than quietly enrolling
whoever is signed in on a shared library machine. The invitation carries the
role it was written with and nothing upgrades it — a test accepts as a student
and then asserts the staff surfaces are still closed.

Probes: twelve mutations, and **two did not bite on the first run, both the
test's fault**:

- the email assertion used `assertStringContainsString('/invitations/{token}')`,
  and the dead URL `/invitations/{token}/accept` *contains* that string — the
  check passed on the broken link. It asserts the exact href now, and that
  `/accept` is absent.
- "registering without an invitation joins nothing" created no invitation at
  all, so code grabbing whatever invitation was lying around would have found
  nothing and looked correct. An invitation for somebody else is now
  outstanding while that test runs.

### 3.6 — The nudge seam — **STUBBED 2026-09-16** (SMS deferred)

`App\Support\Nudges\{Nudge, NudgeChannel, SilentChannel, NudgeDispatcher}`,
`config('vocation.nudges')`.

**The deferred decision was the carrier, not the rules.** Which provider
carries an invitation is a cost-and-contract question. Whether we may contact
a particular student, when, and with what in the message are product questions
and they were already settled. Writing them down now means a future driver is
provider glue rather than a fresh argument about whether to text a
fifteen-year-old at eleven at night.

**The default is silence, and an unknown channel name is also silence.** A
stub that goes live because somebody set an environment variable is the
dangerous kind, so the fallback is not "the first driver registered" but
"nothing". A class named in config that is not a `NudgeChannel` is refused
rather than instantiated — a misconfiguration reaching `deliver()` would be a
fatal error in a scheduled job, at night, on somebody else's machine.

**A channel carries a sentence; it never decides when to speak.** The contract
is two methods and has nowhere to put a schedule. Two things deciding when to
contact a teenager is how a product starts nagging, so cadence stays in 2.4's
`BrainstormSchedule` and the words stay the student's own.

**This is the caller 2.4 was written for.** `BrainstormSchedule::declined()` —
the half of the rule that doubles the interval when an invitation is ignored —
**had no caller anywhere in the application** until the dispatcher existed. So
"an ignored invitation means the rhythm is wrong" had never had a chance to
happen. The dispatcher records every send through it, provisionally: turning
up calls `attended()`, which zeroes the decline count and resets the cadence.

Probes: eight mutations — falling through to the first registered driver,
instantiating anything named, making silence reachable, dropping the
entitlement gate, manufacturing an invitation the schedule declined to give,
sending to an unreachable student, not recording the send, and removing
`canReach` from the contract.

| # | Work |
|---|---|
| ~~3.1~~ | ~~The five places as first-class navigation~~ — **DONE**; locker and plan appear when 3.3 and 3.2 register a route |
| ~~3.2~~ | ~~**The plan** — life in sections, four years at a glance, milestones living *inside* it~~ — **DONE** |
| ~~3.3~~ | ~~The locker — resumes, essays, portfolio artifacts~~ — **DONE** |
| ~~3.4~~ | ~~Parent: progress + milestones only, monthly email with a specific CTA~~ — **DONE** |
| ~~3.5~~ | ~~Admin/pastor: cohort readiness, stalled-student flags, outcome reporting, roster/seat management~~ — **DONE** |
| 3.6 | **DEFERRED 2026-09-16 by the founder, seam stubbed.** SMS is not being built, and the provider question is closed rather than pending — the likely first carrier is a **push notification or an iOS Live Activity** through the Expo app, not a text message. What *is* built is `App\Support\Nudges`: the `NudgeChannel` contract, a `SilentChannel` default and a `NudgeDispatcher` holding every rule that has to be true before a student's phone makes a noise. Adding a carrier is implementing two methods and naming the class in `config/vocation.php`. 9 tests, eight probes. |
| ~~3.7~~ | ~~Cohort invitation entry path~~ — **DONE** |

---

## Phase 4 — Pathway layers
*Deliberately last. Each is a product in its own right.*

### 4.1 — Conversation prompts — **DONE 2026-09-16**

`App\Support\ConversationPrompts`, consumed by `ParentVisibility` and
`CohortView`.

**The highest-stakes copy in the product, and the lowest-tech.** A sentence
about a specific teenager, sent monthly to their parent, read by nobody here
first, is the single worst place to put a language model. So this is a fixed
catalogue — written once, reviewed once — and the tests are what make
"reviewed" mean something after the review is over: **every line in the file
is run through `RedTeamLint` and the suite fails if any of them would be
refused as a narrative.** If "God has called you to" is not allowed inside a
student's portrait, it cannot be acceptable in the email home.

**A prompt is a move, not a description.** Every line has to start with
something the adult can do, and a test enforces it. "They seem disengaged this
month" is a verdict handed to somebody with no way to act on it. Two lines
carried over from 3.5 failed that test on the first run and the *copy* was
fixed rather than the rule — `CohortSignal::NeedsConsent` now opens "Call a
parent" instead of explaining the situation first.

**A prompt may only interpolate what a parent already sees.** `:name` and
`:action` are the entire placeholder vocabulary, asserted by an allowlist —
the first is filled with the first name only, the second with the live
action's title, and both already ship on the parent payload in their own
right. Naming the step is the point: a parent told their kid is going to ask
an aunt about the ICU can offer to drive them, while a parent told only that
"something is in progress" can offer nothing. A catalogue able to reach a
reflection, a gap's evidence or a brain entry would be the privacy boundary
broken in the one payload that leaves by email, so the allowlist is the
boundary rather than good intentions.

**Range, because repetition is how the mechanism dies.** A parent who reads
the identical sentence every month stops opening the email; a counsellor whose
roster repeats one line thirty times is reading wallpaper. Selection is
seeded on student plus month for parents and on signal plus month for cohort
headings — so it varies between months and between families, holds still
within a month, and the page and the email always agree. `GapType::closingMove()`
stays first in every gap bank, so the catalogue extends it rather than quietly
replacing it.

Probes: twelve mutations. One did not bite — `the_sentence_holds_still_within_a_month`
compared two readings against a four-entry bank, so a sentence that re-rolled
on every read coincided one time in four and the test passed by luck. It reads
sixteen times now.


### 4.3 — Vetted jobs and apprenticeships *(done 2026-09-16)*

Safeguarding columns on `job_listings`, `JobVetting`, `StudentJobs`,
`JobApplicationGuide`, `jobs:vet`, `/plan/work`, 22 tests, 24 probes.

**This is the layer where being wrong has a different shape.** Everywhere else
in the product a defect gives a student a bad sentence. Here it sends a
sixteen-year-old to a stranger's address. Every default therefore fails closed,
and each one has a probe.

**The recorded debt is paid.** `job_listings` was built in the legacy adult
product and carried no age requirement, no supervision flag and no vetting
state — the table was correct for adults who chose to open a job board, and
wrong the moment minors reached it. It now has `minimum_age`, `supervised`,
`work_kind`, `vetting_status`, `vetting_findings`, `vetted_at`.

**Pending means invisible, not "shown with a caution".** A warned-about listing
is a listing somebody clicks. `VettingStatus::Pending` is the column default
and only `Passed` is showable, which a test pins at the migration as well as
in the query — a default flipped to `passed` would put everything ingested
overnight in front of minors.

**Silence about age is not permission.** A listing that states no minimum age
is excluded for anyone under eighteen, because a posting that never mentions
age was not written with a minor in mind. An unknown birthdate is treated as
the youngest a high schooler could plausibly be, matching `AccessPolicy`'s
existing asymmetry: hiding work from somebody who could have done it costs an
opportunity, and the other error sends a fourteen-year-old to a night shift.
Both guards are deliberately redundant — SQL already excludes nulls from
`minimum_age <= 16` — and the probe only bites when *both* are removed, which
is the point of keeping the explicit one.

**Vetting is a lint, not a model.** Built like `RedTeamLint` and for the same
reason, plus a sharper one: a model asked "is this a scam?" can be written *at*
by the scammer, who controls the entire input. Blocking rules cover the
documented shapes that target teenagers — advance-fee fraud, money-mule
reshipping, identity harvesting, chat-app-only interviews, and the isolation
language safeguarding guidance treats as a trafficking indicator. Structural
rules block what is *missing*: an unnamed employer or one with no link cannot
be checked by us, a counsellor or a parent, and no phrasing can be written
around a fact about the record. Hype is a **warning** — a real job described
badly is still a real job, and the asymmetry only runs toward blocking what is
dangerous, never toward blocking what is tacky.

**Our reasoning is not shipped.** Findings never reach the student payload. A
page of caveats teaches students to read past caveats, and a rejected listing
never reaches the page at all. A listing this student may not see 404s rather
than 403s: naming a posting we rejected as fraudulent hands a curious teenager
the thing to search for.

**Two steps exist because the applicant is a minor.** The work permit — most
states require an employment certificate under eighteen, issued by the school,
and a student who learns this on their first day has already lost the job. And
telling somebody where the interview is, which the adult walkthrough keeps
too, because that is not a thing you stop doing at eighteen.

**The legacy `/jobs` board now refuses minors** and redirects them to
`/plan/work`. Retrofitting the rules onto it would have put a second
implementation in the codebase, and the second implementation is the one that
drifts.

**The schedule is load-bearing in a way it usually is not.** Because unvetted
means invisible, a vetting pass that never runs does not degrade the student's
page — it empties it, silently and permanently, while ingestion keeps filling
the table. The failure would read as "no jobs this week". It is asserted, and
the first version of that assertion **did not bite**: `str_contains($all,
'jobs:vet')` is satisfied by `jobs:vet --all` alone, so deleting the hourly
pass left the test green. The same substring trap that hid a dead invitation
URL in 3.7.

**Apprenticeships are a first-class `WorkKind`, in the same list as the jobs**,
by test. A separate tab is an argument about which is the real option, and the
copy says outright that it is not the lesser path.

---

### 4.2 — College explorer *(done 2026-09-16)*

`College` + `CollegeInterest`, three computation classes (`CollegeStanding`,
`CollegeCost`, `CollegeApplication`), `CollegeExplorer`, a `colleges:import`
command, `/plan/colleges`, 20 tests, 18 probes.

**Two founding documents collided and the collision is resolved in code.** The
vision asks to show "their real chances of getting in based on GPA, finances,
and more"; DESIGN.md forbids percentages, dials and match scores outright.
Every competitor renders this as a number. `AdmissionStanding` is a word — a
reach, in range, likely — and each word describes *the list*, never the person.
A test asserts no standing copy anywhere contains a percentage, "odds",
"chance of", "score" or "ranked", and that the payload ships no key by those
names either.

**Money was removed from the admissions answer entirely.** The vision names
GPA and finances in one breath and the obvious implementation folds them into
one figure. Finances decide what a place *costs* (`CollegeCost`); they never
decide whether somebody is good enough to be admitted. A student who learns
from our interface that being poor lowers their chances has been taught
something both false and corrosive. A probe that makes a low-income band read
as a reach is one of the eighteen.

**The sticker price is demoted.** It is the single most misleading number in
American education — the number that stops students applying, and almost never
the number anyone pays. The headline is the average net price for the
student's own federal income bracket, looked up and never interpolated, which
is why `IncomeBand` uses the five IPEDS brackets rather than brackets of ours.
A bracket the school suppressed returns **null, never zero**: a $0 net price is
the most consequential wrong number this product could print, and it has its
own probe at both the cost class and the importer.

**No seeder of hand-typed figures.** A net price typed from memory about a real
institution is a fabricated record that looks exactly as trustworthy as a real
one, and a seventeen-year-old makes a financial decision on it. Everything in
`colleges` arrives through `colleges:import`, mapped to the federal College
Scorecard's own column names so the mapping can be checked against the data
dictionary rather than trusted. GPA ranges are not in the Scorecard — they are
optional columns, and a missing range surfaces as "not enough to say" rather
than being guessed from the acceptance rate.

**"The tool will not apply for them"** is the vision's sentence and it is now a
test. Every walkthrough step carries `yours: true`, there is no route matching
`colleges/{college}/apply` or `/submit`, and every line of step copy is linted
for an offer to act. That lint exists because the first version of the test
**did not bite**: it asserted "You press the button" was present, which stayed
true with "We can file this for you." prepended to the same step. Asserting the
right sentence is there never stops a wrong one being added beside it.

**Why a school appears is a join, not a judgement.** The literal reading of
"filtering colleges in real time based on everything the student is saying" is
to hand a model the transcript. What the student said has already become
verified signals and scored categories, so the filter is
portrait → category → pivot → named programme, and the result ships the
programme name as its reason. A student who asks why a school is in front of
them gets "they run Respiratory Therapy" rather than a shrug. The student can
always overrule the portrait with an explicit category, and a probe proves it.

**It is not a sixth place.** The vision names five and 3.1 made the navigation
derive from the router, so the explorer lives at `/plan/colleges` — college
choice is a section of the plan, which is what the vision calls it. A test pins
both the URI and the fact that `StudentPlace` still has exactly five cases.

**Community colleges and trade schools are in the same list as the
universities**, by test. Making a student leave the college explorer to find a
welding certificate teaches them theirs is the lesser path.

**The family is pulled in.** `ConversationPrompts::COLLEGE` is a fifth bank —
the only one addressed to the student rather than to an adult, because the
student has to start the conversation and a tool that starts it for them has
done the first hard thing about leaving home on their behalf. The placeholder
allowlist grew to `:name`, `:action`, `:college`, each admitted for a stated
reason; everything else still fails.

**Two bugs the tests caught.** `College::where()` as a "city, state" helper
shadowed Eloquent's query builder on every instance. And the pivot tables both
carried a uuid primary key that `attach()` does not fill — fixed by dropping
the surrogate key from the pure pivot and making `CollegeInterest` a `Pivot`
model with `HasUuids`, rather than by asking every caller to remember.

### 4.4 — Being in college *(done 2026-09-16)*

`/plan/college-life`. `Enrollment`, `Syllabus`, `CollegeResource`,
`CampusResourceKind`, `SyllabusParser`, `SyllabusImporter`, `ParseSyllabusJob`,
`CampusGuide`, `IcsFeed`, `CalendarFeedController`. 21 tests, 20 probes.

The last layer the vision names, and the one that decides whether any of the
rest mattered: a student who enrols and leaves in the first year has the debt
and not the degree.

**Coursework is milestones, not a second list.** The plan already is "dated
outcomes in sections", so a parallel assignment table would be a second plan
competing with the first and the student would have to choose which one to
believe. A syllabus deadline becomes a `Milestone` of kind `academic` carrying
`syllabus_id`, in the plan they already read.

**The model points; the code computes.** This is the verbatim-provenance
discipline from 0.1 applied to a calendar. `SyllabusParser` is asked for two
things only — the assignment title and `due_text`, *the date exactly as the
syllabus prints it* — and both must appear in the stored `source_text` or the
row is discarded by substring check. The schema deliberately has **no ISO date
field**: an invented `2026-10-14` is a plausible number nothing can check,
while "Oct 14" either appears in the document or does not. The calendar date is
then parsed in PHP from that span, with a bare month and day read into the
term's year and rolled forward when it falls before the term began. A span we
cannot read ("Week 6") is dropped, never guessed.

**Nothing is discarded silently.** Rejects land in `syllabi.discarded` with a
reason and the count is shown to the student, because a parser that handles a
format badly must be visible to the person it failed — a calendar that quietly
loses a midterm is worse than no calendar.

**Re-parsing is safe.** `firstOrCreate` on (syllabus, title): re-reading a
syllabus never duplicates coursework and never resets what a student has
already marked done.

**No invented campus offices.** `CampusResourceKind` carries what is true of
every US campus — what the place is, why using it is ordinary, and *the line to
say when you walk in*, because "I don't know what to say" is the real obstacle.
Institution-specific links are an upgrade from imported `CollegeResource` rows,
never typed from memory. Same refusal as the college net prices in 4.2.

**The calendar is a mirror, never a notifier.** The ICS feed carries the whole
plan rather than only coursework — a scholarship deadline and a problem set
compete for the same Thursday evening — and emits no `VALARM`. A tool that puts
itself on somebody's lock screen at 8am has decided on their behalf that it may
interrupt them; the nudge seam defaults to silence for the same reason. Only
titles and dates cross: no readiness, no confidence, no category, because a
calendar entry is read by whoever glances at a shared screen. Token-addressed
and outside `auth` (a calendar client cannot hold a session), rotatable by the
student, 404 on a bad token.

**Enrollment is asked for, not inferred**, and `college_name` is required while
`college_id` is not — a student at a college nobody has imported is exactly the
student this layer exists for.

⚠️ **A probe that did not bite.** "An unknown token falls back to a student"
passed against an empty database: `User::query()->first()` returns null when
there is no user. An empty fixture cannot tell you a lookup is scoped. The test
now creates a student *with* a token and a milestone first. Third instance of
the same family as the `jobs:vet --all` substring trap.

---

| # | Work |
|---|---|
| ~~4.1~~ | ~~Conversation prompts for parents and mentors~~ — **DONE 2026-09-16** |
| ~~4.2~~ | ~~College explorer~~ — **DONE 2026-09-16** |
| ~~4.3~~ | ~~Vetted jobs and apprenticeships~~ — **DONE 2026-09-16** |
| ~~4.4~~ | ~~In-college layer: campus mapping, syllabus upload, assignment mapping, calendar sync~~ — **DONE 2026-09-16** |

---

## Phase 5 — Validation
*Runs continuously from Phase 1, formalized here.*

### 5.1 — Clarity before and after *(done 2026-09-16)*

`ClarityMoment`, `ClarityStanding`, `ClarityShift`, `ClarityCheck`,
`ClarityMovement`, `ClarityCheckController`, `ClarityCheck.tsx`. 9 tests.

The blueprint calls this "one of the clearest indicators of usefulness", and it
is the only measure in the product that requires asking the student something
twice.

**The same question at both ends.** `ClarityMoment::prompt()` returns an
identical string for `Before` and `After` — a movement between two differently
worded questions measures the wording. The four standings are an enum with an
internal `rank()`; the rank is never rendered, and there is no numeric clarity
score anywhere.

**A reading has to be taken at the right end.** A baseline offered after the
student has answered questions is not a baseline, and a second reading taken
before the portrait exists measures nothing. `refuseAReadingTakenAtTheWrongEnd`
rejects both with a validation error rather than quietly recording a number
that would then be averaged into a claim about the product.

**Append-only, and skippable.** `ClarityCheck` throws on update and delete —
what somebody said before is not something we may revise afterwards — and
`firstOrCreate` makes a second submission at the same moment a no-op rather
than a correction. The gate before question one can be skipped; a measurement
instrument that blocks the thing it is measuring has changed the thing.

**`Unmeasured` is a fourth outcome, never folded into "unchanged".** A missing
second reading is the commonest case (most people never come back), and
counting it as "no change" would let attrition silently read as failure.

**Nothing reaches the student.** The shift is ours, not theirs: telling a
student "you got less clear" is a verdict on them delivered by a tool that
caused it.

### 5.2 — The validation read-out *(done 2026-09-16)*

`ValidationReadout`, `AdminValidationController`, `Admin/Validation.tsx`,
route `admin.validation`. 8 tests.

Ten measures in one place: completion, clarity improved, sounds-like-me,
useful, clear-next-step, both dissent counts, next-step-taken, paid conversion,
engine failure.

⚠️ **The one deliberate exception to "no percentages".** Every other rate in
this product is forbidden, because a number attached to a student becomes a
verdict on them. This page is about *us*: whether the engine works. The
invariant is intact, and the exception is narrow enough to name here.

**No rate below n=30.** Under `MINIMUM_SAMPLE` the rate is `null` and the page
carries `why_no_rate` instead. Four of seven is not fifty-seven percent, it is
four of seven, and a founder reading 57% on a pilot of seven makes decisions
the data cannot support.

**Denominators are the honest ones, not the flattering ones.** Next-step-taken
is measured against students who were *given* a step, not against everybody.
Paid conversion is measured against students who reached a portrait, because
someone who never saw one was never offered anything to buy.

**Dissent is counted in its own right**, not derived as `1 − satisfied`, and
carries `read_these => true`: a student who says the portrait is not them has
written the most useful sentence in the dataset, and a rate hides it.

**Segmentation is an enforced allowlist.** `SEGMENTS` is income band, grade
level and organization — three things students told us — and `segmentKeys()`
throws for anything else. The blueprint asks for bias testing across culture,
gender and neurodiversity; we do not hold those and will not infer them from
names, schools or postcodes. See 5.4 for what we do instead.

### 5.3 — Human review *(done 2026-09-16)*

`ReviewDimension` (9), `ReviewStanding`, `PortraitReview`, `ReviewQueue`,
`AdminPortraitReviewController`, `Admin/PortraitReview.tsx`. 9 tests.

The blueprint's nine review dimensions, one enum, one question each, asserted
to end in a question mark and never to be phrased so that agreeing is the bad
answer.

**The queue hands out the next portrait; the reviewer does not choose.** There
is no `GET /admin/reviews/{profile}` route and a test asserts its absence. A
reviewer who picks reviews the portraits that look interesting, and the ones
that look boring are where an engine fails quietly.

**Objected-to portraits come first**, then oldest-unread, excluding what this
reviewer has already read — but not what *other* reviewers have read, because
two independent readings of the same portrait is the point of having reviewers.

**The student's own answers ship with the portrait.** Judging "does this sound
like them" without them is judging whether the prose is good.

**`coverage()` reports failures per dimension and refuses to total them.** A
portrait that is accurate and unsafe is not 8/9; the composite score is the
thing that makes the unsafe one shippable.

**Reviews are append-only and stamp `prompt_version`,** so a review can be
attributed to the engine that produced the portrait rather than to the engine
we have now.

### 5.4 — Bias invariance *(done 2026-09-16)*

`WordCount` (new), `BiasInvarianceTest`. 5 tests.

We do not hold culture, gender or neurodiversity and will not infer them, which
rules out slicing outcomes by group. **Hold the meaning fixed and vary the
expression, then assert the engine does not move.** Polish, spelling, register
and script are the observable proxies for schooling, class and culture; an
engine blind to them cannot discriminate through that route. This is a stronger
claim than a demographic slice, because it is checked on every run rather than
after enough students have been harmed to be statistically visible.

⚠️ **The pass found a real defect on its first run.** Every word count in the
product used `str_word_count()`, which is byte-oriented and locale-dependent.
On a C-locale machine a Chinese, Japanese or Thai answer counts as **zero
words**, so such a student was permanently capped at "insufficient evidence"
and it looked exactly like somebody who typed nothing; on a machine whose
locale treats high bytes as letters the same answer counts **one word per UTF-8
byte**, three times too generous. Either way a student's ceiling depended on
the server they landed on. Replaced everywhere by `App\Support\WordCount::of()`
— `ConfidenceCalculator` (×2), `BrainCapture`, `ResponseQuality`.

The lesson is the reason to run the pass at all: **the engine was fair in every
rule anybody wrote, and unfair in the utility function underneath them.** No
test, prompt or review dimension could have seen it, because they all agreed
the student had written nothing.

⚠️ **Two probes that did not bite, and what fixed them.** "Revert to
`str_word_count`" passed, because on this machine's locale it returns a number
greater than zero — an `assertGreaterThan(0)` is satisfied by a byte count. The
test now asserts the exact unit count and, separately, that the same amount of
writing buys the same ceiling in either script. Likewise "count only long
words" passed because the polished and plain fixtures happened to be matched on
word length too; the pair is now matched on meaning and word count and
deliberately *unmatched* on register, with a second pair sitting on a band
boundary. **Fixtures balanced on the very thing the probe mutates cannot
falsify anything** — the fourth member of the empty-fixture family.

---

| # | Work |
|---|---|
| ~~5.1~~ | ~~Clarity measured before and after~~ — **DONE 2026-09-16** |
| ~~5.2~~ | ~~Completion, accuracy, usefulness, next-step rate, conversion, dissent~~ — **DONE 2026-09-16** |
| ~~5.3~~ | ~~Human review across the nine dimensions~~ — **DONE 2026-09-16** |
| ~~5.4~~ | ~~Bias testing~~ — **DONE 2026-09-16** (as invariance) |
| 5.5 | Pilot at 50–100 users; larger validation at 500+ — **needs users, not code** |

---

### Safety debt — crisis escalation *(done 2026-09-16)*

`CrisisStanding`, `CrisisCheck`, `SupportBlock.tsx`, wired into
`PathwayCoachController::message()` and API `saveAnswer()`. 13 tests, 12
probes.

Carried since Phase 1 and paid here because it is the last thing in the product
that was enforced by asking nicely. "Crisis content escalates to human support
before vocational interpretation" is a non-negotiable, and it lived only in the
coach's prompt — which means it lived nowhere. A prompt is a request.

**Why not a model.** The usual reason (deterministic, free, cannot be talked
out of it) plus one specific to this: a model asked "is this a crisis?" is
being asked for a clinical judgement, and it will sometimes answer no in order
to be helpful about the question it was actually asked.

**Order is the feature.** The check runs before the model *and before the
entitlement gate*. A freshman, an unconsented junior and a lapsed subscriber
all get the phone number; the one thing here that is not a feature is not
behind the paywall either.

**Two cases, never a severity scale.** `CrisisStanding` is `None` or
`Escalate`. A graded standing would be a diagnosis, which the safety invariant
forbids, and it would invite a graded response — a little coaching for a little
distress.

**Deliberately over-eager, with the false positives named in tests.** "I want
to work in suicide prevention" escalates, and that costs a student one reply
about careers. The other error is not comparable. Past-tense and third-party
disclosures escalate too: a student telling us what their friend said is a
student who needs to know where to send them. Matching is whole-word after
accent folding, and **every locale's phrase list is read regardless of the
student's locale** — a bilingual student switches language for exactly the
things that are hardest to say, and reading the wrong list because a dropdown
said "English" would be the worst possible moment to be tidy about it.

**Escalation is not a report.** Nobody is notified, and a test asserts it.
Parents never see coaching conversations, and a tool that quietly told a school
what a sixteen-year-old typed would be the last thing any of them typed
honestly. Their words *are* still captured to the brain — that sentence is the
one they would least want to have to write twice.

**The message is fixed text, never generated,** asserted to pass the red-team
lint, to name an actual way to reach a person, and to contain no clinical
language. Resources are US services because the product is US; adding a country
means adding that country's verified numbers, never a guess. A wrong crisis
number is worse than no number.

### Safety debt — the lint learns the other two languages *(done 2026-09-16)*

`CrisisCheck` covered `en-US`, `es-419` and `pt-BR` from the day it shipped.
`RedTeamLint` and `JobVetting` did not, which meant a Spanish narrative
containing *"Dios te ha llamado a ser enfermera"* passed the Layer 8 lint
clean, and a Portuguese money-mule posting published. A guardrail that only
reads English is not a guardrail for a product that speaks three languages —
it is a guardrail for the students who happen to answer in English.

**Both phrase lists now carry Spanish and Portuguese**, held in `*_ES_PT`
constants beside their English originals and spread into the same rule rows,
so a rule's identity (`divine_presumption`, `money_mule`) is one thing across
all three languages rather than three near-duplicate rules that could drift.

**Every phrase is stored unaccented and the haystack is folded before
matching.** The alternative — listing `número` and `numero`, `não` and `nao`,
`está` and `esta` — doubles the list and still loses to the next spelling
someone types on a phone keyboard. The fold is a `strtr` map that replaces
**one character with one character**, which is the property that makes the
rest work: a character offset in the folded text is the same offset in the
original, so `matchedText()` can locate the span in the fold and then return
it **as the model actually wrote it**, accents intact. A repair instruction
that quotes `nao ha duvida` back at a draft that says *"não há dúvida"* is a
repair instruction the model cannot act on.

`COVERED_LANGUAGES` is asserted against `ConversationLocale::supported()`, so
adding a fourth locale fails the lint's own test until its phrases are written.
A language the lint cannot read is never reported as clean.

**One phrase was deliberately left out.** *Transferencia bancaria* is how
payroll itself is described in Spanish; listing it as a money-mule signal
would have rejected ordinary legitimate postings. The money-mule list carries
`agente de transferencia de dinero` and `envia el dinero por western union`
instead — the shapes that describe moving *someone else's* money. A test
asserts an ordinary Spanish posting still publishes.

Ten mutations were run against the pass; each one is now caught by exactly one
test. Three did not bite on the first attempt and all three were the tests'
fault: one asserted "this narrative fails" where any of ten rules satisfied it
(now asserts the rule by name), one used copy with no accents in the matched
span (so the fold was a no-op), and one used a clean fixture that never
contained the word the over-broad mutation introduced.

**The golden corpus now speaks all three languages too**, which is what proved
the phrase lists were not finished. Four fixtures were added — a narrative that
must be refused and one allowed to ship, in Spanish and in Portuguese — because
a refusal case alone would be satisfied by a lint that simply refused every
language it could not read. `the_corpus_speaks_every_language_the_product_does`
iterates `ConversationLocale::supported()` and demands both halves per locale.

It found a real hole on its first run. The Spanish banlist had been translated
from the English phrase by phrase, so `this is who you are` became only `esto
es quien eres` — but Spanish forecloses with *lo que eres*, not *quien eres*,
and the natural way to write the banned sentence was the one spelling the list
did not hold. `esto es lo que eres`, `eso es lo que eres`, `eso es quien eres`,
`isso e o que voce e` and `e isso o que voce e` were added. **Translating a
banlist is not the same as writing one**: a rule can be present in every list
and still be unreachable in the language a student actually writes.

The fixtures also now carry a `locale`, applied to the assessment in both the
deterministic and the live harness — a Spanish assessment run without its
locale would have the engine answer a Spanish student in English, and the run
would prove nothing about either.

---

### The engine runs on a model we host *(done 2026-09-16)*

The golden corpus was only ever driven through a third party's model, which is
the wrong default for a product whose entire premise is that a student's own
words are the asset. `App\Ai\Concerns\RunsOnTheConfiguredEngine` lets one
setting — `VOCATION_ENGINE_PROVIDER` plus `VOCATION_ENGINE_MODEL` — repoint
every engine agent at once.

**A trait rather than a parameter, deliberately.** `Promptable` prefers a
`provider()`/`model()` *method* over the `#[Provider]`/`#[Model]` attributes,
so a trait reaches every call site. Threading `provider:` through each
`->prompt()` would have covered only the calls someone remembered, and a run
where two of five layers quietly stayed on the cloud proves nothing about
either arm. Two call sites *did* pass their own values and outranked the trait
— `AnalyzeAssessmentJob::resolveModel()` and `ConversationModelSelector` — and
both now honour the override. The selector reports the variant as
`engine_override` rather than as an arm, because a turn that ran on an
operator override is not a sample of the control or the treatment, and
counting it as one would show up later as a conclusion about a model that was
never asked.

The trait reads each agent's own attributes as its fallback, so the declared
default stays declared in one place. A provider set without a model **throws**,
naming the env var: Ollama answers an unknown model with a 404 that reads like
the local server being down, and that is a bad half-hour to hand someone.

**What the first local run found.** Prism's Ollama handler defaults
`num_predict` to 2048. This engine's largest phase emits seventeen scored
categories, so the model produced valid-looking JSON that simply stopped
mid-object; the parse returned nothing; and the failure surfaced three layers
away as *"Analysis missing required field: dimensions."* The engine also threw
the raw payload away, which made a bad structured response undiagnosable in
production as well as in a test. It is now logged — length, whether it parses,
head and tail — and the exception distinguishes an absent key from a present
but empty one, which are different defects with different fixes.

`num_predict`, `num_ctx` and the timeout all follow the override and are keyed
on the provider, so a cloud run's tight bounds are untouched. A locally hosted
model changes the latency budget by an order of magnitude — measured at ~23
tokens/second against `llama3.2:3b`.

**A fidelity failure that needed no fix.** On the same run the model filled
every `evidence` array with **our own question text** — *"Com o que as pessoas
consistentemente buscam minha ajuda?"* cited as though the student had said it.
Nothing broke: evidence is a reference into the signal index, so a citation
that is not one cannot resolve and the row degrades to `unevidenced`. That is
now pinned by a test rather than trusted, because it is the failure a weaker
model is most likely to produce and the property that makes one safe to point
at this engine at all.

**Still open — and it is a model choice, not code.** Measured against
`llama3.2:3b`:

| output ceiling | wall clock | result |
| --- | --- | --- |
| 2048 (Prism default) | ~120s | truncated mid-object, nothing parsed |
| 8192 | 603s | still nothing parsed |
| 32768 | abandoned past 50 min | had not converged |

Raising the ceiling makes it fail slower, not better: a 3B model under a
grammar this large generates toward the limit instead of closing the object.
A corpus run that *passes* locally needs a more capable local model than the
3B currently installed — `qwen2.5:7b-instruct` or `llama3.1:8b` are the
obvious candidates, and pulling one is a ~5GB decision about someone else's
disk rather than a code change.

The seam, the guardrails and the measurement are done; what remains is
choosing the model to point them at.

---

### Mobile token parity *(done 2026-09-16)*

`DESIGN.md` listed this as a known gap and predicted its consequence:
"`mobile/constants/theme.ts` carries its own values and will drift until
reconciled." It had, and not cosmetically — light `accent` was still `#A8A29E`,
the warm stone the document says indigo replaced; the dark canvas was
`#0F1216`, a cool blue-black the document explicitly rules out; and dark
`accent` was `#94A3B8`, **a second accent hue** where exactly one is allowed.
The mobile app was shipping a palette the design system forbids.

`DesignSystemTest` now parses `DESIGN.md` and asserts the mobile tokens against
it, the same way it already did for `app.css`. **A gap named in prose drifts; a
gap named in a test cannot** — which is also why the Known Gaps list itself was
rewritten: three of its seven entries had been closed for a while and were
still being read as outstanding.

### The answer has to come back in the student's language *(done 2026-09-16)*

The multilingual corpus set a `locale` on every fixture, the agents were handed
a `responseLocale`, and **nothing asserted the engine obeyed it**. That failure
is silent and total: a Spanish student receives a fluent, correct, entirely
English portrait — the red-team lint passes, the evidence standing is right,
every assertion in the live harness passes, and the run reports success.

`App\Support\NarrativeLanguage` answers the question deterministically and
without a model: function-word counts per language, plus a weighted `-ción` /
`-ção` ending, with accents folded one character to one character first so a
model that drops them is read as a typography failure rather than a language
one. It returns `null` on a tie or on nothing to go on — a confident wrong
answer would fail a live run for the wrong reason.

The live harness now asserts it as step 2b, right after the red-team check.

The tests were rewritten twice before they were worth anything. The first
version's fixtures were **over-determined**: the marker words and the suffix
weight each separately decided every sentence, so either mechanism could be
deleted and the suite stayed green. They are now split — one pair separable
only by the ending, one carrying no such ending at all — and the accent test
asserts the *accented* spelling, which is the only one that exercises the fold
at all. Same defect the `JobVetting` accent test had, found the same way.

### The live harness was running on an empty assessment *(found and fixed 2026-09-16)*

The worst defect found so far, and it was found by accident.

`tests/Live/EngineFixtureLiveTest.php` and `tests/Feature/EngineFixtureTest.php`
both wrote each fixture answer to **`answer_text`**. The column is
`response_text`, and `answer_text` is neither a column nor fillable, so Eloquent
discarded every answer without an error. For as long as the live harness has
existed it has been handing a real model **seven blank responses** and grading
what came back.

Nothing failed, because every assertion in the file tolerated nothing:

- assertion 1 loops over extracted signals, and zero signals means zero
  iterations — *"the model never invented a quote"* is trivially true of a model
  that quoted nothing;
- assertion 2, the red-team lint, passes on bland prose;
- assertions 3 and 4 are **ceilings** — don't overstate, don't mistake wanting
  for doing — and fire only on fixtures that expect little.

A suite made entirely of ceilings passes an engine that outputs silence. The
run that exposed this reported `signals: 0`, `unevidenced`,
`insufficient_evidence` on a fixture the corpus records as
`demonstrated`/`moderate`, took 398 seconds, and **passed with three
assertions**. The giveaway was the log: `signal_detection_started` and
`signal_detection_completed` in the same second, which no 7B model can do.

Three things changed:

1. Both harnesses write `response_text`, and the live harness asserts the
   assessment contains the assessment **before** it spends four hundred seconds
   on it (assertion 0).
2. `EngineFixtureTest` gained `what_the_student_wrote_survives_the_trip_into_the_database`,
   which fails on all eight fixtures if the column name is wrong again. The
   harness is part of the test.
3. The live harness gained a **floor** (assertion 5): where the corpus records
   `demonstrated` evidence, the engine has to find some. That is not a recorded
   value, it is the minimum a model must do to be worth running.

This also revises the earlier local-model measurements. `llama3.2:3b` was judged
on output produced from empty input; that judgement is withdrawn, not reversed —
it has not been re-measured.

### The citation vocabulary was never closed *(fixed 2026-09-16)*

With the harness finally feeding it a real assessment, `qwen2.5:7b-instruct`
extracted **seven signals in 93 seconds and every one of them survived the
verbatim check**. Layer 4 works on a 7B. Then the run failed on the new floor:
`unevidenced` standing on a case the corpus records as `demonstrated`.

The cause was ours, not the model's. `VocationalAnalysis` declared
`category_scores[].evidence` as a free array of strings, while the valid values
— `S1` through `Sn` for exactly the signals rendered into that prompt — are
known *before the model is called*. The prompt asked for them politely and
`DualTrack` discarded everything else in silence, which is the precise recipe
for a portrait where every layer worked and nothing is evidenced.

This is the governing guardrail of the whole engine — **never ask a model for a
value you can compute, and constrain every closed vocabulary with an enum** —
and the signal references are the most closed vocabulary in the system. It was
missed because the enum has to be computed per assessment: static schemas are
easy to close, dynamic vocabularies quietly stay open.

`VocationalAnalysis::signalReference()` now reads the references from
`DualTrack::index()` — the same function the resolver uses, so prompt, grammar
and resolver cannot drift — and declares them as an enum. Ollama compiles the
schema to a GBNF grammar, so an invented reference stops being *representable*
rather than being caught afterwards. With no signals it falls back to a plain
string, because an empty enum is not a schema and every citation is
unresolvable in that case anyway.

The same run, with the enum in force:

| | before | after |
|---|---|---|
| citations kept | none | `S1`, `S4` |
| evidence standing | `unevidenced` | `emerging` |
| assertions passed | 13, one failing | 12, all passing |
| wall clock | 440s | **347s** |

Constrained decoding is *faster*, not slower: there is less for the model to
explore. `DualTrack::uncitedReferences()` was added alongside — a pure,
tested function so the job can say out loud when citations are discarded
instead of dropping them mutely, which is what cost a seven-minute run its
diagnosis.

Closing that one exposed the other two, both the same shape and both silent:

| Field | Vocabulary | What it silently caused |
|---|---|---|
| `category_scores[].evidence` | `S1`…`Sn` for this assessment | citations discarded, `unevidenced` on a rich assessment |
| `category_scores[].category` | the 17 taxonomy names | a row that resolves to no category and is carried forward anyway |
| `ranking[].category` (disambiguation) | the competing pair only | `applyResolvedRanking` matches nothing and **the second interpretive pass evaporates** |

The last is the worst of the three: an entire interpretive layer can decline to
happen, with no error and no log line, because a name came back spelled
differently. `TaxonomyPrompt` gained `names()` and `namesFor()`, both reading
the taxonomy data file for the reason `slugs()` already did — a schema must
build without a seeded connection, and a category is defined in one place.

Except that it was not. Closing the vocabulary surfaced that
`VocationalCategorySeeder` holds its **own hand-written copy** of all seventeen
names, independent of `vocational_taxonomy.php`, while `TaxonomyPrompt`'s
docblock claims there is exactly one place a category is defined — true of
slugs, false of names. If those two drift, the grammar constrains the model to
answer with categories the database does not contain. Pinned by
`test_the_taxonomy_names_agree_with_the_seeded_categories` rather than
refactored: unifying them edits seeded production data, which does not belong
in the same change as a measurement.

The general rule, which is what actually went wrong: the guardrail is easy to
apply to a **static** schema and easy to miss on a vocabulary computed **per
request**. Every one of these three is computed from the prompt being built.
When a field's valid values depend on the prompt you just assembled, that is
the case that needs the enum most, and it is the case where nobody thinks to
look.

### The corpus, measured on a model we host *(2026-09-16)*

Five fixtures, `qwen2.5:7b-instruct` on Ollama, all three citation enums in
force, 2063 seconds total. **Four of five pass.**

| Fixture | Locale | Signals | Standing | Confidence | Wall clock |
|---|---|---|---|---|---|
| aspiration-only | en-US | 7 | `emerging` | insufficient_evidence | 449s |
| junior-diagnostic-curiosity | en-US | 8 | `emerging` | weak | 406s |
| portuguese-narrative-that-ships | pt-BR | 7 | `demonstrated` | insufficient_evidence | 416s |
| spanish-narrative-that-ships | es-419 | 6 | `emerging` | insufficient_evidence | 461s |
| thin-answers | en-US | 4 | `aspiration_only` | insufficient_evidence | 330s |

What holds on a 7B, and it is more than expected:

- **Verbatim provenance holds completely.** Thirty-two signals across five
  assessments and not one invented span. This is the claim the whole product
  rests on and it is a substring check, not a matter of model quality.
- **Citations resolve.** Zero `evidence_citations_discarded` warnings once the
  vocabulary was closed. Before the enum, every citation on every category was
  discarded.
- **Both other languages work.** The Spanish and Portuguese fixtures passed the
  lint *and* the language assertion — the engine answered each student in the
  language they wrote in.
- **Thin stays thin.** The deliberately empty assessment produced the fewest
  signals and landed on `aspiration_only` / `insufficient_evidence`. The
  ceiling holds.

### What does not hold: the aspiration/demonstrated distinction

`aspiration-only` fails, and it fails on the distinction the blueprint is built
around. The fixture is a student who *wants* healthcare and has never done any
of it. The engine returned `emerging` — meaning at least one cited signal was
filed on the **demonstrated** track — so `supports_naming_a_direction` came
back true and assertion 4 caught it:

> The engine treated an untested ambition as demonstrated.

This is not a format failure and it cannot be fixed with a grammar. `track` is
already a closed vocabulary and already an enum; the model picked the wrong
member of it. Telling wanting from having done is a *judgement*, and it is the
judgement this product exists to get right — the blueprint's whole reason for
the split is that "the distance between the two generates the development plan."

**We are not papering over this with a heuristic.** The obvious patch — refuse
`demonstrated` for any span containing desire language — would mis-file "I
wanted to help so I sat with her until she stopped crying," which is
demonstrated evidence in the language of wanting. Suppressing real evidence is
the same failure with the sign flipped, and inventing a value we cannot
actually compute violates the guardrail it would claim to serve.

So the measured position is:

| Layer | On a 7B we host |
|---|---|
| Layer 4 extraction, verbatim provenance | ✅ holds |
| Citation resolution, taxonomy naming | ✅ holds, once the vocabularies are closed |
| Red-team lint, language, confidence ceiling | ✅ holds — all deterministic, no model involved |
| Aspiration vs demonstrated track | ❌ **does not hold** |

The failing assertion stays failing. It is correct, it caught a real defect on
the first honest run, and a live harness that only ever passes is the thing
this whole section was written about. The open question for the pilot is
whether Layer 4 alone runs on a stronger model while the rest stays local —
which the engine override cannot express today, since it is global by design.

Cost of the run, for planning: **~7 minutes per assessment**, 100% GPU
resident, 32k context.

---

## Decision 3 — the legacy fold-in map

Sixty-six files across courses, curriculum, job listings, resume/cover-letter
tooling, mentors and the Expo app were built for **adults navigating a career
change**. That is a different product from a sixteen-year-old who cannot answer
"what are you doing after high school." The split below is by *posture*, not by
feature: what does the thing assume about who is using it.

The decisive test is non-negotiable 9 — **the tool never does the student's
work for them** — and it cuts straight through the resume tooling.

### Folds in

| Asset | Lands in | Why |
|---|---|---|
| `ResumeVersion`, `CoverLetter`, `ResumeDocxService` | 3.3 locker | The locker is a place to *keep* artifacts. Storage, versioning and export are posture-neutral. |
| `ResumeParserService`, `ParseResumeUploadJob` | 3.3 locker | Reading in what the student already wrote is the opposite of writing it for them. |
| `ResumeQualityAgent` | 3.3 locker | Critiques a draft the student wrote. Needs a voice and red-team pass before it speaks to a minor. |
| `JobListing`, `JobNormalizerService`, `JobDeduplicationService`, `JobSourceAdapter` | 4.3 vetted jobs | The ingestion and dedup spine is sound, and `soc_code` already ties listings to the taxonomy via `SocVocationalMappingSeeder`. |
| `JobMatchingService`, `SearchJobsTool` | 4.3 vetted jobs | Matching against the vocational profile is exactly what 4.3 needs. |
| `MentorAssignment`, `MentorNote`, `MentorService`, `MentorNotePolicy` | 4.1 conversation prompts | Org-side and already in use; this is the school/church channel, and 4.1 gives mentors the question to ask. |

### Frozen — kept, flagged off, not deleted

| Asset | Why |
|---|---|
| `ResumeWriterAgent`, `CoverLetterWriterAgent`, `GenerateResumeJob`, `GenerateCoverLetterJob` | These **write the document for the person**. For an adult that is a service; for a student it is non-negotiable 9 inverted, and it undercuts the brain invariant in the same breath. They stay for the adult product; they do not enter the student locker. |
| `ResumeCoachAgent`, `CareerCoachAgent`, `CareerProfile`, `VoiceProfile` | The adult track. `PathwayCoachAgent` was forked from `CareerCoachAgent` precisely so the two can ship independently. |
| All 11 course files + all 11 curriculum files | V1 ships no published courses. Curriculum generation is AI-curated coursework, which is content delivery — the vision's thesis is that students are not short of information. |
| `JobApplication`, `ApplicationEvent`, `ApplicationTrackingService`, `DetectGhostedApplicationsJob`, `SendFollowUpRemindersJob` | An adult job-search pipeline. Ghosting detection and follow-up nudges assume someone applying at volume. |
| The Expo app (2.6GB, Expo 55) | V1 is web-first and SMS is the named re-entry point, not push. Reviving it before the web loop is validated doubles the surface that every guardrail has to be enforced across. |

### What freezing actually means

Every legacy surface was already behind a feature flag defaulting to **off** —
except courses and the learning pathway, which had no flag at all and were
therefore reachable by every student on day one of a product with no published
courses. A `courses` flag now gates them, and `LegacySurfaceFreezeTest` asserts
both halves: the surface is unreachable while frozen, and the flag genuinely
brings it back, so this is a freeze and not a deletion wearing a flag's
clothes.

The same coupling showed up from the other side during the live run:
`AnalyzeAssessmentJob` dispatched curriculum generation on every completed
assessment, where it could only fail. See defect 5 below.

### Known debt carried into 4.3

~~`JobClassifierAgent` hardcodes the 17 categories rather than deriving them
from the taxonomy~~ — FIXED 2026-09-16. It now renders its category list from
`database/seeders/data/vocational_taxonomy.php` via `TaxonomyPrompt::index()`,
so the seeder is the single source and a new pathway cannot be added without
the classifier learning it. A test asserts every seeded slug appears in the
rendered instructions.

~~Still open: `JobListing` has salary and remote flags but nothing about age
requirements, supervision or safeguarding.~~ — **FIXED 2026-09-16 in 4.3.**
`minimum_age`, `supervised`, `work_kind`, `vetting_status`, `vetting_findings`
and `vetted_at` now exist, every default fails closed, and `StudentJobs` is the
only thing permitted to decide what a given student may see. The legacy
`/jobs` board, which has none of these rules in its query, refuses minors and
redirects them to `/plan/work` rather than growing a second copy of the rules.

Newly recorded, and the direct analogue of the lint's language gap: **the
vetting phrase lists are English only.** A fraudulent posting written in
Spanish passes every blocking rule trivially. The structural rules — no named
employer, no verifiable link — still bite, and they are the ones no phrasing
can be written around, but the phrase lists are a monolingual defence and
should be described as one rather than discovered.

Also open, found while closing the above: the red-team lint reads English
only. `assessments.locale` is a real column, so a narrative synthesised in
another language would have linted clean — a false all-clear, which is worse
than no lint. `AnalyzeAssessmentJob::synthesizeNarrative()` now refuses an
uncovered locale outright (`RedTeamLint::covers()`), before synthesis rather
than after, because there is no repair for it: a rewritten draft is just as
unreadable to the lint and the retry loop would burn three model calls to
learn nothing. Shipping other languages means teaching the lint, not relaxing
the guard.

---

## ~~Open defect — results are readable by anyone holding the URL~~ FIXED 2026-09-16

`AssessmentController::results()` performed **no authorization at all**: route
model binding resolved the assessment and rendered it, so any caller who knew
or guessed an assessment UUID could read a minor's full vocational portrait —
verbatim answers, the narrative written about them, their confidence level.

The hole existed because the rule was written four separate times, once in each
API controller, and the web page was the copy nobody made. It is now written
once, in `App\Support\AssessmentAccess`, and all four call it.

**Two ways in, and deliberately no expiry.** The signed-in owner, always; or
the holder of the assessment's own 64-character secret. The decision on link
lifetime was made by the product owner: *a student must always be able to open
their link.* So the secret travels in the results URL (`?t=…`), which survives
a bookmark where a session does not — a student who finishes in September opens
the same link in March.

Three consequences, recorded because they are the trade being made:

- The URL is bearer-capable. A student who forwards it has shared their
  portrait. That is now a **decision they make**, where a guessable UUID was
  never a decision at all.
- Possession of the secret authorizes regardless of who is signed in. The
  older API rule also required the caller to be a guest, which meant a student
  signed into a second account could not open their own bookmarked link.
- `GuestUpgradeService` clears the token on registration, so access moves from
  the secret to the account at that moment. Both halves are pinned by one test,
  because changing either alone strands the student.

**Three call sites had to move with it**, all found by following what linked
to the page:

- The **results email** pointed at the JSON API endpoint, which authorizes by
  an `X-Guest-Token` header that a click from an inbox cannot send. The "return
  to your results anytime" button had therefore never worked for the guests it
  was written for. It now points at the page and carries the token — this is
  the copy of the link most likely to still exist in six months.
- The **assessment hand-off** now navigates to the tokenised URL, so the first
  thing a student can bookmark is the durable one.
- The **org member page** linked straight to a student's full portrait. That
  opened only because the page authorized nobody, not because staff were ever
  granted access. The link is removed and the summary staff already saw
  (`primary_domain`, `mode_of_work`) is unchanged. **Open product question for
  Phase 3: should a counsellor be able to read a student's verbatim answers?**
  It is pinned closed by a test so granting it later is a deliberate act.

19 tests, five vacuity probes. One probe was informative: a shared session key
turns out not to be a security hole — the remembered token is still only ever
compared against that assessment's own — but it silently evicts the first
assessment when a student takes a second, so it is pinned as a durability test
rather than a security one.

## The first live run — 2026-09-16

The whole loop was driven once against a real model: assessment → nine-layer
analysis → multi-turn coach → assigned action. Everything before this had been
verified against invariants we designed and then tested for; nothing had been
verified against reality. It is now a `live`-grouped suite (`tests/Live/`),
excluded from the default run, so it is repeatable rather than a one-off.

Six defects surfaced, none of which were visible from reading the code. Five
were fatal to the product working at all.

| # | Defect | Why the test suite could not see it |
|---|---|---|
| 1 | `agent_conversations` did not exist — the AI SDK's migration ships inside the package and had never been published. **The coach had never completed a single turn**, pathway or career. | No test ever called `prompt()`. |
| 2 | That migration declares `user_id` as `foreignId()` (bigint); every user here has a UUID primary key. SQLite would have hidden it forever while Postgres and MySQL rejected it. | Green locally, broken only in production — the worst shape a bug takes. |
| 3 | Both coach controllers called `$response->text()` and `->conversationId()`. Both are **public properties**, so it could never have worked — and the call sat inside a `catch (\Throwable)` returning a generic 503, so the fatal read as a provider outage. | The broad catch converted a loud error into a silent, permanent one. |
| 4 | `RedTeamLint` matched the bare stem `diagnos`, so it fired on *"diagnostic"*. A student drawn to healthcare — one of the seventeen pathways — could not be issued a narrative at all, and the repair instruction handed the model the string `"diagnos"`, which appears nowhere in its draft. | The invariant is *never diagnose the student*, not *never use clinical vocabulary*; the lint encoded the second as a proxy for the first. |
| 5 | `AnalyzeAssessmentJob` dispatched `GenerateCurriculumJob` inline after marking the assessment complete. Courses are legacy and unpublished in V1, so the job could only fail — and under a synchronous dispatcher it unwound a fully successful nine-layer analysis, re-running the whole pipeline on retry at full cost. | Optional downstream work could fail the core pipeline. |
| 6 | The coach ran three turns, quoted the student back to themselves, **named a relationships gap in prose — and recorded nothing and assigned nothing.** | Not a prompt that was ignored: a prompt with no stopping rule. "Ask one question at a time" is unconditional, so a fourth question is always the locally safe move. |

Fix for #6 is the governing guardrail applied to the prompt itself: *never ask
a model for a value you can compute.* The coach is now told which exchange it
is in and whether the student already has a step in progress — both computed —
with a convergence rule keyed to that, and an explicit carve-out that a student
in distress outranks it. On the next live run it recorded the gap and assigned
one action.

A seventh, found while reading the passing transcript: a turn spent entirely on
tool calls returns **empty text**, so the student sends a message and gets back
a blank box. The step budget defaults to `round(tools * 1.5)` — fourteen for
nine tools — and is now raised, with a recovery turn if it happens anyway.

**Still open after the run:** the coach has been driven by one synthetic
student on one profile. 0.5 evaluation logs is the right next investment —
observability cannot be backfilled, and every finding above was a thing that
had been running "successfully" for weeks.

---

## Non-negotiables carried from both documents

1. The engine is the product; the frontend is not.
2. The system interprets; it never declares destiny. No "God has called you to…"
3. Burdens outweigh enjoyment as a signal. Object over activity when pathways compete. Distortions are still evidence — flagged internally, never surfaced raw.
4. Never lock the emotional core of a result. Lock depth, not dignity.
5. Low confidence never produces a forced conclusion — it produces a testable next step.
6. No diagnosis. Crisis content escalates to human support before vocational interpretation.
7. Parents never read coaching conversations.
8. The brain is never deleted.
9. The tool never does the student's work for them.
