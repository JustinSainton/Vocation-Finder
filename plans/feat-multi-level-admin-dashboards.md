# feat: Multi-Level Admin Dashboards

## Enhancement Summary

**Deepened on:** 2026-04-02
**Research agents used:** 9 (repo-research-analyst, best-practices-researcher, framework-docs-researcher, architecture-strategist, security-sentinel, performance-oracle, data-integrity-guardian, agent-native-reviewer, pattern-recognition-specialist)

### Key Improvements from Research
1. **Replace `mentor_id` on pivot with dedicated `mentor_assignments` table** — cleaner separation, supports assignment history, prevents cross-org FK violations
2. **Don't bake abilities into Sanctum tokens** — use runtime checks instead to avoid stale authorization after role changes
3. **Add API parity layer** — 12 of 17 new features were web-only; all need API endpoints for mobile mentors and future agent access
4. **Pre-compute analytics** — dashboard snapshot table + scheduled job prevents N+1 and JSON blob aggregation at scale
5. **Fix 5 pre-existing security vulnerabilities** — `role` in `$fillable`, wildcard Sanctum tokens, invitation privilege escalation, guest token timing attack, member removal cascade

### Critical Pre-Existing Vulnerabilities (Fix Immediately)
- `role` in `User::$fillable` enables mass-assignment role escalation (`app/Models/User.php:24`)
- Sanctum tokens created with no abilities = wildcard access (`app/Http/Controllers/Api/V1/AuthController.php:31,55,108`)
- Any org member can invite as admin via API (`app/Http/Controllers/Api/V1/OrganizationInvitationController.php:23`)
- Guest token comparison uses `===` instead of `hash_equals()` (`app/Http/Controllers/Api/V1/AssessmentController.php:145`)
- Org member removal has no last-admin protection (`app/Http/Controllers/Web/Org/OrgMemberController.php:79`)

---

## Overview

Build three tiers of dashboards that serve distinct audiences within the Vocation Finder platform:

1. **Platform Dashboard** — For business owners to see every level of the business (all users, orgs, assessments, revenue, system health)
2. **Organization Dashboard** — For churches, non-profits, schools, and teams with role hierarchy (org lead, mentors, students)
3. **User Dashboard** — In-app mobile dashboard for individuals to access their vocational profile and journey

## Problem Statement

The current admin and org dashboards are functional but minimal. The admin dashboard has 4 stat cards and a recent assessments table. The org dashboard has basic member management and aggregate insights. The mobile app's dashboard is a placeholder. There is no mentor role, no student progress tracking, no revenue analytics, no survey data visualization, and no vocational journey view for individual users.

## Stakeholder Analysis

| Audience | Needs | Platform |
|----------|-------|----------|
| Platform owners (you) | Revenue, growth trends, system health, all-org oversight | Web (Inertia) |
| Org leads | Member management, assessment quotas, aggregate insights | Web (Inertia) |
| Mentors | View assigned students' profiles, track progress, leave notes | Web (Inertia) + Mobile |
| Students/Users | View own profile, past assessments, learning pathway | Mobile (Expo) |

---

