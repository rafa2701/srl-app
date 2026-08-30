# Commissary "Denuncias" Wording, Active Championship Filtering, Searchable Drivers & Cloudflare R2 Uploads Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement terminology update to "Denuncias", filter active current-year championships, add searchable combobox driver selection, and enable direct video evidence uploads to Cloudflare R2 with WordPress media fallback.

**Architecture:** 
- Terminology updated across CPTs, admin tables, meta boxes, and shortcode UI from "Protestas" to "Denuncias".
- Championship query restricted to `_srl_status != 'completed'` and current calendar year in `[srl_protest_form]`.
- Driver selection fields upgraded to dynamic, searchable comboboxes in `assets/js/public.js` and `assets/css/main.css`.
- Cloudflare R2 upload helper class `SRL_R2_Uploader` uses AWS S3 Signature Version 4 via `wp_remote_request` to stream video/image evidence directly to Cloudflare R2, falling back to `wp_handle_upload` if R2 is disabled/unconfigured.
- Settings added to "Gestión SRL" ➔ "Comisariato Virtual AI" for R2 credentials and bucket info.
- Detailed step-by-step R2 setup guide provided in `docs/cloudflare-r2-setup.md`.

**Tech Stack:** PHP 8.2, WordPress Plugin API, AWS S3 v4 Signature Authentication, Vanilla JavaScript, CSS3.

## Global Constraints

- Must follow the approved spec in `docs/specs/2026-08-30-commissary-denuncias-and-r2-enhancements-design.md`.
- No external heavy SDKs (use lightweight native S3 v4 REST request helper).
- Must maintain backward compatibility and graceful fallback to local WP uploads.
- All PHP files must pass linting with `C:\xampp\php\php.exe -n -l`.

---

### Task 1: Terminology Update (*"Protestas"* ➔ *"Denuncias"*)

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php`
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php`
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/admin-meta-boxes.php`
- Modify: `wp-content/plugins/srl-league-system/includes/shortcodes.php`

**Interfaces:**
- Consumes: CPT definitions and labels
- Produces: Updated labels and messages displaying "Denuncias" / "Denuncia".

- [ ] **Step 1: Update CPT labels in `post-type-protest.php`**
- [ ] **Step 2: Update form titles, button text, and generated post titles in `shortcode-protest-form.php`**
- [ ] **Step 3: Update labels in `admin-meta-boxes.php` and `includes/shortcodes.php`**
- [ ] **Step 4: Lint modified PHP files and commit**

---

### Task 2: Active & Current Year Championship Filtering

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php`

**Interfaces:**
- Consumes: `get_posts` with meta query and date query
- Produces: Filtered list of championships for the current year that are not completed.

- [ ] **Step 1: Update championship query in `shortcode-protest-form.php`**
- [ ] **Step 2: Lint and commit**

---

### Task 3: Searchable Combobox Driver Selector

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php`
- Modify: `wp-content/plugins/srl-league-system/assets/js/public.js`
- Modify: `wp-content/plugins/srl-league-system/assets/css/main.css`

**Interfaces:**
- Consumes: Driver dataset from database
- Produces: Interactive searchable combobox UI with instant filtering, selection tags, and hidden `driver_id` inputs.

- [ ] **Step 1: Update form markup with searchable combobox wrapper**
- [ ] **Step 2: Add autocomplete & filter JS handlers in `assets/js/public.js`**
- [ ] **Step 3: Add combobox styling in `assets/css/main.css`**
- [ ] **Step 4: Commit searchable driver selector**

---

### Task 4: Cloudflare R2 Uploader Class & Admin Settings

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/commissary/class-srl-r2-uploader.php`
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`
- Modify: `wp-content/plugins/srl-league-system/includes/admin-page.php`

**Interfaces:**
- Consumes: Cloudflare R2 S3-compatible REST API, WordPress HTTP API (`wp_remote_request`)
- Produces: `SRL_R2_Uploader::upload_file()`, R2 configuration fields in Admin UI.

- [ ] **Step 1: Create `class-srl-r2-uploader.php` with S3 v4 signature calculation and PUT request**
- [ ] **Step 2: Register R2 settings in `srl-league-system.php`**
- [ ] **Step 3: Add R2 settings section in `includes/admin-page.php`**
- [ ] **Step 4: Lint and commit**

---

### Task 5: Evidence File Upload Handler & Frontend Dropzone

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php`
- Modify: `wp-content/plugins/srl-league-system/assets/js/public.js`
- Modify: `wp-content/plugins/srl-league-system/assets/css/main.css`

**Interfaces:**
- Consumes: AJAX file upload `srl_upload_evidence_file`, `SRL_R2_Uploader`
- Produces: Drag-and-drop file uploader with upload progress, auto-appending public CDN/WP URLs into evidence field.

- [ ] **Step 1: Add AJAX endpoint `srl_upload_evidence_file` with R2 upload and WP fallback**
- [ ] **Step 2: Add dropzone markup to `[srl_protest_form]`**
- [ ] **Step 3: Add AJAX upload handler with progress bar in `assets/js/public.js`**
- [ ] **Step 4: Add uploader styles in `assets/css/main.css`**
- [ ] **Step 5: Lint and commit**

---

### Task 6: Cloudflare R2 Setup Guide & Documentation

**Files:**
- Create: `docs/cloudflare-r2-setup.md`

**Interfaces:**
- Consumes: Cloudflare R2 setup instructions
- Produces: Complete guide with bucket creation, API tokens, CORS configuration, and custom domains.

- [ ] **Step 1: Create `docs/cloudflare-r2-setup.md`**
- [ ] **Step 2: Commit documentation**

---

### Task 7: End-to-End Verification, Version Bump to 1.11.0, Tag & Release

**Files:**
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`
- Modify: `CHANGELOG.md`
- Modify: `TODO.md`
- Move: `docs/specs/2026-08-30-commissary-denuncias-and-r2-enhancements-design.md` -> `docs/specs/archive/2026-08-30-commissary-denuncias-and-r2-enhancements-design.md`

- [ ] **Step 1: Lint all plugin files**
- [ ] **Step 2: Update `TODO.md`, `CHANGELOG.md`, and bump version to `1.11.0`**
- [ ] **Step 3: Move spec to archive**
- [ ] **Step 4: Create git tag `v1.11.0` and push to trigger GitHub Actions release**
