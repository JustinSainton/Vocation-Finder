# Vocational Discernment Assessment Platform

**Type:** Feature — Full Platform Build
**Date:** 2026-03-02
**Status:** Planning
**Deepened:** 2026-03-02 — Enhanced with 16+ parallel research agents

---

## Enhancement Summary

**Deepened on:** 2026-03-02
**Research agents used:** 16+ (architecture-strategist, security-sentinel, performance-oracle, data-integrity-guardian, pattern-recognition-specialist, best-practices-researcher x2, frontend-design, stripe-best-practices, pricing-strategy, react-native-best-practices, signup-flow-cro, onboarding-cro, greenlight/app-store-compliance, framework-docs-researcher, context7)

### Key Improvements
1. **Dual billing architecture required** — Apple IAP (StoreKit 2) for iOS, Stripe for web. Stripe-only will be rejected by Apple.
2. **AI integration via Laravel AI SDK** — Official first-party `laravel/ai` package with built-in Agents, Transcription, Audio (TTS), structured output via JsonSchema, model failover, and 4-layer output validation.
3. **Audio latency budget revised** — Realistic target is 3-5 seconds (not <3s) with parallel pipeline optimization.
4. **37 security findings** — 6 Critical, 11 High (rate limiting, encryption at rest, audio data consent, GDPR compliance).
5. **3 App Store showstoppers** — PrivacyInfo.xcprivacy, StoreKit 2 for IAP, account deletion flow.
6. **CRO architecture** — Never gate results behind signup; inline post-assessment account creation; magic link as primary auth alternative.
7. **Performance budget** — TTI <2s, AudioOrb locked 60fps via Reanimated worklets, uncontrolled TextInputs for assessment flow.

### New Considerations Discovered
- Users-to-orgs relationship must be many-to-many (a user can belong to multiple organizations)
- Conversation turns need their own table (not a JSON column on conversation_sessions)
- `dimensional_mapping` on vocational_profiles should be first-class columns, not JSON
- Free tier uses lighter Claude prompt (~$0.05-0.10/assessment) for condensed summary
- 17+ age rating required; age gate at registration needed for minors
- Audio data transmission requires explicit consent flow before first recording

### Supporting Research Documents
- `specs/technical-research-best-practices.md` — Laravel+Claude integration, queue architecture, AI prompting
- `specs/conversational-audio-ai-research.md` — Audio recording, STT/TTS, AudioOrb, latency optimization
- `plans/stripe-billing-architecture.md` — Stripe product/price structure, webhook handling
- `plans/pricing-strategy.md` — Tier structure, B2C/B2B pricing, revenue projections
- `plans/mobile-performance-optimization.md` — FPS, TTI, bundle size, memory management
- `specs/signup-and-onboarding-cro.md` — Guest-to-user conversion, org admin onboarding
- `plans/app-store-compliance-review.md` — 3 critical, 6 high-risk findings
- `specs/framework-documentation.md` — Laravel 11+, Expo SDK 52+, AI library documentation

---

## Overview

Build a vocational discernment assessment platform that integrates the sacred/secular divide into a cohesive understanding of calling. The platform helps people discover how their unique makeup — body, soul, and spirit — aligns with vocational pathways, reframing all work as ministry. Through a 20-question assessment analyzed by AI, users receive multi-dimensional vocational guidance that goes far beyond personality-test-style categorization.

The platform consists of:
- **Laravel 11+ API** — backend serving both mobile and web
- **Expo React Native app** — iOS & Android with a conversational audio-first interface
- **SaaS web application** — form-based assessment + organization admin dashboards
- **AI analysis engine** — Claude API (via Laravel AI SDK `laravel/ai`) for deep, multi-dimensional vocational mapping

---

## Problem Statement

Existing career assessment tools reduce people to single categories ("You're an INTJ" or "You're a Helper type"). They ignore the theological reality that vocation is multi-dimensional and that all work is service. The sacred/secular divide causes believers to see Sunday worship as spiritual and Monday-Friday work as secular — this assessment seeks to demolish that wall.

No tool currently exists that:
1. Treats vocational calling as multi-dimensional (primary domain + mode of work + secondary orientation)
2. Uses AI to analyze open-ended narrative responses rather than multiple-choice reductions
3. Provides a contemplative, premium assessment experience that honors the gravity of vocational discernment
4. Serves both individuals and organizations (churches, universities) as a SaaS platform

---

## Critical Note: Tech Stack Migration

The design spec (`specs/design-and-interface.md`, lines 338-358) references Bubble.io implementation. This is legacy from an earlier prototype phase. **The actual stack is Laravel API + Expo React Native + SaaS web app.** All Bubble.io references should be disregarded. The design principles and UX flow from that spec remain fully valid — only the implementation platform changes.

---

## Technical Approach

### Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                          │
│                                                         │
│  ┌──────────────────┐    ┌──────────────────────────┐   │
│  │  Expo React      │    │  Web SaaS Application    │   │
│  │  Native App      │    │  (Laravel Blade/Inertia  │   │
│  │  (iOS + Android) │    │   or separate SPA)       │   │
│  │                  │    │                          │   │
│  │  - Audio Conv.   │    │  - Form Assessment       │   │
│  │  - Form Assess.  │    │  - Org Admin Dashboard   │   │
│  │  - Results View  │    │  - Results View          │   │
│  └────────┬─────────┘    └────────────┬─────────────┘   │
│           │                           │                  │
└───────────┼───────────────────────────┼──────────────────┘
            │          API Layer        │
            ▼                           ▼
┌─────────────────────────────────────────────────────────┐
│                  LARAVEL 11+ API                         │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ Auth          │  │ Assessment   │  │ AI Analysis  │  │
│  │ (Sanctum)     │  │ Engine       │  │ (Laravel AI) │  │
│  ├──────────────┤  ├──────────────┤  ├──────────────┤  │
│  │ Org/Tenant   │  │ Results &    │  │ Audio        │  │
│  │ Management   │  │ PDF Export   │  │ Processing   │  │
│  ├──────────────┤  ├──────────────┤  ├──────────────┤  │
│  │ Billing      │  │ Coursework   │  │ Queue        │  │
│  │ (Dual: IAP   │  │ Engine       │  │ Workers      │  │
│  │  + Cashier)  │  │              │  │ (Horizon)    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                         │
└──────────────────────────┬──────────────────────────────┘
                           │
            ┌──────────────┼──────────────┐
            ▼              ▼              ▼
     ┌────────────┐ ┌────────────┐ ┌────────────┐
     │ PostgreSQL │ │ Redis      │ │ S3/Storage │
     │ Database   │ │ Cache/Queue│ │ Audio/PDF  │
     └────────────┘ └────────────┘ └────────────┘
