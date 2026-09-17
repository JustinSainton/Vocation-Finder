---
version: alpha
name: vocational-finder-design
description: Editorial-infrastructure interface for Vocational Finder. Warm off-white canvas carrying editorial serif headlines, monospace eyebrow labels, and grainy real photography, with near-black surfaces reserved for the coach and the vocational brain. Restraint over voltage — a single indigo accent, hairline borders, no shadows, no illustration. Adapted from the Clay design system's structural layer (spacing, tokens, elevation, responsive rules); expressive layer derives from the Vocational Finder Vision Document.

colors:
  primary: "#1C1917"
  primary-active: "#292524"
  primary-disabled: "#E7E5E4"
  ink: "#1C1917"
  body: "#44403C"
  body-strong: "#292524"
  muted: "#78716C"
  muted-soft: "#A8A29E"
  hairline: "#D6D3D1"
  hairline-soft: "#E7E5E4"
  canvas: "#FAFAF7"
  surface-soft: "#F5F5F0"
  surface-card: "#F0EFE9"
  surface-strong: "#EBEAE3"
  canvas-dark: "#14120F"
  surface-dark: "#1F1C18"
  surface-dark-elevated: "#2A2622"
  hairline-dark: "#3A3530"
  on-primary: "#FAFAF7"
  on-dark: "#FAFAF7"
  on-dark-muted: "#A8A29E"
  accent: "#3A3AA0"
  accent-strong: "#2A2A7A"
  accent-on-dark: "#8F8FF5"
  accent-wash: "#EEEEF8"
  success: "#2F7A55"
  warning: "#A8761F"
  error: "#A03530"

typography:
  display-xl:
    fontFamily: "Literata, Georgia, serif"
    fontSize: 64px
    fontWeight: 400
    lineHeight: 1.05
    letterSpacing: -1.5px
  display-lg:
    fontFamily: "Literata, Georgia, serif"
    fontSize: 48px
    fontWeight: 400
    lineHeight: 1.08
    letterSpacing: -1px
  display-md:
    fontFamily: "Literata, Georgia, serif"
    fontSize: 36px
    fontWeight: 400
    lineHeight: 1.15
    letterSpacing: -0.5px
  display-sm:
    fontFamily: "Literata, Georgia, serif"
    fontSize: 28px
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: -0.25px
  title-lg:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 22px
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: -0.2px
  title-md:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 18px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0
  title-sm:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 16px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0
  body-lg:
    fontFamily: "Literata, Georgia, serif"
    fontSize: 18px
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: 0
  body-md:
    fontFamily: "Literata, Georgia, serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: 0
  body-sm:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  caption:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 13px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0
  eyebrow:
    fontFamily: "IBM Plex Mono, ui-monospace, monospace"
    fontSize: 12px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 1.5px
    textTransform: uppercase
  meta:
    fontFamily: "IBM Plex Mono, ui-monospace, monospace"
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0.5px
  button:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1
    letterSpacing: 0
  nav-link:
    fontFamily: "Satoshi, Inter, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0

rounded:
  none: 0px
  xs: 2px
  sm: 4px
  md: 6px
  lg: 8px
  pill: 9999px

spacing:
  xxs: 4px
  xs: 8px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  xxl: 48px
  section: 96px
  hero: 128px

motion:
  fast: "120ms cubic-bezier(0.2, 0, 0, 1)"
  base: "180ms cubic-bezier(0.2, 0, 0, 1)"
  slow: "280ms cubic-bezier(0.2, 0, 0, 1)"

