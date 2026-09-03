<?php
/**
 * SRL Plugin Updater Setup
 * Location: wp-content/plugins/srl-league-system/includes/updater.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function srl_init_plugin_updater() {
    // Guard against missing library if autoload hasn't loaded PUC
    if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
        return;
    }

    $main_plugin_file = dirname( __DIR__ ) . '/srl-league-system.php';

    $update_checker = PucFactory::buildUpdateChecker(
        'https://github.com/rafa2701/srl-app/',
        $main_plugin_file,
        'srl-league-system'
    );

    // Tell PUC to look for release assets matching our zip file pattern
    $update_checker->getVcsApi()->enableReleaseAssets( '/^srl-league-system.*\.zip$/' );

    // Enable background updates if option is checked
    if ( get_option( 'srl_force_auto_update', 0 ) ) {
        add_filter( 'auto_update_plugin', function ( $update, $item ) use ( $main_plugin_file ) {
            if ( isset( $item->plugin ) && $item->plugin === plugin_basename( $main_plugin_file ) ) {
                return true;
            }
            return $update;
        }, 10, 2 );
    }

    // Initialize Theme Updater
    srl_init_theme_updater();
}

if ( ! function_exists( 'srl_init_theme_updater' ) ) {
    /**
     * Initialize theme updater for srl-theme from GitHub releases
     */
    function srl_init_theme_updater() {
        if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
            return;
        }

        $theme_dir = get_theme_root() . '/srl-theme';
        $theme_style = $theme_dir . '/style.css';

        if ( ! file_exists( $theme_style ) ) {
            return;
        }

        $theme_checker = PucFactory::buildUpdateChecker(
            'https://github.com/rafa2701/srl-app/',
            $theme_style,
            'srl-theme'
        );

        // Look for srl-theme.zip in GitHub release assets
        $theme_checker->getVcsApi()->enableReleaseAssets( '/^srl-theme.*\.zip$/' );

        // Enable background auto-update if option is checked
        if ( get_option( 'srl_theme_auto_update', 1 ) ) {
            add_filter( 'auto_update_theme', function ( $update, $item ) {
                if ( isset( $item->theme ) && $item->theme === 'srl-theme' ) {
                    return true;
                }
                return $update;
            }, 10, 2 );
        }
    }
}