```

### Research Insights: Architecture

**From architecture-strategist:**
- Use a unified API surface (single `/api/v1/` prefix) — no separate mobile vs. web API controllers
- All clients hit the same endpoints; Sanctum handles token auth (mobile) and SPA cookie auth (web)
- Consider API versioning from day one (`/api/v1/`) to support future breaking changes

**From security-sentinel (6 Critical, 11 High findings):**
- Rate limiting required on all AI endpoints (Claude API costs add up under abuse)
- Assessment responses must be encrypted at rest (sensitive personal data)
- Audio data transmission requires explicit user consent before first recording
- Implement GDPR data portability and right-to-deletion from launch
- Add Content-Security-Policy headers for the web SaaS

**From performance-oracle:**
- Queue sizing: 2 named queues — `ai-analysis` (long-running, 120s timeout) and `default` (standard jobs)
- Redis Horizon for queue monitoring and scaling
- Target: AI analysis completes within 30-45 seconds after submission
- Implement webhook retry with exponential backoff for both Stripe and Apple callbacks

---

### Monorepo Structure

```
vocation-finder/
├── api/                          # Laravel 11+ Application
│   ├── app/
│   │   ├── Actions/              # Single-purpose business operations
│   │   │   ├── Assessment/
│   │   │   │   ├── StartAssessmentAction.php
│   │   │   │   ├── SaveAnswerAction.php
│   │   │   │   ├── CompleteAssessmentAction.php
│   │   │   │   └── GenerateResultsAction.php
│   │   │   ├── Audio/
│   │   │   │   ├── TranscribeAudioAction.php
│   │   │   │   ├── GenerateQuestionAudioAction.php
│   │   │   │   └── ProcessConversationTurnAction.php
│   │   │   ├── AI/
│   │   │   │   ├── AnalyzeResponsesAction.php
│   │   │   │   ├── GenerateFollowUpAction.php
│   │   │   │   └── SynthesizeVocationalProfileAction.php
│   │   │   └── Organization/
│   │   │       ├── InviteMemberAction.php
│   │   │       └── GenerateOrgReportAction.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Organization.php
│   │   │   ├── OrganizationUser.php          # Pivot for many-to-many
│   │   │   ├── Assessment.php
│   │   │   ├── Question.php
│   │   │   ├── QuestionCategory.php
│   │   │   ├── Answer.php
│   │   │   ├── VocationalProfile.php
│   │   │   ├── VocationalCategory.php
│   │   │   ├── ConversationSession.php
│   │   │   ├── ConversationTurn.php          # Own table, not JSON
│   │   │   └── CourseEnrollment.php
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/           # Versioned API
│   │   │   │   ├── AssessmentController.php
│   │   │   │   ├── AudioConversationController.php
│   │   │   │   ├── ResultsController.php
│   │   │   │   ├── OrganizationController.php
│   │   │   │   └── BillingController.php
│   │   │   └── Resources/
│   │   │       ├── AssessmentResource.php
│   │   │       ├── QuestionResource.php
│   │   │       └── VocationalProfileResource.php
│   │   ├── Agents/
│   │   │   └── VocationalAnalysisAgent.php    # Laravel AI Agent (php artisan make:agent)
│   │   ├── Services/
│   │   │   └── PdfGenerationService.php       # Only non-AI service remaining
│   │   └── Jobs/
│   │       ├── AnalyzeAssessmentJob.php
│   │       ├── TranscribeAudioJob.php
│   │       └── GeneratePdfJob.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   │       └── QuestionSeeder.php       # Seeds all 20 questions
│   ├── routes/
│   │   └── api.php
│   └── config/
│       └── vocation.php                 # App-specific config
│
├── mobile/                       # Expo React Native App
│   ├── app/                      # Expo Router (file-based routing)
│   │   ├── (auth)/
│   │   │   ├── login.tsx
│   │   │   └── register.tsx
│   │   ├── (assessment)/
│   │   │   ├── index.tsx         # Landing / Threshold
│   │   │   ├── orientation.tsx   # Orientation page
│   │   │   ├── conversation.tsx  # Audio conversation interface
│   │   │   ├── written.tsx       # Form-based assessment (ONE screen, internal nav)
│   │   │   ├── synthesis.tsx     # Transition / Synthesis Pause
│   │   │   └── results.tsx       # Vocational Articulation
│   │   ├── (dashboard)/
│   │   │   ├── index.tsx         # Home / past assessments
│   │   │   └── profile.tsx
│   │   └── _layout.tsx
│   ├── components/
│   │   ├── assessment/
│   │   │   ├── QuestionCard.tsx
│   │   │   ├── AudioOrb.tsx      # Reanimated worklets, 60fps
│   │   │   ├── ProgressIndicator.tsx
│   │   │   └── ResultsDocument.tsx
│   │   └── ui/
│   │       ├── Button.tsx        # Direct imports, NO barrel exports
│   │       ├── TextInput.tsx
│   │       └── Typography.tsx
│   ├── hooks/
│   │   ├── useAudioConversation.ts
│   │   ├── useAssessment.ts
│   │   └── useHaptics.ts
│   ├── stores/
│   │   ├── assessmentStore.ts    # Zustand with persist + granular selectors
│   │   ├── syncStore.ts          # Offline sync queue
│   │   └── authStore.ts
│   └── services/
│       ├── api.ts                # API client
│       └── audio.ts              # Audio recording/playback
│
├── web/                          # SaaS Web Application (Inertia or SPA)
│   └── ...
│
├── specs/                        # Spec documents
│   ├── questions.md
│   ├── categories.md
│   ├── design-and-interface.md
│   ├── technical-research-best-practices.md
│   ├── conversational-audio-ai-research.md
│   ├── signup-and-onboarding-cro.md
│   └── framework-documentation.md
│
└── plans/                        # Plans and strategy documents
    ├── feat-vocational-discernment-assessment-platform.md  # This file
    ├── stripe-billing-architecture.md
    ├── pricing-strategy.md
    ├── mobile-performance-optimization.md
    └── app-store-compliance-review.md
