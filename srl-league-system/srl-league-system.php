<?php
/**
 * Plugin Name:       SRL League System
 * Plugin URI:        https://simracinglatinoamerica.com/
 * Description:       Sistema de gestión de campeonatos, resultados y estadísticas para ligas de SimRacing.
 * Version:           1.0.3
 * Author:            Tu Nombre / Gemini AI
 * Author URI:        https://simracinglatinoamerica.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       srl-league-system
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) die;

// Definir constantes
define( 'SRL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SRL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRL_PLUGIN_VERSION', '1.0.3' );

// Hook de activación
register_activation_hook( __FILE__, 'srl_activate_plugin' );
function srl_activate_plugin() {
    require_once SRL_PLUGIN_PATH . 'includes/db-setup.php';
    srl_create_database_tables();
}

// --- Incluir todos los archivos necesarios ---
require_once SRL_PLUGIN_PATH . 'includes/post-types.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-enhancements.php'; // <-- AÑADIDO
require_once SRL_PLUGIN_PATH . 'includes/data-importers/assetto-parser.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-page.php';
require_once SRL_PLUGIN_PATH . 'includes/ajax-handlers.php';
require_once SRL_PLUGIN_PATH . 'includes/shortcodes.php';

// --- Hooks ---
add_action( 'admin_menu', 'srl_admin_menu' );
function srl_admin_menu() {
    add_menu_page( 'Gestión de Ligas SRL', 'Gestión SRL', 'manage_options', 'srl-league-management', 'srl_render_admin_page', 'dashicons-games', 20 );
}

add_action( 'admin_enqueue_scripts', 'srl_admin_enqueue_scripts' );
function srl_admin_enqueue_scripts( $hook ) {
    if ( 'toplevel_page_srl-league-management' != $hook ) return;
    wp_enqueue_script( 'srl-admin-js', SRL_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], SRL_PLUGIN_VERSION, true );
    wp_localize_script( 'srl-admin-js', 'srl_ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'srl-ajax-nonce' ) ]);
}

add_action( 'wp_enqueue_scripts', 'srl_public_enqueue_styles' );
function srl_public_enqueue_styles() {
    wp_enqueue_style( 'srl-public-css', SRL_PLUGIN_URL . 'assets/css/main.css', [], SRL_PLUGIN_VERSION );
}
