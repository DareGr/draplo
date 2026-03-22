# Phase 3A — Preview UI Design Spec

**Date:** 2026-03-22
**Status:** Approved
**Scope:** Preview page with file tree, CodeMirror 6 code viewer, tabs, inline editing, generation info, regenerate flow
**Depends on:** Phase 2A (AI generation engine) — completed

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Code viewer | CodeMirror 6 | Modular (~300KB), supports read-only + editing, lighter than Monaco (~2MB), more capable than Prism.js |
| Page location | Separate route `/projects/{id}/preview` | Preview (output) is a different purpose from wizard (input). Needs different layout with file tree + code viewer. |
| Inline editing | Edit mode toggle in CodeMirror | Users can tweak generated content before export. Changes saved via new PUT endpoint. |
| State management | All files loaded in memory from API | `generation_output` is typically <100KB total. No need for lazy loading or pagination. |

---

## 1. Preview Page Layout

**Route:** `/projects/:projectId/preview`

Uses `AppLayout` (sidebar + topbar). Three-panel layout:

- **Left panel (w-72):** FileTree — collapsible directory tree of all generated files
- **Center panel (flex-1):** PreviewToolbar + PreviewTabs + CodeViewer — file content with syntax highlighting
- **Right panel (w-64):** GenerationInfo — model, tokens, cost, duration, actions

**Data flow:**
1. On mount: `GET /api/projects/{projectId}/preview` — returns `{ files: [{path, content}] }`
2. Also fetch: `GET /api/projects/{projectId}/generation` — returns generation metadata
3. Default selection: first file (typically CLAUDE.md)
4. User clicks file in tree → updates selected file → CodeMirror renders content
5. Tabs track recently opened files (max 5)

**Access control:** Only accessible when project status is `generated`. If status is not `generated`, redirect to `/projects` with a toast message.

---

## 2. FileTree Component

**`resources/js/pages/Preview/FileTree.jsx`**

Parses flat `files` array into nested directory tree structure.

**Display:**
```
CLAUDE.md
PROJECT.md
todo.md
.claude-reference/
  architecture.md
  constants.md
  patterns.md
  decisions.md
database/
  migrations/
    2026_create_appointments.php
routes/
  api.php
```

**Behavior:**
- Directories collapsible (click to toggle), default all expanded
- File icons by type (Material Symbols):
  - `.md` → `description`
  - `.php` → `code`
  - `.json` → `data_object`
  - `api.php` → `route`
  - default → `insert_drive_file`
- Active file: `bg-primary/10 text-primary`
- File size in monospace next to name (e.g., `1.2 KB`)
- Directory shows child file count badge

**Styling:**
- Background: `bg-surface-container-lowest`
- Right border: ghost border `border-r border-outline-variant/10`
- File names: `font-mono text-xs`
- Directory names: `font-mono text-xs font-medium text-on-surface-variant`
- Indentation: `pl-4` per nesting level

---

## 3. CodeViewer with CodeMirror 6

**`resources/js/pages/Preview/CodeViewer.jsx`**

**npm packages:**
- `codemirror`, `@codemirror/view`, `@codemirror/state`, `@codemirror/language`
- `@codemirror/lang-php`, `@codemirror/lang-markdown`, `@codemirror/lang-json`, `@codemirror/lang-javascript`
- `@codemirror/theme-one-dark` (base for custom theme)

**Custom dark theme** matching Draplo design tokens:
- Editor background: `#0d0e11` (surface-container-lowest)
- Gutter background: `#121316` (background)
- Selection: primary at 20% opacity
- Cursor: `#c0c1ff` (primary)
- Line numbers: `#908fa0` (outline)
- Active line: `#1b1b1f` (surface-container-low)

**Language detection** from file extension:
- `.md` → markdown()
- `.php` → php()
- `.json` → json()
- `.js`, `.jsx` → javascript()
- default → plain text (no language extension)

**Features:**
- Line numbers (always)
- Syntax highlighting
- Code folding
- Search with Ctrl+F
- Read-only by default

**Edit mode:**
- Toggled via toolbar button
- When active: CodeMirror becomes editable, a "Save" button appears
- Local state tracks modified content per file
- "Save" calls `PUT /api/projects/{id}/preview/{filepath}`
- After save: update the file in local state, show success toast
- Unsaved changes: if user switches files with unsaved changes, show confirmation "Discard changes?"

---

## 4. PreviewTabs

**`resources/js/pages/Preview/PreviewTabs.jsx`**

Horizontal tab bar above the CodeViewer.

- Max 5 tabs open
- Each tab: file name (basename only, e.g., `architecture.md`) + close button (X)
- Active tab: `bg-surface-container text-primary` with `border-t-2 border-primary` (top accent, per mockup)
- Inactive: `bg-surface-container-low text-on-surface-variant hover:text-on-surface`
- Modified files show a dot indicator (unsaved changes)
- Clicking tab switches CodeViewer content (no API call — content in memory)
- Closing active tab: switch to the tab to its left (or first remaining tab)
- Opening a 6th tab: closes the oldest inactive tab

---

## 5. PreviewToolbar

**`resources/js/pages/Preview/PreviewToolbar.jsx`**

Bar between tabs and CodeViewer.

- **Left:** "Back to Wizard" link (Material Symbol `arrow_back` + text, navigates to `/wizard/{projectId}`). File path breadcrumb in monospace — e.g., `.claude-reference / architecture.md`.
- **Center:** Edit toggle — button with Material Symbol `edit` (read-only mode) or `edit_off` (edit mode). Label: "Edit" / "Read-only".
- **Right:**
  - "Regenerate" button (secondary variant). Shows confirmation dialog before calling API.
  - "Export" button (primary variant, disabled with tooltip "Coming in Phase 3B").

