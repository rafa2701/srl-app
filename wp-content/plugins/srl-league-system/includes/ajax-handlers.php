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
add_action( 'wp_ajax_srl_recalculate_championship_points', 'srl_handle_recalculate_championship_points' );
add_action( 'wp_ajax_srl_save_result_details', 'srl_handle_save_result_details' );
add_action( 'wp_ajax_srl_reorder_results', 'srl_handle_reorder_results' );
add_action( 'wp_ajax_srl_add_manual_result', 'srl_handle_add_manual_result' );
add_action( 'wp_ajax_srl_delete_single_result', 'srl_handle_delete_single_result' );
add_action( 'wp_ajax_srl_create_manual_session', 'srl_handle_create_manual_session' );
add_action( 'wp_ajax_srl_save_event_multiplier', 'srl_handle_save_event_multiplier' );
add_action( 'wp_ajax_srl_find_duplicate_drivers', 'srl_handle_find_duplicate_drivers' );
add_action( 'wp_ajax_srl_get_merge_preview', 'srl_handle_get_merge_preview' );
add_action( 'wp_ajax_srl_perform_driver_merge', 'srl_handle_perform_driver_merge' );
add_action( 'wp_ajax_srl_get_all_drivers_simple', 'srl_handle_get_all_drivers_simple' );

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
        // Asegurarse de que el driver tiene nombre si venía de un import parcial
        $driver_name = $wpdb->get_var($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $driver_id));
        if ( empty($driver_name) ) {
            $last_name = $wpdb->get_var($wpdb->prepare("SELECT driver_name FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE r.driver_id = %d AND s.source_file != 'history.xlsx' LIMIT 1", $driver_id));
            if ($last_name) {
                $wpdb->update($wpdb->prefix . 'srl_drivers', ['full_name' => $last_name], ['id' => $driver_id]);
            }
        }
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

    // Recalcular todos los hitos (Achievements)
    srl_write_to_log('SRL Recalculate: Iniciando sincronización de hitos.');
    SRL_Achievement_Manager::sync_all_achievements();
    srl_write_to_log('SRL Recalculate: Hitos sincronizados.');

    srl_write_to_log('SRL Recalculate: ' . $final_message);
    srl_write_to_log('--- FIN DE RECÁLCULO DE ESTADÍSTICAS SRL ---');
    wp_send_json_success( ['message' => $final_message . ' También se han sincronizado los hitos (logros).'] );
}

