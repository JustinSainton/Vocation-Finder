# Signup & Onboarding CRO Recommendations

**Vocational Discernment Assessment Platform**
**Date:** 2026-03-02
**Status:** Recommendations

---

## Executive Summary

This document applies two CRO skill frameworks -- signup-flow-cro and onboarding-cro -- to the Vocational Discernment Assessment Platform. The core tension throughout is: **conversion optimization must serve the contemplative design philosophy, never override it.** Traditional CRO patterns (urgency, social proof, friction elimination) can destroy the very quality that makes this product different. The recommendations below honor the design spec's non-negotiable principle: "clarity over cleverness, silence over stimulation."

---

## Part 1: Initial Assessment

### Product Context

| Dimension | Answer |
|-----------|--------|
| Product type | Assessment SaaS with AI-generated vocational guidance |
| B2B or B2C | Both. Individuals (B2C) + Organizations: churches, universities (B2B) |
| Core value proposition | Multi-dimensional vocational discernment that treats all work as ministry |
| Signup model | Guest assessment (no account needed) with post-assessment account creation |
| Activation event | Receiving the Vocational Articulation (results document) |
| Target audience | 17-21 year olds primarily; organization admins secondarily |
| Platforms | Expo React Native (iOS/Android) + SaaS web application |

### Flow Type Classification

This is **not** a standard B2C or B2B SaaS signup. It is closest to the **E-commerce Guest Checkout** pattern from the signup skill:

> 1. Guest checkout as default
> 2. Account creation optional post-purchase
> 3. OR Social auth with single click

Except here the "purchase" is completing a 20-question discernment assessment, and the "receipt" is a vocational profile. The value is delivered before the account is created. This is the correct architecture -- the plan already supports this.

### Activation Definition

**The "aha moment" is reading the Vocational Articulation document.** This is the emotional center of the product per the design spec. It should feel like receiving a letter, a vocational mirror. The moment a user reads their results and thinks "this understands me" -- that is activation.

**Secondary activation for organizations:** An admin sees aggregate assessment data across their members and recognizes patterns they can act on (mentoring, role placement, team formation).

---

## Part 2: Guest-to-Registered-User Conversion Flow

### Current Architecture (From Plan)

The plan correctly supports:
- `POST /api/assessments` can be called without authentication
- Guest users complete the full 20-question assessment
- Results are generated and displayed
- Account creation is offered on Page 6 (Continuation/Release) to save results

### Recommended Flow

```
Landing/Threshold
    |
Orientation
    |
Assessment (20 questions) -- guest session, no account needed
    |
Transition/Synthesis Pause
    |
Vocational Articulation (results displayed)
    |
    |--- CONVERSION MOMENT ---
    |
Continuation/Release
    |--- Save results (requires account)
    |--- Download PDF (available without account)
    |--- Email to yourself (soft account creation trigger)
```

### Conversion Moment Design

#### What NOT to do

Standard CRO would say: gate the results behind account creation. "Create an account to see your results." This would be a catastrophic violation of the design philosophy. The user just spent 30-45 minutes in vulnerable self-reflection. Gating the payoff would feel like a bait-and-switch. It would transform a contemplative experience into a transactional one.

Do not:
- Gate any part of the results behind signup
- Show a modal or popup interrupting the results reading
- Add urgency ("Your results will expire in 24 hours")
- Add social proof ("10,000 people have taken this assessment")
- Use bright CTA colors that break the visual system
- Insert the signup form between the synthesis pause and the results

#### What to do

**The conversion ask should feel like a natural continuation of care, not a transaction.**

After the user has read their full Vocational Articulation document and scrolled to the bottom, they reach Page 6 (Continuation/Release). The design spec says this page should "let the user go with dignity" and offer "soft options only."

##### Recommended Page 6 structure:

**Section 1 -- Reflection (no action required)**

A short closing paragraph. Tone: the assessment has done its work. The discernment continues in your life, not on this screen.

**Section 2 -- Preserving your results**

Copy pattern:

```
If you'd like to return to these results later,
you can save them to an account.

[Save my results]

Your responses and results will be kept private.
```