components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button}"
    rounded: "{rounded.none}"
    padding: 14px 24px
    height: 44px
  button-primary-active:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.on-primary}"
    rounded: "{rounded.none}"
  button-primary-disabled:
    backgroundColor: "{colors.primary-disabled}"
    textColor: "{colors.muted}"
    rounded: "{rounded.none}"
  button-secondary:
    backgroundColor: transparent
    textColor: "{colors.ink}"
    borderColor: "{colors.hairline}"
    typography: "{typography.button}"
    rounded: "{rounded.none}"
    padding: 14px 24px
    height: 44px
  button-on-dark:
    backgroundColor: "{colors.on-dark}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.none}"
    padding: 14px 24px
    height: 44px
  button-text-link:
    backgroundColor: transparent
    textColor: "{colors.accent}"
    typography: "{typography.button}"
  text-link:
    backgroundColor: transparent
    textColor: "{colors.accent}"
    typography: "{typography.body-md}"
  top-nav:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.nav-link}"
    height: 64px
  hero-band:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.display-xl}"
    padding: "{spacing.hero}"
  section-label:
    backgroundColor: transparent
    textColor: "{colors.accent}"
    typography: "{typography.eyebrow}"
  editorial-card:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.xl}"
  quote-block:
    backgroundColor: transparent
    textColor: "{colors.ink}"
    borderColor: "{colors.accent}"
    typography: "{typography.display-sm}"
    padding: "0 0 0 24px"
  policy-callout:
    backgroundColor: "{colors.accent-wash}"
    textColor: "{colors.ink}"
    borderColor: "{colors.accent}"
    typography: "{typography.body-md}"
    rounded: "{rounded.sm}"
    padding: "{spacing.lg}"
  text-input:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    borderColor: "{colors.hairline}"
    typography: "{typography.body-md}"
    rounded: "{rounded.sm}"
    padding: 12px 16px
    height: 44px
  text-input-focused:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    borderColor: "{colors.accent}"
    rounded: "{rounded.sm}"
  textarea-narrative:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    borderColor: "{colors.hairline}"
    typography: "{typography.body-lg}"
    rounded: "{rounded.sm}"
    padding: "{spacing.md}"
  badge-pill:
    backgroundColor: "{colors.surface-strong}"
    textColor: "{colors.body}"
    typography: "{typography.eyebrow}"
    rounded: "{rounded.pill}"
    padding: 4px 12px
  confidence-badge:
    backgroundColor: transparent
    textColor: "{colors.muted}"
    borderColor: "{colors.hairline}"
    typography: "{typography.eyebrow}"
    rounded: "{rounded.pill}"
    padding: 4px 12px
  category-tab:
    backgroundColor: transparent
    textColor: "{colors.muted}"
    typography: "{typography.nav-link}"
    rounded: "{rounded.none}"
    padding: 8px 0
  category-tab-active:
    backgroundColor: transparent
    textColor: "{colors.ink}"
    borderColor: "{colors.ink}"
    typography: "{typography.nav-link}"
    rounded: "{rounded.none}"
  coach-surface:
    backgroundColor: "{colors.canvas-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    padding: "{spacing.xl}"
  coach-message-system:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.lg}"
  coach-message-student:
    backgroundColor: transparent
    textColor: "{colors.on-dark-muted}"
    borderColor: "{colors.hairline-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.lg}"
  action-card:
    backgroundColor: "{colors.surface-dark-elevated}"
    textColor: "{colors.on-dark}"
    borderColor: "{colors.accent-on-dark}"
    typography: "{typography.title-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.lg}"
  readiness-meter:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.display-md}"
    rounded: "{rounded.sm}"
    padding: "{spacing.lg}"
  habit-row:
    backgroundColor: transparent
    textColor: "{colors.on-dark}"
    borderColor: "{colors.hairline-dark}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.none}"
    padding: "{spacing.md}"
  brain-entry:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.sm}"
    padding: "{spacing.lg}"
  brain-entry-frozen:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark-muted}"
    borderColor: "{colors.hairline-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.sm}"
    padding: "{spacing.lg}"
  plan-chunk:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark}"
    borderColor: "{colors.hairline-dark}"
    typography: "{typography.title-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.lg}"
  cta-band:
    backgroundColor: "{colors.surface-soft}"
    textColor: "{colors.ink}"
    typography: "{typography.display-md}"
    rounded: "{rounded.none}"
    padding: 80px
  footer:
    backgroundColor: "{colors.surface-soft}"
    textColor: "{colors.body}"
    typography: "{typography.body-sm}"
    padding: 80px