---

## 6. GenerationInfo

**`resources/js/pages/Preview/GenerationInfo.jsx`**

Right sidebar card showing generation metadata.

**Data from `GET /api/projects/{id}/generation`:** (Note: the existing `status()` method in GenerationController needs to be extended to include `cache_read_tokens` and `created_at` in the generation response object.)

| Field | Display |
|-------|---------|
| Provider | Badge: "anthropic" or "gemini" |
| Model | Monospace text |
| Input tokens | Monospace number with comma formatting |
| Output tokens | Monospace number |
| Cache read tokens | Monospace number (green if > 0 indicating cache hit) |
| Cost | Monospace `$0.1234` |
| Duration | Monospace `12.5s` |
| Cached | Green/amber badge |
| Generated at | Monospace timestamp |

**Styling:**
- Background: `bg-surface-container`
- Ghost border: `border border-outline-variant/5`
- Rounded: `rounded-xl`
- Labels: `font-label text-[11px] uppercase tracking-widest text-outline`
- Values: `font-mono text-sm text-on-surface`

---

## 7. Regenerate Flow

1. User clicks "Regenerate" in toolbar
2. Confirmation dialog: "This will regenerate all files. Any unsaved edits will be lost. Continue?"
3. If confirmed: `POST /api/projects/{id}/regenerate` → returns 202
4. PreviewLayout shows overlay: "Regenerating..." with spinner
5. Polls `GET /api/projects/{id}/generation` every 2 seconds (returns fresh project status each call)
6. When status becomes `generated`:
   a. Call `GET /api/projects/{id}/preview` to reload all file contents (replace in-memory state)
   b. Call `GET /api/projects/{id}/generation` once more to get final metadata (tokens, cost)
   c. Reset tabs to first file, dismiss overlay, show success toast
7. When status becomes `failed`: dismiss overlay, show error toast with message

---

## 8. New API Endpoint

**`PUT /api/projects/{project}/preview/{filepath}`**

Updates a single file's content in `generation_output` JSON.

- Validates ownership
- Validates status is `generated`
- Input: `{ content: string }`
- Finds the file by path in `generation_output` array, updates its content
- Returns: `{ path, content }` (the updated file)
- 404 if file path not found in generation_output

**Implementation** in `GenerationController`:
```php
public function updatePreviewFile(Request $request, Project $project, string $filepath): JsonResponse
{
    if ($project->user_id !== auth()->id()) {
        abort(403, 'Unauthorized.');
    }

    if ($project->status !== ProjectStatusEnum::Generated) {
        return response()->json(['message' => 'Project is not generated.'], 404);
    }

    $request->validate(['content' => 'required|string']);

    $files = $project->generation_output;
    $index = collect($files)->search(fn($f) => $f['path'] === $filepath);

    if ($index === false) return response()->json(['message' => 'File not found.'], 404);

    $files[$index]['content'] = $request->input('content');
    $project->update(['generation_output' => $files]);

    return response()->json($files[$index]);
}
```

**Route:** Add to `routes/api.php`:
```php
Route::put('/projects/{project}/preview/{filepath}', [GenerationController::class, 'updatePreviewFile'])
    ->where('filepath', '.*');
```

---

## 9. Navigation Integration

**ProjectList.jsx** — add "Preview" link for projects with status `generated`:
- Shows alongside existing "Resume Wizard" link
- Navigates to `/projects/{id}/preview`

**StepReview.jsx** — after generating:
- The disabled "Generate" button becomes active when status is `wizard_done`
- Clicking triggers generation (calls `POST /api/projects/{id}/generate`)
- After generation starts, shows "Generating..." with poll
- When complete, navigates to `/projects/{id}/preview`

**app.jsx** — add route:
```jsx
<Route path="/projects/:projectId/preview" element={<PreviewLayout />} />
```

---

## 10. Testing

**Backend (add to existing `tests/Feature/GenerationTest.php`):**
- PUT preview file updates content successfully
- PUT preview file returns 403 for other user's project
- PUT preview file returns 404 for non-existent file path
- PUT preview file returns 404 when project not generated

**No frontend tests** — manual browser testing, consistent with Phase 1/2 decision.

---

## 11. File Structure

### New files
```
resources/js/pages/Preview/
├── PreviewLayout.jsx       — main 3-panel page, data loading, regenerate flow
├── FileTree.jsx             — collapsible directory tree
├── CodeViewer.jsx           — CodeMirror 6 wrapper with custom theme
├── PreviewTabs.jsx          — tab bar for open files
├── PreviewToolbar.jsx       — breadcrumb + edit toggle + action buttons
└── GenerationInfo.jsx       — right sidebar with generation metadata
```

### Files to modify
```
resources/js/app.jsx                              — add preview route
resources/js/pages/ProjectList.jsx                — add "Preview" link
resources/js/pages/Wizard/StepReview.jsx          — add generate + redirect flow
app/Http/Controllers/GenerationController.php     — add updatePreviewFile method
routes/api.php                                    — add PUT preview file route
tests/Feature/GenerationTest.php                  — add preview file update tests
```

---

## 12. Out of Scope (Phase 3A)

- GitHub OAuth login (Phase 3B)
- GitHub export / push to repo (Phase 3B)
- Stripe payments / payment gate (Phase 3C)
- ZIP download (Phase 3D)
- Export button functionality (shows "Coming soon" tooltip)
- Monaco Editor (chose CodeMirror 6 instead)
- Diff view between regenerations (future)
- Collaborative editing (future)
- "Build Health" indicator from mockup (decorative, defer to Phase 5 dashboard)
- Status bar with line/column indicator, language badge (nice-to-have, add in polish pass)
- Keyboard shortcuts for tab switching (Ctrl+Tab, defer to polish)
