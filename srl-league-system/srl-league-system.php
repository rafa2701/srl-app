<?php
/**
 * Plugin Name:       SRL League System
 * Version:           0.7.0
 * ... (resto de la cabecera)
 */

if ( ! defined( 'WPINC' ) ) die;

define( 'SRL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SRL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRL_PLUGIN_VERSION', '1.2.0' );

register_activation_hook( __FILE__, 'srl_activate_plugin' );
function srl_activate_plugin() {
    require_once SRL_PLUGIN_PATH . 'includes/db-setup.php';
    srl_create_database_tables();
}

// --- Incluir todos los archivos necesarios ---
require_once SRL_PLUGIN_PATH . 'includes/post-types.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-enhancements.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-meta-boxes.php'; // <-- AÑADIDO
require_once SRL_PLUGIN_PATH . 'includes/data-importers/assetto-parser.php';
require_once SRL_PLUGIN_PATH . 'includes/data-importers/automobilista-parser.php';
require_once SRL_PLUGIN_PATH . 'includes/admin-page.php';
require_once SRL_PLUGIN_PATH . 'includes/ajax-handlers.php';
require_once SRL_PLUGIN_PATH . 'includes/shortcodes.php';

// --- Hooks ---
add_action( 'admin_menu', 'srl_admin_menu' );
function srl_admin_menu() {
    $steering_wheel_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNmZmZmZmYiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEwIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMyIvPjxsaW5lIHgxPSIxMiIgeTE9IjIyIiB4Mj0iMTIiIHkyPSIxNSIvPjxsaW5lIHgxPSI1LjQiIHkxPSI4LjQiIHgyPSI5LjUiIHkyPSIxMC41Ii8+PGxpbmUgeDE9IjE4LjYiIHkxPSI4LjQiIHgyPSIxNC41IiB5Mj0iMTAuNSIvPjwvc3ZnPg==';
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
    // Cargar CSS
    wp_enqueue_style( 'srl-public-css', SRL_PLUGIN_URL . 'assets/css/main.css', [], SRL_PLUGIN_VERSION );
    
    // Cargar JS solo si un shortcode nuestro está presente
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'srl_driver_profile' ) || has_shortcode( $post->post_content, 'srl_standings' ) || has_shortcode( $post->post_content, 'srl_driver_list' ) || has_shortcode( $post->post_content, 'srl_event_results' ) ) {
        
        // 1. Cargar la librería Tablesort desde un CDN
        wp_enqueue_script( 'srl-tablesort', 'https://cdn.jsdelivr.net/npm/tablesort@5.6.0/src/tablesort.min.js', [], null, true );

        // 2. Cargar nuestro script público, que ahora depende de Tablesort
        wp_enqueue_script( 'srl-public-js', SRL_PLUGIN_URL . 'assets/js/public.js', ['jquery', 'srl-tablesort'], SRL_PLUGIN_VERSION, true );
        
        wp_localize_script( 'srl-public-js', 'srl_ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'srl-public-nonce' ) ]);
    }
}
add_action( 'wp_enqueue_scripts', 'srl_public_enqueue_assets' );
/**
 * Carga las plantillas personalizadas para los CPTs desde la carpeta /templates del plugin.
 */
function srl_include_template_function( $template ) {
    if ( is_singular( 'srl_championship' ) ) {
        $new_template = SRL_PLUGIN_PATH . 'templates/single-srl_championship.php';
        if ( '' != $new_template ) {
            return $new_template;
        }
    } elseif ( is_singular( 'srl_event' ) ) {
        $new_template = SRL_PLUGIN_PATH . 'templates/single-srl_event.php';
        if ( '' != $new_template ) {
            return $new_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'srl_include_template_function', 1 );