```

### Research Insights: Monorepo Structure

**From pattern-recognition-specialist:**
- NO barrel exports (`index.ts`) in component directories — use direct imports for tree shaking
- Enforce with `eslint-plugin-no-barrel-files`
- Use `packages/shared-types/` for TypeScript types shared between mobile and web

**From mobile-performance-optimization:**
- The 20-question flow MUST be ONE screen (`written.tsx`) with internal state navigation, NOT 20 separate screens in the nav stack
- Enable React Compiler (`experiments.reactCompiler: true` in app.json) for automatic memoization
- Enable tree shaking via Metro config (`EXPO_UNSTABLE_TREE_SHAKING=1`)

---

### Implementation Phases

---

#### Phase 1: Foundation (Weeks 1-3)

**Goal:** Laravel API scaffolding, database schema, authentication, and question seeding.

##### 1.1 Laravel Project Setup
- `api/` — Laravel 11+ with Sanctum, queues (Redis + Horizon), PostgreSQL
- Docker/Sail for local development
- Environment configuration for Claude API, Whisper API, TTS API keys
- Install Laravel AI SDK: `composer require laravel/ai`
- Configure AI providers in `config/ai.php` (Anthropic key, default model)

##### 1.2 Database Schema & Migrations

```mermaid
erDiagram
    users {
        uuid id PK
        string name
        string email
        string password
        string role "individual|member|admin|super_admin"
        timestamp email_verified_at
        timestamps
    }

    organizations {
        uuid id PK
        string name
        string slug
        string type "church|university|nonprofit|enterprise"
        json settings
        string stripe_id
        string subscription_status
        timestamps
    }

    organization_user {
        uuid id PK
        uuid organization_id FK
        uuid user_id FK
        string role "member|admin"
        timestamps
    }

    assessments {
        uuid id PK
        uuid user_id FK "nullable for guests"
        uuid organization_id FK "nullable"
        string mode "conversation|written"
        string status "in_progress|analyzing|completed|abandoned"
        string guest_token "nullable, for guest sessions"
        json metadata "device, duration, etc."
        timestamp started_at
        timestamp completed_at
        timestamps
    }

    question_categories {
        uuid id PK
        string name "Service Orientation, Problem-Solving Draw, etc."
        string slug
        text description
        text theological_basis
        text what_it_reveals
        integer sort_order
    }

    questions {
        uuid id PK
        uuid category_id FK
        text question_text
        text conversation_prompt "Reworded for audio conversation"
        json follow_up_prompts "AI follow-up question templates"
        integer sort_order
    }

    answers {
        uuid id PK
        uuid assessment_id FK
        uuid question_id FK
        text response_text
        text audio_transcript "If from conversation mode"
        string audio_storage_path "S3 path to audio file"
        json ai_preliminary_analysis "Per-answer pattern detection"
        integer duration_seconds
        timestamps
    }

    conversation_sessions {
        uuid id PK
        uuid assessment_id FK
        string status "active|paused|completed"
        integer current_question_index
        timestamps
    }

    conversation_turns {
        uuid id PK
        uuid conversation_session_id FK
        string role "user|assistant|system"
        text content
        string audio_storage_path "nullable"
        integer duration_seconds "nullable"
        integer sort_order
        timestamps
    }

    vocational_profiles {
        uuid id PK
        uuid assessment_id FK
        text opening_synthesis
        text vocational_orientation
        json primary_pathways "Array of pathway objects"
        text specific_considerations
        json next_steps "Ordered list"
        json ai_analysis_raw "Full Claude response"
        string primary_domain "First-class column"
        string mode_of_work "First-class column"
        string secondary_orientation "First-class column"
        json category_scores "Scoring across 17 categories"
        text ministry_integration "How vocation IS ministry"
        timestamps
    }

    vocational_categories {
        uuid id PK
        string name "Healing & Care, Teaching & Formation, etc."
        string slug
        text description
        text ministry_connection "How this category is ministry"
        json career_pathways "Example careers"
        integer sort_order
    }

    users ||--o{ assessments : "takes"
    users }o--o{ organizations : "many-to-many via organization_user"
    organization_user }o--|| organizations : "belongs to"
    organization_user }o--|| users : "belongs to"
    assessments ||--o{ answers : "contains"
    assessments ||--o| conversation_sessions : "has"
    assessments ||--o| vocational_profiles : "produces"
    conversation_sessions ||--o{ conversation_turns : "contains"
    questions }o--|| question_categories : "belongs to"
    answers }o--|| questions : "for"