function srl_handle_get_achievement_details() {
    check_ajax_referer( 'srl-public-nonce', 'nonce' );
    if ( ! isset( $_POST['driver_id'], $_POST['stat_type'] ) ) wp_send_json_error( ['message' => 'Faltan datos.'] );
    global $wpdb;
    $driver_id = intval( $_POST['driver_id'] );
    $stat_type = sanitize_key( $_POST['stat_type'] );
    $where_clause = '';
    switch ( $stat_type ) {
        case 'victories': $where_clause = "r.position = 1 AND r.is_disqualified = 0 AND r.is_nc = 0"; break;
        case 'podiums': $where_clause = "r.position <= 3 AND r.is_disqualified = 0 AND r.is_nc = 0"; break;
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
/**
 * Maneja el recálculo de puntos para todos los eventos de un campeonato.
 */
function srl_handle_recalculate_championship_points() {
    check_ajax_referer( 'srl_recalculate_points_nonce', 'nonce' );

    if ( ! isset( $_POST['championship_id'] ) || ! current_user_can('manage_options') ) {
        wp_send_json_error( ['message' => 'Error: Faltan datos o no tienes permisos.'] );
    }

    $championship_id = intval( $_POST['championship_id'] );
    
    // Obtener las nuevas reglas de puntuación
    $scoring_rules_json = get_post_meta( $championship_id, '_srl_scoring_rules', true );
    $scoring_rules = json_decode( $scoring_rules_json, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;

    global $wpdb;
    
    // Encontrar todos los eventos y resultados de este campeonato
    $event_ids = get_posts(['post_type' => 'srl_event', 'meta_key' => '_srl_parent_championship', 'meta_value' => $championship_id, 'posts_per_page' => -1, 'fields' => 'ids']);
    
    if ( empty($event_ids) ) {
        wp_send_json_success( ['message' => 'Este campeonato no tiene eventos para recalcular.'] );
    }

    $event_ids_placeholder = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
    $results = $wpdb->get_results( $wpdb->prepare( "SELECT id, position, has_pole, has_fastest_lap, driver_id FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE s.event_id IN ($event_ids_placeholder)", $event_ids ) );

    $affected_drivers = [];
    foreach ( $results as $result ) {
        $points_awarded = 0;
        $points_awarded += ($points_map[ $result->position ] ?? 0);
        if ( $result->has_pole ) $points_awarded += $bonus_pole;
        if ( $result->has_fastest_lap ) $points_awarded += $bonus_fastest_lap;

        $wpdb->update(
            $wpdb->prefix . 'srl_results',
            [ 'points_awarded' => $points_awarded ],
            [ 'id' => $result->id ]
        );
        $affected_drivers[] = $result->driver_id;
    }

    // Recalcular las estadísticas globales de los pilotos afectados
    $unique_affected_drivers = array_unique($affected_drivers);
    foreach ( $unique_affected_drivers as $driver_id ) {
        srl_update_driver_global_stats( $driver_id );
    }

    wp_send_json_success( ['message' => count($results) . ' resultados actualizados. Se recalcularon las estadísticas de ' . count($unique_affected_drivers) . ' pilotos.'] );
}

/**
 * Maneja el guardado de detalles de un resultado y recalcula la sesión.
 */
function srl_handle_save_result_details() {
    check_ajax_referer( 'srl_save_penalties_nonce', 'nonce' );

    if ( ! current_user_can('manage_options') || ! isset($_POST['result_id']) ) {
        wp_send_json_error( ['message' => 'No tienes permisos o faltan datos.'] );
    }

    global $wpdb;
    $result_id = intval($_POST['result_id']);

    $grid_pos = intval($_POST['grid_position']);
    $laps = intval($_POST['laps_completed']);
    $best_lap = srl_parse_edit_time($_POST['best_lap_time']);
    $total_time = srl_parse_edit_time($_POST['total_time']);
    $penalty_ms = intval( floatval($_POST['penalty_seconds']) * 1000 );
    $is_dnf = intval($_POST['is_dnf']);
    $is_nc = intval($_POST['is_nc']);
    $is_dq = intval($_POST['is_dq']);
    $point_penalty = floatval($_POST['point_penalty']);
    $manual_points = floatval($_POST['manual_points']);
    $is_points_manual = intval($_POST['is_points_manual']);

    // 1. Obtener información de la sesión
    $session_id = $wpdb->get_var($wpdb->prepare("SELECT session_id FROM {$wpdb->prefix}srl_results WHERE id = %d", $result_id));
    if (!$session_id) {
        wp_send_json_error( ['message' => 'Resultado no encontrado.'] );
    }

    // 2. Actualizar el resultado editado
    $wpdb->update(
        $wpdb->prefix . 'srl_results',
        [
            'grid_position' => $grid_pos,
            'laps_completed' => $laps,
            'best_lap_time' => $best_lap,
            'total_time' => $total_time,
            'time_penalty' => $penalty_ms,
            'is_dnf' => $is_dnf,
            'is_nc' => $is_nc,
            'is_nc_forced' => 1,
            'is_disqualified' => $is_dq,
            'point_penalty' => $point_penalty,
            'manual_points' => $manual_points,
            'is_points_manual' => $is_points_manual
        ],
        [ 'id' => $result_id ]
    );

    // 3. Recalcular toda la sesión
    srl_recalculate_session_results($session_id);

    wp_send_json_success( ['message' => 'Resultado actualizado y recalculado.'] );
}

/**
 * Maneja el reordenamiento de resultados vía drag-and-drop.
 */
function srl_handle_reorder_results() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    if ( ! current_user_can('manage_options') || ! isset($_POST['result_ids']) ) {
        wp_send_json_error( ['message' => 'No tienes permisos o faltan datos.'] );
    }

    global $wpdb;
    $result_ids = $_POST['result_ids'];
    $position = 1;
    $session_id = 0;

    foreach ( $result_ids as $id ) {
        $id = intval($id);
        if (!$session_id) {
            $session_id = $wpdb->get_var($wpdb->prepare("SELECT session_id FROM {$wpdb->prefix}srl_results WHERE id = %d", $id));
        }
        $wpdb->update( $wpdb->prefix . 'srl_results', ['position' => $position], ['id' => $id] );
        $position++;
    }

    if ($session_id) {
        srl_recalculate_session_results($session_id);
    }

    wp_send_json_success( ['message' => 'Posiciones actualizadas.'] );
}

/**
 * Añade un piloto manualmente a una sesión.
 */
function srl_handle_add_manual_result() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    if ( ! current_user_can('manage_options') || ! isset($_POST['driver_id'], $_POST['session_id']) ) {
        wp_send_json_error( ['message' => 'No tienes permisos o faltan datos.'] );
    }

    global $wpdb;
    $driver_id = intval($_POST['driver_id']);
    $session_id = intval($_POST['session_id']);

    // Verificar si ya existe
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}srl_results WHERE session_id = %d AND driver_id = %d", $session_id, $driver_id));
    if ($exists) {
        wp_send_json_error( ['message' => 'Este piloto ya tiene un resultado en esta sesión.'] );
    }

    // Obtener la última posición
    $last_pos = $wpdb->get_var($wpdb->prepare("SELECT MAX(position) FROM {$wpdb->prefix}srl_results WHERE session_id = %d", $session_id));
    $new_pos = intval($last_pos) + 1;

    $wpdb->insert($wpdb->prefix . 'srl_results', [
        'session_id' => $session_id,
        'driver_id' => $driver_id,
        'position' => $new_pos,
        'grid_position' => $new_pos,
        'points_awarded' => 0
    ]);

    srl_recalculate_session_results($session_id);
    wp_send_json_success( ['message' => 'Piloto añadido.'] );
}