The button opens an inline form -- not a new page, not a modal. The form appears below the button with a gentle transition (no animation flourish). Fields:

1. **Email** (single field, no confirmation)
2. **Password** (with show/hide toggle, strength meter, allow paste)
3. **Name** (optional -- labeled "optional," used for personalizing future correspondence)

That is the entire form. Three fields maximum, one optional.

**Section 3 -- Alternative soft captures**

For users who do not want to create an account:

```
Or simply:

[Download as PDF]    [Email results to yourself]
```

The "Email results to yourself" option is a soft account creation path. If they enter an email to receive results, the system can later offer account creation via that email. This is progressive profiling -- collect the email through a value-exchange, not a form.

**Section 4 -- Continuation invitations**

```
If this raised questions worth sitting with:

- Find a mentor or formation community
- Explore next steps for your vocational orientation
- Return and retake the assessment later
```

These are informational links, not hard funnels.

##### Technical implementation for guest sessions:

1. When a guest starts an assessment, create a server-side session (Laravel session or a temporary `guest_token` stored in a cookie/AsyncStorage).
2. All 20 answers are saved server-side against this guest session via `POST /api/assessments/{id}/answers`.
3. The AI analysis runs and the `VocationalProfile` is generated.
4. When the guest creates an account, the existing `assessment` record gets its `user_id` updated from null to the new user's ID. No data re-entry.
5. If the guest never creates an account, the data can be retained for a configurable period (30 days) then purged. GDPR-compliant: no PII was collected without consent.

##### Conversion rate expectations:

Traditional CRO benchmarks do not apply here. A 15-25% guest-to-registered conversion rate would be strong for this type of product. The design philosophy intentionally lets people leave without pressure. This is correct. Users who do create accounts will be deeply activated -- they chose to return, which means the results resonated.

### Audit Findings: Guest-to-Registered Flow

| Issue | Impact | Fix | Priority |
|-------|--------|-----|----------|
| No account creation path exists in current specs | Users who complete assessment have no way to save results | Implement the inline form on Page 6 as described above | High |
| Email-to-results as soft capture is undefined | Missing the highest-converting low-friction path | Build "Email results to yourself" as a standalone feature that collects email | High |
| PDF download has no capture mechanism | Users get full value with zero engagement data | Allow PDF download without account, but include a subtle footer in the PDF: "Return to your results anytime at [url]" | Medium |
| Guest session expiration is undefined | Users who leave mid-assessment could lose 30 minutes of answers | Persist guest sessions for 30 days minimum; on mobile, Zustand + AsyncStorage handles this; on web, use a `guest_token` cookie | High |
| No re-engagement path for guests who leave | If a guest leaves after seeing results without saving, they are gone forever | The "Email results" option is the mitigation; if they provide email, a single follow-up email at 7 days is acceptable | Medium |

---

## Part 3: Post-Assessment Account Creation Optimization

### Field-by-Field Analysis

Per the signup-flow-cro skill, every field reduces conversion. Here is the analysis for the post-assessment account creation form:

| Field | Verdict | Rationale |
|-------|---------|-----------|
| Email | Required | Essential for account identity and results delivery |
| Password | Required | Needed for return visits. Consider magic link as alternative. |
| Name | Optional | Nice for personalization but not needed to save results. Label clearly as "optional." |
| Phone | Omit | No use case at individual signup. Never ask. |
| Organization | Omit | Inferred later if invited by an org admin. Never ask at individual signup. |
| Role / Use case | Omit | Irrelevant -- they already told you who they are through 20 deep answers. |
| Age / Demographics | Omit | Could be useful for research but is friction at signup. Collect later if needed. |

**Recommended minimum field set: Email + Password. Two fields.**

Name can be included as a third optional field. Nothing else.

### Authentication Options

For 17-21 year olds, social auth options are potentially high-converting but introduce a tonal problem. "Sign in with Google" buttons carry the visual and emotional weight of a consumer app. This conflicts with the contemplative design.

**Recommendation:**