```

### Research Insights: Data Model

**From data-integrity-guardian (13 issues found):**
1. **Users-to-orgs must be many-to-many** — A user can belong to multiple organizations (a student at a university AND a member of a church). Use a pivot table `organization_user`.
2. **Conversation turns must be their own table** — JSON column on `conversation_sessions` will hit PostgreSQL TOAST limits, cannot be indexed, and makes querying individual turns impossible.
3. **`dimensional_mapping` should be first-class columns** — `primary_domain`, `mode_of_work`, `secondary_orientation` on `vocational_profiles` as VARCHAR columns, not JSON. Enables SQL queries and indexing.
4. **Assessments need `organization_id`** — Track which org context an assessment was taken in for aggregate reporting.
5. **Add `guest_token` to assessments** — For guest sessions before account creation. Nullable, indexed.
6. **UUID v7 recommended over v4** — Monotonically increasing UUIDs work better as primary keys (no index fragmentation).
7. **Add `deleted_at` (soft deletes) to users and assessments** — Required for account deletion compliance while maintaining data integrity for org reports.

**From security-sentinel:**
- Encrypt `response_text` and `audio_transcript` columns at rest (Laravel's `encrypted` cast)
- Add retention policy columns: `data_retention_until` on assessments
- Audio files in S3 should use server-side encryption (SSE-S3 or SSE-KMS)

##### 1.3 Question Seeder
- Seed all 20 questions from `specs/questions.md` with categories
- Seed all 17 vocational categories from `specs/categories.md`
- Include conversation-friendly rewording of each question
- Include follow-up prompt templates per question

##### 1.4 Authentication
- Laravel Sanctum for API token auth (mobile) + SPA cookie auth (web)
- Registration, login, password reset
- **Sign in with Apple** (required if any social auth is offered — App Store Guideline 4.8)
- **Google Sign In** (via Laravel Socialite)
- **Magic link authentication** as primary alternative (single email field, lowest friction)
- Organization invitation flow
- Guest assessment support (create account after completion to save results)
- **Account deletion flow** (App Store Guideline 5.1.1 — CRITICAL)
- **Age gate at registration** (confirm 17+ before account creation)

### Research Insights: Authentication

**From app-store-compliance-review (CRITICAL):**
- Sign in with Apple MUST be offered if any social login exists — use `expo-apple-authentication`
- Account deletion MUST be implemented — delete all user data, assessment responses, audio, profiles
- Account deletion must be discoverable in-app (not hidden behind support emails)

**From signup-and-onboarding-cro:**
- Never gate results behind signup — guest assessment with post-assessment account creation
- Inline signup form on Page 6 (Continuation/Release): Email + Password + Name (optional) = 2-3 fields max
- "Save my results" (not "Sign Up Free") — the action is preserving something meaningful
- "Email results to yourself" as a low-friction soft capture alternative
- Magic link is tonally appropriate — feels like receiving a letter
- No CAPTCHA — the 30-45 minute assessment is itself proof of humanity
- Guest sessions persist 30 days minimum
- No email verification required before viewing results

---

#### Phase 2: Assessment Engine (Weeks 3-5)

**Goal:** Written assessment flow + AI analysis pipeline.

##### 2.1 Written Assessment API
- `POST /api/v1/assessments` — Start assessment (works for guests with `guest_token`)
- `POST /api/v1/assessments/{id}/answers` — Save answer (autosave-friendly, debounced)
- `PATCH /api/v1/assessments/{id}/answers/{answerId}` — Update answer
- `POST /api/v1/assessments/{id}/complete` — Trigger analysis
- `GET /api/v1/assessments/{id}/results` — Get vocational profile

##### 2.2 AI Analysis Pipeline

The analysis happens in a queued job after assessment completion:

```
CompletedAssessment
    → AnalyzeAssessmentJob (queued on 'ai-analysis', 120s timeout)
        → VocationalAnalysisAgent (Laravel AI SDK)
            1. Per-answer pattern detection (6 analysis dimensions)
            2. Cross-answer synthesis
            3. Multi-dimensional vocational mapping
            4. Narrative output generation
            5. 4-layer output validation
        → VocationalProfile (saved)
        → Notification to user
```

**Claude Prompt Architecture (via Laravel AI SDK):**

The AI prompt is structured in two phases:

**Phase A — Pattern Analysis:**
Given all 20 responses, identify patterns across the 6 analysis dimensions (Service Orientation, Problem-Solving, Energy Sources, Values & Decision-Making, Response to Obstacles, Vision & Legacy). Output structured JSON.

**Phase B — Narrative Synthesis:**
Given the pattern analysis, generate the multi-dimensional vocational profile as narrative text. Map to the 17 vocational categories but express as personal, specific guidance (not category labels). Include: Opening synthesis, Vocational orientation narrative, Primary pathways, Specific considerations, Next steps, Ministry integration paragraph.

### Research Insights: AI Analysis

**From technical-research-best-practices (Laravel AI SDK integration):**
- Use Laravel AI SDK (`laravel/ai`) — the official first-party AI package for Laravel
- Built-in Agent classes via `php artisan make:agent VocationalAnalysis` with `Promptable` trait
- Structured output via `->schema(JsonSchema $schema)` with automatic validation
- Built-in `Transcription::fromUpload($audio)` replaces custom WhisperTranscriptionService
- Built-in `Audio::of('Your text here')` replaces custom TextToSpeechService
- Multi-provider support with automatic failover: `->failover(['anthropic', 'openai'])`
- Streaming via `->stream()` and broadcasting via `->broadcast(Channel $channel)`
- Testing fakes via `Ai::fake()` — no external API calls needed in tests
- Two-phase prompting is correct — Phase A outputs structured JSON, Phase B uses it as input for narrative
- Cost estimate: ~$0.10-0.16 per full assessment with Claude Sonnet; ~$0.30-0.50 with Opus
- Free tier uses a lighter prompt (Haiku or single-phase Sonnet) at ~$0.05-0.10

**4-Layer Output Validation (from best-practices research):**
1. **JSON Schema Validation** — Laravel AI SDK enforces structure on Phase A output via `->schema()`
2. **Content Safety Filter** — Check for harmful advice, discriminatory content, clinically diagnostic language
3. **Completeness Check** — Verify all required output sections are present and non-empty
4. **Theological Alignment** — Verify ministry integration section exists and is substantive

**From app-store-compliance-review:**
- AI-generated content requires moderation — implement server-side output filtering
- Add "Report an issue with this result" mechanism in results view
- Add visible disclaimer: "This vocational profile was generated by AI... intended as a tool for reflection and discernment, not as professional career counseling."

**Cost Optimization (from research):**
- Cache the system prompt (Claude's prompt caching reduces cost ~90% on system prompt tokens)
- Use Sonnet for Phase A (structured analysis), Sonnet or Opus for Phase B (narrative quality)
- Batch assessment processing during off-peak hours if queue backs up
- Free tier: single-phase Haiku/Sonnet with condensed 2-3 paragraph output

##### 2.3 Results & Export
- `GET /api/v1/assessments/{id}/results` — Full vocational profile
- `GET /api/v1/assessments/{id}/results/pdf` — PDF generation (queued)
- `POST /api/v1/assessments/{id}/results/email` — Email results
- PDF includes subtle footer: "Return to your results anytime at [url]"

---

#### Phase 3: Expo Mobile App — Written Interface (Weeks 4-7)

**Goal:** Beautiful, contemplative form-based assessment in the mobile app.

##### 3.1 Expo Project Setup
- Expo SDK 52+ with Expo Router
- NativeWind/Tailwind CSS v4 for styling
- Zustand for state management with AsyncStorage persistence
- Custom fonts: Literata (serif body) + Satoshi (sans-serif UI)
- React Compiler enabled (`experiments.reactCompiler: true`)
- Tree shaking enabled (`EXPO_UNSTABLE_TREE_SHAKING=1`)
- `PrivacyInfo.xcprivacy` created from project start

##### 3.2 Design System Implementation

Following the spec's non-negotiable design principles:

- **Colors:** Off-white background (`#FAFAF7`), warm near-black text (`#1C1917`), muted accent (`#C4C0B6`)
- **Typography:** Literata serif primary at comfortable reading sizes (18-20px body, line-height 1.7), Satoshi sans-serif for UI elements only
- **Layout:** Single-column, max-width 640px equivalent, generous vertical spacing (32-48px between sections)
- **No:** gradients, shadows, cards, icons in results, charts, category labels