/**
 * Crea una sesión de carrera manual si no existe ninguna.
 */
function srl_handle_create_manual_session() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! current_user_can('manage_options') || ! isset($_POST['event_id']) ) {
        wp_send_json_error( ['message' => 'Sin permisos o faltan datos.'] );
    }

    global $wpdb;
    $event_id = intval($_POST['event_id']);

    $wpdb->insert( $wpdb->prefix . 'srl_sessions', [
        'event_id' => $event_id,
        'session_type' => 'Race',
        'source_file' => 'Manual'
    ] );

    if ($wpdb->insert_id) {
        wp_send_json_success( ['message' => 'Sesión creada.'] );
    } else {
        wp_send_json_error( ['message' => 'Error al crear sesión.'] );
    }
}

/**
 * Elimina un único resultado.
 */
function srl_handle_delete_single_result() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    if ( ! current_user_can('manage_options') || ! isset($_POST['result_id']) ) {
        wp_send_json_error( ['message' => 'No tienes permisos o faltan datos.'] );
    }

    global $wpdb;
    $result_id = intval($_POST['result_id']);
    $result = $wpdb->get_row($wpdb->prepare("SELECT session_id, driver_id FROM {$wpdb->prefix}srl_results WHERE id = %d", $result_id));

    if ($result) {
        $wpdb->delete($wpdb->prefix . 'srl_results', ['id' => $result_id]);
        srl_recalculate_session_results($result->session_id);
        srl_update_driver_global_stats($result->driver_id);
        wp_send_json_success( ['message' => 'Resultado eliminado.'] );
    } else {
        wp_send_json_error( ['message' => 'Resultado no encontrado.'] );
    }
}

/**
 * Guarda el multiplicador de puntos para un evento.
 */
function srl_handle_save_event_multiplier() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    if ( ! current_user_can('manage_options') || ! isset($_POST['event_id'], $_POST['multiplier']) ) {
        wp_send_json_error( ['message' => 'Sin permisos o faltan datos.'] );
    }

    $event_id = intval($_POST['event_id']);
    $multiplier = floatval($_POST['multiplier']);

    update_post_meta($event_id, '_srl_event_points_multiplier', $multiplier);

    // Recalcular la sesión de carrera si existe
    global $wpdb;
    $session_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d AND session_type = 'Race' LIMIT 1", $event_id));

    if ($session_id) {
        srl_recalculate_session_results($session_id);
    }

    wp_send_json_success( ['message' => 'Multiplicador guardado y puntos recalculados.'] );
}

/**
 * Obtiene todos los pilotos para los dropdowns de fusión.
 */
function srl_handle_get_all_drivers_simple() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error();

    global $wpdb;
    $drivers = $wpdb->get_results("SELECT id, full_name, steam_id FROM {$wpdb->prefix}srl_drivers ORDER BY full_name ASC");
    wp_send_json_success($drivers);
}

/**
 * Busca pilotos con nombres idénticos o muy similares.
 */
