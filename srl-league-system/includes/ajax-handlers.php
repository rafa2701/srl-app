<?php
/**
 * Maneja las peticiones AJAX del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

// CORRECCIÓN: Incluir los archivos con las funciones necesarias al principio del archivo.
// Esto asegura que las funciones estén disponibles durante las llamadas AJAX.
// require_once SRL_PLUGIN_PATH . 'includes/core-functions.php';
require_once SRL_PLUGIN_PATH . 'includes/data-importers/assetto-parser.php';


add_action( 'wp_ajax_srl_upload_results_file', 'srl_handle_results_upload' );
add_action( 'wp_ajax_srl_get_events', 'srl_handle_get_events' );
add_action( 'wp_ajax_srl_recalculate_all_stats', 'srl_handle_recalculate_all_stats' );
add_action( 'wp_ajax_srl_get_achievement_details', 'srl_handle_get_achievement_details' );
add_action( 'wp_ajax_nopriv_srl_get_achievement_details', 'srl_handle_get_achievement_details' );
add_action( 'wp_ajax_srl_delete_event_results', 'srl_handle_delete_event_results' );
add_action( 'wp_ajax_srl_bulk_upload_results', 'srl_handle_bulk_upload' );
add_action( 'wp_ajax_srl_import_history_file', 'srl_handle_history_import' );
add_action( 'wp_ajax_srl_cleanup_orphan_results', 'srl_handle_cleanup_orphans' );

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
    srl_write_to_log('--- INICIO DE RECÁLCULO DE ESTADÍSTICAS SRL ---');

    global $wpdb;
    $driver_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}srl_drivers" );
    if ( empty( $driver_ids ) ) {
        srl_write_to_log('SRL Recalculate: No se encontraron pilotos.');
        wp_send_json_error( ['message' => 'No se encontraron pilotos para recalcular.'] );
    }
    srl_write_to_log('SRL Recalculate: Se encontraron ' . count($driver_ids) . ' pilotos para procesar.');

    foreach ( $driver_ids as $driver_id ) {
        srl_update_driver_global_stats( $driver_id );
    }
    srl_write_to_log('SRL Recalculate: Se actualizaron las estadísticas básicas (victorias, podios, etc.).');

    $wpdb->query( "UPDATE {$wpdb->prefix}srl_drivers SET championships_won_count = 0" );
    srl_write_to_log('SRL Recalculate: Se resetearon los contadores de campeonatos ganados.');
    
    $completed_championships = get_posts([ 'post_type' => 'srl_championship', 'posts_per_page' => -1, 'meta_key' => '_srl_status', 'meta_value' => 'completed' ]);
    srl_write_to_log('SRL Recalculate: Se encontraron ' . count($completed_championships) . ' campeonatos completados para analizar.');

    $champs_recalculated = 0;
    foreach ( $completed_championships as $champ ) {
        if ( ! function_exists('srl_calculate_championship_standings') ) {
            require_once SRL_PLUGIN_PATH . 'includes/core-functions.php';
        }
        $standings = srl_calculate_championship_standings( $champ->ID );
        if ( ! empty( $standings ) ) {
            $winner_driver_id = array_key_first( $standings );
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}srl_drivers SET championships_won_count = championships_won_count + 1 WHERE id = %d", $winner_driver_id ) );
            $champs_recalculated++;
            srl_write_to_log('SRL Recalculate: Campeonato ID ' . $champ->ID . ' procesado. Ganador: Piloto ID ' . $winner_driver_id);
        } else {
            srl_write_to_log('SRL Recalculate: Campeonato ID ' . $champ->ID . ' no tiene resultados y fue omitido.');
        }
    }
    
    $final_message = 'Se han recalculado las estadísticas para ' . count( $driver_ids ) . ' pilotos y se han reasignado ' . $champs_recalculated . ' campeonatos.';
    srl_write_to_log('SRL Recalculate: ' . $final_message);
    srl_write_to_log('--- FIN DE RECÁLCULO DE ESTADÍSTICAS SRL ---');
    wp_send_json_success( ['message' => $final_message] );
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
/**
 * Maneja la eliminación de todos los resultados y sesiones de un evento.
 */
function srl_handle_delete_event_results() {
    check_ajax_referer( 'srl_delete_event_results_nonce', 'nonce' );

    if ( ! isset( $_POST['event_id'] ) || ! current_user_can('manage_options') ) {
        wp_send_json_error( ['message' => 'Error: Faltan datos o no tienes permisos.'] );
    }

    global $wpdb;
    $event_id = intval( $_POST['event_id'] );

    // 1. Encontrar todas las sesiones para este evento
    $session_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d", $event_id ) );

    if ( ! empty( $session_ids ) ) {
        $session_ids_placeholder = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );

        // 2. Encontrar todos los pilotos afectados ANTES de borrar los resultados
        $affected_drivers = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT driver_id FROM {$wpdb->prefix}srl_results WHERE session_id IN ($session_ids_placeholder)", $session_ids ) );

        // 3. Borrar los resultados y las sesiones
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}srl_results WHERE session_id IN ($session_ids_placeholder)", $session_ids ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}srl_sessions WHERE id IN ($session_ids_placeholder)", $session_ids ) );

        // 4. Recalcular las estadísticas de los pilotos afectados
        foreach ( $affected_drivers as $driver_id ) {
            srl_update_driver_global_stats( $driver_id );
        }
        
        wp_send_json_success( ['message' => 'Resultados eliminados. Se han actualizado las estadísticas de ' . count($affected_drivers) . ' pilotos.'] );
    } else {
        wp_send_json_success( ['message' => 'No había resultados que eliminar.'] );
    }
}