- Offer social auth (Google, Apple) but style them to match the design system. Use the secondary sans-serif font. Muted borders. No bright brand colors on the buttons.
- Label them: "Continue with Google" / "Continue with Apple" -- not "Sign up."
- Position them below the email/password form, not above. The email form should feel primary because it matches the quiet, intentional tone. Social auth is a convenience option, not the lead.
- On mobile (Expo), Apple Sign In is required by App Store if any social auth is offered. Implement both Google and Apple.

### Magic Link Alternative

Consider offering a passwordless option:

```
Save your results with just your email.
We'll send you a link to return anytime.

[Email address]
[Send me a link]
```

This reduces the form to a single field. No password to remember. The user receives an email with a magic link that creates their account and logs them in. This is the lowest-friction path and tonally appropriate -- it feels like receiving a letter, which echoes the design spec's language about the results feeling like "a letter from a wise mentor."

**Trade-off:** Magic links have lower return-visit rates than password accounts because users must check email to log in. For a product where users may only return occasionally (to revisit results, retake assessment), this may be acceptable.

### Password UX (If Password Path Is Used)

- Show/hide toggle (eye icon)
- Show requirements upfront, not after failure
- Strength meter (not rigid rules)
- Allow paste
- Use the secondary sans-serif font for the form
- No CAPTCHA -- the 30-45 minute assessment is itself proof of humanity

### Error Handling

- Inline validation only (no submit-and-fail)
- If email already exists: "This email already has an account. [Sign in instead]" -- with a link that preserves the guest session so they can merge results
- Do not clear the form on any error
- Focus the cursor on the problem field

### Post-Submit Experience

After account creation:
- No email verification required to view results (they are already on the results page)
- Send a welcome email that is NOT a standard SaaS welcome. It should echo the tone of the assessment. One paragraph. Something like: "Your vocational discernment results are saved. You can return to them anytime. The discernment continues."
- No "Complete your profile" prompts
- No onboarding checklist
- No feature tour

The user has already had the core product experience. The account is just a container for their results. Do not introduce new friction after they have already completed the meaningful work.

### Mobile-Specific Signup Optimization

- Touch targets: 48px minimum height for all form fields and buttons
- Use `keyboardType="email-address"` for email field
- Use `secureTextEntry` with toggle for password field
- Support autofill (iOS AutoFill, Android Autofill Framework)
- Single-column layout (already mandated by design spec)
- Sticky "Save my results" button above keyboard when form is active
- Test on actual devices: iPhone SE (smallest), iPhone 15 Pro Max (largest), Samsung Galaxy A series (common Android in the 17-21 demographic)

---

## Part 4: Onboarding for Organization Admins

Organization admins (church leaders, university staff, nonprofit directors) have a fundamentally different entry point and activation moment than individual assessment-takers. They need their own flow.

### Admin Activation Definition

**Aha moment for org admins:** Seeing the first completed assessment result for one of their members. This is when they understand the depth and quality of the product.

**Secondary aha moment:** Viewing aggregate data across multiple members and recognizing patterns they can use for mentoring, team building, or ministry placement.

### Admin Onboarding Flow

#### Step 0: Entry Point

Org admins arrive via a separate path -- likely a "For Organizations" page on the marketing site, a direct sales conversation, or a referral from another org admin.

This is a B2B flow. It should be clean and professional but still honor the broader design philosophy.

#### Step 1: Account Creation (Org Admin)

Fields for org admin signup:

| Field | Required | Rationale |
|-------|----------|-----------|
| Name | Yes | Used in member invitations ("Justin invited you to...") |
| Email | Yes | Account identity |
| Password | Yes | Or magic link |
| Organization name | Yes | Needed to create the org entity |
| Organization type | Yes | Church, University, Nonprofit, Enterprise -- affects dashboard and billing |

This is 5 fields, which is acceptable for B2B. Use a two-step form:

**Step 1a:** Name + Email + Password (or social auth)
**Step 1b:** Organization name + Organization type (dropdown: Church, University, Nonprofit, Enterprise)

Show progress: "Step 1 of 2" / "Step 2 of 2"

The psychological commitment pattern applies here: after they complete Step 1a (personal info), they are more likely to complete Step 1b (org info) because they have already invested effort.

