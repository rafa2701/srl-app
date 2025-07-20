<?php
/**
 * Plugin Name: SRL Core – No REST
 * Description: Sim-Racing League management using native WP objects.
 * Version:     1.0.0
 * Author:      SR Latinoamérica
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------- Activation ---------- */
register_activation_hook( __FILE__, 'srl_activate' );
function srl_activate() {
    srl_create_results_table();
    flush_rewrite_rules();
}

function srl_create_results_table() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srl_results (
        session_id BIGINT UNSIGNED NOT NULL,
        driver_id  BIGINT UNSIGNED NOT NULL,
        position   SMALLINT,
        fastest_lap TIME NULL,
        dnf        TINYINT(1) DEFAULT 0,
        laps       SMALLINT,
        PRIMARY KEY (session_id, driver_id),
        KEY driver_idx (driver_id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/* ---------- CPTs ---------- */
add_action( 'init', 'srl_register_cpts' );
function srl_register_cpts() {

    // Driver
    register_post_type( 'driver', [
        'labels'        => [ 'name' => 'Drivers', 'singular_name' => 'Driver' ],
        'public'        => true,
        'rewrite'       => [ 'slug' => 'drivers' ],
        'supports'      => [ 'title', 'thumbnail', 'custom-fields' ],
        'show_in_rest'  => false,
    ] );

    // Championship
    register_post_type( 'srl_championship', [
        'labels'        => [ 'name' => 'Championships', 'singular_name' => 'Championship' ],
        'public'        => true,
        'rewrite'       => [ 'slug' => 'championships' ],
        'supports'      => [ 'title', 'editor', 'custom-fields' ],
        'show_in_rest'  => false,
    ] );

    // Event
    register_post_type( 'srl_event', [
        'labels'        => [ 'name' => 'Events', 'singular_name' => 'Event' ],
        'public'        => true,
        'rewrite'       => [ 'slug' => 'events' ],
        'supports'      => [ 'title', 'editor', 'custom-fields' ],
        'show_in_rest'  => false,
        'hierarchical'  => true,   // parent = championship
    ] );

    // Session
    register_post_type( 'srl_session', [
        'labels'        => [ 'name' => 'Sessions', 'singular_name' => 'Session' ],
        'public'        => true,
        'rewrite'       => [ 'slug' => 'sessions' ],
        'supports'      => [ 'title', 'editor', 'custom-fields' ],
        'show_in_rest'  => false,
        'hierarchical'  => true,   // parent = event
    ] );
}

/* ---------- Admin Menu ---------- */
add_action( 'admin_menu', 'srl_admin_menu' );
function srl_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=srl_championship',
        'Import Results',
        'Import',
        'manage_options',
        'srl-import',
        'srl_render_import_page'
    );
}

function srl_render_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
    echo '<div class="wrap"><h1>Import Results</h1><p>Upload form will go here.</p></div>';
}