/**
 * Maneja la subida en lote de archivos de resultados de Assetto Corsa.
 */
function srl_handle_bulk_upload() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! isset( $_POST['championship_id'] ) || empty( $_FILES['bulk_results_files'] ) ) {
        wp_send_json_error( ['message' => 'Faltan datos. Selecciona un campeonato y al menos un archivo.'] );
    }

    $championship_id = intval( $_POST['championship_id'] );
    $files = $_FILES['bulk_results_files'];
    $file_data = [];
    $log = [];

    // 1. Validar y leer todos los archivos primero
    for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
        $file_name = $files['name'][$i];
        if ( strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) ) !== 'json' ) {
            $log[] = "Error: El archivo '{$file_name}' no es un .json y fue omitido.";
            continue;
        }
        $content = file_get_contents( $files['tmp_name'][$i] );
        $json_data = json_decode( $content, true );
        if ( json_last_error() !== JSON_ERROR_NONE || !isset($json_data['TrackName'], $json_data['Date']) ) {
            $log[] = "Error: El archivo '{$file_name}' está corrupto o no tiene los campos 'TrackName' o 'Date' y fue omitido.";
            continue;
        }
        $file_data[] = [
            'name' => $file_name,
            'date' => strtotime( $json_data['Date'] ),
            'track_name' => $json_data['TrackName'],
            'content' => $content,
        ];
    }

    // 2. Ordenar archivos por fecha
    usort( $file_data, fn($a, $b) => $a['date'] <=> $b['date'] );

    // 3. Procesar archivos en orden
    global $wpdb;
    $round_number = 1;
    foreach ( $file_data as $data ) {
        // Crear el título del evento
        $track_name_formatted = ucwords( str_replace( ['_', '-'], ' ', $data['track_name'] ) );
        $event_date_formatted = date( 'd/m/Y', $data['date'] );
        $event_title = "R{$round_number}: {$track_name_formatted} - ({$event_date_formatted})";

        // Crear el post del evento
        $event_id = wp_insert_post([
            'post_title'  => $event_title,
            'post_type'   => 'srl_event',
            'post_status' => 'publish',
        ]);

        if ( $event_id ) {
            update_post_meta( $event_id, '_srl_parent_championship', $championship_id );
            update_post_meta( $event_id, '_srl_track_name', $data['track_name'] );
            update_post_meta( $event_id, '_srl_event_date', date('Y-m-d', $data['date']) );

            // Crear la sesión e importar resultados
            $wpdb->insert( $wpdb->prefix . 'srl_sessions', [ 'event_id' => $event_id, 'session_type' => 'Race', 'source_file' => sanitize_file_name( $data['name'] ) ] );
            $session_id = $wpdb->insert_id;
            srl_parse_assetto_corsa_results( $data['content'], $session_id, $event_id );
            
            $log[] = "Éxito: El archivo '{$data['name']}' fue importado como '{$event_title}'.";
            $round_number++;
        } else {
            $log[] = "Error: No se pudo crear el evento para el archivo '{$data['name']}'.";
        }
    }

    wp_send_json_success( ['message' => 'Proceso completado.', 'log' => $log] );
}

function srl_handle_history_import() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( empty( $_FILES['history_file'] ) ) {
        wp_send_json_error( ['message' => 'No se ha subido ningún archivo.'] );
    }
    
    // Aumentar el límite de tiempo de ejecución para este proceso
    set_time_limit(300); // 5 minutos

    $file = $_FILES['history_file'];
    
    // Llamar a la función principal del parser de Automobilista
    $result = srl_parse_automobilista_history_file( $file['tmp_name'] );

    if ( $result['status'] === 'success' ) {
        wp_send_json_success( ['message' => 'Migración completada.', 'log' => $result['log']] );
    } else {
        wp_send_json_error( ['message' => 'Ocurrió un error durante la migración.', 'log' => $result['log']] );
    }
}
function srl_handle_cleanup_orphans() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error( ['message' => 'No tienes permisos.'] );
    }

    global $wpdb;
    // Encontrar IDs de sesiones cuyo event_id no existe en la tabla de posts
    $orphan_sessions_query = "
        SELECT s.id FROM {$wpdb->prefix}srl_sessions s
        LEFT JOIN {$wpdb->prefix}posts p ON s.event_id = p.ID
        WHERE p.ID IS NULL
    ";
    $orphan_session_ids = $wpdb->get_col($orphan_sessions_query);

    if ( empty($orphan_session_ids) ) {
        wp_send_json_success( ['message' => 'No se encontraron resultados huérfanos. ¡La base de datos está limpia!'] );
    }

    $ids_placeholder = implode( ',', array_fill( 0, count( $orphan_session_ids ), '%d' ) );
    
    // Borrar resultados y sesiones huérfanas
    $results_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}srl_results WHERE session_id IN ($ids_placeholder)", $orphan_session_ids ) );
    $sessions_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}srl_sessions WHERE id IN ($ids_placeholder)", $orphan_session_ids ) );

    wp_send_json_success( ['message' => "Limpieza completada. Se eliminaron {$sessions_deleted} sesiones y {$results_deleted} resultados huérfanos."] );
}