### Research Insights: Design System

**From frontend-design skill:**
- Use Literata (Google Fonts, open source) over Source Serif Pro — better screen readability at body sizes
- Use Satoshi (free for commercial use) as the sans-serif — humanist warmth without being clinical
- Background: `#FAFAF7` (warm off-white, not blue-white)
- Text: `#1C1917` (Tailwind stone-950, warmer than pure near-black)
- Accent: `#A8A29E` (stone-400) for dividers and secondary text
- Button: `#1C1917` background, `#FAFAF7` text — inverted, minimal
- Touch targets: 48px minimum height for all interactive elements
- The assessment flow should feel like turning pages in a book — use `animation: 'fade'` for screen transitions

##### 3.3 Assessment Flow Screens

Per the design spec's 6 pages:
1. **Landing/Threshold** — Headline + framing + "Begin discernment" button
2. **Orientation** — Permission-setting + symbolic honesty checkbox
3. **Assessment Flow** — ONE screen with internal navigation, one question visible at a time, auto-expanding text input (uncontrolled with `defaultValue`), autosave via debounced Zustand sync, "Question X of 20" indicator, no validation feedback
4. **Transition/Synthesis Pause** — Calm paragraph + Continue button (no loading indicators)
5. **Vocational Articulation** — Scrollable letter-style document (ScrollView, not FlatList), PDF download, email
6. **Continuation/Release** — Inline account creation form, soft next steps, "Save my results" / "Download PDF" / "Email results to myself"

### Research Insights: Assessment Flow

**From mobile-performance-optimization:**
- Use uncontrolled TextInput with `defaultValue` (not `value`) for the 20 question inputs — eliminates JS-to-native round-trip flicker
- Debounce autosave at 500ms — saves to Zustand (which persists to AsyncStorage)
- Use `InteractionManager.runAfterInteractions()` for API syncs after screen transitions
- Assessment flow is ONE screen (`written.tsx`) — not 20 nav stack entries

**From signup-and-onboarding-cro (Page 6 architecture):**
- Never gate results behind signup
- Page 6 inline signup form: Email + Password + Name(optional), styled in design system
- Social auth (Google + Apple) below the email form, muted styling: "Continue with Google"
- "Email results to yourself" as single-field soft capture alternative
- Privacy assurance: "Your responses and results are kept private." (one line, no icon)
- No onboarding checklist, no feature tour after account creation

##### 3.4 Haptic Feedback
- Light haptic on "Continue" button press
- Medium haptic on assessment completion
- Subtle haptic on screen transitions (not every interaction)
- No haptics during typing/answering (preserves contemplative feel)

### Research Insights: Haptic Feedback

**From conversational-audio-ai-research:**
- Use `expo-haptics` for all haptic patterns
- `Haptics.impactAsync(ImpactFeedbackStyle.Light)` for Continue
- `Haptics.notificationAsync(NotificationFeedbackType.Success)` for assessment completion
- In conversation mode: light haptic when AI starts listening, medium when AI starts speaking
- Triggered from Reanimated worklets via `scheduleOnRN()` (not `runOnJS`)

---

#### Phase 4: Audio Conversation Interface (Weeks 6-10)

**Goal:** The primary "magical" conversational interface on mobile.

##### 4.1 Audio Pipeline Architecture

```
User speaks → expo-audio recording (16kHz mono AAC)
    → Upload to Laravel API → Whisper API (transcription)
    → Laravel AI SDK Agent (analysis + follow-up determination)
    → TTS API (OpenAI TTS "nova" voice) → Stream audio to app → Playback
```

##### 4.2 Conversation Flow Logic

The conversation controller manages:
1. **Greeting & orientation** — Pre-cached TTS audio introduces the process
2. **Question asking** — Pre-cached TTS reads question aloud
3. **Active listening** — App records user's verbal response (expo-audio)
4. **Transcription** — Whisper API transcribes to text
5. **Follow-up determination** — Claude evaluates if response is sufficient
6. **Follow-up or advance** — Either ask clarifying question or move to next
7. **Wrap-up** — After question 20, graceful closing and transition to results

##### 4.3 Audio Interface Component ("AudioOrb")

The centerpiece visual element during conversation:
- Animated circular element responding to audio levels via `useSharedValue` (Reanimated)
- Subtle pulsing animation when AI is "speaking" — all on UI thread
- Gentle haptic feedback synchronized with state changes via `scheduleOnRN()`
- Minimal surrounding UI (question count only, no text input visible)
- React Native Reanimated for locked 60fps animations
- States: `idle`, `listening`, `speaking`, `processing`

### Research Insights: AudioOrb & Audio Pipeline

**From conversational-audio-ai-research:**
- Record at 16kHz mono AAC (not 44.1kHz stereo) — STT engines expect this, reduces upload size
- Use `expo-audio` (new hooks-based API), not legacy `expo-av`
- Pre-cache all question TTS audio at conversation start (20 audio files, ~2-3MB total)
- Use OpenAI TTS with "nova" voice — warm, natural, $15/1M chars (~$0.02-0.10 per conversation)
- Realistic end-to-end latency: 3-5 seconds with parallel pipeline (not <3s as originally targeted)

