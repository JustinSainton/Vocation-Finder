# Vocational Discernment Assessment Platform -- Pricing Strategy

**Type:** Strategy -- Pricing & Monetization
**Date:** 2026-03-02
**Status:** Recommendation

---

## 1. Business Context Gathered

### Product Type
- **Type:** SaaS + one-time assessment product (hybrid)
- **Platform:** Mobile app (Expo React Native) + web SaaS application
- **Current pricing:** None (pre-launch)
- **Target market:** B2C individuals (ages 17-21 initially, expanding to adults) + B2B organizations (churches, universities, nonprofits)
- **Go-to-market motion:** Hybrid -- self-serve for individuals, sales-assisted for organizations

### Value & Competition
- **Primary value delivered:** Multi-dimensional vocational clarity that integrates faith and calling, going far beyond personality-type labels
- **Alternatives customers consider:**
  - StrengthsFinder / CliftonStrengths ($19.99 one-time)
  - Enneagram assessments ($12 one-time)
  - Career assessment platforms ($20-50 one-time)
  - DISC assessments ($20-40)
  - Myers-Briggs / 16Personalities (free basic, $49+ premium)
  - YouScience ($29 individual, B2B for schools)
  - Career counseling sessions ($75-200/hour)
- **What makes this different:**
  1. AI-analyzed open-ended narrative responses (not multiple choice reduction)
  2. Multi-dimensional output (primary domain + mode of work + secondary orientation)
  3. Theological integration -- all work framed as ministry/calling
  4. Audio conversational interface (premium, contemplative experience)
  5. 17-category dimensional mapping vs. single-label categorization

### Cost to Serve
| Component | Cost Per Assessment | Notes |
|-----------|-------------------|-------|
| Claude API (analysis) | $0.10 - $0.50 | Two-phase prompt: pattern analysis + narrative synthesis |
| Whisper transcription (audio mode) | $0.03 - $0.10 | ~20 minutes of audio |
| TTS generation (audio mode) | $0.02 - $0.10 | AI voice responses during conversation |
| PDF generation | ~$0.01 | Server-side rendering |
| Infrastructure (compute, DB, storage) | ~$0.02 - $0.05 | Per assessment amortized |
| **Total -- Written mode** | **$0.13 - $0.56** | |
| **Total -- Audio conversation mode** | **$0.18 - $0.76** | |

### Goals
- **Primary:** Growth -- maximize adoption among the 17-21 age cohort and their institutional sponsors
- **Secondary:** Revenue sustainability -- cover AI costs and fund development
- **Tertiary:** Build toward B2B expansion as the primary revenue engine

---

## 2. Value-Based Pricing Framework Applied

```
Customer's perceived value of vocational clarity
(avoids years of career uncertainty, $10k+ in wrong-major costs)
──────────────────────────────────────────────── $200+

Your price opportunity
──────────────────────────────────────────────── $15-39 (individual)

Next best alternative (StrengthsFinder $19.99, career counseling $75-200/hr)
──────────────────────────────────────────────── $12-50

AI + infrastructure cost to serve
──────────────────────────────────────────────── $0.13-0.76
```

**Key insight:** The cost to serve is extremely low ($0.13-$0.76) relative to the perceived value and competitive alternatives. The product delivers multi-session counselor-level insight for a fraction of that cost. This means healthy margins at competitive price points.

However, the target demographic (17-21 year olds) has limited disposable income. The pricing must account for this by leaning on B2B (organizations paying on behalf of users) as the primary revenue driver, while keeping B2C accessible.

---

## 3. Pricing Personas

| Persona | Description | Needs | WTP | Buying Process |
|---------|-------------|-------|-----|----------------|
| **Student (17-21)** | High school junior/senior or college freshman/sophomore exploring calling | Core assessment, results, PDF | Low ($0-25) | Self-serve, impulse/referral |
| **Adult Career Changer (25-45)** | Mid-career professional questioning calling | Deep assessment, audio mode, coursework | Medium ($25-50) | Self-serve, considered purchase |
| **Youth Pastor / Campus Minister** | Wants to facilitate vocational discernment for their group | Bulk assessments, group insights, admin dashboard | Medium-High ($5-15/seat) | Relationship-driven |
| **University Career Center** | Needs scalable assessment tool for students | High volume, admin dashboard, aggregate reporting, SSO | High ($3-10/seat at volume) | Procurement, sales-led |
| **Church / Nonprofit** | Congregation-wide or program-specific vocational exploration | Moderate volume, admin tools, branded experience | Medium ($5-12/seat) | Committee decision, relationship-led |

