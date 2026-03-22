# Phase 3A — Preview UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a three-panel preview page with file tree, CodeMirror 6 code viewer (syntax highlighting + inline editing), tabs, generation info, and regenerate flow — so users can view and tweak their AI-generated project scaffold.

**Architecture:** New `/projects/:projectId/preview` React route using AppLayout. PreviewLayout fetches all files and generation metadata on mount, manages selected file / tabs / edit state. FileTree parses flat file array into nested directories. CodeViewer wraps CodeMirror 6 with a custom dark theme. One new Laravel endpoint for inline file editing.

**Tech Stack:** React 18, CodeMirror 6 (@codemirror/*), Tailwind CSS 4, Laravel 12 (one new endpoint)

**Spec:** `docs/superpowers/specs/2026-03-22-phase3a-preview-ui-design.md`

---

## File Structure

### New files
```
resources/js/pages/Preview/
├── PreviewLayout.jsx       — main 3-panel page, data loading, regenerate flow, state management
├── FileTree.jsx             — collapsible directory tree with icons
├── CodeViewer.jsx           — CodeMirror 6 wrapper with custom theme + edit mode
├── PreviewTabs.jsx          — tab bar for open files (max 5)
├── PreviewToolbar.jsx       — breadcrumb + edit toggle + action buttons
└── GenerationInfo.jsx       — right sidebar with generation metadata
```

### Files to modify
```
resources/js/app.jsx                              — add preview route
resources/js/pages/ProjectList.jsx                — add "Preview" link for generated projects
resources/js/pages/Wizard/StepReview.jsx          — add generate trigger + redirect
app/Http/Controllers/GenerationController.php     — add updatePreviewFile + extend status()
routes/api.php                                    — add PUT preview file route
tests/Feature/GenerationTest.php                  — add preview file update tests
```

---

## Task 1: Install CodeMirror 6 + Backend Endpoint

**Files:**
- Modify: `app/Http/Controllers/GenerationController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/GenerationTest.php`
- Modify: `package.json` (npm install)

- [ ] **Step 1: Install CodeMirror npm packages**

```bash
cd /c/draplo
npm install codemirror @codemirror/view @codemirror/state @codemirror/language \
  @codemirror/lang-php @codemirror/lang-markdown @codemirror/lang-json @codemirror/lang-javascript \
  @codemirror/theme-one-dark @codemirror/search
```

- [ ] **Step 2: Add updatePreviewFile method to GenerationController**

Add this method to `app/Http/Controllers/GenerationController.php`:

```php
use Illuminate\Http\Request;

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

    if ($index === false) {
        return response()->json(['message' => 'File not found.'], 404);
    }

    $files[$index]['content'] = $request->input('content');
    $project->update(['generation_output' => $files]);

    return response()->json($files[$index]);
}
```

- [ ] **Step 3: Extend status() to include cache_read_tokens and created_at**

In the `status()` method, update the `$data['generation']` array to include:
```php
'cache_read_tokens' => $generation->cache_read_tokens,
'created_at' => $generation->created_at?->toISOString(),
```

- [ ] **Step 4: Add PUT route to routes/api.php**

Add inside the `auth:sanctum` group, after the existing preview routes:
```php
Route::put('/projects/{project}/preview/{filepath}', [GenerationController::class, 'updatePreviewFile'])
    ->where('filepath', '.*');
```

- [ ] **Step 5: Add backend tests**

Add to `tests/Feature/GenerationTest.php`:

```php
it('updates a preview file content', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Original'],
            ['path' => 'PROJECT.md', 'content' => '# Project'],
        ],
    ]);

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$project->id}/preview/CLAUDE.md", [
            'content' => '# Updated Content',
        ])
        ->assertOk()
        ->assertJsonPath('path', 'CLAUDE.md')
        ->assertJsonPath('content', '# Updated Content');

    $project->refresh();
    expect($project->generation_output[0]['content'])->toBe('# Updated Content');
});

it('returns 403 when updating another users preview file', function () {
    $other = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $other->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [['path' => 'test.md', 'content' => 'x']],
    ]);

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$project->id}/preview/test.md", ['content' => 'hacked'])
        ->assertForbidden();
});

it('returns 404 when updating preview of non-generated project', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$project->id}/preview/test.md", ['content' => 'x'])
        ->assertNotFound();
});

it('returns 404 for non-existent file path', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [['path' => 'CLAUDE.md', 'content' => '#']],
    ]);

    $this->actingAs($this->user)
        ->putJson("/api/projects/{$project->id}/preview/nonexistent.md", ['content' => 'x'])
        ->assertNotFound();
});
```

- [ ] **Step 6: Run tests**

```bash
php artisan test
```

Expected: All existing tests + 4 new tests pass.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: install CodeMirror 6, add updatePreviewFile endpoint with tests"
```

---

## Task 2: FileTree Component

**Files:**
- Create: `resources/js/pages/Preview/FileTree.jsx`

- [ ] **Step 1: Create FileTree**

Create `resources/js/pages/Preview/FileTree.jsx`:

The component receives `files` (flat array of `{path, content}`) and `activeFile` (path string) and `onSelectFile` callback.

It must:
1. Parse flat paths into a nested tree: `{ name, path, type: 'file'|'dir', children?, size? }`
2. Sort: directories first (alpha), then files (alpha)
3. Render recursively with indentation (`pl-4` per level)
4. Directories are collapsible (track expanded state, default all expanded)
5. File icons by extension: `.md` → `description`, `.php` → `code`, `.json` → `data_object`, default → `insert_drive_file`
6. Active file highlighted: `bg-primary/10 text-primary`
7. File size displayed: `font-mono text-[10px] text-outline` (format as KB)
8. Directory shows child count badge

Styling:
- Container: `h-full overflow-y-auto bg-surface-container-lowest border-r border-outline-variant/10 py-4`
- Each item: `flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-surface-container-low transition-colors`
- File names: `font-mono text-xs text-on-surface-variant`
- Directory toggle icon: `expand_more` / `chevron_right` (Material Symbol, 16px)

- [ ] **Step 2: Verify build**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add FileTree component with directory nesting and file icons"
```

---

## Task 3: CodeViewer with CodeMirror 6

**Files:**
- Create: `resources/js/pages/Preview/CodeViewer.jsx`

- [ ] **Step 1: Create CodeViewer**

Create `resources/js/pages/Preview/CodeViewer.jsx`:

A React component wrapping CodeMirror 6. Props:
- `content` (string) — file content to display
- `filePath` (string) — for language detection
- `editable` (boolean) — whether editing is enabled
- `onChange` (callback) — called with new content when edited
- `onSave` (callback) — called when user presses Ctrl+S in edit mode

Implementation:
1. Use `useRef` for the editor container div and EditorView instance
2. Create EditorView on mount with extensions:
   - Line numbers: `lineNumbers()`
   - Active line highlight: `highlightActiveLine()`
   - Fold gutter: `foldGutter()`
   - Search: `search()`
   - Language extension based on file path (detect from extension)
   - Custom dark theme (create with `EditorView.theme()`)
   - `EditorView.editable.of(editable)`
   - `EditorState.readOnly.of(!editable)`
   - Update listener that calls `onChange` with doc content
3. When `content` or `filePath` changes: replace the editor state entirely (new `EditorState.create()`)
4. When `editable` changes: reconfigure the editor

Custom theme colors:
- `&`: background `#0d0e11`
- `.cm-gutters`: background `#121316`, color `#908fa0`
- `.cm-activeLineGutter`: background `#1b1b1f`
- `.cm-activeLine`: background `#1b1b1f`
- `&.cm-focused .cm-cursor`: borderLeftColor `#c0c1ff`
- `&.cm-focused .cm-selectionBackground`: background `rgba(192, 193, 255, 0.2)`
- `.cm-selectionBackground`: background `rgba(192, 193, 255, 0.1)`

Language detection helper:
```js
function getLanguageExtension(filePath) {
    if (filePath.endsWith('.php')) return php();
    if (filePath.endsWith('.md')) return markdown();
    if (filePath.endsWith('.json')) return json();
    if (filePath.endsWith('.js') || filePath.endsWith('.jsx')) return javascript();
    return [];
}
```

- [ ] **Step 2: Verify build**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add CodeViewer with CodeMirror 6, custom dark theme, language detection"
```

---

## Task 4: PreviewTabs + PreviewToolbar + GenerationInfo

**Files:**
- Create: `resources/js/pages/Preview/PreviewTabs.jsx`
- Create: `resources/js/pages/Preview/PreviewToolbar.jsx`
- Create: `resources/js/pages/Preview/GenerationInfo.jsx`

- [ ] **Step 1: Create PreviewTabs**

Props: `tabs` (array of `{path, modified}`), `activeTab` (path), `onSelectTab`, `onCloseTab`

- Render horizontal row of tab buttons
- Each tab shows basename (e.g., `architecture.md` not full path)
- Active: `bg-surface-container text-primary border-t-2 border-primary`
- Inactive: `bg-surface-container-low text-on-surface-variant`
- Modified indicator: small `bg-primary` dot if `modified` is true
- Close button: small X icon, `text-outline hover:text-on-surface`
- Container: `flex items-center gap-0 border-b border-outline-variant/5 bg-surface-container-low overflow-x-auto`

- [ ] **Step 2: Create PreviewToolbar**

Props: `filePath`, `editable`, `onToggleEdit`, `onRegenerate`, `projectId`

- Left: "Back to Wizard" link (`arrow_back` icon + text, links to `/wizard/{projectId}`). File path breadcrumb in monospace — split path by `/`, join with ` / ` separator, each in `text-on-surface-variant`.
- Center: Edit toggle button. Icon: `edit` when read-only, `edit_off` when editable. Label toggles.
- Right: "Regenerate" (Button secondary), "Export" (Button primary, disabled with `title="Coming in Phase 3B"`).

Container: `flex items-center justify-between px-4 py-2 bg-surface-container-lowest border-b border-outline-variant/5`

- [ ] **Step 3: Create GenerationInfo**

Props: `generation` (object from API with tokens, cost, etc.)

Renders a card with labeled rows:
- Provider badge, model name, token counts (formatted with commas), cost ($X.XXXX), duration (Xs), cached badge, timestamp
- Labels: `font-label text-[11px] uppercase tracking-widest text-outline`
- Values: `font-mono text-sm text-on-surface`
- Card: `bg-surface-container rounded-xl p-5 border border-outline-variant/5`

Handle null/loading state: show "No generation data" placeholder.

- [ ] **Step 4: Verify build**

```bash
npm run build
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add PreviewTabs, PreviewToolbar, and GenerationInfo components"
```

---

## Task 5: PreviewLayout (Main Page)

**Files:**
- Create: `resources/js/pages/Preview/PreviewLayout.jsx`
- Modify: `resources/js/app.jsx`

- [ ] **Step 1: Create PreviewLayout**

This is the main orchestrator. It manages all state and composes the sub-components.

**State:**
- `files` — array from API (all generated files)
- `generation` — metadata from API
- `loading` — boolean
- `selectedFile` — path string (current file in CodeViewer)
- `openTabs` — array of `{path, modified}` (max 5)
- `editable` — boolean (edit mode toggle)
- `modifiedContent` — `Map<path, string>` tracking unsaved edits
- `regenerating` — boolean (overlay state)
- `toast` — `{message, type}` or null

**On mount:**
1. Fetch `GET /api/projects/{projectId}/preview` → set `files`
2. Fetch `GET /api/projects/{projectId}/generation` → set `generation`
3. If status is not `generated`, redirect to `/projects` with toast
4. Select first file, open it as first tab

**File selection** (from FileTree or Tab click):
1. If `editable` and current file has unsaved changes → confirm discard
2. Set `selectedFile` to new path
3. If path not in `openTabs` → add it (evict oldest if > 5)

**Edit mode:**
- Toggle `editable` state
- When CodeViewer reports change → store in `modifiedContent` map, mark tab as modified

**Save:**
- Call `PUT /api/projects/{projectId}/preview/{filepath}` with `{ content }`
- On success: update file in `files` array, remove from `modifiedContent`, unmark tab

**Regenerate:**
- Confirm dialog
- Call `POST /api/projects/{projectId}/regenerate`
- Set `regenerating: true`
- Poll `GET /api/projects/{projectId}/generation` every 2s
- On `generated`: reload files + generation data, reset tabs, dismiss overlay
- On `failed`: show error toast, dismiss overlay

**Layout:**
```jsx
<AppLayout activePage="preview">
    <div className="flex h-[calc(100vh-4rem)]">
        {/* Left: File Tree */}
        <div className="w-72 shrink-0">
            <FileTree files={files} activeFile={selectedFile} onSelectFile={handleSelectFile} />
        </div>

        {/* Center: Code */}
        <div className="flex-1 flex flex-col min-w-0">
            <PreviewToolbar ... />
            <PreviewTabs ... />
            <div className="flex-1 overflow-hidden">
                <CodeViewer
                    content={modifiedContent.get(selectedFile) ?? getFileContent(selectedFile)}
                    filePath={selectedFile}
                    editable={editable}
                    onChange={handleContentChange}
                />
            </div>
            {editable && modifiedContent.has(selectedFile) && (
                <div className="px-4 py-2 bg-surface-container border-t border-outline-variant/5 flex justify-end">
                    <Button variant="primary" onClick={handleSave} loading={saving}>
                        Save Changes
                    </Button>
                </div>
            )}
        </div>

        {/* Right: Generation Info */}
        <div className="w-64 shrink-0 p-4 overflow-y-auto">
            <GenerationInfo generation={generation} />
        </div>
    </div>

    {/* Regenerating overlay */}
    {regenerating && (
        <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex items-center justify-center">
            <div className="text-center">
                <div className="animate-spin ...">...</div>
                <p className="text-primary font-mono mt-4">Regenerating...</p>
            </div>
        </div>
    )}

    {toast && <Toast ... />}