#### Step 2: Guided Setup (First 60 Seconds After Account Creation)

Per the onboarding-cro skill, choose between product-first, guided setup, or value-first. For org admins, **guided setup** is correct because they need to configure before getting value.

**Welcome screen copy:**

```
Welcome, [Name].

Your organization has been created.
Here's what happens next:

1. Invite your first members
2. They take the assessment
3. You see their results (with their permission)

Let's start by inviting someone.
```

**One primary CTA:** "Invite your first member"

This follows the "one goal per session" principle. Do not show the full dashboard yet. Do not explain all features. The single goal for the first session is: get one member invited.

#### Step 3: Member Invitation

The invitation interface should be minimal:

```
Invite a member

[Email address]
[Add a personal note (optional)]

[Send invitation]

You can invite more people later from your dashboard.
```

The invitation email that the member receives should match the contemplative tone:

```
Subject: [Org Name] has invited you to a vocational discernment experience

[Name] has invited you to take a vocational discernment assessment
through [Org Name].

This is not a test. There are no right answers.
It takes about 30-45 minutes and is best done without distractions.

[Begin]
```

No marketing language. No feature lists. No testimonials.

#### Step 4: First Value Delivery

Once the first invited member completes their assessment, the org admin receives a notification:

```
[Member Name] has completed their assessment.

[View their vocational profile]
```

This is the activation moment. The admin reads the member's results and understands the depth of the product. From here, the admin is self-motivated to invite more members.

#### Step 5: Dashboard Discovery (Gradual)

The admin dashboard should use **empty states as onboarding** per the onboarding skill:

**Members tab (0 members):**
```
No members yet.

Invite people from your organization to take the
vocational discernment assessment. You'll see their
results here once they complete it.

[Invite members]
```

**Insights tab (0 completed assessments):**
```
Aggregate insights will appear here after
multiple members complete their assessments.

These patterns can help with mentoring,
team formation, and ministry placement.
```

**Reports tab:**
```
Organization reports become available once
you have five or more completed assessments.

[Learn more about reports]
```

Each empty state explains what the section does, what it will look like with data, and what action unlocks it. No dummy data or previews -- the contemplative design philosophy favors honesty over simulation.

### Admin Onboarding Checklist

Per the onboarding skill, a checklist works well for "multiple setup steps required" B2B products. However, the standard checklist pattern (persistent sidebar widget with checkmarks) would feel too gamified for this product.

**Adaptation:** Use a subtle, non-intrusive task list on the dashboard home page:

```
Getting started

  Complete your organization profile
  Invite your first member
  Review your first completed assessment
  Explore aggregate insights

3 of 4 remaining
```

Design rules for this checklist:
- No checkmark animations
- No confetti or celebration modals
- No progress percentage
- Completed items show with a quiet strikethrough
- The list disappears entirely once all items are done
- It can be dismissed at any time with a small "Dismiss" text link

### Admin Email Sequence

| Trigger | Timing | Content |
|---------|--------|---------|
| Account created | Immediate | Welcome + confirmation. One paragraph. Link to dashboard. |
| No invitations sent | 48 hours | "Your organization is ready. Invite your first member to begin." |
| First invitation sent | Immediate | Confirmation. "When they complete the assessment, you'll be notified." |
| First member completes assessment | Immediate | "[Name] has completed their assessment. View their vocational profile." |
| 3+ members completed | When triggered | "You now have enough data for aggregate insights. View patterns." |
| Stalled (no activity 14 days) | 14 days | "Your organization account is waiting. Need help getting started?" Offer a setup call. |

All emails should be plain text or minimal HTML. No bright buttons. No marketing design. The email should feel like correspondence, not a campaign.

---

## Part 5: Balancing Conversion Optimization with Contemplative Design

This is the most critical section. The two forces -- CRO and contemplative design -- are not inherently opposed, but they require careful negotiation.

### Principle: Optimize for Depth of Engagement, Not Speed of Conversion

Traditional CRO optimizes for speed: how fast can we move someone from visitor to signed-up user? This product optimizes for depth: how fully can someone engage with the discernment process?