---

## 4. Value Metric Analysis

### What drives value for each persona?

| Usage Pattern | Value Delivered | Potential Metric |
|---------------|-----------------|------------------|
| Each completed assessment | Direct vocational clarity | Per assessment |
| More members assessed in an org | More institutional insight + individual impact | Per seat / per assessment |
| Audio conversation mode | Premium, deeper experience | Feature gate |
| Post-assessment coursework | Ongoing formation | Subscription / add-on |
| Retaking assessment (growth tracking) | Longitudinal development insight | Per assessment |

### Recommended Value Metric: **Per Assessment**

**Rationale:**
- Each assessment completion is a discrete, high-value moment
- Aligns price with value delivered (one assessment = one vocational profile)
- Easy to understand for both individuals and organizations
- Scales naturally: organizations pay for the assessments their members take
- Avoids the "seat" problem where people pay but never take the assessment

For organizations, this translates to a **per-seat model with assessment credits** -- they purchase access for N members, each of whom gets one assessment included.

---

## 5. Tier Structure: Good-Better-Best

### B2C Individual Pricing

```
+---------------------+---------------------+---------------------+
|     REFLECT          |     DISCERN          |     ILLUMINATE       |
|     Free             |     $19              |     $39              |
|                      |     one-time         |     one-time         |
+---------------------+---------------------+---------------------+
| Written assessment   | Written assessment   | Written assessment   |
| (20 questions)       | (20 questions)       | (20 questions)       |
|                      |                      |                      |
| Basic vocational     | Full multi-dimen-    | Full multi-dimen-    |
| summary (2-3         | sional vocational    | sional vocational    |
| paragraphs)          | profile              | profile              |
|                      |                      |                      |
|         --           | PDF download         | PDF download         |
|         --           | Email results        | Email results        |
|         --           |         --           | Audio conversation   |
|                      |                      | mode                 |
|         --           |         --           | Coursework access    |
|                      |                      | (first module)       |
|         --           |         --           | Ministry integration |
|                      |                      | deep-dive            |
|         --           |         --           | Retake after 6 mo.   |
|                      |                      | (with growth         |
|                      |                      | comparison)          |
+---------------------+---------------------+---------------------+
```

#### Tier Rationale

**Reflect (Free):**
- **Purpose:** Remove all barriers to entry for the 17-21 cohort. Let anyone experience the assessment.
- **What they get:** The full 20-question written assessment with a condensed vocational summary -- enough to be genuinely useful, but not the full multi-dimensional analysis.
- **Why free works here:**
  - The target demo has limited money
  - Free users become evangelists in churches and schools ("You have to take this")
  - The free tier creates the viral loop that drives B2B adoption (youth pastor sees 10 students talking about it, then buys for the whole group)
  - Low marginal cost (~$0.10-0.15 per free assessment using a lighter Claude prompt)
  - The condensed summary creates natural upgrade desire ("I want the full analysis")
- **Conversion trigger:** "Unlock your full vocational profile" after seeing the summary

**Discern ($19 one-time):**
- **Purpose:** The core paid product for individuals who want the real thing
- **Price justification:** Priced at StrengthsFinder parity ($19.99) but rounded down to $19 for charm pricing. Delivers significantly more value (narrative, multi-dimensional, personalized) than StrengthsFinder's category report.
- **This is the anchor tier** -- most individual buyers land here

**Illuminate ($39 one-time):**
- **Purpose:** Premium experience for those who want the full depth
- **Price justification:** 2x the Discern tier. The audio conversation mode alone has higher production value and cost. Comparable to a premium career assessment ($30-50 range) but includes ongoing value (coursework, retake).
- **Target:** Adults (25+) with more disposable income, or students whose parents are paying

#### Why One-Time, Not Subscription (for B2C)

The assessment is a discrete event, not ongoing software usage. Subscriptions would feel misaligned -- people do not take vocational assessments monthly. One-time pricing:
- Matches the mental model ("I want to take this assessment")
- Reduces friction for the price-sensitive target demo
- Avoids subscription fatigue in a market with too many subscriptions
- Coursework can be a future subscription upsell once trust is established

---

### B2B Organization Pricing