**Latency Optimization Pipeline:**
```
User stops speaking
    → Upload audio to API (parallel with...)
    → Whisper transcription (~1-2s)
    → Claude follow-up check (~1-2s, streaming)
    → TTS generation (~0.5-1s, streaming)
    → Play response
Total: 3-5 seconds (optimized) vs 5-7 seconds (sequential)
```

**From mobile-performance-optimization:**
- AudioOrb MUST use Reanimated `useSharedValue` + `useAnimatedStyle` — all animation on UI thread
- Audio level updates flow through shared values, not `useState`
- Clean up recordings on unmount — `stopAndUnloadAsync()` in useEffect cleanup
- Upload audio files immediately, keep only metadata in memory (prevent memory leaks)
- Unload previous `Audio.Sound` before loading new TTS response

**From app-store-compliance-review:**
- Microphone purpose string must explain: feature, data transmission, optional nature
- Audio data consent flow required BEFORE first recording
- If TTS continues in background: declare `UIBackgroundModes: audio`

##### 4.4 Conversation API Endpoints
- `POST /api/v1/conversations/start` — Initialize conversation session
- `POST /api/v1/conversations/{id}/audio` — Upload audio chunk for transcription
- `POST /api/v1/conversations/{id}/turn` — Process turn and get AI response
- `GET /api/v1/conversations/{id}/audio-response` — Stream TTS audio
- `POST /api/v1/conversations/{id}/complete` — End conversation, trigger analysis

##### 4.5 Offline Handling
- Conversation mode requires connectivity (STT + TTS + Claude)
- Written mode supports offline with Zustand + AsyncStorage persistence, syncs when connected
- Clear UI indication when offline with graceful fallback to written mode
- No error modals or "You're offline" banners during written assessment — sync silently

### Research Insights: Offline Architecture

**From mobile-performance-optimization:**
- Implement a `syncStore.ts` with a pending actions queue
- Network listener (`@react-native-community/netinfo`) triggers queue processing on reconnect
- AsyncStorage has 6MB limit on Android — sufficient for text answers but never store audio files there
- Audio files stored as temporary filesystem files, referenced by URI, uploaded when online

---

#### Phase 5: Multi-Tenant SaaS & Billing (Weeks 8-12)

**Goal:** Organizations manage members' assessments. Dual billing for iOS + web.

##### 5.1 Organization Management
- Organization CRUD with roles (admin, member) via `organization_user` pivot
- Users can belong to MULTIPLE organizations (many-to-many)
- Invitation system (email invitations with unique codes)
- Organization-specific branding (minimal: logo + name)
- Shared DB with `organization_id` on assessments (using Global Scopes for automatic filtering)

##### 5.2 Billing Architecture — DUAL SYSTEM

**CRITICAL: Apple IAP required for iOS in-app digital purchases.**

```
iOS App (StoreKit 2)                Web SaaS (Stripe)
        |                                   |
        v                                   v
  Apple Receipt -----> Laravel API <----- Stripe Webhook
                           |
                    Unified Subscription
                        State (DB)
```

**iOS (StoreKit 2):**
- Use `react-native-iap` or `expo-in-app-purchases` for StoreKit 2
- Individual assessments as non-consumable IAP
- Server-side receipt validation via Apple App Store Server Notifications v2
- Apple takes 30% commission (15% for Small Business Program under $1M/year)

**Web (Stripe via Laravel Cashier):**
- Stripe-hosted Checkout (redirect, not custom card forms)
- Dynamic payment methods enabled in Stripe Dashboard
- Organization billing managed entirely via web dashboard

**See:** `plans/stripe-billing-architecture.md` for full Stripe product/price structure, webhook handling, and implementation checklist.

##### 5.3 Pricing Tiers

**B2C Individual (one-time purchase):**
| Tier | Price | Includes |
|------|-------|----------|
| Reflect (Free) | $0 | Full 20-question written assessment + condensed 2-3 paragraph summary |
| Discern | $19 | Full multi-dimensional vocational profile, PDF, email results |
| Illuminate | $39 | Everything + audio conversation, coursework, retake after 6 months |

**B2B Organization (per-seat, annual):**
| Tier | Price | Seats |
|------|-------|-------|
| Community | $8/seat | 10-50 |
| Formation | $6/seat | 51-250 |
| Institution | Custom | 250+ |

**See:** `plans/pricing-strategy.md` for full pricing rationale, revenue projections ($33K-$139K year 1), competitive positioning, and launch strategy.

### Research Insights: Billing

**From app-store-compliance-review (CRITICAL):**
- The iOS app MUST NOT contain links, buttons, or text directing to external purchase (anti-steering)
- Organization billing via web dashboard only — never surface org pricing in the iOS app
- Google Play also requires their billing for digital goods — plan for triple billing if needed

**From stripe-best-practices skill:**
- 2 Stripe Products: "Individual Assessment" (one-time) and "Organization Plan" (subscription)
- Use lookup keys for prices (not hardcoded price IDs)
- Stripe-hosted Checkout only — never build custom card forms
- Webhook handling: `checkout.session.completed`, `invoice.paid`, `customer.subscription.updated`, `customer.subscription.deleted`

**From pricing-strategy skill:**
- Free tier is non-negotiable — removes barriers for 17-21 cohort, drives viral B2B adoption
- Free-to-paid gate is on analysis depth, not question access — user completes full assessment
- Estimated 15-25% free-to-Discern conversion (high-intent moment after 30-45 min investment)
- Cost to serve: ~$0.13-0.56 per written assessment, ~$0.18-0.76 per audio assessment

##### 5.4 Admin Dashboard
- View members and their assessment status
- Aggregate insights across organization (anonymized trends)
- Export organization reports
- Manage invitations and member roles
- Empty states as onboarding: explain what each section does, what action unlocks it

### Research Insights: Admin Onboarding

