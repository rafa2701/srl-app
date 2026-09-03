<?php
/**
 * REST API Endpoints for Virtual Commissary
 * Location: wp-content/plugins/srl-league-system/includes/commissary/rest-api.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'rest_api_init', 'srl_register_commissary_rest_routes' );

/**
 * Register REST API routes.
 */
function srl_register_commissary_rest_routes() {
    register_rest_route( 'srl/v1', '/rulebook', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'srl_rest_get_rulebook',
        'permission_callback' => 'srl_rest_verify_api_key',
    ] );

    register_rest_route( 'srl/v1', '/protest-update', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'srl_rest_update_protest_verdict',
        'permission_callback' => 'srl_rest_verify_api_key',
    ] );
}

/**
 * Validate API Key for incoming REST requests.
 */
function srl_rest_verify_api_key( WP_REST_Request $request ) {
    $configured_key = get_option( 'srl_api_secret_key', '' );

    // If no key is configured, reject by default for security
    if ( empty( $configured_key ) ) {
        return new WP_Error( 'rest_forbidden', 'API Key no configurada en el servidor WordPress.', [ 'status' => 403 ] );
    }

    $provided_key = $request->get_header( 'X-SRL-API-KEY' );
    if ( empty( $provided_key ) ) {
        $auth_header = $request->get_header( 'authorization' );
        if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
            $provided_key = trim( $matches[1] );
        }
    }
    if ( empty( $provided_key ) ) {
        $provided_key = $request->get_param( 'api_key' );
    }

    if ( ! empty( $provided_key ) && hash_equals( $configured_key, $provided_key ) ) {
        return true;
    }

    return new WP_Error( 'rest_forbidden', 'Acceso denegado: API Key inválida o ausente.', [ 'status' => 401 ] );
}

/**
 * Handler: GET /wp-json/srl/v1/rulebook
 */
function srl_rest_get_rulebook( WP_REST_Request $request ) {
    $rulebook = get_option( 'srl_rulebook_markdown', '' );
    $updated_at = get_option( 'srl_rulebook_updated_at', '' );

    return rest_ensure_response( [
        'success'    => true,
        'rulebook'   => $rulebook,
        'updated_at' => $updated_at,
        'length'     => strlen( $rulebook ),
    ] );
}

/**
 * Handler: POST /wp-json/srl/v1/protest-update
 */
function srl_rest_update_protest_verdict( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $protest_id = isset( $params['protest_id'] ) ? intval( $params['protest_id'] ) : 0;
    if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
        return new WP_Error( 'invalid_protest', 'El ID de protesta es inválido o no existe.', [ 'status' => 404 ] );
    }

    $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'completed';
    $error = isset( $params['error'] ) ? sanitize_textarea_field( $params['error'] ) : '';
    $verdict = isset( $params['verdict'] ) ? $params['verdict'] : null;

    if ( $status === 'completed' && ! empty( $verdict ) ) {
        if ( function_exists( 'srl_clean_verdict_utf8' ) ) {
            $verdict = srl_clean_verdict_utf8( $verdict );
        }
        update_post_meta( $protest_id, '_srl_ai_status', 'completed' );
        update_post_meta( $protest_id, '_srl_ai_verdict', is_array( $verdict ) ? wp_slash( wp_json_encode( $verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) : wp_slash( $verdict ) );
        update_post_meta( $protest_id, '_srl_ai_error', '' );
        update_post_meta( $protest_id, '_srl_ai_processed_at', current_time( 'mysql' ) );
    } elseif ( $status === 'failed' ) {
        update_post_meta( $protest_id, '_srl_ai_status', 'failed' );
        update_post_meta( $protest_id, '_srl_ai_error', $error );
    } else {
        update_post_meta( $protest_id, '_srl_ai_status', $status );
    }

    return rest_ensure_response( [
        'success'    => true,
        'message'    => 'Protesta #' . $protest_id . ' actualizada con éxito.',
        'protest_id' => $protest_id,
        'status'     => get_post_meta( $protest_id, '_srl_ai_status', true ),
    ] );
}