## Technical Approach

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    PLATFORM DASHBOARD                    │
│  /admin/*  (EnsureAdmin middleware, role='admin')        │
│  Revenue · Growth · All Orgs · All Users · System Health │
├─────────────────────────────────────────────────────────┤
│                 ORGANIZATION DASHBOARD                   │
│  /org/{organization}/*  (EnsureOrgRole middleware)       │
│  Members · Mentors · Students · Insights · Quotas       │
├─────────────────────────────────────────────────────────┤
│                    USER DASHBOARD                        │
│  Mobile app (dashboard) tabs + API                      │
│  Home · Assessments · Pathway · Profile                 │
└─────────────────────────────────────────────────────────┘
       ↕ Shared Service Layer (no controller duplication)
┌─────────────────────────────────────────────────────────┐
│                      API LAYER                          │
│  /api/v1/*  (Sanctum auth, runtime ability checks)      │
│  Every web feature has an API equivalent                │
└─────────────────────────────────────────────────────────┘
```

### Data Model Changes

```mermaid
erDiagram
    Organization ||--o{ OrganizationUser : has
    User ||--o{ OrganizationUser : belongs_to
    OrganizationUser {
        uuid id PK
        uuid organization_id FK
        uuid user_id FK
        string role "admin|mentor|member"
        timestamp created_at
    }

    Organization ||--o{ MentorAssignment : has
    User ||--o{ MentorAssignment : mentors
    User ||--o{ MentorAssignment : is_mentored
    MentorAssignment {
        uuid id PK
        uuid organization_id FK
        uuid mentor_id FK "users.id"
        uuid student_id FK "users.id"
        string status "active|paused|completed"
        timestamp assigned_at
        timestamp completed_at
    }

    User ||--o{ Assessment : takes
    Assessment ||--o| VocationalProfile : produces
    Assessment ||--o{ AssessmentSurvey : has

    Organization ||--o{ MentorNote : has
    User ||--o{ MentorNote : writes
    User ||--o{ MentorNote : receives
    MentorNote {
        uuid id PK
        uuid organization_id FK
        uuid mentor_id FK "users.id"
        uuid student_id FK "users.id"
        text content "NOT NULL"
        string visibility "mentor_only|shared"
        soft_deletes deleted_at
        timestamps created_at updated_at
    }

    DashboardSnapshot {
        uuid id PK
        string metric_key
        json value
        date snapshot_date
        timestamp computed_at
    }
}
```

### Research Insights: Data Model

**Use `mentor_assignments` table instead of `mentor_id` on pivot** (Architecture + Data Integrity reviewers):
- Separates membership from mentoring — a student's org membership is independent of their mentor relationship
- Supports assignment history (reassignment creates new row, old row gets `completed_at`)
- Prevents cross-org FK violations (composite validation: mentor and student must both be in same org)
- Unique constraint on `(organization_id, student_id)` where `status = 'active'` enforces one active mentor per student per org
- When a mentor leaves the org, their assignments are set to `status = 'completed'` — no cascade issues

**`MentorNote` must include soft deletes** (Pattern + Data Integrity reviewers):
- Mentor notes contain sensitive vocational/spiritual observations
- Project convention per MEMORY.md: "soft deletes everywhere"
- `restrictOnDelete()` on FKs prevents user deletion from silently destroying notes
- Default `visibility` to `'mentor_only'` so notes are never accidentally exposed

**Pre-compute analytics with `DashboardSnapshot`** (Performance reviewer):
- Platform KPIs, survey aggregates, and domain distributions pre-computed every 15-30 min
- Avoids unbounded `COUNT(*)` and JSON blob deserialization on every page load
- Current `OrgInsightsController` runs 12 queries in a loop — replace with single `GROUP BY`

### Authorization Layer

**Two-layer strategy** (Architecture + Security reviewers):

| Layer | Purpose | Scope |
|-------|---------|-------|
| Middleware | Route-group gatekeeping (coarse) | "Can this user access org routes at all?" |
| Policies | Resource-level authorization (fine) | "Can this mentor see this specific student?" |

**Middleware approach** — Create parameterized `EnsureOrgRole` (don't modify `EnsureOrgAdmin`):
```php
// Usage in routes
Route::middleware('org.role:admin')->group(...)        // billing, settings, member management
Route::middleware('org.role:admin,mentor')->group(...) // student views, notes, insights
```

**Sanctum: runtime checks, NOT token-baked abilities** (Architecture reviewer):
- Use Sanctum tokens purely for authentication (as done today)
- Check permissions at runtime via Policies against the database
- Eliminates stale-token problem when roles change
- No need for token rotation or forced re-login on promotion

**Remove `Gate::before` global admin bypass** (Security reviewer):
- Instead, add `before()` method to each Policy individually
- Platform admins get explicit per-policy bypass with audit logging
- Prevents leaking data across org boundaries

```php
// In each Policy, NOT globally
class OrganizationPolicy {
    public function before(User $user): ?bool {
        if ($user->role === 'admin') {
            Log::info('admin_policy_bypass', ['user' => $user->id, 'policy' => 'OrganizationPolicy']);
            return true;
        }
        return null;
    }
}
```

**Security: Remove `role` from `User::$fillable` immediately** — enables mass-assignment role escalation.

### API Parity Layer

**Every web feature needs an API endpoint** (Agent-Native reviewer):

| Web Route | API Equivalent | Consumer |
|-----------|---------------|----------|
| `GET /admin` | `GET /v1/admin/stats` | Monitoring, Slack bots |
| `GET /admin/revenue` | `GET /v1/admin/revenue` | Reporting automation |
| `GET /admin/surveys` | `GET /v1/admin/surveys` | Analytics |
| `GET /org/{org}/mentors` | `GET /v1/org/{org}/mentors` | Mentor mobile view |
| `POST /org/{org}/mentors/assign` | `POST /v1/org/{org}/mentor-assignments` | Mobile |
| `GET /org/{org}/members` (students) | `GET /v1/org/{org}/members?role=member` | Mentor mobile |
| `GET /org/{org}/members/{id}` | `GET /v1/org/{org}/members/{id}` | Student detail |
| `POST /org/{org}/members/{id}/notes` | `POST /v1/org/{org}/members/{id}/notes` | Mentor mobile |
| `GET /org/{org}/insights` | `GET /v1/org/{org}/insights` | Reporting |
| User dashboard | `GET /v1/me/dashboard` | Mobile (aggregated) |
| Student notes | `GET /v1/me/mentor-notes` | Mobile |

**Shared service layer** prevents controller duplication:
```
App\Services\PlatformAnalyticsService   → used by AdminDashboardController (web) + AdminStatsController (API)
App\Services\OrgInsightsService         → used by OrgInsightsController (web) + API equivalent
App\Services\MentorNoteService          → used by OrgMentorNoteController (web) + API equivalent
```

---

## Implementation Phases

### Phase 0: Security Fixes (Do First — 1 day)

**Fix pre-existing vulnerabilities before adding new authorization surface area.**

- [ ] Remove `role` from `User::$fillable` (`app/Models/User.php:24`)
- [ ] Add `wherePivot('role', 'admin')` check in `OrganizationInvitationController::store` (API)
- [ ] Switch guest token comparison to `hash_equals()` in `AssessmentController` and `ResultsController`
- [ ] Add last-admin protection to `OrgMemberController::remove`
- [ ] Add database indexes: `assessments.created_at`, `assessments.completed_at`, `[assessments.organization_id, created_at]`, `[assessments.organization_id, status]`, `[assessment_surveys.assessment_id, type]`

### Phase 1a: Data Model + Authorization (2-3 days)

- [ ] Create `mentor_assignments` migration and `MentorAssignment` model (UUID, soft deletes, HasFactory)
- [ ] Create `mentor_notes` migration and `MentorNote` model (UUID, soft deletes, HasFactory, `content` NOT NULL, `visibility` default `'mentor_only'`, `restrictOnDelete` FKs)
- [ ] Create `dashboard_snapshots` migration and `DashboardSnapshot` model
- [ ] Create `OrganizationPolicy` with per-policy admin bypass (not global `Gate::before`)
- [ ] Create `AssessmentPolicy` with org-scoped mentor access chain
- [ ] Create `MentorNotePolicy` (mentors see own notes, students see only `shared`, admins see all)
- [ ] Create `EnsureOrgRole` middleware (parameterized, replaces need for separate mentor middleware)
- [ ] Update invitation validation to accept `'mentor'` role (both web + API controllers)

### Phase 1b: Inertia + Mobile Auth (1-2 days)

- [ ] Share org context from `EnsureOrgRole` middleware via `Inertia::share('currentOrg', ...)` (not global `HandleInertiaRequests`)
- [ ] Refactor `OrgLayout` to read from `usePage().props` shared data (remove explicit props)
- [ ] Update all existing Org pages to use refactored layout
- [ ] Add `role` and `organizations` (with pivot roles) to API auth responses (all 3 methods in `AuthController`)
- [ ] Update mobile `User` type and `authStore` with `role` and `organizations`
- [ ] Add re-fetch on app resume (`checkAuth` updates role/orgs from server)
- [ ] Extract shared TypeScript types (`OrgRef`, etc.) into `resources/js/types/`

### Phase 2: Platform Dashboard (3-5 days)

- [ ] Create `PlatformAnalyticsService` with pre-computed snapshot reads
- [ ] Create `ComputeDashboardSnapshots` artisan command (scheduled every 15 min)
- [ ] Create `RevenueSnapshotService` that syncs Stripe data to local table (never call Stripe in page render)
- [ ] Install `recharts` in web frontend
- [ ] Build enhanced `Admin/Dashboard.tsx` with KPI cards + trend charts (reading from snapshots)
- [ ] Build `Admin/Revenue.tsx` with MRR/ARR/churn from local snapshot table
- [ ] Build `Admin/Surveys.tsx` with before/after delta analysis (SQL aggregation, not PHP iteration)
- [ ] Build `Admin/Conversations/Index.tsx` (metadata only — `withCount('turns')`, no content loaded) and `Show.tsx` (full transcript with audit logging)
- [ ] Add corresponding API endpoints (`/v1/admin/stats`, `/v1/admin/revenue`, `/v1/admin/surveys`)
- [ ] Add `DateRangePicker` component; all analytics accept `?from=&to=` params
- [ ] Refactor existing `OrgInsightsController` loop to single `GROUP BY` query

### Phase 3: Organization Dashboard (4-6 days)

- [ ] Create `MentorService`, `OrgInsightsService` shared service classes
- [ ] Build mentor assignment UI (drag-and-drop or dropdown, `POST /org/{org}/mentor-assignments`)
- [ ] Build `Org/Members/Index.tsx` enhancement with role filter, mentor column, progress indicators
- [ ] Build `Org/Members/Show.tsx` with full student profile, assessment history, mentor notes timeline
- [ ] Build mentor note CRUD: create, update visibility, delete (web + API)
- [ ] Build `Org/Insights.tsx` enhancement with recharts, survey score analysis
- [ ] Add mentor-scoped student views (mentors see only assigned students via `MentorAssignment`)
- [ ] Add assessment quota tracker with visual progress bar
- [ ] Add corresponding API endpoints for all org features
- [ ] Handle user removal cascade: nullify `organization_id` on assessments, soft-delete mentor notes, complete mentor assignments

### Phase 4: User Dashboard — Mobile (3-5 days)

- [ ] Create aggregated `GET /v1/me/dashboard` endpoint (profile summary + recent assessment + pathway status + shared mentor notes in one call)
- [ ] Create `GET /v1/me/mentor-notes` endpoint (student's shared notes)
- [ ] Switch `(dashboard)/_layout.tsx` from `Stack` to `Tabs` with `lazy: true`
- [ ] Build Home tab with profile summary card, journey status, quick actions, shared mentor notes
- [ ] Build Assessments tab with past assessment list (reuse existing `GET /v1/assessments`)
- [ ] Build Pathway tab with nested Stack for course detail navigation
- [ ] Enhance Profile tab with org membership display, subscription status
- [ ] Use `Tabs.Protected` for role-based tab visibility

### Performance Checklist (Apply During All Phases)

- [ ] No controller action exceeds 8 database queries
- [ ] All aggregations on tables >10k rows use SQL or snapshot table (never PHP iteration)
- [ ] All list views paginated (max 25 per page)
- [ ] Time-series charts bucket: daily for <90d, weekly for <365d, monthly for >365d
- [ ] Max 200 data points per chart; `isAnimationActive={false}` for >100 points
- [ ] Mobile tabs use `lazy: true` to avoid 4 simultaneous API calls on mount
- [ ] Test with 10,000 seeded assessments to validate 2-second page load target

---

## Acceptance Criteria

### Functional Requirements
- [ ] Platform admin can view all users, orgs, assessments, revenue, and survey data
- [ ] Org leads can manage members, assign mentors, view all student progress
- [ ] Mentors can view ONLY assigned students' profiles and leave notes (row-level scoping enforced)
- [ ] Students can view their own vocational profile and pathway in the mobile app
- [ ] Students can see mentor notes marked as `shared` (never `mentor_only`)
- [ ] Role-based access control prevents unauthorized access at every level
- [ ] Every web feature has a corresponding API endpoint

### Non-Functional Requirements
- [ ] Dashboard pages load in under 2 seconds with 10k+ assessments
- [ ] Charts render smoothly on desktop via recharts (NOT used in mobile — web only)
- [ ] Mobile tabs feel native with smooth transitions and lazy loading
- [ ] All analytics queries use indexes, snapshots, or `GROUP BY` (no N+1, no PHP iteration)
- [ ] Stripe API never called synchronously in page renders

### Quality Gates
- [ ] Authorization policies have feature tests (including negative: mentor cannot see non-assigned student)
- [ ] Admin dashboard has integration tests with seeded data (10k assessments)
- [ ] Mobile tab navigation has snapshot tests
- [ ] No N+1 queries — enforce with `assertQueryCount()` in tests
- [ ] Security: `role` not in `$fillable`, Sanctum tokens have no wildcard, invitation requires admin pivot

---

## Risk Analysis

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| N+1 queries in analytics | High | High | Snapshot table + single GROUP BY queries + query count assertions |
| Role escalation via mass assignment | High (exists now) | Critical | Remove `role` from `$fillable` in Phase 0 |
| Stale Sanctum token abilities | Medium | High | Use runtime checks only — no token-baked abilities |
| Mentor accessing non-assigned students | Medium | High | Row-level scoping in Policy + controller, tested with negative cases |
| Cross-org mentor assignment | Medium | High | Composite validation in `MentorAssignment` creation |
| Stripe API latency in page renders | High | Medium | Revenue snapshot table, never sync Stripe API |
| Concurrent assessment quota race condition | Medium | Medium | Atomic `SELECT FOR UPDATE` or decrement |
| OrgLayout refactor breaks existing pages | Medium | Medium | Phase 1b is a focused refactor touching all Org pages |
| SQLite write contention with scheduled jobs | Low | Medium | Plan PostgreSQL migration for production |

## Future Considerations

- **Real-time updates**: WebSocket-powered live dashboard (assessment completions, new signups)
- **Export/reporting**: CSV/PDF export of analytics for org leads
- **Push notifications**: For mentors when students complete assessments, when notes are shared
- **Multi-org support**: Users belonging to multiple organizations with org switcher
- **Cohort grouping**: Batch students into cohorts (e.g., "Spring 2026 Class") with cohort-level insights
- **AI-powered cohort insights**: Aggregate analysis across an org's students using AI
- **Activity feed**: Polymorphic `activity_log` table for timeline views (consider Spatie Activity Log)
- **Admin impersonation**: Session-based impersonation with persistent banner for debugging org views
- **Command palette**: Cmd+K for admin quick navigation (standard in modern admin tools)

## References

### Internal References
- Admin layout: `resources/js/Layouts/AdminLayout.tsx`
- Org layout: `resources/js/Layouts/OrgLayout.tsx`
- Existing admin dashboard: `resources/js/Pages/Admin/Dashboard.tsx`
- Existing org insights: `resources/js/Pages/Org/Insights.tsx` (N+1 loop at lines 57-75)
- Organization model: `app/Models/Organization.php`
- User model: `app/Models/User.php` (**`role` in `$fillable` — security fix needed**)
- Auth middleware: `app/Http/Middleware/EnsureAdmin.php`, `EnsureOrgAdmin.php`
- Auth controller: `app/Http/Controllers/Api/V1/AuthController.php` (3 token creation points)
- Invitation controller: `app/Http/Controllers/Api/V1/OrganizationInvitationController.php` (missing role check)
- Billing config: `config/billing.php`
- Mobile dashboard: `mobile/app/(dashboard)/index.tsx`
- Mobile auth store: `mobile/stores/authStore.ts` (User type lacks role/orgs)
- API routes: `routes/api.php`
- Web routes: `routes/web.php` (inline dashboard route at lines 61-99 — extract to controller)

### Framework Documentation
- [Laravel 13 Authorization](https://laravel.com/docs/13.x/authorization) — Policies and Gates
- [Laravel 13 Sanctum](https://laravel.com/docs/13.x/sanctum) — Token abilities (use for auth only, not authorization)
- [Laravel 13 AI SDK](https://laravel.com/docs/13.x/ai-sdk) — Agents, structured output, conversation memory, tools
- [Inertia.js v2 Persistent Layouts](https://v2.inertiajs.com/pages) — `layout` property on page components
- [Expo Router Tabs](https://docs.expo.dev/router/advanced/tabs/) — Tab navigation patterns
- [Expo Router Protected Routes](https://docs.expo.dev/router/advanced/protected/) — `Tabs.Protected` for role-based tabs
- [Recharts Documentation](https://recharts.org/en-US/api) — Web-only charting (NOT React Native compatible)
- [Laravel 12 Cashier](https://laravel.com/docs/12.x/billing) — Subscription management
- [Spatie Activity Log](https://spatie.be/docs/laravel-activitylog) — For future activity feed

### Review Agent Sources
- Architecture strategist: mentor_assignments table, runtime auth, middleware strategy
- Security sentinel: 14 findings including 2 critical pre-existing vulnerabilities
- Performance oracle: snapshot table, GROUP BY refactors, index recommendations, Stripe caching
- Data integrity guardian: race conditions, FK design, soft delete propagation, privacy on user removal
- Agent-native reviewer: API parity gap (12/17 features web-only), aggregated mobile endpoint
- Pattern recognition specialist: naming conventions, type extraction, RESTful route consistency
