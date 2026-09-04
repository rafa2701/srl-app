# SRL League System TODO

## Current Sprint
- [ ] Implement Commissary Visibility Controls (admin only/public)

## Completed
- [x] Expansión de Hitos Históricos y Sección de Estadísticas Curiosas
  - [x] Task 1: Centralized Achievement Definitions & Keys in `SRL_Achievement_Manager`
  - [x] Task 2: Calculate Timing Records & Championship Records
  - [x] Task 3: Calculate Streaks, DNF Streaks, Dry Streaks & Efficiency
  - [x] Task 4: Update Leaderboard Queries in `SRL_Achievement_Manager`
  - [x] Task 5: Admin Panel: Global Curiosity Toggle & Grouped Settings
  - [x] Task 6: Frontend Template & Formatting in Shortcode (`/hitos/`)
  - [x] Task 7: Verification, Documentation & Spec Archiving
- [x] Incident Protest Frontend Template & Multi-Admin Collaborative Voting System
  - [x] Task 1: CPT `srl_protest` Public Queryable Routing & Automatic Naming Scheme
  - [x] Task 2: AI Steward User Provisioning & Voting/Quorum Administration Settings
  - [x] Task 3: Multi-Admin & External Steward Voting Engine with Quorum & Tie-Breaker Logic
  - [x] Task 4: Frontend Single Template `single-srl_protest.php` & Media Player
  - [x] Task 5: Theme Deployment & Update Pipeline (`srl-theme`)
  - [x] Task 6: Verification, Version Bump, Documentation, TODO & Changelog Updates
- [x] Replace Virtual Commissary FTP Sync with Cloudflare R2 Sync
  - [x] Task 1: Refactor `SRL_R2_Uploader` to include S3 SigV4 `get_object` and `delete_object`
  - [x] Task 2: Create `r2-sync.php` background task to poll Cloudflare R2
  - [x] Task 3: Replace n8n FTP node with AWS S3 node in `virtual-commissary-workflow.json`
- [x] Cloudflare R2 Direct Presigned Upload & Upload Limits Handling
  - [x] Task 1: Add `SRL_R2_Uploader::get_presigned_put_url()` for direct S3 SigV4 client uploads bypassing WordPress PHP limits
  - [x] Task 2: Add `srl_get_r2_upload_url` AJAX endpoint in `shortcode-protest-form.php`
  - [x] Task 3: Implement client-side direct `PUT` streaming to Cloudflare R2 with progress bar in `assets/js/public.js`
  - [x] Task 4: Dynamic server upload limit fallback & early `post_max_size` overflow detection
  - [x] Task 5: Update CORS documentation for `https://srlatinoamerica.yzz.me` in `docs/cloudflare-r2-setup.md`
  - [x] Task 6: Bump plugin version to 1.13.0 for browser cache busting
- [x] Native WP-Cron FTP Sync for n8n Webhook Bypassing Bot Protection
  - [x] Task 1: Create `ftp-sync.php` background task
  - [x] Task 2: Replace n8n HTTP Request node with FTP node
- [x] Fix Virtual Commissary n8n Workflow Execution Race Condition
  - [x] Task 1: Identify root cause of `Node hasn't been executed` error in `Consensus: Chief Steward` node
  - [x] Task 2: Update `docs/n8n/virtual-commissary-workflow.json` to execute `Analyze` nodes sequentially
- [x] Fix Evidence File Upload & Dropzone Click Trigger in Protest Form
  - [x] Task 1: Systematic debugging of `Maximum call stack size exceeded` and AJAX 400 Bad Request
  - [x] Task 2: Fix dropzone click event bubbling loop on file input in `assets/js/public.js`
  - [x] Task 3: Add client-side size & extension validation and informative HTTP status error handling in `assets/js/public.js`
  - [x] Task 4: Move hidden input outside dropzone container, add temporary allowed MIME types filter, and graceful JSON error reporting in `shortcode-protest-form.php`
  - [x] Task 5: Add robust `srl_verify_public_nonce` supporting guest/cached/admin nonces across public AJAX handlers
  - [x] Task 6: Add dynamic server max upload limit detection (`wp_max_upload_size()`) and early `post_max_size` overflow check
  - [x] Task 7: Bump plugin version to 1.12.6 for browser cache busting
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


