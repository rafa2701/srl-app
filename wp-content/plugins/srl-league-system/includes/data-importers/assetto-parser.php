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
function srl_parse_assetto_corsa_results( $json_content, $session_id, $event_id ) {
    global $wpdb;
    $data = json_decode( $json_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return ['status' => 'error', 'message' => 'Error al decodificar el JSON: ' . json_last_error_msg()];
    }

    // --- OBTENER REGLAS DE PUNTUACIÓN ---
    $championship_id = get_post_meta( $event_id, '_srl_parent_championship', true );
    $scoring_rules_json = get_post_meta( $championship_id, '_srl_scoring_rules', true );
    $scoring_rules = json_decode( $scoring_rules_json, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;

    // --- Determinar la vuelta rápida válida ---
    $fastest_lap_time = PHP_INT_MAX;
    $fastest_lap_driver_guid = null;
    if ( ! empty( $data['Laps'] ) ) {
        foreach ( $data['Laps'] as $lap ) {
            $guid = $lap['DriverGuid'] ?? $lap['DriverID'] ?? null;
            if ( isset($lap['Cuts']) && $lap['Cuts'] == 0 && isset($lap['LapTime']) && $lap['LapTime'] > 0 && $lap['LapTime'] < $fastest_lap_time ) {
                $fastest_lap_time = $lap['LapTime'];
                $fastest_lap_driver_guid = $guid;
            }
        }
    }

    // --- Determinar el Poleman (GridPosition más bajo, usualmente 1 o 0) ---
    $pole_driver_guid = null;
    $min_grid_pos = PHP_INT_MAX;
    if ( ! empty( $data['Result'] ) ) {
        foreach ( $data['Result'] as $result_item ) {
            if ( isset($result_item['GridPosition']) && $result_item['GridPosition'] >= 0 && $result_item['GridPosition'] < $min_grid_pos ) {
                $min_grid_pos = $result_item['GridPosition'];
                $pole_driver_guid = $result_item['DriverGuid'] ?? $result_item['DriverID'] ?? null;
            }
        }
    }

    // Obtener la regla de % de vueltas
    $min_lap_percentage = $scoring_rules['rules']['min_lap_percentage_for_points'] ?? 0;
     // Encontrar las vueltas del ganador
    $winner_laps = 0;
    if (!empty($data['Result'])) {
        $winner_guid = $data['Result'][0]['DriverGuid'] ?? $data['Result'][0]['DriverID'] ?? null;
        $winner_laps = count( array_filter($data['Laps'], function($lap) use ($winner_guid) {
             $guid = $lap['DriverGuid'] ?? $lap['DriverID'] ?? null;
             return $guid === $winner_guid;
        }) );
    }

    if ( empty( $data['Result'] ) ) {
        return ['status' => 'error', 'message' => 'El archivo JSON no contiene la sección "Result".'];
    }

    // --- Lógica avanzada de carrera (Laps Analysis) ---
    $lap_leaders = [];
    $driver_positions_at_lap = [];
    if ( ! empty( $data['Laps'] ) ) {
        // Agrupar laps por driver y ordenarlas por Timestamp
        $laps_by_driver = [];
        foreach ( $data['Laps'] as $lap ) {
            $guid = $lap['DriverGuid'] ?? $lap['DriverID'] ?? null;
            if ( $guid ) {
                $laps_by_driver[$guid][] = $lap;
            }
        }
        foreach ( $laps_by_driver as $guid => &$laps ) {
            usort( $laps, function($a, $b) { return $a['Timestamp'] <=> $b['Timestamp']; } );
        }

        // Determinar líder de cada vuelta y posiciones
        $max_laps = 0;
        foreach ( $laps_by_driver as $guid => $laps ) {
            $max_laps = max($max_laps, count($laps));
        }

        for ( $i = 0; $i < $max_laps; $i++ ) {
            $lap_ends = [];
            foreach ( $laps_by_driver as $guid => $laps ) {
                if ( isset($laps[$i]) ) {
                    $lap_ends[$guid] = $laps[$i]['Timestamp'];
                }
            }
            asort($lap_ends);
            $ordered_drivers = array_keys($lap_ends);
            if ( !empty($ordered_drivers) ) {
                $lap_leaders[$i+1] = $ordered_drivers[0];
                foreach ( $ordered_drivers as $idx => $guid ) {
                    $driver_positions_at_lap[$guid][$i+1] = $idx + 1;
                }
            }
        }
    }

    $processed_drivers = [];
    $position = 1;
    foreach ( $data['Result'] as $result_item ) {
        $driver_guid = $result_item['DriverGuid'] ?? $result_item['DriverID'] ?? null;
        $driver_name = $result_item['DriverName'] ?? '';

        if (!$driver_guid && isset($result_item['CarId'], $data['Cars'])) {
            foreach($data['Cars'] as $car) {
                if ($car['CarId'] == $result_item['CarId']) {
                    $driver_guid = $car['Driver']['Guid'] ?? null;
                    $driver_name = $car['Driver']['Name'] ?? $driver_name;
                    break;
                }
            }
        }

        $driver_id = srl_get_or_create_driver( $driver_guid, $driver_name );
        if ( ! $driver_id ) continue;
        
        $processed_drivers[] = $driver_id;

        $laps_completed = isset($laps_by_driver[$driver_guid]) ? count($laps_by_driver[$driver_guid]) : 0;

        // Lógica para Grand Slam y Closer (Solo AC)
        $led_every_lap = 0;
        $late_overtakes = 0;

        if ( ! empty($lap_leaders) ) {
            // 1. Led Every Lap?
            $led_count = 0;
            foreach ($lap_leaders as $lnum => $lguid) {
                if ($lguid === $driver_guid) $led_count++;
            }
            if ($led_count === $winner_laps && $position === 1) {
                $led_every_lap = 1;
            }

            // 2. Overtakes in final 10% (The Closer)
            if ($winner_laps > 5) {
                $threshold_lap = floor($winner_laps * 0.9);
                $pos_at_threshold = $driver_positions_at_lap[$driver_guid][$threshold_lap] ?? 0;
                if ($pos_at_threshold > 0 && $position < $pos_at_threshold) {
                    $late_overtakes = $pos_at_threshold - $position;
                }
            }
        }

        $is_dnf = ( $result_item['TotalTime'] == 0 );
        $has_pole = ( $driver_guid === $pole_driver_guid && $pole_driver_guid !== null );
        $has_fastest_lap = ( $driver_guid === $fastest_lap_driver_guid && $fastest_lap_driver_guid !== null );

        // --- LÓGICA DE PUNTOS CON REGLA DE DNF ---
        $points_awarded = 0;
        $can_score = !$is_dnf;
        if ($is_dnf && $min_lap_percentage > 0 && $winner_laps > 0) {
            $lap_percentage = ($laps_completed / $winner_laps) * 100;
            if ($lap_percentage >= $min_lap_percentage) {
                $can_score = true;
            }
        }
        
        if ($can_score) {
            $points_awarded += ($points_map[ $position ] ?? 0);
            if ($has_pole) $points_awarded += $bonus_pole;
            if ($has_fastest_lap) $points_awarded += $bonus_fastest_lap;
        }

        $result_data = [
            'session_id'      => $session_id,
            'driver_id'       => $driver_id,
            'team_name'       => $result_item['CarModel'] ?? '',
            'car_model'       => $result_item['CarModel'] ?? '',
            'position'        => $position,
            'grid_position'   => $result_item['GridPosition'] ?? 0,
            'best_lap_time'   => $result_item['BestLap'] ?? 0,
            'total_time'      => $result_item['TotalTime'] ?? 0,
            'has_pole'        => $has_pole ? 1 : 0,
            'has_fastest_lap' => $has_fastest_lap ? 1 : 0,
            'laps_completed'  => $laps_completed,
            'is_dnf'          => $is_dnf ? 1 : 0,
            'points_awarded'  => $points_awarded,
            'led_every_lap'   => $led_every_lap,
            'late_overtakes'  => $late_overtakes,
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
            SUM(CASE WHEN r.position = 1 AND r.is_disqualified = 0 AND r.is_nc = 0 THEN 1 ELSE 0 END) as victories_count,
            SUM(CASE WHEN r.position <= 3 AND r.is_disqualified = 0 AND r.is_nc = 0 THEN 1 ELSE 0 END) as podiums_count,
            SUM(CASE WHEN r.position <= 5 AND r.is_disqualified = 0 AND r.is_nc = 0 THEN 1 ELSE 0 END) as top_5_count,
            SUM(CASE WHEN r.position <= 10 AND r.is_disqualified = 0 AND r.is_nc = 0 THEN 1 ELSE 0 END) as top_10_count,
            SUM(r.has_pole) as poles_count,
            SUM(r.has_fastest_lap) as fastest_laps_count,
            SUM(CASE WHEN r.position = 1 AND r.has_pole = 1 AND r.has_fastest_lap = 1 AND r.is_disqualified = 0 AND r.is_nc = 0 THEN 1 ELSE 0 END) as hat_tricks_count,
            SUM(r.is_dnf) as dnfs_count,
            SUM(r.is_disqualified) as dq_count
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
                'hat_tricks_count'   => $stats->hat_tricks_count,
                'dnfs_count'         => $stats->dnfs_count,
                'dq_count'           => $stats->dq_count,
            ],
            [ 'id' => $driver_id ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ],
            [ '%d' ]
        );

        // Actualizar hitos del piloto
        SRL_Achievement_Manager::calculate_streaks( $driver_id );
        SRL_Achievement_Manager::calculate_efficiency( $driver_id );
    }
}