```
+---------------------+---------------------+---------------------+
|     COMMUNITY        |     FORMATION        |     INSTITUTION      |
|     $8/seat          |     $6/seat          |     Custom           |
|     (10-50 seats)    |     (51-250 seats)   |     (250+ seats)     |
+---------------------+---------------------+---------------------+
| Full assessment      | Full assessment      | Full assessment      |
| (written + audio)    | (written + audio)    | (written + audio)    |
| for each member      | for each member      | for each member      |
|                      |                      |                      |
| Full vocational      | Full vocational      | Full vocational      |
| profiles             | profiles             | profiles             |
|                      |                      |                      |
| PDF + email          | PDF + email          | PDF + email          |
| results              | results              | results              |
|                      |                      |                      |
| Admin dashboard      | Admin dashboard      | Admin dashboard      |
|                      |                      |                      |
| Aggregate insights   | Aggregate insights   | Aggregate insights   |
| (anonymized)         | (anonymized)         | (anonymized)         |
|                      |                      |                      |
|         --           | Organization         | Organization         |
|                      | branding             | branding             |
|                      |                      |                      |
|         --           | Org-wide reports     | Org-wide reports     |
|                      | (exportable)         | (exportable)         |
|                      |                      |                      |
|         --           | Coursework access    | Coursework access    |
|                      | for all members      | for all members      |
|                      |                      |                      |
|         --           |         --           | SSO / SAML           |
|         --           |         --           | Dedicated support    |
|         --           |         --           | Custom onboarding    |
|         --           |         --           | API access           |
|         --           |         --           | Priority roadmap     |
+---------------------+---------------------+---------------------+
```

#### B2B Pricing Rationale

**Why per-seat (not per-assessment or flat rate)?**
- Per-seat is the most familiar model for org buyers (matches how they budget)
- Per-assessment penalizes retakes and discourages usage
- Flat rate leaves money on the table for larger organizations
- Per-seat with volume tiers rewards growth: "Add more students at a lower price"

**Community ($8/seat, 10-50 seats):**
- **Target:** Small churches, campus ministries, small nonprofits
- **Total cost:** $80-400 per cohort -- well within most ministry budgets
- **Price justification:** At $8/seat, the org is paying less than half what an individual would pay for the Illuminate tier, getting bulk value. Compared to bringing in a career counselor for a workshop ($200-500 for an afternoon), this is dramatically cheaper and provides individual, personalized results.
- **Minimum 10 seats:** Prevents individuals from gaming the org price

**Formation ($6/seat, 51-250 seats):**
- **Target:** Mid-size churches, university departments, larger nonprofits
- **Total cost:** $306-1,500 -- fits institutional budget line items
- **25% volume discount** from Community tier rewards growth
- Includes coursework access and org reports that make it a programmatic tool, not just an assessment

**Institution (Custom, 250+ seats):**
- **Target:** University-wide rollouts, large church networks, denominational programs
- **Contact sales** -- these deals involve procurement, custom contracts, and integration needs
- **Expected range:** $3-5/seat at 500+ volume
- SSO/SAML and API access serve the enterprise requirement of these buyers

#### B2B Billing Model

- **Annual billing only** for organizations (simplifies budgeting, improves cash flow)
- **Seats are "assessment slots"** -- the org purchases N slots, and N members can each take a full assessment
- **Unused seats roll over for 12 months** (reduces purchase anxiety)
- **Top-up pricing:** Organizations can buy additional seats mid-term at the same per-seat rate

---

## 6. Free vs. Paid Boundary

The free tier boundary is the most critical pricing decision. Here is the exact delineation:

### What Is Free (Reflect Tier)

| Feature | Free | Paid |
|---------|------|------|
| Account creation | Yes | Yes |
| Written assessment (all 20 questions) | Yes | Yes |
| Autosave and resume | Yes | Yes |
| Basic vocational summary (2-3 paragraphs) | Yes | Yes |
| Full multi-dimensional vocational profile | No | Yes |
| Dimensional mapping (primary domain, mode of work, secondary orientation) | No | Yes |
| 17-category scoring breakdown | No | Yes |
| Ministry integration narrative | No | Yes |
| PDF download | No | Yes |
| Email results | No | Yes |
| Audio conversation mode | No | Yes (Illuminate / B2B) |
| Coursework modules | No | Yes (Illuminate / B2B) |
| Retake with growth comparison | No | Yes (Illuminate / B2B) |

### Why This Boundary Works

**The user completes the full assessment for free.** This is non-negotiable. If we gate the questions, the user never experiences the contemplative assessment process that IS the product. The free tier must let them answer all 20 questions.