</AppLayout>
```

- [ ] **Step 2: Add preview route to app.jsx**

Add import and route:
```jsx
import PreviewLayout from './pages/Preview/PreviewLayout';

// In Routes, before the catch-all:
<Route path="/projects/:projectId/preview" element={<PreviewLayout />} />
```

- [ ] **Step 3: Verify build**

```bash
npm run build
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add PreviewLayout with 3-panel view, tabs, edit mode, regenerate flow"
```

---

## Task 6: Navigation Integration (ProjectList + StepReview)

**Files:**
- Modify: `resources/js/pages/ProjectList.jsx`
- Modify: `resources/js/pages/Wizard/StepReview.jsx`

- [ ] **Step 1: Update ProjectList**

In `ProjectList.jsx`, add a "Preview" link for projects with `generated` status. Alongside the existing "Resume" link for draft projects:

```jsx
{project.status === 'generated' && (
    <Link
        to={`/projects/${project.id}/preview`}
        className="text-secondary text-sm font-medium hover:text-secondary-container transition-colors"
    >
        Preview
    </Link>
)}
```

- [ ] **Step 2: Update StepReview — add generate + redirect flow**

In `StepReview.jsx`, replace the disabled "Generate Scaffold" button with a functional one:

When the wizard is on step 6 (review) and the project status is `wizard_done`:
1. The "Generate" button becomes active (primary gradient, not disabled)
2. Clicking calls `POST /api/projects/{id}/generate` via the api module
3. Shows a "Generating..." state with spinner
4. Polls `GET /api/projects/{id}/generation` every 2 seconds
5. When status becomes `generated`: `navigate(`/projects/${projectId}/preview`)`
6. When status becomes `failed`: show error toast

This requires:
- Import `api` from `../../api`
- Import `useNavigate` from react-router-dom
- Add local state: `generating`, `generationError`
- The generate button replaces the static disabled button

- [ ] **Step 3: Verify build**

```bash
npm run build
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add Preview link in ProjectList, generate + redirect in StepReview"
```

---

## Task 7: Final Verification

**Files:** None (verification only)

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass (existing + 4 new preview update tests).

- [ ] **Step 2: Verify frontend build**

```bash
npm run build
```

- [ ] **Step 3: Verify all routes**

```bash
php artisan route:list --path=api 2>/dev/null | head -30
```

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: Phase 3A complete — Preview UI with CodeMirror 6"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | CodeMirror install + backend endpoint + tests | GenerationController, routes, GenerationTest |
| 2 | FileTree component | FileTree.jsx |
| 3 | CodeViewer with CodeMirror 6 | CodeViewer.jsx |
| 4 | PreviewTabs + PreviewToolbar + GenerationInfo | 3 components |
| 5 | PreviewLayout (main orchestrator) | PreviewLayout.jsx, app.jsx |
| 6 | Navigation integration | ProjectList.jsx, StepReview.jsx |
| 7 | Final verification | Tests + build |