---

## Overview

Vocational Finder should feel like **infrastructure for your future, not a school portal**. The register is the one named in the Vision Document: *ambitious, precise, quietly confident.* Explicitly **not** a friendly app for confused teenagers.

The base atmosphere is a **warm off-white canvas** (`{colors.canvas}` — #FAFAF7) carrying **editorial serif** headlines and body copy, with **monospace eyebrow labels** marking every section. Brand voltage comes from restraint and typography, not from color or illustration. A **single indigo accent** (`{colors.accent}`) does all the work that a six-color palette would do elsewhere.

**Near-black surfaces** (`{colors.canvas-dark}`) are reserved for the two places where the student does sustained private work: **the coach and the vocational brain**. This mirrors the Vision Document's own construction, which runs dark for the product narrative and warm-light for the analytical sections.

**Provenance.** The structural layer of this system — the 4px spacing scale, 96px section rhythm, token-reference discipline, hairline elevation model, responsive strategy, and 44px touch targets — is adapted from the Clay design system. The expressive layer — serif display, monospace labels, grainy photography, restraint, dark product surfaces — derives from the Vocational Finder Vision Document. Where the two conflicted, the vision won. Clay's claymation illustrations, mascots, six-color saturated card system, rounded display sans, generous border radius, and cream-throughout mandate were all deliberately **not** carried over.

**Key Characteristics:**
- Warm off-white canvas (`{colors.canvas}` — #FAFAF7). Never a cool gray. The warmth is non-negotiable.
- Editorial serif (Literata) for **both** display and body copy. This is deliberate — running text is serif, not sans.
- Monospace (IBM Plex Mono) for eyebrow labels, page meta, and numeric readouts only.
- A single indigo accent. `{colors.accent}` on light, `{colors.accent-on-dark}` on dark.
- **Square corners on buttons.** Radius is minimal throughout (2–8px); nothing is pill-shaped except status badges.
- No shadows. Depth comes from hairline borders and the light/dark surface split.
- **Real photography with grain.** No illustration, no 3D renders, no mascots, no stock photos of smiling students.
- Section rhythm `{spacing.section}` (96px); hero bands `{spacing.hero}` (128px).
- Dark surfaces are functional, not decorative — they mark the coach and brain, nothing else.

## Colors

### Accent
- **Accent** (`{colors.accent}` — #3A3AA0): Indigo. Section eyebrows, links, focus rings, quote rules, active states on light surfaces.
- **Accent Strong** (`{colors.accent-strong}` — #2A2A7A): Pressed and active link states.
- **Accent on Dark** (`{colors.accent-on-dark}` — #8F8FF5): Periwinkle. The same role on dark surfaces, lightened for legibility.
- **Accent Wash** (`{colors.accent-wash}` — #EEEEF8): Policy callouts and highlighted passages on light. Use sparingly.

There is exactly one accent hue. Do not introduce a second.

### Surface — Light
- **Canvas** (`{colors.canvas}` — #FAFAF7): The default page floor.
- **Surface Soft** (`{colors.surface-soft}` — #F5F5F0): Footer, CTA bands.
- **Surface Card** (`{colors.surface-card}` — #F0EFE9): Editorial cards, result sections.
- **Surface Strong** (`{colors.surface-strong}` — #EBEAE3): Emphasized bands, badge fills.
- **Hairline** (`{colors.hairline}` — #D6D3D1): 1px borders.
- **Hairline Soft** (`{colors.hairline-soft}` — #E7E5E4): Internal dividers and table rules.

### Surface — Dark (coach and brain only)
- **Canvas Dark** (`{colors.canvas-dark}` — #14120F): Warm near-black. The coach and brain page floor.
- **Surface Dark** (`{colors.surface-dark}` — #1F1C18): Message bubbles, brain entries, readiness panel.
- **Surface Dark Elevated** (`{colors.surface-dark-elevated}` — #2A2622): The action card — the single most important element on the screen.
- **Hairline Dark** (`{colors.hairline-dark}` — #3A3530): Borders on dark.

The dark near-blacks are **warm** (they carry a stone/umber bias), not cool blue-black. This keeps them in family with the light canvas.

### Text
- **Ink** (`{colors.ink}` — #1C1917): Headlines and primary text.
- **Body Strong** (`{colors.body-strong}` — #292524): Lead paragraphs.
- **Body** (`{colors.body}` — #44403C): Default running text.
- **Muted** (`{colors.muted}` — #78716C): Sub-headings, meta, footer.
- **Muted Soft** (`{colors.muted-soft}` — #A8A29E): Captions, fine print, disabled.
- **On Dark** (`{colors.on-dark}` — #FAFAF7) / **On Dark Muted** (`{colors.on-dark-muted}` — #A8A29E).

### Semantic
Deliberately desaturated relative to conventional UI palettes — bright semantic color would break the restraint.
- **Success** (`{colors.success}` — #2F7A55) · **Warning** (`{colors.warning}` — #A8761F) · **Error** (`{colors.error}` — #A03530).

**Never** use semantic color to encode vocational categories, confidence levels, or readiness. Those are typographic and positional, not chromatic — color-coding a person's vocational direction cheapens it and creates accessibility problems.

## Typography

### Font Family
Three faces, each with a strict job:

- **Literata** (serif) — all display headlines **and** all running body copy. This is the editorial voice.
- **Satoshi** (sans) — UI only: titles, buttons, nav, form labels, small print.
- **IBM Plex Mono** (mono) — eyebrow labels, page meta, numeric readouts, timestamps. **New addition**; not currently in the stack.

Mixing these roles is a system violation. Body copy does not become sans because it is inside a card.

### Hierarchy

| Token | Family | Size | Weight | Line Height | Letter Spacing | Use |
|---|---|---|---|---|---|---|
| `{typography.display-xl}` | Literata | 64px | 400 | 1.05 | -1.5px | Landing hero |
| `{typography.display-lg}` | Literata | 48px | 400 | 1.08 | -1px | Section heads, result openings |
| `{typography.display-md}` | Literata | 36px | 400 | 1.15 | -0.5px | Sub-section heads, CTA bands |
| `{typography.display-sm}` | Literata | 28px | 400 | 1.2 | -0.25px | Pull quotes, Q10 pathway sentences |
| `{typography.title-lg}` | Satoshi | 22px | 600 | 1.3 | -0.2px | Panel titles |
| `{typography.title-md}` | Satoshi | 18px | 600 | 1.4 | 0 | Card titles, action titles |
| `{typography.title-sm}` | Satoshi | 16px | 600 | 1.4 | 0 | List labels, form group labels |
| `{typography.body-lg}` | Literata | 18px | 400 | 1.6 | 0 | Assessment questions, result narrative |
| `{typography.body-md}` | Literata | 16px | 400 | 1.6 | 0 | Default running text |
| `{typography.body-sm}` | Satoshi | 14px | 400 | 1.5 | 0 | UI copy, footer, fine print |
| `{typography.caption}` | Satoshi | 13px | 500 | 1.4 | 0 | Captions, helper text |
| `{typography.eyebrow}` | IBM Plex Mono | 12px | 500 | 1.4 | 1.5px | Section labels, uppercase |
| `{typography.meta}` | IBM Plex Mono | 12px | 400 | 1.4 | 0.5px | Timestamps, counts, version meta |
| `{typography.button}` | Satoshi | 14px | 600 | 1.0 | 0 | Buttons |
| `{typography.nav-link}` | Satoshi | 14px | 500 | 1.4 | 0 | Nav items |

### Principles
Literata at **regular weight with negative letter-spacing** is the brand voice at display sizes. Do not reach for bold — weight is not how this system creates emphasis. Size, space, and the accent rule are.

The eyebrow label is the system's signature move. Every major section opens with a monospace uppercase label above the serif headline. It is what makes the interface read as *engineered* rather than *editorial-decorative*.

Result copy carries the blueprint's voice constraints. The visual system must not fight them: no mechanical percentage displays, no score dials, no "76% match" readouts. Confidence is a word (`Strong` / `Moderate` / `Emerging` / `Weak` / `Insufficient evidence`), set in `{typography.eyebrow}`.

### Note on Font Substitutes
If **IBM Plex Mono** is unavailable, `JetBrains Mono` or `Söhne Mono` (licensed) are acceptable. Avoid the system `ui-monospace` default as a primary — its metrics vary too much across platforms for a label face. If **Literata** is unavailable, `Source Serif 4` or `Georgia` degrade acceptably.

## Layout

### Spacing System
- **Base unit:** 4px.
- **Tokens:** `{spacing.xxs}` 4 · `{spacing.xs}` 8 · `{spacing.sm}` 12 · `{spacing.md}` 16 · `{spacing.lg}` 24 · `{spacing.xl}` 32 · `{spacing.xxl}` 48 · `{spacing.section}` 96 · `{spacing.hero}` 128.
- **Section padding:** 96px between major bands; 128px for hero.
- **Card internal padding:** 32px for editorial and plan cards; 24px for messages, brain entries, and action cards.

### Grid & Container
Two container widths, chosen by content type:

- **Reading measure — 640px.** Assessment questions, results narrative, coach conversation, brain entries. Long-form prose that must not exceed a comfortable line length. This preserves the existing `AppLayout` convention.
- **Working width — 1120px.** Dashboards, the plan, cohort/admin views, marketing pages. Grids and side-by-side comparison.

Do not run body prose at 1120px. The 640px measure is load-bearing for a product whose core output is paragraphs a teenager has to actually read.

### Whitespace Philosophy
Whitespace is the primary expressive tool. Where Clay spends its budget on saturated color, this system spends it on air. If a screen feels empty, that is usually correct — reduce elements before reducing space.

## Elevation & Depth

| Level | Treatment | Use |
|---|---|---|
| Flat | No shadow, no border | Body sections, nav, hero |
| Hairline | 1px `{colors.hairline}` / `{colors.hairline-dark}` | Inputs, cards, table rules |
| Tonal | `{colors.surface-card}` / `{colors.surface-dark}` fill | Editorial cards, messages, brain entries |
| Elevated dark | `{colors.surface-dark-elevated}` + accent border | The action card only |
| Focus | 2px `{colors.accent}` outline, 2px offset | Keyboard focus, never removed |

**No drop shadows anywhere.** Depth is tonal and positional. This is what "engineered, not decorated" means in practice.

### Decorative Depth
- **Grainy real photography** — the only image type in the system. Photographs of real work, real places, real hands, real materials. Applied full-bleed on hero and CTA bands, with a subtle grain overlay.
- **Prohibited:** 3D renders, claymation, mascots, flat vector spot illustration, gradient meshes, and stock photography of smiling students. The Vision Document rules these out by name.

## Shapes

### Border Radius Scale

| Token | Value | Use |
|---|---|---|
| `{rounded.none}` | 0px | **All buttons.** Preserves existing convention. |
| `{rounded.xs}` | 2px | Focus rings, small chips |
| `{rounded.sm}` | 4px | Inputs, textareas, brain entries, policy callouts |
| `{rounded.md}` | 6px | Cards, messages, action cards, plan chunks |
| `{rounded.lg}` | 8px | Large panels, media containers |
| `{rounded.pill}` | 9999px | Status and confidence badges only |

This is a **deliberate divergence from Clay**, whose 12/16/24px scale matches its rounded display face. With an editorial serif and a "precise, quietly confident" register, generous radius reads soft and consumer. Square buttons are the system's default.

## Components

**`top-nav`** — 64px, canvas background, hairline bottom border. Nav links in Satoshi 14/500. No shadow on scroll; the hairline is sufficient.

**`button-primary`** — Ink fill, canvas text, square, 44px, 14/24px padding. The one high-contrast element on a light page.
**`button-secondary`** — Transparent with a hairline border. Used for everything that is not the single primary action on a screen.
**`button-on-dark`** — Inverted for coach and brain surfaces.
**`button-text-link` / `text-link`** — Accent colored, underlined on hover only.

**`section-label`** — The eyebrow. Monospace, uppercase, accent colored, 1.5px tracking, sitting above a serif headline. Present on every major section.

**`editorial-card`** — Tonal cream fill, 6px radius, 32px padding, no border. The default container for result sections and long-form content.

**`quote-block`** — No fill. A 2px accent left rule with 24px padding. Set in `{typography.display-sm}`. Used for the 17 Q10 pathway sentences and pulled student language.

**`policy-callout`** — Accent wash fill with an accent left border. For limitations, confidence caveats, and consent/privacy notices. This is where the blueprint's required humility language lives visually.

**`text-input` / `textarea-narrative`** — Canvas fill, hairline border, 4px radius, 44px min height. The narrative textarea runs `{typography.body-lg}` serif at 18px — students write paragraphs here, and it should feel like writing, not like filling a form.

**`confidence-badge`** — Outlined pill, monospace uppercase, muted. Carries `Strong` / `Moderate` / `Emerging` / `Weak` / `Insufficient evidence`. **Never colored by level** — an amber "weak" badge would tell a 17-year-old something the blueprint forbids the system from saying.

**`category-tab` / `category-tab-active`** — Square, underline-on-active. No pill fills.

**`coach-surface`** — Dark canvas. The coach is the front door and should feel like a different room from the assessment.
**`coach-message-system`** — Tonal dark fill, 6px radius.
**`coach-message-student`** — Transparent with a hairline-dark border. The student's own words are visually distinct from the coach's, which matters given the brain's "surfaces what they said" rule.

**`action-card`** — Elevated dark with an accent border. **One per screen, never a list.** This is the single most important element in the product; the Vision Document's whole thesis is that a session ends with exactly one concrete thing to do.

**`readiness-meter`** — Dark panel, `{typography.display-md}` for the level, monospace meta for the delta. Must show what moves it. No dials, no gauges, no percentages.

**`habit-row`** — Borderless rows separated by hairline-dark rules. Daily and weekly cadence.

**`brain-entry`** — Tonal dark, 4px radius, verbatim student text in serif, monospace timestamp.
**`brain-entry-frozen`** — Muted text with a hairline border, for the lapsed-subscription frozen state. Must read as *preserved and waiting*, never as disabled or lost.

**`plan-chunk`** — Dark card with a hairline border. Holds a section of the four-year plan with its milestones inside it.

**`cta-band` / `footer`** — Surface-soft, 80px padding. Square, not rounded.

## Do's and Don'ts

### Do
- Anchor every light page on `{colors.canvas}` (#FAFAF7). The warmth differentiates it from cool-gray edtech.
- Open every major section with a monospace `{component.section-label}` eyebrow above a serif headline.
- Reserve dark surfaces for the coach and the brain. Their darkness is meaningful.
- Use real, grainy photography. Real work, real materials, real places.
- Keep exactly one `{component.action-card}` on screen at a time.
- Set running body copy in Literata serif, including inside cards.
- Let whitespace carry the emphasis. Reduce elements before reducing space.
- Use `{token.refs}` everywhere. Never inline a hex value.
- Express confidence and readiness typographically, never chromatically.

### Don't
- Don't use cool grays for canvas. The warm tint is non-negotiable.
- Don't introduce a second accent hue. One indigo does all the work.
- Don't use illustration, 3D renders, mascots, or stock photos of smiling students.
- Don't round buttons. Square is the default; pills are for status badges only.
- Don't add drop shadows. Depth is tonal and positional.
- Don't bold serif display type for emphasis. Use size and space.
- Don't show percentages, match scores, dials, or gauges. The blueprint forbids "76% match" language, and the visual system must not reintroduce it.
- Don't color-code vocational categories or confidence levels.
- Don't run body prose at the 1120px working width.
- Don't render the frozen brain state as greyed-out or disabled. It is preserved, not broken.

## Responsive Behavior

### Breakpoints

| Name | Width | Key Changes |
|---|---|---|
| Mobile | < 768px | Hamburger nav; display-xl 64→36px; single column; reading measure = full width minus 20px gutters; action card full-bleed |
| Tablet | 768–1024px | Nav tightens; grids 2-up; reading measure holds at 640px |
| Desktop | 1024–1440px | Full nav; grids 3-up; working width 1120px |
| Wide | > 1440px | As desktop with more air; content caps at 1120px |

### Touch Targets
- All buttons minimum 44 × 44px (WCAG AAA).
- `{component.text-input}` height 44px.
- `{component.habit-row}` check targets minimum 44px — these get tapped daily on a phone.

### Collapsing Strategy
- Nav collapses to hamburger below 768px.
- The five places (coach, brain, locker, habits, plan) become a persistent bottom bar on mobile — they must be *"reachable from anywhere, not buried inside a flow."*
- Grids reduce column count rather than scaling type.
- The action card never collapses or truncates. It is the point of the screen.

## Motion

Short, eased, purposeful — *"motion and layering that feels engineered, not decorated."*

- `{motion.fast}` 120ms — hover, focus, small state changes.
- `{motion.base}` 180ms — panel and message entrance, tab changes.
- `{motion.slow}` 280ms — surface transitions, light↔dark route changes.

No bounce, no spring, no parallax, no scroll-triggered reveals on content. Respect `prefers-reduced-motion` by dropping to opacity-only transitions.

## Iteration Guide

1. Work one component at a time; reference its YAML key (`{component.action-card}`, `{component.brain-entry-frozen}`).
2. Pick the surface first — light for assessment, results, marketing; dark for coach and brain. Surface choice is semantic.
3. Variants live as separate entries (`-active`, `-disabled`, `-frozen`).
4. Use `{token.refs}` everywhere; never inline hex.
5. Hover states are not documented per-component; they follow `{motion.fast}` with a one-step tonal change.
6. Display type stays Literata 400 with negative tracking. Body stays Literata. UI stays Satoshi. Labels stay mono.
7. Before adding color, try space, rule, or type weight instead.

## Known Gaps

Closed since this document was written:

- ~~IBM Plex Mono is not yet in the font stack.~~ Shipped, via `.type-eyebrow`.
- ~~No dark surfaces exist in the app today.~~ Shipped; the coach and brain
  use them, and `DesignSystemTest` asserts the dark room is inhabited.
- ~~The accent indigo is new.~~ Adopted; the warm stone it replaced is gone.
- ~~Mobile (Expo) token parity is not addressed here.~~ Reconciled 2026-09-16.
  It had drifted exactly as predicted, and not cosmetically: light `accent`
  was still the warm stone this document says indigo replaced, the dark canvas
  was `#0F1216` — a cool blue-black ruled out above — and dark `accent` was
  `#94A3B8`, **a second accent hue**. `DesignSystemTest` now parses this
  document and asserts `mobile/constants/theme.ts` against it, so the gap
  cannot reopen silently. A gap named in prose drifts; a gap named in a test
  cannot.

Still open, and deliberately:

- Photography direction (sourcing, grain treatment, duotone rules) is
  specified in principle but has no asset library yet. This needs pictures,
  not code.
- Data-visualization tokens (readiness over time, cohort charts) are out of
  scope. They stay out until there is something to plot that does not break
  the no-color-coding rule — which readiness and confidence never will.
- Email templates (the monthly parent email) are not covered. The parent
  surface ships as a token-addressed web report; when an email carries design
  rather than a link, it gets tokens and a test like everything else.
