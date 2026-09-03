# srl_protest Frontend Template & Multi-Admin Voting System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide a dedicated modern frontend template for incident protests (`single-srl_protest.php`) with an embedded HTML5 / responsive media player, an AJAX-driven multi-admin voting engine with quorum and tie-breaker handling, user provisioning for `comisario-ai`, external steward delegation, frontend ruling actions, and a theme update deployment pipeline.

**Architecture:** 
- The CPT `srl_protest` is made publicly queryable with rewrite slug `reclamo` and an automatic incident naming convention.
- A new modular voting engine (`class-srl-protest-voting.php`) manages atomic post meta arrays (`_srl_protest_votes`), eliminating classic editor `_edit_lock` conflicts.
- `comisario-ai` user is automatically provisioned and votes according to configurable modes (`disabled`, `always`, `tiebreaker`).
- The frontend template (`single-srl_protest.php`) enforces visibility settings, embeds video evidence with slow-motion speed controls, and renders an interactive deliberation console for admins.
- PUC updater is extended to deploy `srl-theme.zip` alongside the plugin.

**Tech Stack:** PHP 8.2+, WordPress CPT / Rewrite API, WordPress AJAX & Nonces, HTML5 Video API, Plugin Update Checker (PUC v5).

## Global Constraints
- Naming format: `[Evento] - [Apellido Demandante] vs [Apellido Acusado] #[N]`
- User `comisario-ai` Display Name: `Comisario Virtual AI`
- Atomic post meta storage (`_srl_protest_votes`) to prevent race conditions and lockouts
- Minimum Quorum setting default: `3`
- Follow `AGENTS.md` and `.agents/rules/workflow.md` (TODO.md and CHANGELOG.md updates)

---

### Task 1: CPT `srl_protest` Public Queryable Routing & Automatic Naming Scheme

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php`
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php`

**Interfaces:**
- Produces: `srl_format_protest_title_and_slug( $post_id )`
- Updates: CPT `srl_protest` with `'publicly_queryable' => true`, `'rewrite' => ['slug' => 'reclamo', 'with_front' => false]`

- [ ] **Step 1: Update CPT registration args in `post-type-protest.php`**
Enable public queryability and configure rewrite rules:
```php
'public'             => true,
'publicly_queryable' => true,
'has_archive'        => false,
'rewrite'            => [ 'slug' => 'reclamo', 'with_front' => false ],
```

- [ ] **Step 2: Implement `srl_format_protest_title_and_slug()`**
Calculates the event name, extracts the last names of both drivers, counts existing protests for this pair in this event to determine `#[N]`, and sets `post_title` and `post_name` (slug):
```php
function srl_format_protest_title_and_slug( $post_id ) {
    // Extract event, protester last name, accused last name
    // Generate: "GP Monza - Pérez vs González #1"
    // Slug: "gp-monza-perez-gonzalez-1"
}
```

- [ ] **Step 3: Integrate with form submission and `save_post`**
Hook into `wp_insert_post` / `save_post_srl_protest` (preventing infinite recursion) and frontend submission in `shortcode-protest-form.php`.

- [ ] **Step 4: Verify via CLI & PHP Lint**
Run: `php -l wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php
git commit -m "feat: make srl_protest publicly queryable with automated title and slug format"
```

---

### Task 2: AI Steward User Provisioning & Voting/Quorum Administration Settings

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/admin-page.php`
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php`

**Interfaces:**
- Produces: `srl_ensure_ai_steward_user()` returning user ID of `comisario-ai`
- Options: `srl_protest_frontend_visibility`, `srl_protest_min_quorum`, `srl_protest_ai_vote_mode`

- [ ] **Step 1: Implement `srl_ensure_ai_steward_user()` in `post-type-protest.php`**
Checks `get_user_by('login', 'comisario-ai')`. If not found, creates user with login `comisario-ai`, display name `Comisario Virtual AI`, role `subscriber`, and meta `_srl_is_ai_steward => 1`.

- [ ] **Step 2: Add Settings Fields in `includes/admin-page.php`**
Under tab `commissary`:
1. `srl_protest_frontend_visibility`: Select (`public_sanitized` / `admins_only`).
2. `srl_protest_min_quorum`: Number field (min 1, default 3).
3. `srl_protest_ai_vote_mode`: Select (`disabled` / `always` / `tiebreaker`).

- [ ] **Step 3: Register settings in `register_setting`**
Add sanitize callbacks for visibility, quorum integer, and AI vote mode enum.

- [ ] **Step 4: Verify via PHP Lint**
Run: `php -l wp-content/plugins/srl-league-system/includes/admin-page.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/admin-page.php wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php
git commit -m "feat: add AI steward provisioning and commissary voting settings"
```

---