A deeply engaged user who never creates an account has still received the product's value. An account-created user who skipped through the assessment quickly has not.

**Practical implication:** The primary metric is assessment completion rate, not signup rate. Signup is a secondary metric that follows naturally from a powerful assessment experience.

### Principle: Friction Is Sometimes the Feature

The signup skill says "every field reduces conversion." True. But the design spec includes a symbolic checkbox on the Orientation page: "I'm willing to answer honestly, not impressively." This checkbox is explicitly non-functional -- it adds friction for philosophical reasons. It slows the user down. It asks them to commit to a posture.

This is good friction. It filters for seriousness and creates the contemplative container. CRO should never recommend removing it.

**Generalized rule for this product:** Before removing any friction, ask: "Does this friction serve the contemplative experience?" If yes, it stays. If no, remove it.

Examples of friction that serves the experience:
- The orientation page (could be skipped, but sets posture)
- One question per screen (could show all 20, but destroys pacing)
- No progress bar percentage (could show %, but creates urgency)
- Forward-only navigation as default (could allow random access, but destroys linearity)
- The synthesis pause page (could skip straight to results, but ruins anticipation)

Examples of friction that does NOT serve the experience:
- Requiring account creation before assessment
- Requiring email verification before showing results
- Multi-page signup forms
- CAPTCHA challenges
- Mandatory profile completion after signup

### Principle: Trust Signals Should Be Structural, Not Decorative

The signup skill recommends trust signals: testimonials, security badges, user counts, "No credit card required." Most of these would violate the design spec.

Instead, trust in this product is communicated structurally:
- **The quality of the questions** signals seriousness (users read question 1 and know this is different)
- **The design itself** signals care (minimal, quiet, intentional)
- **The results quality** signals competence (when the AI output is genuinely insightful)
- **The lack of sales pressure** signals integrity (no upsell before value delivery)

The only traditional trust element worth including is a privacy assurance near the signup form:

```
Your responses and results are kept private.
```

One line. No badge. No shield icon.

### Principle: Microcopy Is Ministry

In a contemplative product, microcopy carries disproportionate weight. Every word on every button, every label, every placeholder matters more than in a standard SaaS app.

**Signup skill defaults vs. contemplative adaptations:**

| Standard CRO | Contemplative Adaptation | Why |
|-------------|-------------------------|-----|
| "Sign Up Free" | "Save my results" | The action is preserving something meaningful, not "signing up" |
| "Create Account" | "Create an account to return to these results" | Explains the purpose, not just the action |
| "Get Started" | "Begin discernment" | Already in the design spec. Perfect. |
| "Start Free Trial" | Never used | This is not a trial. It is an experience. |
| "Submit" | "Continue" | "Submit" implies judgment. "Continue" implies journey. |
| "Your email" (placeholder) | "Email address" (label) | Always use labels, not placeholders. Per signup skill: "Labels: Always visible (not just placeholders)" |
| "Create a strong password" | "Choose a password" | No anxiety. No judgment on strength. A strength meter can appear quietly. |

### What to A/B Test (Carefully)

The experiment ideas from both skills should be filtered through the design philosophy. Here are the experiments worth running:

**Test 1: Magic link vs. password for post-assessment account creation**
- Hypothesis: Magic link (single email field) will convert higher than email + password
- Metric: Account creation rate on Page 6
- Risk: Low. Both options respect the design philosophy.

**Test 2: "Email results to yourself" vs. "Save my results" as primary CTA on Page 6**
- Hypothesis: "Email results" has lower perceived commitment and may convert better
- Metric: Email capture rate
- Risk: Low. Both are soft asks.

**Test 3: Inline form vs. separate page for account creation**
- Hypothesis: Inline form (appearing below the CTA button on Page 6) converts better than navigating to a separate signup page
- Metric: Account creation completion rate
- Risk: Low. Inline form stays within the contemplative flow.

**Test 4: Showing "Question X of 20" vs. no progress indicator**
- Hypothesis: The current spec says show "Question 7 of 20." Test whether removing it entirely affects completion rates.
- Metric: Assessment completion rate
- Risk: Medium. Users may need orientation. But the contemplative argument for removing it (eliminates countdown anxiety) is worth testing.

