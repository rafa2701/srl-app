<?php
/**
 * Plugin Name:       SRL League System
 * Plugin URI:        https://simracinglatinoamerica.com/
 * Description:       Sistema de gestión de campeonatos, resultados y estadísticas para ligas de SimRacing.
 * Version:           1.8.0
 * Author:            Rafael Leon / Gemini AI
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
define( 'SRL_PLUGIN_VERSION', '1.8.0' );

// Cargar la librería PhpSpreadsheet
if ( file_exists( SRL_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
    require_once SRL_PLUGIN_PATH . 'vendor/autoload.php';
}


// Hook de activación
register_activation_hook( __FILE__, 'srl_activate_plugin' );
function srl_activate_plugin() {
    require_once SRL_PLUGIN_PATH . 'includes/db-setup.php';
    srl_create_database_tables();
}

// --- Incluir todos los archivos necesarios ---
require_once SRL_PLUGIN_PATH . 'includes/core-functions.php';
require_once SRL_PLUGIN_PATH . 'includes/post-types.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-enhancements.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-meta-boxes.php';
require_once SRL_PLUGIN_PATH . 'includes/data-importers/assetto-parser.php';
require_once SRL_PLUGIN_PATH . 'includes/data-importers/automobilista-parser.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-page.php';
require_once SRL_PLUGIN_PATH . 'includes/ajax-handlers.php';
require_once SRL_PLUGIN_PATH . 'includes/shortcodes.php';

// --- Hooks ---
add_action( 'admin_menu', 'srl_admin_menu' );
function srl_admin_menu() {
    $steering_wheel_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZHRoPSIyMCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNmZmZmZmYiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEwIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMyIvPjxsaW5lIHgxPSIxMiIgeTE9IjIyIiB4Mj0iMTIiIHkyPSIxNSIvPjxsaW5lIHgxPSI1LjQiIHkxPSI4LjQiIHgyPSI5LjUiIHkyPSIxMC41Ii8+PGxpbmUgeDE9IjE4LjYiIHkxPSI4LjQiIHgyPSIxNC41IiB5Mj0iMTAuNSIvPjwvc3ZnPg==';
    add_menu_page( 'Gestión de Ligas SRL', 'Gestión SRL', 'manage_options', 'srl-league-management', 'srl_render_admin_page', $steering_wheel_svg, 20 );
}

add_action( 'admin_enqueue_scripts', 'srl_admin_enqueue_scripts' );
function srl_admin_enqueue_scripts( $hook ) {
    $screen = get_current_screen();
    if ( 'toplevel_page_srl-league-management' != $hook && 'srl_championship' != $screen->post_type && 'srl_event' != $screen->post_type ) return;
    wp_enqueue_script( 'srl-admin-js', SRL_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], SRL_PLUGIN_VERSION, true );
    wp_localize_script( 'srl-admin-js', 'srl_ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'srl-ajax-nonce' ) ]);
}

/**
 * Encola los scripts y estilos para el frontend.
 */
function srl_public_enqueue_assets() {
    wp_enqueue_style( 'srl-public-css', SRL_PLUGIN_URL . 'assets/css/main.css', [], SRL_PLUGIN_VERSION );
    
    global $post;
    if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'srl_driver_profile' ) || has_shortcode( $post->post_content, 'srl_standings' ) || has_shortcode( $post->post_content, 'srl_driver_list' ) || has_shortcode( $post->post_content, 'srl_event_results' ) ) ) {
        
        wp_enqueue_script( 'srl-tablesort', 'https://cdn.jsdelivr.net/npm/tablesort@5.6.0/src/tablesort.min.js', [], null, true );
        wp_enqueue_script( 'srl-public-js', SRL_PLUGIN_URL . 'assets/js/public.js', ['jquery', 'srl-tablesort'], SRL_PLUGIN_VERSION, true );
        
        wp_localize_script( 'srl-public-js', 'srl_ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'srl-public-nonce' ) ]);
    }
}
add_action( 'wp_enqueue_scripts', 'srl_public_enqueue_assets' );

/**
 * Carga las plantillas personalizadas para los CPTs.
 */
function srl_include_template_function( $template ) {
    if ( is_singular( 'srl_championship' ) ) {
        $new_template = SRL_PLUGIN_PATH . 'templates/single-srl_championship.php';
        if ( '' != $new_template ) return $new_template;
    } elseif ( is_singular( 'srl_event' ) ) {
        $new_template = SRL_PLUGIN_PATH . 'templates/single-srl_event.php';
        if ( '' != $new_template ) return $new_template;
    }
    return $template;
}
add_filter( 'template_include', 'srl_include_template_function', 1 );

/**
 * NUEVO: Se activa antes de que un post sea eliminado.
 * Limpia los resultados, sesiones y recalcula stats si el post es un Evento.
 */
function srl_cleanup_on_event_delete( $post_id ) {
    if ( get_post_type( $post_id ) !== 'srl_event' ) {
        return;
    }

    global $wpdb;
    $event_id = $post_id;

    // 1. Encontrar todas las sesiones para este evento
    $session_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d", $event_id ) );

    if ( ! empty( $session_ids ) ) {
        $session_ids_placeholder = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );

        // 2. Encontrar todos los pilotos afectados ANTES de borrar
        $affected_drivers = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT driver_id FROM {$wpdb->prefix}srl_results WHERE session_id IN ($session_ids_placeholder)", $session_ids ) );

        // 3. Borrar los resultados y las sesiones
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}srl_results WHERE session_id IN ($session_ids_placeholder)", $session_ids ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}srl_sessions WHERE id IN ($session_ids_placeholder)", $session_ids ) );

        // 4. Recalcular las estadísticas de los pilotos afectados
        foreach ( $affected_drivers as $driver_id ) {
            srl_update_driver_global_stats( $driver_id );
        }
    }
}
add_action( 'before_delete_post', 'srl_cleanup_on_event_delete' );
