# Free Open Source Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove all Stripe/payment infrastructure and convert Draplo from a three-tier paid model (Free/Pro/Pro+) to 100% free open source with "Buy Me a Coffee" donation support.

**Architecture:** This is a removal-heavy refactor. We strip Stripe columns/config/enums/plan-gating code, replace the landing page pricing section with a free+donate message, update Dashboard/Settings/Sidebar/Footer/README to remove payment references, add a `DONATE_URL` env var, and create a `<BuyMeACoffee />` React component. A new migration drops Stripe columns from the users table.

**Tech Stack:** Laravel 12, React 18, Blade, Tailwind CSS 4, PostgreSQL 16

---

### Task 1: Database Migration — Drop Stripe Columns

**Files:**
- Create: `database/migrations/2026_03_23_000001_remove_stripe_columns_from_users.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'plan', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id', 100)->nullable();
            $table->string('plan', 50)->default('free');
            $table->timestamp('paid_at')->nullable();
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: Migration completes, `stripe_customer_id`, `plan`, `paid_at` columns removed from users table.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_03_23_000001_remove_stripe_columns_from_users.php
git commit -m "feat: add migration to drop Stripe columns from users table"
```

---

### Task 2: Remove UserPlanEnum and Clean User Model

**Files:**
- Delete: `app/Enums/UserPlanEnum.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Delete UserPlanEnum**

Delete the file `app/Enums/UserPlanEnum.php` entirely.

- [ ] **Step 2: Update User model — remove Stripe references**

In `app/Models/User.php`:

1. Remove the `use App\Enums\UserPlanEnum;` import (line 5)
2. Remove from `$fillable`: `'stripe_customer_id'`, `'plan'`, `'paid_at'` (lines 31-33)
3. Remove from `casts()`: `'plan' => UserPlanEnum::class` and `'paid_at' => 'datetime'` (lines 58-59)
4. Remove methods: `isFree()`, `isPaid()`, `isSubscriber()` (lines 75-88)

The resulting `$fillable` should be:
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'github_id',
    'github_token',
    'github_username',
    'avatar_url',
    'generation_count',
    'is_admin',
];
```

The resulting `casts()` should be:
```php
protected function casts(): array
{
    return [
        'password' => 'hashed',
        'github_token' => 'encrypted',
        'is_admin' => 'boolean',
    ];
}
```

- [ ] **Step 3: Clean up UserFactory**

In `database/factories/UserFactory.php`:
1. Remove `use App\Enums\UserPlanEnum;` import (line 5)
2. Remove `'plan' => UserPlanEnum::Free,` from `definition()` (line 28)

- [ ] **Step 4: Clean up DevUserSeeder**

In `database/seeders/DevUserSeeder.php`:
Remove `'plan' => 'free',` from the `updateOrCreate` call (line 18).

- [ ] **Step 5: Verify no other files reference UserPlanEnum**

Run: `grep -r "UserPlanEnum" app/ config/ database/ tests/`
Expected: No results (all references removed).

- [ ] **Step 6: Commit**

```bash
git add app/Enums/UserPlanEnum.php app/Models/User.php database/factories/UserFactory.php database/seeders/DevUserSeeder.php
git commit -m "feat: remove UserPlanEnum and Stripe fields from User model, factory, seeder"
```

> **Note:** Tasks 1-2 should be treated as atomic. Do not run the test suite between them — the migration drops columns that the model/factory still reference until Task 2 is complete.

---

### Task 3: Remove Stripe from Config and Feature Flags

**Files:**
- Modify: `config/app.php` (lines 137)
- Modify: `app/Http/Controllers/ConfigController.php` (line 12)
- Modify: `.env.example` (line 66)
- Modify: `.env.production.example` (lines 62, 83-86)

- [ ] **Step 1: Remove Stripe flag from config/app.php**

In `config/app.php`, remove line 137:
```php
'stripe' => env('STRIPE_ENABLED', true),
```

Also rename `premium_templates` to `templates`:
```php
'templates' => env('TEMPLATES_ENABLED', true),
```

Add donate/repo config entries:
```php
'donate_url' => env('DONATE_URL', 'https://buymeacoffee.com/darko'),
'github_repo_url' => env('GITHUB_REPO_URL', 'https://github.com/DareGr/draplo'),
```

- [ ] **Step 2: Update ConfigController**