**From onboarding-cro:**
- Admin activation moment: seeing the first completed assessment result for a member
- Guided setup: single goal for first session — "Invite your first member"
- Two-step admin signup: Step 1 (Name + Email + Password), Step 2 (Org name + Org type)
- 6-email automated sequence: welcome → nudge if no invites (48h) → invite confirmation → first completion notification → aggregate insights available → stalled re-engagement (14 days)
- All emails should be plain text or minimal HTML — feel like correspondence, not campaigns

---

#### Phase 6: Post-Assessment Coursework (Weeks 10-14)

**Goal:** Formation pathways and ongoing engagement after assessment.

##### 6.1 Coursework Engine
- Courses linked to vocational categories and pathways
- Modular content (text, video, reflection prompts)
- Progress tracking per user
- Organization-assignable courses

##### 6.2 Ministry Integration Content
- Specific guidance on how their vocation IS ministry
- Practical exercises for integrating faith and work
- Community connection points

---

### Data Models — Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Multi-tenancy | Shared DB + `organization_id` + Global Scopes | Simplest for early stage, scales to thousands of orgs |
| User-org relationship | Many-to-many (pivot table) | Users belong to multiple organizations |
| Primary key | UUID v7 | Monotonically increasing, no index fragmentation, no record count leaking |
| AI integration | Laravel AI SDK (`laravel/ai`) | Official first-party, Agents, Transcription, Audio, structured output, failover, testing fakes |
| AI provider | Claude (Anthropic) via Laravel AI | Best at nuanced, empathetic narrative generation |
| STT provider | OpenAI Whisper API | Best accuracy for conversational speech |
| TTS provider | OpenAI TTS ("nova" voice) | Natural-sounding, warm voice, $15/1M chars |
| State management | Zustand + AsyncStorage persist | Lightweight, offline-first, granular selectors |
| CSS | NativeWind (Tailwind v4) | Consistent styling between web and native |
| Billing (iOS) | StoreKit 2 | Required by Apple for digital content |
| Billing (web) | Stripe via Laravel Cashier | Industry standard, Stripe-hosted Checkout |
| Package manager | pnpm | Efficient for monorepo with Expo |
| Conversation turns | Own table (not JSON) | Queryable, indexable, no TOAST limits |
| Dimensional mapping | First-class columns | SQL-queryable, not buried in JSON |

---

## Questions for Professors & Psychologists

Based on thorough review of the specs, these are questions to discuss with your co-founder's academic advisors:

### Assessment Design & Validity
1. **Psychometric validation:** How do we validate that the 20 questions reliably distinguish between the 17 vocational categories? Should we conduct a pilot study with a control group? What sample size would be needed for statistical significance?

2. **Age range expansion:** The current questions are revised for ages 17-21. What modifications would be needed for adults (25-65) who are mid-career or considering career transitions? The framing ("think about a class or project") is school-centric.

3. **Response depth calibration:** In the audio conversation interface, how do we determine when a response is "sufficient" for analysis? What constitutes an adequately rich response vs. a superficial one? Should there be a minimum response length/duration?

4. **Follow-up question protocol:** For the conversational AI, what psychological principles should govern when and how follow-up questions are asked? How do we probe deeper without leading or biasing the respondent?

5. **Test-retest reliability:** Should the assessment produce consistent results if taken again weeks or months later? How should we handle someone retaking it — show previous results, start fresh, or show how they've changed?

### Theological & Philosophical
6. **Theological framework breadth:** The current framework leans Reformed/Lutheran (cultural mandate, Providence, stations). How do we make this accessible to non-Reformed believers, Catholics, Orthodox Christians, or even non-Christian users who resonate with the concept of calling? Where is the theological boundary?

7. **Category 5 sensitivity:** The "Suffering & Limitation" questions touch on potentially painful topics (failure, family limitations). What safeguards or framing should we use to prevent the assessment from being re-traumatizing? Should there be a "skip" option?

8. **The "Pastoral & Missionary Work" category:** This is the only category that's explicitly religious. How do we handle someone who maps strongly to this but wants to avoid vocational ministry? The whole point is that all work is ministry — so is this category contradictory to the thesis?

### Output & Ethics
9. **Multi-dimensional weighting:** When the AI identifies primary domain + mode of work + secondary orientation, what happens when these conflict? (e.g., someone drawn to arts but energized by systems). How should conflicts be presented?

10. **Avoiding harm:** Could the assessment inadvertently discourage someone from a viable path? What ethical guidelines should govern the AI's output? Should there be a disclaimer that this is discernment guidance, not clinical assessment?

11. **Cultural bias:** The questions reference concepts (making a team, getting accepted somewhere) that may be culturally specific. How should we adapt for international users or different socioeconomic backgrounds?

12. **Longitudinal tracking:** Your specs mention post-assessment coursework. How should the system track vocational development over time? Is there a validated framework for measuring "calling clarity" over months/years?

### Technical Psychology
13. **Conversational bias:** In the audio conversation mode, the AI's voice, tone, and follow-up patterns could unconsciously bias responses. What research exists on interviewer effect in AI-mediated assessments? How do we control for this?

14. **Social desirability bias:** People may answer "what sounds spiritual" rather than honestly. The honesty checkbox helps, but are there established techniques (e.g., forced-choice, indirect questioning) that could reduce this without ruining the open-ended format?

15. **20-minute vs. 45-minute tension:** Your audio interface targets ~20 minutes, but the written assessment says 30-45 minutes. Does response quality differ significantly between modes? Should we adjust expectations or question count per mode?

---

## Acceptance Criteria

### Functional Requirements
- [ ] User can create an account and take the written assessment (20 questions)
- [ ] Guest users can complete assessment without account, create account after to save results
- [ ] Answers autosave on every keystroke change (debounced 500ms)
- [ ] AI analyzes all 20 responses and generates a multi-dimensional vocational profile
- [ ] Vocational profile follows the narrative format from specs (not category labels)
- [ ] AI output passes 4-layer validation (schema, safety, completeness, theology)
- [ ] User can take the assessment via audio conversation on mobile
- [ ] Audio conversation achieves 3-5 second response latency
- [ ] Results can be downloaded as PDF and emailed
- [ ] Organizations can invite members and view aggregate assessment data
- [ ] Dual billing works: StoreKit 2 for iOS, Stripe for web
- [ ] Account deletion flow is implemented and discoverable
- [ ] AI disclaimer visible on results page

