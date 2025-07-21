<?php
/**
 * Maneja las peticiones AJAX del plugin.
 *
 * @package SRL_League_System
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Hook para la petición de subida de resultados
add_action( 'wp_ajax_srl_upload_results_file', 'srl_handle_results_upload' );
add_action( 'wp_ajax_srl_get_events', 'srl_handle_get_events' );

function srl_handle_results_upload() {
    // 1. Seguridad: Verificar el nonce
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    // 2. Validar que los datos necesarios están presentes
    if ( ! isset( $_POST['championship_id'], $_POST['event_id'], $_POST['session_type'] ) || empty( $_FILES['results_file'] ) ) {
        wp_send_json_error( ['message' => 'Faltan datos. Por favor, completa todos los campos.'] );
    }

    $event_id = intval( $_POST['event_id'] );
    $session_type = sanitize_text_field( $_POST['session_type'] );
    $file = $_FILES['results_file'];

    // 3. Validar el archivo
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( ['message' => 'Error al subir el archivo. Código: ' . $file['error']] );
    }
    if ( $file['type'] !== 'application/json' ) {
        wp_send_json_error( ['message' => 'Formato de archivo incorrecto. Solo se admiten archivos .json.'] );
    }

    // 4. Leer el contenido del archivo
    $json_content = file_get_contents( $file['tmp_name'] );
    if ( $json_content === false ) {
        wp_send_json_error( ['message' => 'No se pudo leer el contenido del archivo.'] );
    }

    // 5. Crear la sesión en la base de datos y obtener su ID
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'srl_sessions';
    $wpdb->insert(
        $sessions_table,
        [
            'event_id' => $event_id,
            'session_type' => $session_type,
            'source_file' => sanitize_file_name( $file['name'] )
        ]
    );
    $session_id = $wpdb->insert_id;

    if ( ! $session_id ) {
        wp_send_json_error( ['message' => 'Error al crear la sesión en la base de datos.'] );
    }

    // 6. Llamar al parser que ya creamos
    $result = srl_parse_assetto_corsa_results( $json_content, $session_id );

    // 7. Enviar respuesta al frontend
    if ( $result['status'] === 'success' ) {
        wp_send_json_success( ['message' => $result['message']] );
    } else {
        wp_send_json_error( ['message' => $result['message']] );
    }
}


// Hook para obtener los eventos de un campeonato
add_action( 'wp_ajax_srl_get_events', 'srl_handle_get_events' );


/**
 * Obtiene los eventos (CPT) de un campeonato (CPT) usando el campo personalizado.
 */
function srl_handle_get_events() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! isset( $_POST['championship_id'] ) ) {
        wp_send_json_error( ['message' => 'No se proporcionó ID de campeonato.'] );
    }

    $championship_id = intval( $_POST['championship_id'] );
    
    $args = [
        'post_type'      => 'srl_event',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => '_srl_parent_championship', // <-- Usamos el nuevo campo de SCF
                'value'   => $championship_id,
                'compare' => '=',
            ],
        ],
    ];
    $event_posts = get_posts( $args );

    $events = [];
    if ( ! empty( $event_posts ) ) {
        foreach ( $event_posts as $event_post ) {
            $events[] = [
                'id'   => $event_post->ID,
                'name' => $event_post->post_title,
            ];
        }
    }
    
    wp_send_json_success( $events );
}
// Hook para la nueva petición de detalles de logros
add_action( 'wp_ajax_srl_get_achievement_details', 'srl_handle_get_achievement_details' );
add_action( 'wp_ajax_nopriv_srl_get_achievement_details', 'srl_handle_get_achievement_details' ); // Para usuarios no logueados

function srl_handle_get_achievement_details() {
    check_ajax_referer( 'srl-public-nonce', 'nonce' );

    if ( ! isset( $_POST['driver_id'], $_POST['stat_type'] ) ) {
        wp_send_json_error( ['message' => 'Faltan datos.'] );
    }

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
        SELECT event_post.ID as event_id, event_post.post_title as event_name
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        JOIN {$wpdb->prefix}posts event_post ON s.event_id = event_post.ID
        WHERE r.driver_id = %d AND s.session_type = 'Race' AND {$where_clause}
        ORDER BY event_post.post_date DESC
    ", $driver_id);

    $results = $wpdb->get_results( $query );
    
    $achievements = [];
    foreach($results as $result) {
        $achievements[] = [
            'name' => $result->event_name,
            'url'  => get_permalink($result->event_id)
        ];
    }

    wp_send_json_success( $achievements );
}