**The gate is on analysis depth, not on participation.** After completing the assessment:
- Free users receive a genuine, useful 2-3 paragraph vocational summary
- Paid users receive the full multi-dimensional profile (opening synthesis, vocational orientation narrative, primary pathways, specific considerations, next steps, ministry integration)

**This creates the ideal upgrade moment.** The user has invested 30-45 minutes answering deeply personal questions about their calling. They are emotionally invested. They see a meaningful but incomplete summary. The full profile -- which they know the AI has already generated from their answers -- is available for $19. This is the highest-intent moment possible.

### Free Tier Cost Control

The free tier uses a lighter Claude prompt to generate the condensed summary. Estimated cost:
- Lighter Claude analysis: ~$0.05-0.10 per free assessment
- At 10% conversion to paid: cost to acquire a paying customer is $0.50-1.00 in AI costs
- This is excellent unit economics

---

## 7. Upsell Paths

### Path 1: Free to Paid (Reflect to Discern)
- **Trigger:** Assessment completion -- user sees condensed summary with "Unlock your full vocational profile"
- **Friction:** One-click purchase, Apple Pay / Google Pay on mobile
- **Messaging:** "Your responses revealed patterns across [X] dimensions. Your full vocational profile explores these in depth."
- **Expected conversion:** 15-25% (high-intent moment after 30-45 min investment)

### Path 2: Discern to Illuminate
- **Trigger:** After viewing full written results -- "Experience the audio conversation for deeper insight"
- **Value prop:** The audio conversation mode can surface things that writing cannot. A second, conversational pass through the questions with AI follow-ups provides richer data.
- **Messaging:** "Many people find that speaking about their calling reveals insights that writing alone misses."
- **Expected conversion:** 5-10% of Discern users
- **Price differential:** $20 upgrade ($39 - $19 already paid)

### Path 3: Individual to Organization
- **Trigger:** A user who has taken the assessment shares it with their youth pastor, campus minister, or career center
- **This is the primary growth engine.** Individual usage seeds organizational adoption.
- **Enablement:** Easy sharing ("Send your results to your pastor/advisor"), referral mechanism ("Invite your group")
- **Messaging to org buyer:** "10 of your students have already taken this assessment. Unlock aggregate insights and bring it to your whole group."

### Path 4: Coursework Upsell
- **Timeline:** Phase 6 feature (post-launch)
- **Model:** $9.99/month subscription or $29/quarter for ongoing coursework access
- **Content:** Formation pathways tied to vocational profile results, practical exercises, reflection prompts, video content
- **Trigger:** After viewing results -- "Continue your vocational formation with guided coursework tailored to your profile"
- **This becomes the recurring revenue engine** once the assessment creates the initial relationship

### Path 5: Retake / Growth Assessment
- **Trigger:** 6-12 months after initial assessment
- **Price:** $14.99 (discounted from full price as a retention tool)
- **Value:** Side-by-side comparison showing vocational growth and clarification over time
- **Messaging:** "See how your sense of calling has developed over the past [X] months"

### Path 6: Coaching Integration (Future)
- **Model:** Marketplace connecting users with vocational coaches/mentors
- **Revenue:** 15-20% platform fee on coaching sessions
- **Price:** Coach sets rate, platform facilitates ($50-150/session typical)
- **Trigger:** Post-results -- "Want to explore these findings with a trained vocational coach?"

---

## 8. Launch Pricing Strategy

### Pre-Launch (Weeks -8 to 0): Build the List

**Strategy: Founding Member pricing**
- Announce the platform with a landing page
- Offer "Founding Member" early access at 50% off ($9.50 for Discern, $19.50 for Illuminate)
- Founding Members get lifetime access to their tier, including future coursework
- Goal: 200-500 founding members to validate demand and seed testimonials
- Collect Van Westendorp survey data from the waitlist to validate price points

### Soft Launch (Weeks 0-4): Validated Learning

**Strategy: Free assessment for everyone, paid unlock at discounted launch pricing**
- Launch the free tier (Reflect) to everyone
- Discern at $14.99 (launch price, ~25% below final price)
- Illuminate at $29 (launch price, ~25% below final price)
- No B2B launch yet -- focus on individual product-market fit
- Track: conversion rate free-to-paid, completion rate, NPS, qualitative feedback
- Goal: 1,000 free assessments, 150-250 paid conversions

