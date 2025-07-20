<?php
/**
 * Plugin Name: SRL Core – No REST
 * Description: Sim-Racing League management using native WP objects.
 * Version:     1.0.0
 * Author:      SR Latinoamérica
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------- Auto-load parser ---------- */
require_once __DIR__ . '/includes/class-parser.php';

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

/* ---------- Upload & Import ---------- */
function srl_render_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden' );
    }

    // Handle upload on POST
    if ( ! empty( $_POST['srl_import_nonce'] ) && wp_verify_nonce( $_POST['srl_import_nonce'], 'srl_import_action' ) ) {
        srl_handle_import_file();
    }

    ?>
    <div class="wrap">
        <h1>Import Results</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'srl_import_action', 'srl_import_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="srl_file">File (CSV / JSON)</label></th>
                    <td>
                        <input type="file" name="srl_file" id="srl_file" accept=".csv,.json" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="srl_session_id">Session</label></th>
                    <td>
                        <?php
                        wp_dropdown_posts( [
                            'post_type'        => 'srl_session',
                            'name'             => 'srl_session_id',
                            'show_option_none' => '— Select Session —',
                        ] );
                        ?>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Upload & Import' ); ?>
        </form>
    </div>
    <?php
}

function srl_handle_import_file() {
    if ( empty( $_FILES['srl_file']['tmp_name'] ) || empty( $_POST['srl_session_id'] ) ) {
        echo '<div class="notice notice-error"><p>Missing file or session.</p></div>';
        return;
    }

    $file_path = $_FILES['srl_file']['tmp_name'];
    $session_id = absint( $_POST['srl_session_id'] );

    $result = SRL_Parser::import_json( $file_path, $session_id );

    $class = $result['success'] ? 'notice-success' : 'notice-error';
    echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $result['message'] ) . '</p></div>';
}

/* ---------- Helper used by parser (keep it global) ---------- */
function srl_ensure_driver_post( $name, $steam_id = '' ) {
    $existing = get_page_by_title( $steam_id, OBJECT, 'driver' ); // title = Steam GUID
    if ( $existing ) {
        return $existing->ID;
    }

    return wp_insert_post( [
        'post_type'   => 'driver',
        'post_title'  => $steam_id,
        'post_name'   => sanitize_title( $name . '-' . substr( $steam_id, -6 ) ),
        'post_status' => 'publish',
        'meta_input'  => [
            'srl_driver_name' => $name,
            'srl_steam_id'    => $steam_id,
        ],
    ] );
}