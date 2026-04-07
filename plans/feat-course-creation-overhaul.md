# Course Creation Overhaul — Architecture Plan

## Problem

The current course creation form (`resources/js/Pages/Admin/Courses/Form.tsx`) exposes raw JSON textareas for `content_blocks` on both courses and modules. This is unusable for non-technical curriculum designers. Additionally, there's no way to import existing curriculum from external sources (PDFs, slides, documents, videos).

## Key Insight

The existing `content_blocks` JSON array is already a **block-based document model** — the same pattern used by Notion, Editor.js, and similar tools. The data layer is sound. The upgrade is entirely a presentation layer change for Phase 1.

## Existing Block Types

```json
[
  { "type": "text", "content": "..." },
  { "type": "reflection", "prompt": "..." },
  { "type": "checkpoint", "question": "...", "options": ["A", "B", "C"] },
  { "type": "video", "url": "...", "title": "..." }
]
```

## Phase 1: Visual Block Editor

**Goal:** Replace raw JSON textareas with a visual, drag-and-drop block editor.

**New npm dependencies:**
- `@tiptap/react` + core extensions — headless rich text editor (~30KB), React-native, fully styleable with Tailwind. Used only inside `text` blocks.
- `@dnd-kit/core` + `@dnd-kit/sortable` — drag-and-drop for block and module reordering.

**New frontend components:**

```
resources/js/Components/Admin/BlockEditor/
├── BlockEditor.tsx          # Main: renders block list, add/remove/reorder
├── BlockToolbar.tsx         # "Add block" menu
├── blocks/
│   ├── TextBlock.tsx        # Tiptap rich text editor
│   ├── ReflectionBlock.tsx  # Styled textarea for reflection prompts
│   ├── CheckpointBlock.tsx  # Question + options builder
│   ├── VideoBlock.tsx       # URL input with embed preview
│   ├── ImageBlock.tsx       # Upload or URL with preview (Phase 2)
│   └── PdfBlock.tsx         # Upload with filename display (Phase 2)
└── DragHandle.tsx           # Reusable drag handle
```

**How it works:**
- `BlockEditor` receives `value: ContentBlock[]` and `onChange: (blocks) => void`
- Each block type maps to its mini-editor component
- The editor reads/writes the exact same JSON structure the backend already expects
- `Form.tsx` replaces JSON textareas with `<BlockEditor>`

**Module management upgrade:**
- Accordion-style expand/collapse for modules
- Drag-and-drop module reordering (auto-updates sort_order)
- Each module gets its own `<BlockEditor>` for content_blocks

**Backend changes:** None. JSON structure is unchanged.

---

## Phase 2: Media Upload & Storage

**New model:** `CourseMedia`

```
course_media table:
- uuid id (PK)
- course_id (FK, nullable)
- filename, original_filename
- mime_type, size_bytes
- disk, path (S3 path)
- type enum: image, pdf, video, document, presentation
- metadata JSON (dimensions, duration, page count, etc.)
- uploaded_by (FK to users)
- timestamps, softDeletes
```

**New controller:** `AdminCourseMediaController`
- `store()` — upload file to S3, create CourseMedia record
- `destroy()` — soft delete + remove from S3
- `show()` — return signed temporary URL

**New block types:**
- `image` — `{ type: "image", media_id: "uuid", url: "signed_url", alt: "..." }`
- `pdf` — `{ type: "pdf", media_id: "uuid", url: "signed_url", title: "..." }`

---

## Phase 3: AI-Powered Content Import

**Architecture mirrors existing resume parsing pipeline:**
`ResumeParserService` → `ParseResumeUploadJob` pattern.

**New services:**

```
app/Services/CurriculumImport/
├── CurriculumImportService.php    # Orchestrator
├── Extractors/
│   ├── PdfExtractor.php           # Extract text from PDF
│   ├── DocxExtractor.php          # Extract using phpoffice/phpword
│   ├── YouTubeExtractor.php       # Fetch transcript via YouTube API
│   └── PresentationExtractor.php  # Extract slides
└── CurriculumParserAgent.php      # AI agent: raw text → modules + blocks
```

**New job:** `ParseCurriculumImportJob` (queue: ai-analysis)

**New model:** `CurriculumImport`

```
curriculum_imports table:
- uuid id (PK)
- course_id (FK, nullable)
- source_type enum: pdf, docx, youtube, pptx, google_doc
- source_path (S3 path or URL)
- status: pending, processing, ready, failed
- proposed_structure JSON (AI-generated modules + blocks)
- final_structure JSON (after user review)
- metadata JSON
- created_by (FK to users)
- timestamps
```

**Import UI flow:**
1. "Import Content" button → modal with source options
2. Upload file or paste URL
3. Backend processes → dispatches ParseCurriculumImportJob
4. UI polls for status
5. Preview/review screen with BlockEditor for tweaking
6. Confirm → modules + blocks merged into course

---

## Phase 4: Personalization Prompts Editor

Per-block personalization prompt editor — a text input below each block when "Enable Personalization" is toggled. Replaces the raw `personalization_prompts` JSON.

No migration needed — existing JSON structure maps prompts to blocks.

---

## Phasing Summary

| Phase | Scope | Backend Changes | New Dependencies |
|-------|-------|-----------------|------------------|
| 1 | Visual block editor + module UX | None | @tiptap/*, @dnd-kit/* |
| 2 | Media upload + image/pdf blocks | CourseMedia model + controller | None |
| 3 | AI content import pipeline | CurriculumImport model + services + job | None |
| 4 | Personalization prompts editor | None | None |

## Open Decisions

1. **Rich text editor:** Tiptap (recommended) vs plain Markdown textarea
2. **Import preview flow:** Separate wizard page vs inline modal/drawer
3. **Prioritization:** Start with Phase 1, then layer on subsequent phases