In `app/Http/Controllers/ConfigController.php`, remove:
```php
'stripe_enabled' => (bool) config('app.flags.stripe', true),
```

And change:
```php
'premium_templates_enabled' => (bool) config('app.flags.premium_templates', true),
```
to:
```php
'templates_enabled' => (bool) config('app.flags.templates', true),
```

- [ ] **Step 3: Update .env.example**

Remove:
```
STRIPE_ENABLED=true
```

Rename:
```
PREMIUM_TEMPLATES_ENABLED=true
```
to:
```
TEMPLATES_ENABLED=true
```

Add at the end:
```
# Donate / Community
DONATE_URL=https://buymeacoffee.com/darko
GITHUB_REPO_URL=https://github.com/DareGr/draplo
```

- [ ] **Step 4: Update .env.production.example**

Remove `STRIPE_ENABLED=true` from feature flags section.
Remove the entire Stripe section (lines 83-86):
```
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

Rename `PREMIUM_TEMPLATES_ENABLED=true` to `TEMPLATES_ENABLED=true`.

Add:
```
# Donate / Community
DONATE_URL=https://buymeacoffee.com/darko
GITHUB_REPO_URL=https://github.com/DareGr/draplo
```

- [ ] **Step 5: Commit**

```bash
git add config/app.php app/Http/Controllers/ConfigController.php .env.example .env.production.example
git commit -m "feat: remove Stripe feature flag, add DONATE_URL config"
```

---

### Task 4: Create BuyMeACoffee React Component

**Files:**
- Create: `resources/js/components/BuyMeACoffee.jsx`

- [ ] **Step 1: Create the component**

```jsx
export default function BuyMeACoffee({ size = 'default' }) {
    const donateUrl = window.__draplo?.donateUrl || 'https://buymeacoffee.com/darko';
    const githubUrl = window.__draplo?.githubRepoUrl || 'https://github.com/DareGr/draplo';

    if (size === 'small') {
        return (
            <a
                href={donateUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-xs font-mono text-on-surface-variant hover:text-primary transition-colors"
            >
                <span className="material-symbols-outlined text-sm">coffee</span>
                Buy Me a Coffee
            </a>
        );
    }

    return (
        <div className="flex flex-col items-center gap-4">
            <div className="flex gap-3">
                <a
                    href={donateUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-bold text-sm hover:opacity-90 transition-opacity"
                >
                    <span className="material-symbols-outlined text-lg">coffee</span>
                    Buy Me a Coffee
                </a>
                <a
                    href={githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-5 py-2.5 border border-outline-variant/15 text-on-surface rounded-md font-medium text-sm hover:bg-surface-container-high transition-colors"
                >
                    <span className="material-symbols-outlined text-lg">star</span>
                    Star on GitHub
                </a>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/BuyMeACoffee.jsx
git commit -m "feat: add BuyMeACoffee React component"
```

---

### Task 5: Replace Landing Page Pricing Section

**Files:**
- Modify: `resources/views/landing.blade.php` (lines 47-62 nav, lines 397-515 pricing, lines 537-550 footer)

- [ ] **Step 1: Update navigation — remove Pricing link**

In `resources/views/landing.blade.php`, replace line 53:
```html
<a class="text-on-surface-variant hover:text-white transition-colors text-sm" href="#pricing">Pricing</a>
```
with:
```html
<a class="text-on-surface-variant hover:text-white transition-colors text-sm" href="#community">Community</a>
```

- [ ] **Step 2: Replace pricing section with free+donate section**

Replace the entire `<section id="pricing" ...>` (lines 397-515) with:

```html
<section id="community" class="py-24 px-8 bg-surface-container-lowest">
    <div class="max-w-3xl mx-auto text-center">
        <span class="text-primary font-mono text-xs tracking-widest uppercase">Open Source</span>
        <h2 class="text-4xl font-extrabold text-white mt-2">Completely Free. Forever.</h2>
        <p class="text-on-surface-variant mt-4 max-w-xl mx-auto text-lg">
            No premium tiers. No paywalls. No limits.<br>
            Every feature &mdash; all 25 templates, GitHub export, BYOS deploy &mdash; is yours.
        </p>

        <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-surface-container rounded-xl p-6">
                <span class="material-symbols-outlined text-primary text-3xl">smart_toy</span>
                <h3 class="text-white font-bold mt-3">AI Generation</h3>
                <p class="text-on-surface-variant text-sm mt-1">All 25 templates, unlimited generations</p>
            </div>
            <div class="bg-surface-container rounded-xl p-6">
                <span class="material-symbols-outlined text-primary text-3xl">upload</span>
                <h3 class="text-white font-bold mt-3">Full Export</h3>
                <p class="text-on-surface-variant text-sm mt-1">GitHub push + ZIP download, no limits</p>
            </div>
            <div class="bg-surface-container rounded-xl p-6">
                <span class="material-symbols-outlined text-primary text-3xl">dns</span>
                <h3 class="text-white font-bold mt-3">BYOS Deploy</h3>
                <p class="text-on-surface-variant text-sm mt-1">Hetzner, DO, Linode, Vultr &mdash; your server</p>
            </div>
        </div>

        <p class="text-on-surface-variant mt-12 text-sm">
            If Draplo saves you time, consider supporting development.
        </p>
        <div class="mt-4 flex justify-center gap-3">
            <a href="{{ config('app.donate_url') }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-bold text-sm hover:opacity-90 transition-opacity">
                <span class="material-symbols-outlined text-lg">coffee</span>
                Buy Me a Coffee
            </a>
            <a href="{{ config('app.github_repo_url') }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-5 py-2.5 border border-outline-variant/15 text-on-surface rounded-md font-medium text-sm hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-lg">star</span>
                Star on GitHub
            </a>
        </div>
    </div>
</section>
```

- [ ] **Step 3: Update footer — add donate link**

In the footer `<nav>` (around line 543), add before the Privacy link:
```html
<a href="{{ config('app.donate_url') }}" target="_blank" rel="noopener" class="font-mono text-xs text-outline hover:text-on-surface-variant transition-colors">Donate</a>
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: replace pricing section with free+donate on landing page"
```

---

### Task 6: Update Dashboard — Remove Plan Card

**Files:**
- Modify: `resources/js/pages/Dashboard.jsx` (lines 82, 96-108)

- [ ] **Step 1: Remove plan stat card**

In `resources/js/pages/Dashboard.jsx`:

1. Remove line 82: `const planLabel = user?.plan || 'free';`
2. Replace the Plan stat card (lines 100-108) with a community card:
```jsx
<StatCard icon="favorite" value={
    <span className="inline-flex items-center px-2.5 py-0.5 bg-primary/15 text-primary rounded text-lg font-bold font-mono">
        Open Source
    </span>
} label="Community" />
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/Dashboard.jsx
git commit -m "feat: replace Plan stat card with Community badge on Dashboard"
```

---

### Task 7: Update Settings — Remove Plan and Billing Sections

**Files:**
- Modify: `resources/js/pages/Settings.jsx` (lines 48, 96-113)

- [ ] **Step 1: Remove plan card from Settings**

In `resources/js/pages/Settings.jsx`:

1. Remove line 48: `const planLabel = user?.plan || 'free';`
2. Replace the Plan card (lines 96-113) with:
```jsx
{/* Community Card */}
<div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
    <div className="flex items-center gap-2 mb-3">
        <span className="material-symbols-outlined text-primary text-xl">favorite</span>
        <h3 className="text-on-surface font-bold">Community</h3>
    </div>
    <span className="inline-flex items-center px-3 py-1 bg-primary/15 text-primary rounded text-sm font-bold font-mono">
        Open Source &mdash; Free forever
    </span>
    <div className="mt-3">
        <a
            href={window.__draplo?.donateUrl || 'https://buymeacoffee.com/darko'}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary text-sm font-medium hover:underline"
        >
            Buy Me a Coffee
        </a>
    </div>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/Settings.jsx
git commit -m "feat: replace Plan card with Community card on Settings page"
```

---

### Task 8: Update TopBar and Sidebar — Remove Pricing/Pro References

**Files:**
- Modify: `resources/js/components/TopBar.jsx` (line 5)
- Modify: `resources/js/components/Sidebar.jsx` (lines 87-88)

- [ ] **Step 0: Update TopBar — change Pricing link**

In `resources/js/components/TopBar.jsx`, change line 5:
```jsx
{ label: 'Pricing', to: '/pricing' },
```
to:
```jsx
{ label: 'Community', to: '/#community' },
```

- [ ] **Step 1: Update sidebar user info**

In `resources/js/components/Sidebar.jsx`, replace lines 87-88:
```jsx
<p className="text-xs font-label text-primary truncate">Pro Plan</p>
<p className="text-[10px] font-mono text-on-surface-variant">Architect Mode</p>
```
with:
```jsx
<p className="text-xs font-label text-primary truncate">Open Source</p>
<p className="text-[10px] font-mono text-on-surface-variant">Community Edition</p>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/TopBar.jsx resources/js/components/Sidebar.jsx
git commit -m "feat: update TopBar and Sidebar — remove Pricing/Pro Plan references"
```

---

### Task 9: Update README.md

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add badges and update feature flags table**

At the top of README.md, after the title line, add badges:
```markdown
[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-donate-yellow?logo=buy-me-a-coffee&logoColor=white)](https://buymeacoffee.com/darko)
[![GitHub Stars](https://img.shields.io/github/stars/DareGr/draplo?style=social)](https://github.com/DareGr/draplo)
[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
```

- [ ] **Step 2: Update feature flags table**

Remove the `STRIPE_ENABLED` row. Rename `PREMIUM_TEMPLATES_ENABLED` to `TEMPLATES_ENABLED`. Remove the line "Disabling `STRIPE_ENABLED` makes all features free (perfect for self-hosting)."

Add a new row:
```
| `DONATE_URL` | `https://buymeacoffee.com/darko` | Donation link shown in UI |
| `GITHUB_REPO_URL` | `https://github.com/DareGr/draplo` | GitHub repo link shown in UI |
```

- [ ] **Step 3: Add support section at the bottom, before License**

```markdown
## Support Development

Draplo is 100% free and open source. If it saved you time, consider supporting:

- [Buy Me a Coffee](https://buymeacoffee.com/darko)
- [Star on GitHub](https://github.com/DareGr/draplo)
- [Contribute](CONTRIBUTING.md)
```

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "feat: update README with donation badges and remove pricing references"
```

---

### Task 10: Update CLAUDE.md and PROJECT.md

**Files:**
- Modify: `CLAUDE.md`
- Modify: `PROJECT.md`
- Modify: `todo.md`

- [ ] **Step 1: Update CLAUDE.md**

In `CLAUDE.md`, update the **Business model** section to reflect:
```
**Business model:** 100% free, open source (AGPL-3.0). Community-supported via donations (Buy Me a Coffee). No premium tiers, no paywalls. All features available to all users.
```

Remove references to:
- "Draplo Cloud — Free/Pro/Pro+" tiers
- "$29 one-time" and "$12/mo" pricing
- `EnsureUserIsPro` and `EnsureUserIsProPlus` middleware
- Stripe Configuration section
- `STRIPE_ENABLED` from Feature Flags section

Rename `PREMIUM_TEMPLATES_ENABLED` to `TEMPLATES_ENABLED` in Feature Flags.

Add to Feature Flags:
```
DONATE_URL=https://buymeacoffee.com/darko
GITHUB_REPO_URL=https://github.com/DareGr/draplo
```

- [ ] **Step 2: Update PROJECT.md**

Remove pricing structure section (lines 51-64). Replace with:
```
**Access model:** All features are free for all authenticated users. No paid tiers.
```

- [ ] **Step 3: Update todo.md**

Mark Stripe tasks as removed/cancelled:
- Line 101: Change `- [ ] Stripe integration` to `- [x] ~~Stripe integration~~ — removed (free model)`
- Line 102: Change `- [ ] Stripe webhook handler` to `- [x] ~~Stripe webhook handler~~ — removed`
- Line 103: Change `- [ ] Free preview gate` to `- [x] ~~Free preview gate~~ — removed`
- Line 105: Change `- [ ] Write tests: ... Stripe payment flow` to remove Stripe reference

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md PROJECT.md todo.md
git commit -m "docs: update project docs to reflect free open source model"
```

---

### Task 11: Update .claude-reference/ Docs and MANUAL-TESTING.md

**Files:**
- Modify: `.claude-reference/architecture.md` — remove `stripe_events` table schema, Stripe API endpoints, `stripe_customer_id`/`paid_at` from users table schema, Stripe in directory tree
- Modify: `.claude-reference/patterns.md` — remove "Payment Gate Middleware" section (EnsureUserIsPro/EnsureUserIsProPlus examples)
- Modify: `.claude-reference/decisions.md` — update feature flags rationale to remove Stripe, remove Stripe webhook idempotency gotcha
- Modify: `docs/MANUAL-TESTING.md` — remove `STRIPE_ENABLED=true` from env setup (line 38)

- [ ] **Step 1: Update architecture.md**

Remove or update: `stripe_events` table, Stripe service/controller in directory tree, Stripe API endpoints, `stripe_customer_id` and `paid_at` from users table schema.

- [ ] **Step 2: Update patterns.md**

Remove the "Payment Gate Middleware" section entirely (the EnsureUserIsPro and EnsureUserIsProPlus middleware pattern examples).

- [ ] **Step 3: Update decisions.md**

Remove Stripe from feature flags decision rationale. Remove "Stripe webhook idempotency" gotcha.

- [ ] **Step 4: Update MANUAL-TESTING.md**

Remove `STRIPE_ENABLED=true` from the env setup instructions (line 38).

- [ ] **Step 5: Commit**

```bash
git add .claude-reference/architecture.md .claude-reference/patterns.md .claude-reference/decisions.md docs/MANUAL-TESTING.md
git commit -m "docs: remove Stripe references from architecture docs and testing guide"
```

---

### Task 12: Update Tests — Remove Plan References

**Files:**
- Check and modify any test files referencing `plan`, `UserPlanEnum`, `isPaid`, `isSubscriber`, `stripe`

- [ ] **Step 1: Find all test references**

Run: `grep -rn "plan\|UserPlanEnum\|isPaid\|isSubscriber\|stripe\|Stripe" tests/`

- [ ] **Step 2: Update any tests that set `plan` on user factories or assertions**

Remove `'plan' => 'free'` or `'plan' => UserPlanEnum::Free` from test user creation. Remove any assertions about plan values.

- [ ] **Step 3: Run full test suite**

Run: `php artisan test`
Expected: All tests pass with no references to removed columns/enums.

- [ ] **Step 4: Commit**

```bash
git add tests/
git commit -m "test: remove Stripe/plan references from test suite"
```

---

### Task 13: Update Feature Flag Test

**Files:**
- Modify: test file for ConfigController flags endpoint

- [ ] **Step 1: Find the flag test**

Run: `grep -rn "stripe_enabled\|flags" tests/`

- [ ] **Step 2: Update test assertions**

Remove assertion for `stripe_enabled`. Rename `premium_templates_enabled` to `templates_enabled`.

- [ ] **Step 3: Run test**

Run: `php artisan test --filter=flags`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add tests/
git commit -m "test: update feature flag test to remove Stripe, rename templates flag"
```

---

### Task 14: Update FULL_PRODUCTION_TEST.md

**Files:**
- Modify: `docs/FULL_PRODUCTION_TEST.md`

- [ ] **Step 1: Update pricing section tests**

Replace Part 2.8 (Pricing Section) tests to check for the new "Completely Free. Forever." section instead of three pricing cards.

- [ ] **Step 2: Update feature flags test**

In Part 14.1, remove `stripe_enabled` from expected JSON. Rename `premium_templates_enabled` to `templates_enabled`.

- [ ] **Step 3: Update Dashboard tests**

In Part 10.1, change "Plan" stat card check to "Community" / "Open Source".

- [ ] **Step 4: Update Settings tests**

In Part 11.2, replace Plan section check with Community section.

- [ ] **Step 5: Commit**

```bash
git add docs/FULL_PRODUCTION_TEST.md
git commit -m "docs: update production test plan for free model refactor"
```

---

### Task 15: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test`
Expected: All tests pass.

- [ ] **Step 2: Search for any remaining Stripe/plan references**

Run: `grep -rn "stripe\|Stripe\|isPaid\|isSubscriber\|UserPlanEnum\|Pro Plan\|Pro+\|EnsureUserIsPro" app/ resources/ config/ routes/ --include="*.php" --include="*.jsx" --include="*.blade.php"`
Expected: Only legitimate references (e.g., Stripe integration in wizard StepIntegrations for generated output scaffolds — this is correct and should stay).

- [ ] **Step 3: Build frontend assets**

Run: `npm run build`
Expected: Build succeeds with no errors.

- [ ] **Step 4: Final commit if any remaining cleanup**

```bash
git add -A
git commit -m "chore: final cleanup for free open source refactor"
```