**Do NOT test:**
- Adding urgency messaging ("Complete your profile to save results")
- Adding social proof to the signup form
- Gating any results behind account creation
- Adding a progress bar with percentage
- Pop-up or modal signup prompts during results reading
- Gamification of any kind

---

## Part 6: Mobile vs. Web Onboarding Differences

The platform serves two client types: Expo React Native (mobile) and SaaS web application. The core experience is the same, but the onboarding must account for platform differences.

### Individual Assessment-Takers

| Dimension | Mobile (Expo) | Web |
|-----------|--------------|-----|
| Entry point | App Store download or direct link | URL (likely from org invitation or marketing) |
| First screen | Landing/Threshold (same content) | Landing/Threshold (same content) |
| Authentication timing | Same: after assessment completion | Same: after assessment completion |
| Assessment mode | Written (form) or Audio (conversation) | Written (form) only |
| Keyboard handling | Native keyboard with proper types; sticky CTA above keyboard | Standard web form behavior |
| Autosave | Zustand + AsyncStorage (works offline) | API calls (requires connectivity) |
| Social auth | Google + Apple Sign In (Apple required by App Store policy) | Google only (Apple optional) |
| Results viewing | Scrollable document within app; "Download PDF" and "Email results" | Scrollable document; "Download PDF," "Email results," "Print" (browser native) |
| Haptic feedback | Light haptic on Continue, medium on completion | None (web has no haptic API) |
| Account creation form | 48px touch targets, native keyboard types, autofill support | Standard web form, no special mobile considerations |
| Session persistence | AsyncStorage -- survives app close and restart | Cookie/localStorage -- survives tab close, clears on cache clear |
| Push notifications | Available after account creation (ask permission strategically, not on first launch) | Not applicable (email only for re-engagement) |

#### Mobile-Specific Onboarding Considerations

**App Store first-launch flow:**
When a user downloads from the App Store, they land directly on the Landing/Threshold page. No splash screen with app branding. No "What's new" carousel. No permission requests. The app opens and the user sees:

```
Most people are taught to choose a career.
Very few are taught to discern a calling.

[Begin discernment]
```

This is the entire first screen. It should load instantly (no skeleton screens, no loading states).

**Audio conversation mode onboarding (mobile only):**
The conversation mode requires microphone permission. Do NOT request this on app launch. Request it when the user chooses "Conversation" mode, with context:

```
This mode works as a spoken conversation.
You'll speak your answers aloud, and the
assessment will respond in kind.

To begin, we'll need access to your microphone.

[Allow microphone access]
```

If denied, gracefully fall back:

```
No problem. You can use the written assessment instead.

[Continue with written assessment]
```

**Offline handling:**
If the user loses connectivity during the written assessment, the app should continue working seamlessly (Zustand persistence). No error modals. No "You're offline" banners. When connectivity returns, sync silently. The user should never know the interruption happened.

If the user tries to start the conversation mode without connectivity:

```
The conversation mode requires an internet connection.
You can use the written assessment offline.

[Continue with written assessment]
```

**Push notification timing:**
Never ask for push notification permission during the assessment flow. The only appropriate time is after account creation, on a return visit, when the user has a reason to want notifications (e.g., "We'll let you know when your results are ready" if AI analysis takes more than a few seconds, or organization-related notifications).

#### Web-Specific Onboarding Considerations

