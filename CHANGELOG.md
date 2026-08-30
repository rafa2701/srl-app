# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