### Growth Launch (Weeks 4-12): Full Pricing + B2B

**Strategy: Move to full individual pricing, launch B2B**
- Discern moves to $19 (communicate as "launch pricing is ending")
- Illuminate moves to $39
- Grandfather all launch-price customers at their paid price
- Launch B2B Community tier ($8/seat) with 3-5 pilot organizations (churches, one university)
- Offer pilot organizations 25% discount for 6 months in exchange for case studies and testimonials

### Maturity (Months 4-12): Optimize

- A/B test pricing page (layout and messaging, not price -- avoid price A/B testing per the skill's guidance)
- Launch Formation tier ($6/seat, 51+ seats) once pilot orgs validate demand
- Begin coursework development for subscription upsell
- Introduce Institution tier with sales outreach to universities
- Monitor and adjust based on:
  - Conversion rate (target: 15-25% free-to-Discern)
  - B2B pipeline velocity
  - Churn/repeat usage patterns
  - Customer feedback on price sensitivity

---

## 9. Pricing Page Design Recommendations

### Above the Fold
- Clear three-tier comparison table (Reflect / Discern / Illuminate)
- Discern tier visually highlighted as "Most Popular"
- One-time pricing emphasized (not subscription -- this is a differentiator vs. SaaS fatigue)
- Primary CTA: "Start Your Assessment" (goes to free tier -- everyone starts free)

### Anchoring Strategy
- Show Illuminate ($39) first or visually prominent to anchor expectations high
- Discern ($19) then feels like a great value by comparison
- Reflect (Free) removes all risk: "Not sure? Start free."

### Trust Signals
- "Your responses are private and encrypted"
- "Used by [X] churches and universities" (once B2B launches)
- "Created in partnership with psychologists and theologians"
- Money-back guarantee: "If your vocational profile does not provide meaningful insight, we will refund your purchase. No questions asked." (30-day window)

### Organization Pricing Page (Separate)
- Per-seat pricing with volume tier slider
- ROI framing: "Less than the cost of one career counseling session per student"
- Case study callouts from pilot organizations
- "Schedule a Demo" CTA for Institution tier
- Testimonials from youth pastors and career center directors

---

## 10. Key Pricing Metrics to Track

| Metric | Target | Why It Matters |
|--------|--------|----------------|
| Free tier completion rate | >60% | Measures product engagement |
| Free-to-Discern conversion | 15-25% | Core monetization efficiency |
| Discern-to-Illuminate upgrade | 5-10% | Upsell effectiveness |
| B2B avg. deal size | $400-800 | Revenue per org |
| Cost per free assessment | <$0.15 | Unit economics sustainability |
| Paid ARPU | $22-28 | Blended individual revenue |
| B2B seat utilization | >70% | Org value realization |
| NPS (post-assessment) | >50 | Product-market fit indicator |
| 6-month retake rate | >10% | Retention and longitudinal value |

---

## 11. Price Sensitivity Hypotheses (To Validate)

The following hypotheses should be tested via Van Westendorp survey with the waitlist audience before finalizing prices:

### Hypothesis 1: Students are price-sensitive below $25
- **Test:** Van Westendorp with 17-21 year old respondents
- **Expected:** OPP (Optimal Price Point) between $12-20 for full assessment
- **If confirmed:** $19 Discern price is well-positioned

### Hypothesis 2: Parents and adults will pay 2x student price
- **Test:** Van Westendorp with 25-45 year old respondents and parents of 17-21 year olds
- **Expected:** OPP between $25-45
- **If confirmed:** $39 Illuminate price is well-positioned

### Hypothesis 3: Organizations anchor to "per student" budget lines
- **Test:** Interviews with 5-10 youth pastors and career center directors
- **Expected:** Budget mental model is $5-15 per student for assessment tools
- **If confirmed:** $6-8/seat pricing is competitive

### Hypothesis 4: Free tier drives B2B adoption
- **Test:** Track how many B2B leads originate from individual free users
- **Expected:** >30% of B2B inquiries come from orgs where members already used the free tier
- **If confirmed:** Free tier is the growth engine, invest in it

---

## 12. Competitive Positioning Summary

```
                  Price
                   ^
           $50+   |                        [Career Counseling Session]
                  |                              $75-200/hr
                  |
           $39    |  ........................[ILLUMINATE]
                  |                   (audio + coursework + retake)
                  |
           $20    |  [StrengthsFinder $19.99]...[DISCERN $19]
                  |  [Enneagram $12]
                  |  [DISC $20-40]
                  |
            $0    |  [16Personalities Free].....[REFLECT Free]
                  |
                  +------------------------------------------>
                     Single Label    Multi-Dimensional    Ongoing
                     Output          Analysis             Formation
                                     Depth --->
```

**Positioning statement:** "The depth of career counseling at the price of an assessment, with the accessibility of a free starting point."

---

## 13. Revenue Projections (Year 1)

### Conservative Scenario

| Channel | Volume | Price | Revenue |
|---------|--------|-------|---------|
| Free assessments (Reflect) | 5,000 | $0 | $0 |
| Discern conversions (20% of free) | 1,000 | $19 | $19,000 |
| Illuminate conversions (5% of free) | 250 | $39 | $9,750 |
| B2B Community (15 orgs x 25 avg seats) | 375 seats | $8/seat | $3,000 |
| B2B Formation (3 orgs x 100 avg seats) | 300 seats | $6/seat | $1,800 |
| **Total Year 1** | | | **$33,550** |
| **AI costs (est.)** | | | **($2,500)** |
| **Gross margin** | | | **$31,050 (92.5%)** |

### Optimistic Scenario

| Channel | Volume | Price | Revenue |
|---------|--------|-------|---------|
| Free assessments (Reflect) | 15,000 | $0 | $0 |
| Discern conversions (25% of free) | 3,750 | $19 | $71,250 |
| Illuminate conversions (8% of free) | 1,200 | $39 | $46,800 |
| B2B Community (40 orgs x 30 avg seats) | 1,200 seats | $8/seat | $9,600 |
| B2B Formation (10 orgs x 120 avg seats) | 1,200 seats | $6/seat | $7,200 |
| B2B Institution (2 orgs x 500 avg seats) | 1,000 seats | $4/seat | $4,000 |
| **Total Year 1** | | | **$138,850** |
| **AI costs (est.)** | | | **($8,500)** |
| **Gross margin** | | | **$130,350 (93.9%)** |

---

## 14. Pricing Checklist

### Before Setting Prices
- [x] Defined target customer personas (5 personas identified)
- [x] Researched competitor pricing (StrengthsFinder, Enneagram, DISC, career counseling)
- [x] Identified value metric (per assessment / per seat)
- [ ] Conducted willingness-to-pay research (Van Westendorp -- do during pre-launch)
- [x] Mapped features to tiers

### Pricing Structure
- [x] Chosen number of tiers (3 B2C + 3 B2B)
- [x] Differentiated tiers clearly (analysis depth, audio mode, coursework)
- [x] Set price points based on competitive analysis and value
- [x] Created annual billing strategy (B2B annual-only)
- [x] Planned enterprise/custom tier (Institution)

### Validation (Pre-Launch TODOs)
- [ ] Run Van Westendorp survey with 100-200 target users on waitlist
- [ ] Validate B2B pricing with 5-10 youth pastor / career center interviews
- [ ] Confirm unit economics with actual Claude API usage in testing
- [ ] Set up Stripe products and pricing in test mode
- [ ] Build pricing page with recommended design
- [ ] Set up analytics tracking for all conversion funnel metrics

---

## 15. Key Decisions Summary

| Decision | Recommendation | Rationale |
|----------|---------------|-----------|
| B2C model | One-time purchase (not subscription) | Assessment is a discrete event; subscription feels misaligned for target demo |
| Free tier | Full assessment, condensed results | Maximizes viral loop and B2B seeding; low marginal cost |
| B2C anchor price | $19 (Discern tier) | StrengthsFinder parity; affordable for target demo; healthy margin |
| B2C premium | $39 (Illuminate tier) | 2x anchor; justified by audio mode + coursework + retake |
| B2B model | Per-seat, annual billing | Matches org budget mental model; simplifies procurement |
| B2B pricing | $8/seat (small), $6/seat (mid), custom (large) | Volume tiers reward growth; competitive with assessment tools |
| Free-to-paid gate | Analysis depth (not question access) | Users must experience the full assessment to feel upgrade pull |
| Launch strategy | Founding member discount, then soft launch at 25% off, then full price | De-risks pricing, builds early community, validates demand |
| Upsell priority | Free > Discern > B2B > Coursework subscription | Ordered by expected volume and conversion probability |
| Recurring revenue | Coursework subscription ($9.99/mo) -- Phase 6 | Deferred until post-assessment engagement is validated |
