<?php
/**
 * Archivo para procesar los resultados en formato JSON de Assetto Corsa.
 *
 * @package SRL_League_System
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Procesa el contenido de un archivo JSON de resultados de Assetto Corsa
 * e inserta los datos en las tablas personalizadas de la base de datos.
 *
 * @param string $json_content El contenido del archivo JSON.
 * @param int    $session_id   El ID de la sesión a la que pertenecen estos resultados.
 * @return array Un array con el estado del proceso y un mensaje.
 */
function srl_parse_assetto_corsa_results( $json_content, $session_id ) {
    global $wpdb;

    $data = json_decode( $json_content, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return ['status' => 'error', 'message' => 'Error al decodificar el JSON: ' . json_last_error_msg()];
    }

    // --- 1. Determinar la vuelta rápida válida de la sesión ---
    $fastest_lap_time = PHP_INT_MAX;
    $fastest_lap_driver_guid = null;

    if ( ! empty( $data['Laps'] ) ) {
        foreach ( $data['Laps'] as $lap ) {
            if ( $lap['Cuts'] == 0 && $lap['LapTime'] > 0 && $lap['LapTime'] < $fastest_lap_time ) {
                $fastest_lap_time = $lap['LapTime'];
                $fastest_lap_driver_guid = $lap['DriverGuid'];
            }
        }
    }

    // --- 2. Procesar los resultados de cada piloto ---
    if ( empty( $data['Result'] ) ) {
        return ['status' => 'error', 'message' => 'El archivo JSON no contiene la sección "Result".'];
    }

    $processed_drivers = []; // Para evitar procesar un piloto dos veces
    $position = 1;
    foreach ( $data['Result'] as $result_item ) {
        $driver_guid = $result_item['DriverGuid'];
        $driver_name = $result_item['DriverName'];

        // --- 2a. Buscar o crear el piloto en nuestra BD ---
        $driver_id = srl_get_or_create_driver( $driver_guid, $driver_name );
        if ( ! $driver_id ) {
            continue;
        }
        $processed_drivers[] = $driver_id;

        // --- 2b. Preparar los datos para la tabla de resultados ---
        $has_pole = ( $result_item['GridPosition'] == 1 );
        $has_fastest_lap = ( $driver_guid === $fastest_lap_driver_guid );
        $is_dnf = ( $result_item['TotalTime'] == 0 ); // Asumimos DNF si no hay tiempo total

        $result_data = [
            'session_id'      => $session_id,
            'driver_id'       => $driver_id,
            'team_name'       => $result_item['CarModel'],
            'car_model'       => $result_item['CarModel'],
            'position'        => $position,
            'grid_position'   => $result_item['GridPosition'], // <-- NUEVO
            'best_lap_time'   => $result_item['BestLap'],
            'total_time'      => $result_item['TotalTime'],
            'laps_completed'  => count( array_filter($data['Laps'], fn($lap) => $lap['DriverGuid'] === $driver_guid) ),
            'has_pole'        => $has_pole,
            'has_fastest_lap' => $has_fastest_lap,
            'is_dnf'          => $is_dnf,
            'points_awarded'  => 0,
        ];
        
        $wpdb->replace( $wpdb->prefix . 'srl_results', $result_data );
        
        $position++;
    }

    // --- 3. Actualizar las estadísticas globales de los pilotos procesados ---
    foreach ( array_unique( $processed_drivers ) as $driver_id ) {
        srl_update_driver_global_stats( $driver_id );
    }

    return ['status' => 'success', 'message' => 'Resultados importados y estadísticas globales actualizadas.'];
}

/**
 * Obtiene o crea un piloto en la base de datos.
 *
 * @param string $guid El SteamID64 del piloto.
 * @param string $full_name El nombre completo del piloto.
 * @return int|false El ID del piloto o false si hay error.
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
 *
 * @param int $driver_id El ID del piloto a actualizar.
 */
function srl_update_driver_global_stats( $driver_id ) {
    global $wpdb;

    // Consulta para obtener todas las estadísticas de un piloto en carreras
    $stats = $wpdb->get_row( $wpdb->prepare("
        SELECT
            COUNT(r.id) as races_count,
            SUM(CASE WHEN r.position = 1 THEN 1 ELSE 0 END) as victories_count,
            SUM(CASE WHEN r.position <= 3 THEN 1 ELSE 0 END) as podiums_count,
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
                'poles_count'        => $stats->poles_count,
                'fastest_laps_count' => $stats->fastest_laps_count,
                'dnfs_count'         => $stats->dnfs_count,
            ],
            [ 'id' => $driver_id ]
        );
    }
}
