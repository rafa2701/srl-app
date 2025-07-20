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

function srl_handle_get_events() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );
    if ( ! isset( $_POST['championship_id'] ) ) {
        wp_send_json_error( ['message' => 'No se proporcionó ID de campeonato.'] );
    }

    $championship_id = intval( $_POST['championship_id'] );
    
    $args = [
        'post_type'      => 'srl_event',
        'post_parent'    => $championship_id,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids', // Solo necesitamos los IDs y títulos
    ];
    $event_posts = get_posts( $args );

    $events = [];
    if ( ! empty( $event_posts ) ) {
        foreach ( $event_posts as $event_id ) {
            $events[] = [
                'id'   => $event_id,
                'name' => get_the_title( $event_id ),
            ];
        }
    }
    
    wp_send_json_success( $events );
}
