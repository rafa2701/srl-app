# Automatic Update Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a robust, automated update flow for the SRL League System plugin using GitHub Releases and PUC.

**Architecture:** A GitHub Action packages the plugin and its Composer dependencies into a ZIP file upon pushing a semantic version tag. The Plugin Update Checker (PUC) library periodically checks the GitHub repository for these release assets and integrates seamlessly with the native WordPress updater UI. A toggle in the plugin's settings allows forcing background auto-updates.

**Tech Stack:** PHP 8, WordPress Plugin API, Composer, GitHub Actions, Plugin Update Checker (PUC)

## Global Constraints

- Must work directly with the public `rafa2701/srl-app` GitHub repository.
- Composer must be run locally or via CI to package the plugin properly; end users do not run Composer on their servers.
- The auto-update toggle must override WordPress core behavior when enabled.
- GitHub Action must only package the plugin directory (`wp-content/plugins/srl-league-system`), not the entire monorepo.

---

### Task 1: GitHub Actions Release Pipeline

**Files:**
- Create: `.github/workflows/release-plugin.yml`

**Interfaces:**
- Consumes: N/A
- Produces: A `.zip` release artifact on GitHub Releases for every version tag.

- [ ] **Step 1: Create the GitHub Actions workflow file**

```yaml
name: Build & Release SRL League System Plugin

on:
  push:
    tags:
      - 'v*.*.*'

jobs:
  build-and-release:
    name: Package Plugin & Create Release
    runs-on: ubuntu-latest
    permissions:
      contents: write

    steps:
      - name: Checkout Repository
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, xml, ctype, iconv, intl, gd
          tools: composer:v2

      - name: Install Composer Dependencies
        working-directory: wp-content/plugins/srl-league-system
        run: composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

      - name: Prepare Plugin Distribution Directory
        run: |
          mkdir -p build/srl-league-system
          cp -r wp-content/plugins/srl-league-system/* build/srl-league-system/
          rm -rf build/srl-league-system/tests
          cd build
          zip -r ../srl-league-system.zip srl-league-system/
          cd ..

      - name: Create GitHub Release & Upload Asset
        uses: softprops/action-gh-release@v2
        with:
          files: srl-league-system.zip
          generate_release_notes: true
          draft: false
          prerelease: false
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

- [ ] **Step 2: Commit workflow file**

```bash
git add .github/workflows/release-plugin.yml
git commit -m "ci: Add GitHub Action for plugin release"
```

### Task 2: Install Plugin Update Checker (PUC)

**Files:**
- Modify: `wp-content/plugins/srl-league-system/composer.json` (via composer)

**Interfaces:**
- Consumes: N/A
- Produces: Composer dependency `yahnis-elsts/plugin-update-checker`

- [ ] **Step 1: Require PUC via Composer**

Run the following command in PowerShell:
```powershell
cd wp-content/plugins/srl-league-system
composer require yahnis-elsts/plugin-update-checker
```

- [ ] **Step 2: Commit changes**

Run the following command in PowerShell:
```powershell
git add wp-content/plugins/srl-league-system/composer.json wp-content/plugins/srl-league-system/composer.lock
git commit -m "chore: Add plugin-update-checker dependency"
```

### Task 3: Create Updater Initialization Module

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/updater.php`

**Interfaces:**
- Consumes: `yahnis-elsts/plugin-update-checker` autoload classes
- Produces: `srl_init_plugin_updater()` function

- [ ] **Step 1: Create `updater.php`**

```php
<?php
/**
 * SRL Plugin Updater Setup
 * Location: wp-content/plugins/srl-league-system/includes/updater.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function srl_init_plugin_updater() {
    $main_plugin_file = dirname( __DIR__ ) . '/srl-league-system.php';

    $update_checker = PucFactory::buildUpdateChecker(
        'https://github.com/rafa2701/srl-app/',
        $main_plugin_file,
        'srl-league-system'
    );

    $update_checker->getVcsApi()->enableReleaseAssets('/^srl-league-system.*\.zip$/');
}
```

- [ ] **Step 2: Commit `updater.php`**

```bash
git add wp-content/plugins/srl-league-system/includes/updater.php
git commit -m "feat: Initialize plugin update checker"
```

### Task 4: Hook Updater & Register Settings

**Files:**
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`

**Interfaces:**
- Consumes: `srl_init_plugin_updater()`
- Produces: Registered setting `srl_force_auto_update`, loaded updater on `plugins_loaded`

- [ ] **Step 1: Add setting to `srl_register_settings`**

In `srl-league-system.php`, find `function srl_register_settings() {` (around line 221).
Add `register_setting( 'srl_settings_group', 'srl_force_auto_update' );` inside the function.

```php
function srl_register_settings() {
    register_setting( 'srl_settings_group', 'srl_site_logo' );
    // ... other settings
    register_setting( 'srl_settings_group', 'srl_achievement_settings' );
    register_setting( 'srl_settings_group', 'srl_force_auto_update' );
}
```

- [ ] **Step 2: Require updater and handle auto-update logic**

At the bottom of `srl-league-system.php`, add:

```php
// Cargar inicializador del actualizador
if ( file_exists( SRL_PLUGIN_PATH . 'includes/updater.php' ) ) {
    require_once SRL_PLUGIN_PATH . 'includes/updater.php';
    add_action( 'plugins_loaded', 'srl_init_plugin_updater' );
}

// Lógica de actualización automática en segundo plano
if ( get_option( 'srl_force_auto_update' ) ) {
    add_filter( 'auto_update_plugin', 'srl_force_auto_update_filter', 10, 2 );
}

function srl_force_auto_update_filter( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'srl-league-system' ) {
        return true;
    }
    return $update;
}
```

- [ ] **Step 3: Commit changes**

```bash
git add wp-content/plugins/srl-league-system/srl-league-system.php
git commit -m "feat: Register update setting and hook updater logic"
```

### Task 5: Add Setting Toggle in Admin UI

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/admin-page.php`

**Interfaces:**
- Consumes: `srl_force_auto_update` option value
- Produces: UI Checkbox for administrators

- [ ] **Step 1: Retrieve setting value and add to settings table**

In `includes/admin-page.php`, find the settings initialization around line 74:
```php
$default_orderby = get_option( 'srl_championship_default_orderby', 'date' );
$default_order = get_option( 'srl_championship_default_order', 'DESC' );
$force_auto_update = get_option( 'srl_force_auto_update' ); // Add this line
```

Find the `</table>` for the settings group (around line 116), and add a new table row `<tr>` right above it:

```php
<tr valign="top">
    <th scope="row">Actualizaciones Automáticas</th>
    <td>
        <label>
            <input type="checkbox" name="srl_force_auto_update" value="1" <?php checked( 1, $force_auto_update ); ?> />
            Forzar actualizaciones automáticas en segundo plano (Plugin SRL League System)
        </label>
        <p class="description">Si está activado, el plugin se actualizará automáticamente sin intervención manual tan pronto como haya una nueva versión en GitHub.</p>
    </td>
</tr>
```

- [ ] **Step 2: Commit admin page changes**

```bash
git add wp-content/plugins/srl-league-system/includes/admin-page.php
git commit -m "feat: Add UI toggle for background auto-updates"
```
