# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [1.14.1] - 2026-09-03
### Fixed
- Fixed fatal error `Cannot redeclare srl_init_theme_updater()` by adding `function_exists` guards in both `updater.php` and `srl-theme/functions.php`.
- Fixed rewrite rule caching for `/reclamo/` permalinks and removed `is_singular('srl_protest')` from `srl_restrict_commissary_access` to allow individual protest pages to render their dedicated template.
- Fixed AI verdict data extraction in frontend protest template (`single-srl_protest.php`) supporting direct unnested consensus verdicts, proportional fault bar rendering, and conditional persona breakdowns.
- Bumped `srl-theme` version to `1.1.1` and `srl-league-system` to `1.14.1`.

## [1.14.0] - 2026-09-02
### Added
- **Dedicated Frontend Protest Single Template (`single-srl_protest.php`)**:
  - Clean frontend single page view for `srl_protest` CPT (`reclamo` permalink rewrite slug) featuring SRL dark motorsport aesthetics.
  - Automatic title and slug formatting: `[Evento] - [Apellido Demandante] vs [Apellido Acusado] #[N]` (e.g. `GP Monza - Pérez vs González #1` -> `gp-monza-perez-gonzalez-1`).
  - Embedded HTML5 video player with slow-motion speed controls (0.25x, 0.5x, 1.0x) for precise steward incident analysis + responsive embeds for YouTube and Twitch clips.
  - Role-based visibility and public sanitized view: Guests and drivers see incident facts, evidence player, and official public resolution/sanctions, while internal deliberations and votes remain hidden.
- **Concurrent Multi-Admin Collaborative Voting Engine (`SRL_Protest_Voting`)**:
  - Atomic post meta array storage (`_srl_protest_votes`) eliminating WordPress classic editor `_edit_lock` conflicts and allowing multiple stewards to vote simultaneously.
  - One-click voting ("🟢 Procede" vs "🔴 No Procede") with optional justification notes.
  - Quorum counter and live simple majority progress bar.
  - Delegated external steward voting modal enabling admins to record votes from guest stewards.
- **AI Virtual Commissary User Provisioning & Configurable Voting**:
  - Automatic provisioning of user `comisario-ai` with Display Name **`Comisario Virtual AI`**.
  - Configurable AI participation modes in admin settings: *Always Active* (casts 1 vote automatically once analysis completes), *Tie-Breaker Only* (activates vote only to break a 50/50 tie when quorum is met), or *Disabled* (consultative only).
  - Admin settings for Frontend Visibility (`public_sanitized` / `admins_only`) and Minimum Quorum (default: 3 votes).
- **Frontend Ruling and Case Management**:
  - Steward resolution console allowing admins to apply official status, adopt AI recommended penalties with one click, and submit final sanction text.
  - Case reopening mechanism (`🔓 Reabrir Reclamo`) to return resolved protests to review.
- **Theme Update & Deployment Pipeline**:
  - Configured Plugin Update Checker (PUC) for `srl-theme` matching `srl-theme.zip` from GitHub Releases in `includes/updater.php`.
  - Added theme auto-update toggle in General Settings and bumped `srl-theme` to version `1.1.0`.

## [1.13.10] - 2026-09-02
### Fixed
- Fixed corrupted Spanish accents and special characters (e.g. `u00f3` instead of `ó`, `u00e1` instead of `á`) caused by WordPress stripping slashes on `update_post_meta`.
- Added `srl_clean_verdict_utf8` helper to automatically restore and clean stripped unicode characters for both existing and newly imported verdicts.
- Switched verdict serialization to `wp_slash( wp_json_encode( ..., JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )` to preserve native UTF-8 strings in MySQL.

## [1.13.9] - 2026-09-02
### Changed
- Release build bump to trigger GitHub Actions release packaging and update notification for live probe logs and resilient verdict sync.

## [1.13.8] - 2026-09-02
### Added
- Real-time probe diagnostic logging system (`srl_commissary_probe_logs`) tracking every sync attempt with HTTP status codes, URL tested, and response messages.
- Live probe logs table in the WordPress admin under Settings > Virtual Commissary.
- Manual sync triggers: "Comprobar Veredicto Ahora" button on protest edit screens and "Forzar Sondeo Global Ahora" in settings to bypass the 5-minute cron delay on demand.
### Fixed
- Fixed critical blocker in `r2-sync.php` where probes aborted early if S3 API direct upload was not fully configured, preventing public verdict URL checks.
- Fixed verdict rendering in `admin-meta-boxes.php` to seamlessly handle verdicts without a `chief_steward` wrapper key.

