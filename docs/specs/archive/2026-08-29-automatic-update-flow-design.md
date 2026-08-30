# SRL League System - Automatic Update Flow Design

## Objective
Implement a robust, automated update flow for the SRL League System plugin, allowing it to receive updates directly from the GitHub repository, matching the native WordPress update experience.

## Architecture & Approach
We will utilize the **GitHub Releases + Plugin Update Checker (PUC) + GitHub Actions** approach.
The repository (`rafa2701/srl-app`) is public, so no authentication tokens are necessary for checking updates.

## Components & Implementation Details

### 1. GitHub Actions Pipeline
- **File Location:** `.github/workflows/release-plugin.yml`
- **Trigger:** Pushing semantic version tags (e.g., `v1.2.3`).
- **Action:**
  - Checks out the repository.
  - Runs `composer install --no-dev` to bundle all dependencies (like `phpspreadsheet`).
  - Packages the `wp-content/plugins/srl-league-system` directory into `srl-league-system.zip`.
  - Creates a new GitHub Release and attaches the `.zip` as a release asset.

### 2. Plugin Update Checker (PUC) Integration
- **Installation:** Require `yahnis-elsts/plugin-update-checker` via `composer.json`.
- **Initialization:**
  - Create `includes/updater.php` to initialize `PucFactory::buildUpdateChecker`.
  - Point the checker to the `https://github.com/rafa2701/srl-app/` repository.
  - Enable release assets filtering: `$update_checker->getVcsApi()->enableReleaseAssets('/^srl-league-system.*\.zip$/');`.
- **Inclusion:** Require `includes/updater.php` from the main `srl-league-system.php` file on `plugins_loaded`.

### 3. Auto-Update Toggle Settings
- **UI Element:** Add a new checkbox in the existing "Gestión SRL" settings page labeled "Habilitar actualizaciones automáticas en segundo plano" (Enable background auto-updates).
- **Storage:** Save this preference as an option in the WordPress database (e.g., `srl_force_auto_update`).
- **Logic:**
  - If the option is enabled, add a filter to `auto_update_plugin` that always returns `true` for the `srl-league-system` plugin.
  - If the option is disabled, do not add the filter, allowing WordPress core UI to handle auto-update preferences.

### 4. Post-Update Database Migrations
- The existing `srl_check_for_updates()` function running on `admin_init` handles database schema changes.
- Ensure the plugin version is correctly updated in `srl-league-system.php` to trigger the migration logic after an in-place file replacement.

## Scope & Constraints
- The workflow specifically packages the plugin, ignoring the theme (`wp-content/themes/srl-theme`).
- Relies on GitHub Actions availability and standard WordPress Cron for checking transients.

## Success Criteria
- Tagging a new release on GitHub automatically builds and attaches the zip file.
- The WordPress dashboard detects the update.
- The admin can 1-click update, or background updates apply automatically based on the plugin's setting toggle.
- Composer dependencies (`phpspreadsheet` and `puc`) are included in the downloaded zip.
