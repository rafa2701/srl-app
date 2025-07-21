<?php
/**
 * Archivo para procesar los resultados en formato JSON de Assetto Corsa.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

/**
 * Procesa el contenido de un archivo JSON de resultados de Assetto Corsa.
 */
function srl_parse_assetto_corsa_results( $json_content, $session_id ) {
    global $wpdb;
    $data = json_decode( $json_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return ['status' => 'error', 'message' => 'Error al decodificar el JSON: ' . json_last_error_msg()];
    }

    $fastest_lap_time = PHP_INT_MAX;
    $fastest_lap_driver_guid = null;
    if ( ! empty( $data['Laps'] ) ) {
        foreach ( $data['Laps'] as $lap ) {
            if ( isset($lap['Cuts']) && $lap['Cuts'] == 0 && isset($lap['LapTime']) && $lap['LapTime'] > 0 && $lap['LapTime'] < $fastest_lap_time ) {
                $fastest_lap_time = $lap['LapTime'];
                $fastest_lap_driver_guid = $lap['DriverGuid'];
            }
        }
    }

    if ( empty( $data['Result'] ) ) {
        return ['status' => 'error', 'message' => 'El archivo JSON no contiene la sección "Result".'];
    }

    $processed_drivers = [];
    $position = 1;
    foreach ( $data['Result'] as $result_item ) {
        $driver_guid = $result_item['DriverGuid'];
        $driver_name = $result_item['DriverName'];
        $driver_id = srl_get_or_create_driver( $driver_guid, $driver_name );
        if ( ! $driver_id ) continue;
        
        $processed_drivers[] = $driver_id;

        $result_data = [
            'session_id'      => $session_id,
            'driver_id'       => $driver_id,
            'team_name'       => $result_item['CarModel'],
            'car_model'       => $result_item['CarModel'],
            'position'        => $position,
            'grid_position'   => $result_item['GridPosition'],
            'best_lap_time'   => $result_item['BestLap'],
            'total_time'      => $result_item['TotalTime'],
            'laps_completed'  => count( array_filter($data['Laps'], fn($lap) => $lap['DriverGuid'] === $driver_guid) ),
            'has_pole'        => ( $result_item['GridPosition'] == 1 ),
            'has_fastest_lap' => ( $driver_guid === $fastest_lap_driver_guid ),
            'is_dnf'          => ( $result_item['TotalTime'] == 0 ),
            'points_awarded'  => 0,
        ];
        
        $wpdb->replace( $wpdb->prefix . 'srl_results', $result_data );
        $position++;
    }

    foreach ( array_unique( $processed_drivers ) as $driver_id ) {
        srl_update_driver_global_stats( $driver_id );
    }

    return ['status' => 'success', 'message' => 'Resultados importados y estadísticas globales actualizadas.'];
}

/**
 * Obtiene o crea un piloto en la base de datos.
 */
function srl_get_or_create_driver( $guid, $full_name ) {
    global $wpdb;
    $drivers_table = $wpdb->prefix . 'srl_drivers';
    if ( empty( $guid ) || empty( $full_name ) ) return false;

    $driver_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $drivers_table WHERE steam_id = %s", $guid ) );
    if ( $driver_id ) {
        return (int) $driver_id;
    } else {
        $wpdb->insert( $drivers_table, [ 'steam_id' => $guid, 'full_name' => $full_name ], [ '%s', '%s' ] );
        return $wpdb->insert_id ?: false;
    }
}

/**
 * Recalcula y actualiza los contadores globales para un piloto específico.
 */
function srl_update_driver_global_stats( $driver_id ) {
    global $wpdb;
    $stats = $wpdb->get_row( $wpdb->prepare("
        SELECT
            SUM(CASE WHEN r.position = 1 THEN 1 ELSE 0 END) as victories_count,
            SUM(CASE WHEN r.position <= 3 THEN 1 ELSE 0 END) as podiums_count,
            SUM(CASE WHEN r.position <= 5 THEN 1 ELSE 0 END) as top_5_count,
            SUM(CASE WHEN r.position <= 10 THEN 1 ELSE 0 END) as top_10_count,
            SUM(r.has_pole) as poles_count,
            SUM(r.has_fastest_lap) as fastest_laps_count,
            SUM(r.is_dnf) as dnfs_count
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        WHERE r.driver_id = %d AND s.session_type = 'Race'
    ", $driver_id) );

    if ( $stats ) {
        $wpdb->update(
            $wpdb->prefix . 'srl_drivers',
            [
                'victories_count'    => $stats->victories_count,
                'podiums_count'      => $stats->podiums_count,
                'top_5_count'        => $stats->top_5_count,
                'top_10_count'       => $stats->top_10_count,
                'poles_count'        => $stats->poles_count,
                'fastest_laps_count' => $stats->fastest_laps_count,
                'dnfs_count'         => $stats->dnfs_count,
            ],
            [ 'id' => $driver_id ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d' ],
            [ '%d' ]
        );
    }
}