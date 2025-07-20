<?php
/**
 * SRL JSON Parser
 * Reads Assetto Corsa server-manager JSON files.
 */
class SRL_Parser {

    /**
     * Parse and import a JSON file for a given WP session ID.
     *
     * @param string $json_path   Full path to the uploaded .json file.
     * @param int    $session_id  WP post ID of the srl_session.
     * @return array              [success => bool, message => string, rows => int]
     */
    public static function import_json( $json_path, $session_id ) {

        if ( ! is_readable( $json_path ) ) {
            return [ 'success' => false, 'message' => 'File not readable', 'rows' => 0 ];
        }

        $raw = json_decode( file_get_contents( $json_path ), true );
        if ( ! $raw || ! isset( $raw['results'] ) || ! is_array( $raw['results'] ) ) {
            return [ 'success' => false, 'message' => 'Invalid JSON structure', 'rows' => 0 ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'srl_results';

        // 1️⃣ Delete any previous results for this session (idempotent re-import)
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE session_id = %d", $session_id ) );

        // 2️⃣ Store session-level meta (fastest lap etc.)
        update_post_meta( $session_id, 'srl_track', sanitize_text_field( $raw['trackName'] ?? '' ) );
        update_post_meta( $session_id, 'srl_fastest_lap_ms', absint( $raw['fastestLap']['lapTime'] ?? 0 ) );

        // 3️⃣ Insert every driver row
        $rows = 0;
        foreach ( $raw['results'] as $row ) {

            $driver_id = self::ensure_driver_post(
                sanitize_text_field( $row['driverName'] ),
                sanitize_text_field( $row['steamId'] )
            );

            $wpdb->insert( $table, [
                'session_id'  => $session_id,
                'driver_id'   => $driver_id,
                'position'    => absint( $row['position'] ),
                'fastest_lap' => self::ms_to_time( absint( $row['bestLap'] ?? 0 ) ),
                'dnf'         => 0, // AC simplified has no DNF flag – we’ll add later if needed
                'laps'        => absint( $row['lapsCompleted'] ?? 0 ),
            ] );

            $rows++;
        }

        // 4️⃣ Bust driver-stats cache
        $driver_ids = wp_list_pluck( $raw['results'], 'steamId' );
        foreach ( $driver_ids as $sid ) {
            $pid = get_page_by_title( $sid, OBJECT, 'driver' );
            if ( $pid ) {
                delete_transient( 'srl_stats_' . $pid->ID );
            }
        }

        return [ 'success' => true, 'message' => 'Imported ' . $rows . ' rows', 'rows' => $rows ];
    }

    /**
     * Convert milliseconds → HH:MM:SS.mmm string for MySQL TIME column.
     */
    private static function ms_to_time( $ms ) {
        $ms = absint( $ms );
        if ( ! $ms ) return null;

        $hours   = floor( $ms / 3600000 );
        $minutes = floor( ( $ms % 3600000 ) / 60000 );
        $seconds = floor( ( $ms % 60000 ) / 1000 );
        $millis  = $ms % 1000;

        return sprintf( '%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $millis );
    }

    /**
     * Identical helper for driver post creation / lookup.
     */
    private static function ensure_driver_post( $name, $steam_id ) {
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
}