function srl_handle_find_duplicate_drivers() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error();

    global $wpdb;
    $table = $wpdb->prefix . 'srl_drivers';

    // 1. Buscar duplicados exactos por nombre
    $exact_duplicates = $wpdb->get_results("
        SELECT full_name, COUNT(*) as count
        FROM $table
        GROUP BY full_name
        HAVING count > 1
    ");

    $groups = [];
    foreach ($exact_duplicates as $dup) {
        $drivers = $wpdb->get_results($wpdb->prepare("SELECT id, full_name, steam_id FROM $table WHERE full_name = %s", $dup->full_name));
        $groups[] = [
            'type' => 'exact',
            'name' => $dup->full_name,
            'drivers' => $drivers
        ];
    }

    // 2. Buscar por Steam ID duplicado (si existiera, que no debería por el unique key de los importers, pero por si acaso)
    $steam_duplicates = $wpdb->get_results("
        SELECT steam_id, COUNT(*) as count
        FROM $table
        WHERE steam_id IS NOT NULL AND steam_id != ''
        GROUP BY steam_id
        HAVING count > 1
    ");

    foreach ($steam_duplicates as $dup) {
        $drivers = $wpdb->get_results($wpdb->prepare("SELECT id, full_name, steam_id FROM $table WHERE steam_id = %s", $dup->steam_id));
        $groups[] = [
            'type' => 'steam_id',
            'name' => 'Steam ID: ' . $dup->steam_id,
            'drivers' => $drivers
        ];
    }

    wp_send_json_success($groups);
}

/**
 * Previsualiza los datos que se moverán en una fusión.
 */
function srl_handle_get_merge_preview() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error();

    global $wpdb;
    $id_a = intval($_POST['driver_a']);
    $id_b = intval($_POST['driver_b']);

    if ($id_a === $id_b) wp_send_json_error(['message' => 'No puedes fusionar un piloto consigo mismo.']);

    $driver_a = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $id_a));
    $driver_b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $id_b));

    if (!$driver_a || !$driver_b) wp_send_json_error(['message' => 'Uno de los pilotos no existe.']);

    // Validar Steam IDs
    $can_merge = true;
    $warning = '';
    if (!empty($driver_a->steam_id) && !empty($driver_b->steam_id) && $driver_a->steam_id !== $driver_b->steam_id) {
        $can_merge = false;
        $warning = 'BLOQUEADO: Ambos pilotos tienen Steam IDs diferentes (' . $driver_a->steam_id . ' vs ' . $driver_b->steam_id . '). No se recomienda fusionar.';
    }

    $results_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}srl_results WHERE driver_id = %d", $id_b));
    $achievements_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}srl_achievements WHERE driver_id = %d", $id_b));

    wp_send_json_success([
        'can_merge' => $can_merge,
        'warning' => $warning,
        'driver_a' => $driver_a,
        'driver_b' => $driver_b,
        'results_to_move' => $results_count,
        'achievements_to_move' => $achievements_count
    ]);
}

/**
 * Ejecuta la fusión de dos pilotos.
 */
function srl_handle_perform_driver_merge() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error();

    global $wpdb;
    $id_a = intval($_POST['driver_a']);
    $id_b = intval($_POST['driver_b']);
    $force = isset($_POST['force']) && $_POST['force'] === 'true';

    $driver_a = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $id_a));
    $driver_b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $id_b));

    if (!$driver_a || !$driver_b) wp_send_json_error(['message' => 'Error: Pilotos no encontrados.']);

    // Validación final de seguridad
    if (!empty($driver_a->steam_id) && !empty($driver_b->steam_id) && $driver_a->steam_id !== $driver_b->steam_id && !$force) {
        wp_send_json_error(['message' => 'Error de seguridad: Steam IDs incompatibles.']);
    }

    // 1. Mover resultados (usar INSERT IGNORE o similar no es necesario aquí porque el unique key es session_id + driver_id)
    // Pero si el Piloto A ya tiene un resultado en una sesión donde el B también estaba, el update fallará por el UK.
    // En ese caso, deberíamos borrar el resultado del Piloto B (el duplicado) o decidir qué hacer.

    $sessions_a = $wpdb->get_col($wpdb->prepare("SELECT session_id FROM {$wpdb->prefix}srl_results WHERE driver_id = %d", $id_a));

    if (!empty($sessions_a)) {
        $placeholders = implode(',', array_fill(0, count($sessions_a), '%d'));
        // Borrar resultados del Piloto B en sesiones donde A ya existe
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}srl_results WHERE driver_id = %d AND session_id IN ($placeholders)", array_merge([$id_b], $sessions_a)));
    }

    // Ahora mover el resto
    $wpdb->update($wpdb->prefix . 'srl_results', ['driver_id' => $id_a], ['driver_id' => $id_b]);

    // 2. Mover logros
    $wpdb->update($wpdb->prefix . 'srl_achievements', ['driver_id' => $id_a], ['driver_id' => $id_b]);

    // 3. Completar datos faltantes en A si B los tiene
    $update_data = [];
    if (empty($driver_a->steam_id) && !empty($driver_b->steam_id)) $update_data['steam_id'] = $driver_b->steam_id;
    if (empty($driver_a->nationality) && !empty($driver_b->nationality)) $update_data['nationality'] = $driver_b->nationality;
    if (empty($driver_a->photo_id) && !empty($driver_b->photo_id)) {
        $update_data['photo_id'] = $driver_b->photo_id;
        $update_data['photo_url'] = $driver_b->photo_url;
    }

    if (!empty($update_data)) {
        $wpdb->update($wpdb->prefix . 'srl_drivers', $update_data, ['id' => $id_a]);
    }

    // 4. Recalcular estadísticas para Piloto A
    if (function_exists('srl_update_driver_global_stats')) {
        srl_update_driver_global_stats($id_a);
    }

    // 5. Eliminar Piloto B
    $wpdb->delete($wpdb->prefix . 'srl_drivers', ['id' => $id_b]);

    wp_send_json_success(['message' => 'Fusión completada con éxito. El Piloto B ha sido eliminado y sus datos transferidos al Piloto A.']);
}
