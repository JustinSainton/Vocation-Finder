# Photography Direction — For Approval Before Sourcing

**Status:** SPEC ONLY — no images ship with this document. Nothing here is final
until Justin approves subject list, treatment, and licensing.
**Governs:** DESIGN.md § Decorative Depth + Known Gaps ("needs pictures, not code").

## Why this exists

DESIGN.md allows exactly one image type — **grainy real photography** — and
rules out, by name: 3D renders, claymation, mascots, flat vector spot
illustration, gradient meshes, and stock photos of smiling students. The app
currently ships zero photography, so every hero and CTA band is typography on
canvas. That restraint works, but the arrival screens are the one place where
a photograph does what type cannot: prove, before a word is read, that this
product is about real work in the real world.

## Subjects (proposed, 6)

Real work, real places, real hands, real materials. No faces performing
emotion for the camera; hands and places carry the meaning.

1. Hands mending — fishing net, bicycle chain, torn jacket. Repair as attention.
2. A workshop bench at end of day — tools down, shavings swept into a pile.
3. Kitchen pass during service — tickets up, hands plating. Work under pressure.
4. A field or garden row at first light — cultivation, seasons, patience.
5. Stairwell / hallway of a school or hospital — thresholds people cross daily.
6. An empty chair pulled up to a worktable — the invitation. Reserved for the
   arrival screen if only one image ships.

## Treatment (non-negotiable)

- Subtle film grain overlay on every image, same density everywhere.
- Warm black point: shadows lean umber/stone (`#14120F` family), never blue.
- No duotone, no color wash, no vignette presets. If legibility needs help,
  darken via a warm translucent scrim, not a filter.
- Landscape orientation, minimum 2400px wide. Full-bleed on hero and CTA
  bands only; nowhere else.

## Placement map

| Surface | Image | Notes |
|---|---|---|
| Arrival (web `/`, mobile assessment index) | #6, the chair | Below the headline or full-bleed top; must not push the honesty commitment below the fold on a 390px-wide phone |
| CTA bands (`cta-band`) | Rotate 1–5 | One per band, never repeated on the same page |
| Coach and brain | None, ever | Dark surfaces are functional. Photography there would decorate the two rooms the system keeps deliberately bare. |
| Results portrait | None | The portrait is a letter. Letters do not have hero images. |

## Sourcing and license

- Commission or license, never scrape. Preferred: a half-day shoot with a
  documentary photographer (real workplaces, real permission).
- Fallback: licensed stock filtered hard — search "hands at work", reject
  anything with eye contact and a smile, reject anything color-graded teal.
- Every shipped file records photographer, license, and date in
  `public/images/provenance.json` (web) and the asset catalog notes (mobile).
- Total budget for launch: six images. Not sixty. Restraint is the brand.

## Approval gate

Do not source, commission, or ship any photograph until Justin signs:
1. this subject list (add/remove/replace),
2. the grain + warm-black treatment on one sample,
3. the arrival-screen placement mock.

When approved, assets land in `public/images/` + `mobile/assets/images/`
with the provenance file, and `DesignSystemTest` gains an assertion that
every referenced image exists — the same "a gap named in a test cannot
reopen silently" discipline DESIGN.md already uses for tokens.