**Organization admin dashboard (web only):**
The admin dashboard is web-only (per the plan's architecture). Admins will access it via browser. The onboarding flow described in Part 4 applies here.

Web-specific details for the admin dashboard:
- Responsive design but optimized for desktop (admins manage members and view reports on larger screens)
- No mobile admin dashboard in v1 -- if an admin visits on mobile, show a simplified view with a message: "For the best experience managing your organization, visit on a desktop browser."
- Session management: standard Laravel session with Sanctum SPA authentication
- No mobile-app-style navigation patterns. Use a minimal sidebar or top navigation.

**Assessment on web:**
The web assessment should match the mobile experience exactly in content and pacing. Differences are only technical:
- No autosave to local storage by default. Each answer is saved via API call. If the API call fails, queue it in localStorage and retry.
- Add `beforeunload` handler to warn if the user tries to close the tab during the assessment: "Your progress is saved, but you'll need to return to complete the assessment."
- The single-column, 600-680px max-width layout from the design spec translates naturally to web. On very large screens, the generous whitespace reinforces the contemplative feel.

### Platform-Specific Signup Forms

**Mobile signup form (post-assessment):**

```
Save your results

[Email address]            <- keyboardType="email-address", autoComplete="email"
[Password]                 <- secureTextEntry, autoComplete="new-password"
[Full name (optional)]     <- autoComplete="name"

[Save my results]          <- 48px height, full width

--- or ---

[Continue with Apple]      <- Apple Sign In (required by App Store)
[Continue with Google]     <- Google Sign In

Your responses and results are kept private.
```

**Web signup form (post-assessment):**

```
Save your results

Email address
[                    ]     <- type="email", autocomplete="email"

Password
[                    ]     <- type="password", autocomplete="new-password"

Full name (optional)
[                    ]     <- autocomplete="name"

[Save my results]

--- or ---

[Continue with Google]

Your responses and results are kept private.
```

Both forms are identical in structure. The only differences are platform-specific input attributes and the presence of Apple Sign In on mobile.

---

## Part 7: Metrics & Measurement Plan

### Key Metrics to Track

#### Assessment Funnel
| Step | Metric | Target |
|------|--------|--------|
| Landing page | Visitors who click "Begin discernment" | 40-60% (high-intent visitors) |
| Orientation | Visitors who click "Continue" past orientation | 85-90% |
| Assessment start | Users who answer question 1 | 95% of those who pass orientation |
| Assessment completion | Users who answer all 20 questions | 60-70% |
| Results viewed | Users who scroll through full Vocational Articulation | 95% of completers |
| Account created | Users who create an account after viewing results | 15-25% of completers |

#### Account Creation Funnel
| Step | Metric |
|------|--------|
| Form impressions | How many completers see the signup form on Page 6 |
| Form starts | How many begin filling in a field |
| Form completions | How many submit successfully |
| Email capture (total) | Account creation + "Email results" combined |
| Social auth usage | % who use Google/Apple vs. email/password |

#### Organization Admin Funnel
| Step | Metric | Target |
|------|--------|--------|
| Admin account created | -- | -- |
| First invitation sent | Within 24 hours of account creation | 60% |
| First member completes assessment | Within 7 days of first invitation | 40% |
| Admin views first member result | Within 24 hours of member completion | 80% |
| 5+ members completed | Within 30 days | 30% |

#### What to Track Per Field (Signup Form)
- Focus events (did they click into the field?)
- Blur events (did they leave the field?)
- Error occurrences by field
- Time spent per field
- Which field was last interacted with before abandonment

### Implementation Notes

All tracking should be implemented with privacy in mind:
- No tracking during the assessment itself (questions 1-20). The assessment is a sacred space. Analytics should measure completion, not behavior during.
- Track funnel steps (page transitions), not granular user behavior within the assessment.
- Track signup form interactions (field-level) to optimize the conversion form.
- All analytics data should be anonymized and aggregated. Do not tie assessment content to analytics profiles.

---

## Part 8: Quick Wins, High-Impact Changes, and Test Hypotheses

### Quick Wins (Implement Immediately)

1. **Add "Email results to yourself" option on Page 6.** Single email field. Captures email with minimal friction. Can be implemented as a standalone endpoint that sends a formatted email and stores the email for future account creation prompting.

2. **Style social auth buttons to match design system.** Muted borders, secondary font, no brand colors. "Continue with Google" not "Sign up with Google."

3. **Add privacy assurance line below signup form.** "Your responses and results are kept private." One line, no icon.

4. **Implement inline validation on signup form.** Email format check, password strength meter (quiet, not alarming), typo detection (gmial.com suggestion).

5. **Set guest session persistence to 30 days.** Ensure users who leave mid-assessment can return. On mobile (AsyncStorage) this is straightforward. On web, use a long-lived cookie + server session.

### High-Impact Changes (Week-Level Effort)

1. **Build the magic link authentication option.** Single-field signup ("Enter your email, we'll send you a link to return anytime"). Requires email sending infrastructure, token generation, and a landing page for the magic link.

2. **Build the org admin onboarding flow.** Two-step signup, guided first-invitation experience, empty states with onboarding copy, and the 6-email automated sequence.

3. **Implement guest-to-user session migration.** When a guest creates an account, seamlessly attach their existing assessment and results to the new user record. No data re-entry, no "your results will appear shortly" delay.

4. **Build the admin notification system.** Email notifications when members complete assessments. This is the activation trigger for org admins.

### Test Hypotheses (A/B Test When Traffic Allows)

1. **Magic link vs. password:** Does a single-field magic link signup outperform the email + password form on Page 6?

2. **"Email results" primary vs. "Save results" primary:** Which CTA on Page 6 captures more emails?

3. **Inline form vs. separate page:** Does showing the signup form inline on Page 6 outperform navigating to a dedicated signup page?

4. **Social auth position:** Does placing Google/Apple auth above or below the email form affect conversion?

5. **Orientation page checkbox:** Does the symbolic honesty checkbox affect assessment completion rates? (Hypothesis: it increases completion by creating commitment. Test to confirm.)

---

## Part 9: Copy Deliverables

### Page 6 (Continuation/Release) -- Full Copy

```
[After the Vocational Articulation document ends]

---

This assessment has offered one perspective on your calling.
Discernment continues in the lives you touch and the work you do.


If you'd like to return to these results:

[Save my results]

    Email address
    [                              ]

    Password
    [                              ]

    Full name (optional)
    [                              ]

    [Save my results]

    --- or ---

    [Continue with Google]
    [Continue with Apple]          <- mobile only

    Your responses and results are kept private.


Or simply:

[Download as PDF]    [Email results to myself]


---

If this raised questions worth sitting with:

  Communities and mentors in your area
  Formation resources for your vocational orientation
  Return and revisit this assessment
```

### Welcome Email (Post Account Creation)

```
Subject: Your vocational discernment results are saved

Your results from the vocational discernment assessment
are now saved to your account. You can return to them
anytime at [link].

The discernment continues beyond this assessment --
in your conversations, your choices, and your work.

[Return to my results]
```

### Organization Invitation Email

```
Subject: You're invited to a vocational discernment experience

[Admin Name] from [Org Name] has invited you to take
a vocational discernment assessment.

This is not a test. There are no right answers.
It takes about 30-45 minutes and is best done
without distractions.

[Begin]
```

### Stalled Admin Re-engagement Email (14 Days)

```
Subject: Your organization is ready when you are

Your [Org Name] account is set up and waiting.

When you're ready, the next step is simple:
invite one person to take the assessment.

[Invite your first member]

If you have questions or want help getting started,
reply to this email.
```

---

## Summary of Recommendations by Priority

| Priority | Recommendation | Category |
|----------|---------------|----------|
| High | Guest assessment without account requirement (already in plan -- confirm implementation) | Architecture |
| High | Post-assessment inline signup form on Page 6 with 2-3 fields max | Signup Flow |
| High | "Email results to yourself" as low-friction email capture | Signup Flow |
| High | Guest session persistence (30 days minimum) with session migration on account creation | Signup Flow |
| High | Magic link as primary/alternative auth method | Signup Flow |
| High | Org admin two-step signup with guided first-invitation onboarding | Onboarding |
| Medium | Social auth styled to match contemplative design system | Signup Flow |
| Medium | Empty states as onboarding for org admin dashboard | Onboarding |
| Medium | Admin email notification sequence (6 trigger-based emails) | Onboarding |
| Medium | Field-level analytics on signup form | Measurement |
| Medium | PDF footer with return URL for guests who download without account | Signup Flow |
| Low | A/B testing magic link vs. password | Experimentation |
| Low | A/B testing CTA copy variations on Page 6 | Experimentation |
| Low | Push notification permission timing optimization (mobile) | Onboarding |
