# Vocational Operating System — Remaining TODOs by Phase

> Auto-generated from `plans/feat-vocational-operating-system.md` unchecked items.
> Last updated: 2026-04-03

---

## Phase 1: Foundation (Feature Flags + Career Profile)

### 1A. Feature Flags — COMPLETE

### 1B. Career Profile — Mostly Complete

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `ResumeParserService` (evaluate `sharpapi/laravel-resume-parser` vs custom AI parsing) | Medium — needed for PDF import to actually parse content |
| TODO | Create `ParseResumeUploadJob` for background PDF processing | Medium — companion to above |

---

## Phase 2: Job Discovery Engine

### 2A. Ingestion Pipeline — Mostly Complete

| Status | Task | Priority |
|--------|------|----------|
| TODO | Build `JSearchAdapter` with RapidAPI auth and rate limiting | Low — start with free sources (Adzuna + Muse), add JSearch when coverage gaps emerge |
| TODO | Create `JobNormalizerService` to unify data across sources | Low — adapters handle normalization inline currently |
| TODO | Configure Horizon queue for `job-pipeline` worker | Medium — add supervisor in `config/horizon.php` for job ingestion |

### 2B. Job Discovery UI — Mostly Complete

| Status | Task | Priority |
|--------|------|----------|
| DONE | Web browse/search page with filters + pagination | — |
| DONE | Web job detail page with match breakdown + save/apply | — |
| DONE | Mobile jobs tab with recommended feed + save | — |
| TODO | Mobile job detail screen (navigate from card to full detail) | Low — apply button works from card |
| TODO | Mobile search with filters (search bar + filter bottom sheet) | Low — recommended feed is primary UX |

---

## Phase 3: AI Resume & Cover Letter Builder

### 3A. Voice Profile System

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `VoiceProfile` model + migration | High |
| TODO | Create `VoiceAnalyzerAgent` using Laravel AI SDK | High |
| TODO | Build sample submission UI (web + mobile) | Medium |
| TODO | Implement voice profile display (shows detected style characteristics) | Medium |

### 3B. Resume Builder

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `ResumeVersion` model + migration | High |
| TODO | Create `ResumeWriterAgent` with company research tool | High |
| TODO | Create `ResumeQualityAgent` for scoring and AI-slop detection | High |
| TODO | Create `CompanyResearchTool` (The Muse API + web) | Medium |
| TODO | Implement two-pass generation with voice profile rewrite | High |
| TODO | Implement quality gate (auto-regenerate below score threshold) | Medium |
| TODO | Generate DOCX using PHPWord (ATS-friendly format) | High — `composer require phpoffice/phpword` |
| TODO | Generate PDF using DomPDF (matching `ResultsPdf` pattern) | Medium |
| TODO | Store generated files on S3 with temporary URL access | Medium |
| TODO | Build web resume list + detail pages | Medium |
| TODO | Build mobile resume list + detail screens | Medium |
| TODO | Track resume generation usage for billing (metered event) | Low |

### 3C. Cover Letter Builder

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `CoverLetter` model + migration | High |
| TODO | Create `CoverLetterWriterAgent` with 3-touch method | High |
| TODO | Apply same anti-AI-slop pipeline (voice profile + quality scoring) | Medium |
| TODO | Generate as PDF and DOCX | Medium |
| TODO | Build web + mobile detail views | Medium |
| TODO | Track generation usage for billing | Low |

### 3D. Conversational Resume Coach

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `ResumeCoachAgent` with `RemembersConversations` + `HasTools` | High |
| TODO | Create `GetAssessmentAnswersTool` for reading vocational profile in conversation | High |
| TODO | Create `SaveCareerProfileTool` for persisting data from conversation | High |
| TODO | Create `GenerateResumeTool` for triggering resume creation from conversation | High |
| TODO | Implement life-stage detection logic (middle school → experienced) | High |
| TODO | Build stage-appropriate resume templates (5 formats) | Medium |
| TODO | Build web conversation UI (similar to existing assessment conversation) | Medium |
| TODO | Build mobile conversation screen | Medium |
| TODO | Handle edge cases: abandon mid-conversation, return to refine | Low |
| TODO | Warm, encouraging system prompt for all experience levels | High |

---

## Phase 4: Application Tracking

### 4A. Tracking Core

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `JobApplication` model with status enum + migration | High |
| TODO | Create `ApplicationEvent` model for activity log | High |
| TODO | Create `ApplicationTrackingService` with status transition validation | High |
| TODO | Build API CRUD + analytics endpoint | High |
| TODO | Build web kanban board view (drag-and-drop status changes) | Medium |
| TODO | Build web list view as alternative | Low |
| TODO | Build mobile applications tab with status-grouped list | Medium |
| TODO | Build mobile application detail with event timeline | Medium |
| TODO | Implement quick-add from job listings | Medium |
| TODO | Create `DetectGhostedApplicationsJob` scheduled daily | Medium |
| TODO | Create follow-up reminder notifications (push + email) | Low |
| TODO | Install `expo-notifications` for mobile push support | Low |

### 4B. Application Analytics

| Status | Task | Priority |
|--------|------|----------|
| TODO | Build analytics aggregation queries | Medium |
| TODO | Expose via API for mobile and web | Medium |
| TODO | Display on user dashboard | Medium |
| TODO | Feed into organization and platform admin dashboards | Low |

---

## Phase 5: Dashboard Analytics & AI Career Coaching

### 5A. Organization Dashboard Extensions

| Status | Task | Priority |
|--------|------|----------|
| TODO | Extend `OrganizationDashboardController` with job analytics | Medium |
| TODO | Build org-level aggregate queries (scoped to org members) | Medium |
| TODO | Add web dashboard panels for job search metrics | Medium |
| TODO | Add mobile org dashboard sections | Low |

### 5B. Platform Admin Dashboard Extensions

| Status | Task | Priority |
|--------|------|----------|
| TODO | Extend `PlatformAnalyticsService` with job platform metrics | Medium |
| TODO | Add `DashboardSnapshot` metric keys for new KPIs | Medium |
| TODO | Build admin web dashboard panels | Medium |
| TODO | Track external API costs and usage | Low |

### 5C. AI Career Coaching Conversation

| Status | Task | Priority |
|--------|------|----------|
| TODO | Create `CareerCoachAgent` with conversation memory | Medium |
| TODO | Create tools: `SearchJobsTool`, `GetVocationalProfileTool`, `GetCareerProfileTool` | Medium |
| TODO | Build web conversation UI (extend existing conversation patterns) | Medium |
| TODO | Build mobile conversation screen | Medium |
| TODO | Gate behind feature flag | Low |

---

## Cross-Cutting / Dependencies

| Task | Phase | Priority |
|------|-------|----------|
| `composer require phpoffice/phpword` | Phase 3 | Must install before resume DOCX generation |
| `npx expo install expo-sharing` | Phase 3 | Needed for mobile file sharing |
| `npx expo install expo-notifications expo-device` | Phase 4 | Needed for push notifications |
| `npx expo install expo-pdf` (or alternative) | Phase 3 | Needed for mobile PDF preview |
| Register for Adzuna API credentials | Phase 2 | Must have before ingestion works |
| Register for O*NET Web Services | Phase 2 | Optional — SOC mapping is pre-seeded |
| Configure Horizon `job-pipeline` queue supervisor | Phase 2 | Needed for production ingestion |
| Add Stripe metered billing meters | Phase 3 | Resume/cover letter generation tracking |
