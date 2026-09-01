<?php
/**
 * FTP Sync for Virtual Commissary (Bypasses bot protection)
 * Location: wp-content/plugins/srl-league-system/includes/commissary/ftp-sync.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

// Register the custom cron schedule (every 5 minutes)
add_filter( 'cron_schedules', 'srl_add_cron_interval' );
function srl_add_cron_interval( $schedules ) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__( 'Every 5 Minutes' ),
    );
    return $schedules;
}

// Schedule the cron event on init if not scheduled
add_action( 'init', 'srl_schedule_ftp_sync_cron' );
function srl_schedule_ftp_sync_cron() {
    if ( ! wp_next_scheduled( 'srl_process_ftp_verdicts_cron' ) ) {
        wp_schedule_event( time(), 'every_five_minutes', 'srl_process_ftp_verdicts_cron' );
    }
}

// The cron job hook
add_action( 'srl_process_ftp_verdicts_cron', 'srl_process_ftp_verdicts_handler' );

/**
 * Reads JSON verdict files uploaded via FTP and updates the database.
 */
function srl_process_ftp_verdicts_handler() {
    $upload_dir = wp_upload_dir();
    $verdicts_dir = $upload_dir['basedir'] . '/srl-verdicts';

    if ( ! file_exists( $verdicts_dir ) ) {
        wp_mkdir_p( $verdicts_dir );
        return; // Nothing to process yet
    }

    $json_files = glob( $verdicts_dir . '/*.json' );
    if ( empty( $json_files ) ) {
        return;
    }

    foreach ( $json_files as $file_path ) {
        $json_data = file_get_contents( $file_path );
        $params = json_decode( $json_data, true );

        if ( ! is_array( $params ) ) {
            // Invalid JSON, move or delete
            unlink( $file_path );
            continue;
        }

        $protest_id = isset( $params['protest_id'] ) ? intval( $params['protest_id'] ) : 0;
        
        if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
            // Invalid protest ID
            unlink( $file_path );
            continue;
        }

        $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'completed';
        $error = isset( $params['error'] ) ? sanitize_textarea_field( $params['error'] ) : '';
        $verdict = isset( $params['verdict'] ) ? $params['verdict'] : null;

        // Process the verdict exactly like the REST API
        if ( $status === 'completed' && ! empty( $verdict ) ) {
            update_post_meta( $protest_id, '_srl_ai_status', 'completed' );
            update_post_meta( $protest_id, '_srl_ai_verdict', is_array( $verdict ) ? wp_json_encode( $verdict ) : $verdict );
            update_post_meta( $protest_id, '_srl_ai_error', '' );
            update_post_meta( $protest_id, '_srl_ai_processed_at', current_time( 'mysql' ) );
        } elseif ( $status === 'failed' ) {
            update_post_meta( $protest_id, '_srl_ai_status', 'failed' );
            update_post_meta( $protest_id, '_srl_ai_error', $error );
        } else {
            update_post_meta( $protest_id, '_srl_ai_status', $status );
        }

        // Cleanup the file after successful processing
        unlink( $file_path );
    }
}
