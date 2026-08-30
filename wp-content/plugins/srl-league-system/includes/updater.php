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
}
