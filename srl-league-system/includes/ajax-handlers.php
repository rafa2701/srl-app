<?php
/**
 * Maneja las peticiones AJAX del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'wp_ajax_srl_upload_results_file', 'srl_handle_results_upload' );
add_action( 'wp_ajax_srl_get_events', 'srl_handle_get_events' );
add_action( 'wp_ajax_srl_recalculate_all_stats', 'srl_handle_recalculate_all_stats' );
add_action( 'wp_ajax_srl_get_achievement_details', 'srl_handle_get_achievement_details' );
add_action( 'wp_ajax_nopriv_srl_get_achievement_details', 'srl_handle_get_achievement_details' );

function srl_handle_results_upload() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! isset( $_POST['event_id'], $_POST['session_type'] ) || empty( $_FILES['results_file'] ) ) {
        wp_send_json_error( ['message' => 'Faltan datos. Por favor, completa todos los campos.'] );
    }
    $event_id = intval( $_POST['event_id'] );
    $session_type = sanitize_text_field( $_POST['session_type'] );
    $file = $_FILES['results_file'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( ['message' => 'Error al subir el archivo. Código: ' . $file['error']] );
    }
    global $wpdb;
    $wpdb->insert( $wpdb->prefix . 'srl_sessions', [ 'event_id' => $event_id, 'session_type' => $session_type, 'source_file' => sanitize_file_name( $file['name'] ) ] );
    $session_id = $wpdb->insert_id;
    if ( ! $session_id ) {
        wp_send_json_error( ['message' => 'Error al crear la sesión en la base de datos.'] );
    }
    $file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    $result = [];
    if ( $file_extension === 'json' ) {
        $json_content = file_get_contents( $file['tmp_name'] );
        if ( $json_content === false ) wp_send_json_error( ['message' => 'No se pudo leer el contenido del archivo JSON.'] );
        $result = srl_parse_assetto_corsa_results( $json_content, $session_id, $event_id );
    } elseif ( in_array( $file_extension, ['xls', 'xlsx'] ) ) {
        $result = srl_parse_automobilista_results( $file['tmp_name'], $session_id, $event_id );
    } else {
        wp_send_json_error( ['message' => 'Formato de archivo no soportado. Sube un .json o .xls.'] );
    }
    if ( $result['status'] === 'success' || $result['status'] === 'info' ) {
        wp_send_json_success( ['message' => $result['message']] );
    } else {
        wp_send_json_error( ['message' => $result['message']] );
    }
}

function srl_handle_get_events() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! isset( $_POST['championship_id'] ) ) wp_send_json_error( ['message' => 'No se proporcionó ID de campeonato.'] );
    $championship_id = intval( $_POST['championship_id'] );
    $args = [ 'post_type' => 'srl_event', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => [ [ 'key' => '_srl_parent_championship', 'value' => $championship_id, 'compare' => '=' ] ] ];
    $event_posts = get_posts( $args );
    $events = [];
    if ( ! empty( $event_posts ) ) {
        foreach ( $event_posts as $event_post ) {
            $events[] = [ 'id' => $event_post->ID, 'name' => $event_post->post_title ];
        }
    }
    wp_send_json_success( $events );
}

/**
 * Recalcula las estadísticas globales de TODOS los pilotos y los campeonatos ganados.
 */
function srl_handle_recalculate_all_stats() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    global $wpdb;
    $driver_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}srl_drivers" );
    if ( empty( $driver_ids ) ) wp_send_json_error( ['message' => 'No se encontraron pilotos para recalcular.'] );

    foreach ( $driver_ids as $driver_id ) {
        srl_update_driver_global_stats( $driver_id );
    }

    $wpdb->query( "UPDATE {$wpdb->prefix}srl_drivers SET championships_won_count = 0" );
    $completed_championships = get_posts([ 'post_type' => 'srl_championship', 'posts_per_page' => -1, 'meta_key' => '_srl_status', 'meta_value' => 'completed' ]);
    $champs_recalculated = 0;
    foreach ( $completed_championships as $champ ) {
        $standings = srl_calculate_championship_standings( $champ->ID );
        if ( ! empty( $standings ) ) {
            $winner_driver_id = array_key_first( $standings );
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}srl_drivers SET championships_won_count = championships_won_count + 1 WHERE id = %d", $winner_driver_id ) );
            $champs_recalculated++;
        }
    }
    wp_send_json_success( ['message' => 'Se han recalculado las estadísticas para ' . count( $driver_ids ) . ' pilotos y se han reasignado ' . $champs_recalculated . ' campeonatos.'] );
}

function srl_handle_get_achievement_details() {
    check_ajax_referer( 'srl-public-nonce', 'nonce' );
    if ( ! isset( $_POST['driver_id'], $_POST['stat_type'] ) ) wp_send_json_error( ['message' => 'Faltan datos.'] );
    global $wpdb;
    $driver_id = intval( $_POST['driver_id'] );
    $stat_type = sanitize_key( $_POST['stat_type'] );
    $where_clause = '';
    switch ( $stat_type ) {
        case 'victories': $where_clause = "r.position = 1"; break;
        case 'podiums': $where_clause = "r.position <= 3"; break;
        case 'poles': $where_clause = "r.has_pole = 1"; break;
        case 'fastest_laps': $where_clause = "r.has_fastest_lap = 1"; break;
        default: wp_send_json_error( ['message' => 'Tipo de estadística no válido.'] );
    }
    $query = $wpdb->prepare("
        SELECT event_post.ID as event_id, event_post.post_title as event_name, champ_post.post_title as championship_name
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        JOIN {$wpdb->prefix}posts event_post ON s.event_id = event_post.ID
        JOIN {$wpdb->prefix}postmeta pm ON event_post.ID = pm.post_id AND pm.meta_key = '_srl_parent_championship'
        JOIN {$wpdb->prefix}posts champ_post ON pm.meta_value = champ_post.ID
        WHERE r.driver_id = %d AND s.session_type = 'Race' AND {$where_clause}
        ORDER BY event_post.post_date DESC
    ", $driver_id);
    $results = $wpdb->get_results( $query );
    $achievements = [];
    foreach($results as $result) {
        $achievements[] = [ 'name' => esc_html( $result->event_name ) . ' - <span class="srl-modal-champ-name">' . esc_html( $result->championship_name ) . '</span>', 'url'  => get_permalink($result->event_id) ];
    }
    wp_send_json_success( $achievements );
}
