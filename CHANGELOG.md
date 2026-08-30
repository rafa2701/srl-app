# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