### Non-Functional Requirements
- [ ] Written assessment works offline with sync (mobile)
- [ ] API response times < 200ms for standard endpoints
- [ ] AI analysis completes within 30-45 seconds of submission
- [ ] Audio transcription latency < 2 seconds
- [ ] App passes Apple App Store review (PrivacyInfo.xcprivacy, IAP, Sign in with Apple, account deletion)
- [ ] App passes Google Play review
- [ ] GDPR-compliant data handling (encryption at rest + transit, data portability, right to deletion)
- [ ] Supports 1000+ concurrent users at launch
- [ ] TTI < 2 seconds (cold start)
- [ ] AudioOrb maintains 58+ FPS during animation

### Design Requirements
- [ ] Implements the non-negotiable design philosophy from specs
- [ ] Off-white background (#FAFAF7), warm near-black text (#1C1917), no gradients/shadows/cards
- [ ] Literata serif body + Satoshi sans-serif UI only
- [ ] Single-column, centered, 640px max width
- [ ] Interface never reacts to answer content (no "Great answer!" etc.)
- [ ] Haptic feedback is subtle and intentional (not on every interaction)
- [ ] Page 6 inline signup form: 2-3 fields, no modal, no popup

### Quality Gates
- [ ] Unit tests for all Actions and Services (>80% coverage)
- [ ] Integration tests for assessment flow end-to-end
- [ ] AI prompt evaluation suite (test with sample responses)
- [ ] Accessibility audit (WCAG 2.1 AA minimum)
- [ ] Security audit (OWASP top 10, especially for personal data)
- [ ] Performance budget enforcement (TTI, FPS, bundle size)

---

## Risk Analysis & Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| AI output quality inconsistent | Medium | High | 4-layer validation, evaluation suite, human review for v1 |
| Audio latency too high (>5s) | Medium | High | Pre-cache question TTS, parallel pipeline, fallback to written |
| Apple App Store rejection | Medium | High | PrivacyInfo.xcprivacy, StoreKit 2, Sign in with Apple, account deletion, age gate, content moderation — all addressed |
| Assessment data breach | Low | Critical | Encryption at rest + transit, minimal retention, SOC 2 prep, S3 SSE |
| Theological criticism | Medium | Medium | Advisory board review, clear disclaimer, broad theological tent |
| User abandonment during 20-question flow | Medium | High | Autosave + resume, progress indication, conversation mode as faster alternative, 30-day guest session persistence |
| Dual billing complexity | High | Medium | Unified subscription state table, server-side receipt validation for both Apple and Stripe |
| Claude API cost overruns | Low | Medium | Rate limiting, lighter prompts for free tier, prompt caching, queue-based processing |

---

## Future Considerations

- **Coaching AI:** Post-assessment AI companion for ongoing vocational development
- **Community matching:** Connect people with similar vocational profiles
- **Employer integration:** Organizations use results for team building and role fit
- **Internationalization:** Multi-language support, culturally adapted questions
- **Assessment versioning:** Updated questions based on psychometric validation data
- **Marketplace integration:** Connect users with mentors, courses, and formation programs
- **Coursework subscription:** $9.99/month recurring revenue engine (Phase 6+)

---

## References

### Specs
- `specs/questions.md` — 20 assessment questions across 7 categories with AI analysis instructions
- `specs/categories.md` — 17 vocational categories with multi-dimensional output example
- `specs/design-and-interface.md` — Full design philosophy and page-by-page UI specification

### Research Documents (Generated by /deepen-plan)
- `specs/technical-research-best-practices.md` — Laravel+Claude integration via Laravel AI SDK, queue architecture, AI prompting, validation
- `specs/conversational-audio-ai-research.md` — Audio recording, STT/TTS, AudioOrb animation, latency optimization
- `plans/stripe-billing-architecture.md` — Full Stripe product/price structure, Cashier config, webhook handling
- `plans/pricing-strategy.md` — B2C/B2B tiers, competitive positioning, revenue projections, launch strategy
- `plans/mobile-performance-optimization.md` — FPS, TTI, bundle size, memory management, profiling workflows
- `specs/signup-and-onboarding-cro.md` — Guest-to-user conversion, org admin onboarding, copy deliverables
- `plans/app-store-compliance-review.md` — 3 critical, 6 high-risk App Store compliance findings
- `specs/framework-documentation.md` — Laravel 11+, Expo SDK 52+, AI/ML library documentation
- `specs/on-device-llm-research.md` — On-device LLM frameworks, model benchmarks, React Native bindings
- `specs/on-device-speech-processing-research.md` — On-device STT/TTS quality benchmarks, WER comparisons
- `specs/hybrid-on-device-ai-architecture-research.md` — Hybrid architecture patterns, platform API analysis
- `specs/on-device-ai-architecture-recommendation.md` — **Consolidated recommendation** — tiered hybrid with Llama 3.1 8B assessment

### Technical
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — API authentication
- [Laravel Cashier (Stripe)](https://laravel.com/docs/billing) — Web subscription billing
- [Laravel AI SDK](https://laravel.com/ai) — Official first-party AI integration (Agents, Transcription, Audio, structured output)
- [Expo Router](https://docs.expo.dev/routing/introduction/) — File-based routing for React Native
- [Expo Audio](https://docs.expo.dev/versions/latest/sdk/audio/) — Audio recording and playback
- [React Native Reanimated](https://docs.swmansion.com/react-native-reanimated/) — 60fps animations
- [Anthropic Claude API](https://docs.anthropic.com/) — AI analysis engine
- [OpenAI Whisper API](https://platform.openai.com/docs/guides/speech-to-text) — Speech-to-text transcription
- [OpenAI TTS](https://platform.openai.com/docs/guides/text-to-speech) — Text-to-speech for conversation mode
- [react-native-iap](https://github.com/dooboolab/react-native-iap) — StoreKit 2 / Google Play Billing
- [Zustand](https://github.com/pmndrs/zustand) — State management with persistence
