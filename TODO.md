# SRL League System TODO

## Current Sprint
- (Add upcoming tasks here)

## Completed
- [x] Add OpenRouter Alternative Model Support to n8n Workflow
  - [x] Modify virtual-commissary-workflow.json
  - [x] Update docs/n8n/README.md instructions
- [x] Fix Admin Tabs Switching & JavaScript Syntax in Gestion SRL
  - [x] Task 1: Systematic debugging and root cause investigation of admin.js syntax error
  - [x] Task 2: Fix unclosed merge confirmation handler in `assets/js/admin.js`
  - [x] Task 3: Enhance tab switching logic with URL hash support and options.php referer preservation
  - [x] Task 4: Bump plugin version to 1.12.3 for browser cache busting
- [x] Official Sporting Regulations & Virtual Racing Etiquette Documentation
  - [x] Task 1: Create `docs/reglamento-deportivo-srl.md` (1:1 Markdown conversion of 24 Chapters & 45 Articles from PDF)
  - [x] Task 2: Create `docs/codigo-etiqueta-carreras.md` (Virtual Racing Etiquette, Vortex of Danger, Corner Rights, AI Evaluation Framework)
  - [x] Task 3: Create `docs/reglamento-completo-srl.md` (Merged Master Rulebook for AI Stewards and website publication)
  - [x] Task 4: Documentation, TODO, and CHANGELOG updates with spec archival
- [x] Commissary "Denuncias" Wording, Active Championship Filtering, Searchable Drivers & Cloudflare R2 Uploads
  - [x] Task 1: Terminology Update ("Protestas" -> "Denuncias") across CPT, Meta Boxes, Shortcodes
  - [x] Task 2: Active & Current Year Championship Filtering
  - [x] Task 3: Searchable Combobox Driver Selector in Form
  - [x] Task 4: Cloudflare R2 Uploader Class & Admin Settings
  - [x] Task 5: Evidence File Upload Handler & Frontend Dropzone
  - [x] Task 6: Cloudflare R2 Setup Guide & Documentation
  - [x] Task 7: End-to-End Verification, Version Bump to 1.11.0, Tag & Release
- [x] Virtual Commissary System (Comisariato Virtual AI)
  - [x] Task 1: CPT `srl_protest` and Data Model Registration (includes/commissary/post-type-protest.php)
  - [x] Task 2: REST API Endpoints for Rulebook & n8n Callback (includes/commissary/rest-api.php)
  - [x] Task 3: Settings & Rulebook Management UI in Admin (includes/admin-page.php, srl-league-system.php)
  - [x] Task 4: Admin Meta Boxes & AI Dispatch for `srl_protest` (includes/commissary/admin-meta-boxes.php, assets/js/admin.js)
  - [x] Task 5: Frontend Protest Submission Form Shortcode `[srl_protest_form]` (includes/commissary/shortcode-protest-form.php, assets/js/public.js)
  - [x] Task 6: Plugin Integration & Asset Enqueuing (srl-league-system.php)
  - [x] Task 7: n8n Workflow Blueprint & Homelab Documentation (docs/n8n/)
  - [x] Task 8: Verification, Documentation, Spec Archival (TODO, CHANGELOG, spec archive)
- [x] Set up Project Management Rules (AGENTS.md)
- [x] Set up TODO and CHANGELOG
- [x] Develop automatic update workflow (GitHub Actions + PUC)
  - [x] Task 1: GitHub Actions Release Pipeline (.github/workflows/release-plugin.yml)
  - [x] Task 2: Install Plugin Update Checker via Composer
  - [x] Task 3: Create Updater Initialization Module (includes/updater.php)
  - [x] Task 4: Hook Updater & Register Settings (srl-league-system.php)
  - [x] Task 5: Add Setting Toggle in Admin UI (includes/admin-page.php)

## Backlog
- (Add future tasks here)