### Task 3: Multi-Admin & External Steward Voting Engine

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/commissary/class-srl-protest-voting.php`
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/rest-api.php`
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`

**Interfaces:**
- Class `SRL_Protest_Voting`:
  - `get_votes( $protest_id )`
  - `cast_admin_vote( $protest_id, $user_id, $decision, $notes )`
  - `add_external_vote( $protest_id, $steward_name, $decision, $notes, $added_by )`
  - `delete_vote( $protest_id, $vote_key )`
  - `evaluate_ai_verdict_vote( $protest_id, $verdict )`
  - `calculate_tally( $protest_id )`
- Endpoints:
  - `srl_cast_protest_vote`
  - `srl_add_external_steward_vote`
  - `srl_delete_steward_vote`
  - `srl_finalize_protest_ruling`
  - `srl_reopen_protest`

- [ ] **Step 1: Create `class-srl-protest-voting.php`**
Implement the voting class, data structures, atomic update wrappers, quorum checks, tie-breaker activation, and AJAX handlers with nonces and permission checks.

- [ ] **Step 2: Update `rest-api.php` callback**
When n8n reports AI verdict completion, call `SRL_Protest_Voting::evaluate_ai_verdict_vote()` to record AI vote according to configured mode.

- [ ] **Step 3: Require class in `srl-league-system.php`**
Add `require_once SRL_PLUGIN_PATH . 'includes/commissary/class-srl-protest-voting.php';` and register AJAX hooks.

- [ ] **Step 4: Verify via PHP Lint**
Run: `php -l wp-content/plugins/srl-league-system/includes/commissary/class-srl-protest-voting.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/commissary/class-srl-protest-voting.php wp-content/plugins/srl-league-system/includes/commissary/rest-api.php wp-content/plugins/srl-league-system/srl-league-system.php
git commit -m "feat: implement multi-admin and external steward voting engine"
```

---

### Task 4: Frontend Single Template `single-srl_protest.php` & Media Player

**Files:**
- Create: `wp-content/plugins/srl-league-system/templates/single-srl_protest.php`
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`
- Modify: `wp-content/plugins/srl-league-system/assets/js/public.js`
- Modify: `wp-content/plugins/srl-league-system/assets/css/public.css`

**Interfaces:**
- Template filter: maps `single-srl_protest.php` with theme override support.
- Frontend JS: handles AJAX vote submission, modal popups, slow-motion controls (0.25x, 0.5x, 1.0x), and live progress bar updates.

- [ ] **Step 1: Register template in `srl_include_template_function`**
In `srl-league-system.php`, add handler for `is_singular('srl_protest')`.

- [ ] **Step 2: Create `templates/single-srl_protest.php`**
Implement full template layout:
1. Visibility gate (`admins_only` check).
2. Header & driver duel faceoff cards.
3. Incident description.
4. HTML5 video player with slow-motion speed controls (0.25x, 0.5x, 1x) and iframe fallback.
5. Public sanitized official verdict banner.
6. Steward Deliberation Console (for admins): live voting bar, vote action buttons, external steward modal, votes table, AI analysis accordion, and final ruling / sanction form with reopen capability.

- [ ] **Step 3: Add interactive styles in `assets/css/public.css`**
Modern motorsport dark cards, neon accent badges, progress bars, and modal styles.

- [ ] **Step 4: Add interactive script in `assets/js/public.js`**
AJAX voting handlers, modal controls, copy AI sanction shortcut, and video speed switches.

- [ ] **Step 5: Verify via PHP Lint**
Run: `php -l wp-content/plugins/srl-league-system/templates/single-srl_protest.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**
```bash
git add wp-content/plugins/srl-league-system/templates/single-srl_protest.php wp-content/plugins/srl-league-system/srl-league-system.php wp-content/plugins/srl-league-system/assets/js/public.js wp-content/plugins/srl-league-system/assets/css/public.css
git commit -m "feat: add single-srl_protest frontend template with media player and voting console"
```

---

### Task 5: Theme Deployment & Update Pipeline (`srl-theme`)

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/updater.php`
- Modify: `wp-content/themes/srl-theme/style.css`
- Modify: `wp-content/plugins/srl-league-system/includes/admin-page.php`

**Interfaces:**
- Produces: `srl_init_theme_updater()`
- PUC checker watching `srl-theme.zip` on `rafa2701/srl-app`.

- [ ] **Step 1: Implement `srl_init_theme_updater()` in `includes/updater.php`**
Hook into `init` / `after_setup_theme` to check `srl-theme/style.css` using `PucFactory::buildUpdateChecker` matching asset pattern `/^srl-theme.*\.zip$/`.

- [ ] **Step 2: Update `wp-content/themes/srl-theme/style.css`**
Add standard theme headers (Theme Name: SRL Theme, Version: 1.1.0, Author: SRL Team) to enable proper PUC version comparison.

- [ ] **Step 3: Add Theme Update info/toggle in `includes/admin-page.php`**
Display theme update status alongside plugin update status in General Settings.

- [ ] **Step 4: Verify via PHP Lint**
Run: `php -l wp-content/plugins/srl-league-system/includes/updater.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/updater.php wp-content/themes/srl-theme/style.css wp-content/plugins/srl-league-system/includes/admin-page.php
git commit -m "feat: setup theme update checker and release deployment pipeline"
```

---

### Task 6: Verification, Version Bump, Documentation, TODO & Changelog

**Files:**
- Modify: `TODO.md`
- Modify: `CHANGELOG.md`
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php` (bump to 1.14.0)
- Modify: `wp-content/themes/srl-theme/style.css` (bump to 1.1.0)

- [ ] **Step 1: Bump version numbers for cache busting and release alignment**
Plugin: 1.14.0, Theme: 1.1.0.

- [ ] **Step 2: Run syntax validation on all touched PHP files**
Run: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`

- [ ] **Step 3: Update `TODO.md` and `CHANGELOG.md`**
Record the new sprint items under `[Unreleased]`.

- [ ] **Step 4: Commit**
```bash
git add TODO.md CHANGELOG.md wp-content/plugins/srl-league-system/srl-league-system.php wp-content/themes/srl-theme/style.css
git commit -m "chore: bump version to 1.14.0 and document changes in TODO and CHANGELOG"
```
