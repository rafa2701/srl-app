# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