## [1.13.7] - 2026-09-02
### Added
- Added an option in Virtual Commissary settings to toggle visibility between "Public" and "Admin Only" (`admin_only` by default).
- Implemented redirection logic to prevent guest access to the commissary pages and hid the homepage card for non-admin users when visibility is restricted.

## [1.13.6] - 2026-09-02
### Fixed
- Fixed bug where `r2-sync.php` failed to extract verdict JSON if it was generated by n8n inside an array with a markdown code block `[{"text": "```json..."}]`. It now properly unpacks the JSON array and extracts the markdown payload.

## [1.13.5] - 2026-09-02
### Added
- Added an option in Virtual Commissary settings to fetch verdicts from a public URL (e.g. custom domain mapped to an R2 bucket) instead of relying solely on the S3 API for retrieval.

## [1.13.4] - 2026-09-02
### Fixed
- Completely bypassed WP-Cron limitations and 403 Forbidden loopback requests on InfinityFree by replacing `wp_schedule_event` with a lightweight, transient-based `init` hook cron in `r2-sync.php`.

## [1.13.3] - 2026-09-01
### Fixed
- Fixed R2 sync failing to find verdicts uploaded to the root of the Cloudflare R2 bucket instead of the `srl-verdicts` folder by making `r2-sync.php` check both locations.

## [1.13.2] - 2026-09-01
### Changed
- Replaced FTP synchronization with Cloudflare R2 object polling (`r2-sync.php`) to bypass InfinityFree FTP server blocks on automated connections.
- Refactored `SRL_R2_Uploader` to include S3 SigV4 `get_object` and `delete_object` methods.

## [1.13.1] - 2026-09-01
### Added
- Implemented native WP-Cron FTP synchronization (`ftp-sync.php`) to bypass web bot protection challenges (like `aes.js`) when receiving n8n verdict callbacks.
### Fixed
- Fixed race condition in Virtual Commissary n8n workflow (`docs/n8n/virtual-commissary-workflow.json`) by wiring the 3 Persona analysis nodes sequentially instead of in parallel to prevent execution errors on the Chief Steward consensus node.
- Prevented AI Persona hallucination when video links are inaccessible by adding an `insufficient_evidence` property to the JSON schema and updating persona system prompts.

## [1.13.0] - 2026-08-31
### Added
- **Direct Presigned Cloudflare R2 Uploads**: Implemented S3 SigV4 presigned PUT URL generation (`SRL_R2_Uploader::get_presigned_put_url()`) and AJAX endpoint `srl_get_r2_upload_url`.
- Client-side direct streaming to Cloudflare R2 global CDN with real-time progress bar in `[srl_protest_form]`, completely bypassing WordPress PHP `post_max_size` and hosting server limitations.
- Updated Cloudflare R2 setup documentation with `srlatinoamerica.yzz.me` domain in CORS policy.
### Fixed
- Dynamic server upload limit fallback (`wp_max_upload_size()`) and early detection of payload truncation for local uploads.
- Bumped plugin version to `1.13.0`.

## [1.12.5] - 2026-08-31
### Fixed
- Fixed "Sesión de seguridad expirada" nonce validation failure during evidence upload and protest submissions by implementing `srl_verify_public_nonce()` to support logged-in user sessions, guest nonces, and cached page nonces.
- Updated `assets/js/public.js` to read nonce dynamically from the form DOM element `#protest_nonce` with `srl_ajax_object.nonce` fallback.
- Bumped plugin version to `1.12.5` for browser cache busting.

## [1.12.4] - 2026-08-31
### Added
- OpenRouter Alternative Model Support in n8n workflow (`docs/n8n/virtual-commissary-workflow.json`) to easily use models like Minimax, Qwen, etc.
- OpenRouter configuration instructions in `docs/n8n/README.md`.
### Fixed
- Fixed browser file explorer dialog failing to open when clicking the dropzone upload area in `[srl_protest_form]` due to an infinite click event bubbling recursion (`RangeError: Maximum call stack size exceeded` in `public.js:181`).
- Fixed 400 Bad Request error on AJAX file uploads by adding explicit action query parameters, client-side 100MB file size & extension filtering, and temporary MIME type filter support for MKV, WebM, MOV, and MP4 videos in WordPress.
- Bumped plugin version to `1.12.4` for client-side asset cache busting.

## [1.12.3] - 2026-08-30
### Added
- Official SRL Sporting Regulations in Markdown (`docs/reglamento-deportivo-srl.md`) converted 1:1 from official PDF (24 Chapters, 45 Articles, and complete R/T/A/E/P/C/S Sanctions Tables).
- Virtual Racing Etiquette & AI Stewarding Guidelines (`docs/codigo-etiqueta-carreras.md`) defining Vortex of Danger, Corner Rights / Overlap criteria, Moving under Braking, Safe Rejoins, and AI incident evaluation matrices.
- Unified Master Rulebook (`docs/reglamento-completo-srl.md`) combining sporting regulations with technical etiquette rules for website publication and AI Commissary REST API ingestion (`GET /wp-json/srl/v1/rulebook`).
### Fixed
- Fixed tab navigation failure in "Gestión SRL" (`srl-league-management`) caused by a missing closing bracket in `assets/js/admin.js` that broke script execution.
- Added URL hash synchronization and auto-activation on load so tabs retain their active state across page reloads and options saving.

## [1.12.0] - 2026-08-30
### Added
- Smart Hybrid Event / Gran Premio selector allowing drivers to enter custom GP names if not yet created or uploaded by admins.
- Automatic provisioning and linking of `srl_event` posts when custom GP names are submitted.
- Configurable "Modo de Selección de Gran Premio / Evento" setting in "Gestión SRL" ➔ "Comisariato Virtual AI" (Hybrid, Free Text, Dropdown Only).
### Fixed
- Fixed 403 Forbidden AJAX error in `srl_get_events` for non-logged-in guest users by adding `wp_ajax_nopriv_srl_get_events` and verifying both public and admin nonces.

## [1.11.1] - 2026-08-30
### Changed
- Standardized commissary terminology to "Reclamos" / "Reclamo" across all CPT labels, admin menus, shortcodes, and UI buttons.

## [1.11.0] - 2026-08-30
### Added
- Dynamic active championship filtering in `[srl_protest_form]`, prioritizing current-year active championships
- Instant searchable driver comboboxes with client-side typing filter and selection badges for Demandante and Acusado fields
- Direct video/image evidence drag-and-drop uploader with real-time AJAX progress bar in `[srl_protest_form]`
- Cloudflare R2 direct storage integration via lightweight S3 SigV4 `SRL_R2_Uploader` class with graceful WordPress media fallback
- Cloudflare R2 settings in "Gestión SRL" ➔ "Comisariato Virtual AI"
- Comprehensive Cloudflare R2 setup guide (`docs/cloudflare-r2-setup.md`)

## [1.10.0] - 2026-08-30
### Added
- Virtual Commissary System (Comisariato Virtual AI) for sim racing incident protests
- Custom Post Type `srl_protest` with custom admin columns, status badges, and filtering
- REST API endpoint `GET /wp-json/srl/v1/rulebook` with API key authentication for n8n rulebook retrieval
- REST API callback endpoint `POST /wp-json/srl/v1/protest-update` for recording AI verdicts into WordPress
- "Comisariato Virtual AI" settings tab in "Gestión SRL" for Markdown Rulebook, n8n Webhook URL, and API Secret Key
- Admin Meta Boxes for `srl_protest` displaying interactive Chief Steward consensus (blame % bar & penalties) and 3 Persona cards (Strict, Lax, Balanced)
- "Enviar a Comisariato Virtual (n8n)" AJAX dispatch button with background webhook trigger
- Frontend shortcode `[srl_protest_form]` with dynamic championship -> event cascading dropdown, driver selector, and multiple video evidence URL inputs
- Automatic provisioning of `/comisariato/` page on plugin activation and admin init
- Interactive Comisariato card in homepage main menu shortcode (`[srl_main_menu]`)
- Production-ready n8n workflow blueprint (`docs/n8n/virtual-commissary-workflow.json`) integrating Google Gemini 1.5 Pro multimodal AI
- Homelab deployment guide (`docs/n8n/README.md`) for n8n + Gemini AI integration
- Project management setup with `AGENTS.md`, `.agents/rules/workflow.md`, `TODO.md`, and `CHANGELOG.md`
- Spec-driven development structure with archiving support
- GitHub Actions workflow (`release-plugin.yml`) for automated packaging and releases of both plugin and theme
- Integrated `yahnis-elsts/plugin-update-checker` (PUC) for both plugin and theme GitHub Release asset updates
- Auto-update setting in "Gestión SRL" admin page allowing forced background updates for plugin
