<?php
/**
 * Archivo para funciones centrales del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;



/**
 * FUNCIÓN CENTRAL: Calcula la clasificación final de un campeonato con desempates.
 *
 * @param int $championship_id El ID del post del campeonato.
 * @return array La clasificación final ordenada, con driver_id como clave.
 */
function srl_calculate_championship_standings( $championship_id ) {
    global $wpdb;

    $scoring_rules_json = get_post_meta( $championship_id, '_srl_scoring_rules', true );
    $scoring_rules = json_decode( $scoring_rules_json, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;

    $event_ids = get_posts(['post_type' => 'srl_event', 'meta_key' => '_srl_parent_championship', 'meta_value' => $championship_id, 'posts_per_page' => -1, 'fields' => 'ids']);
    if ( empty($event_ids) ) return [];

    $event_ids_placeholder = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
    $results = $wpdb->get_results( $wpdb->prepare( "SELECT d.id as driver_id, d.full_name, d.steam_id, r.position, r.has_pole, r.has_fastest_lap, r.points_awarded, r.is_disqualified, r.is_nc FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE s.event_id IN ($event_ids_placeholder) AND s.session_type = 'Race'", $event_ids ) );
    
    if ( empty( $results ) ) return [];

    $standings = [];
    foreach ( $results as $result ) {
        $driver_id = $result->driver_id;
        if ( ! isset( $standings[ $driver_id ] ) ) {
            $standings[ $driver_id ] = [ 'name' => $result->full_name, 'steam_id' => $result->steam_id, 'points' => 0, 'races' => 0, 'wins' => 0, 'podiums' => 0, 'poles' => 0, 'fastest_laps' => 0, 'positions' => array_fill(1, 10, 0) ];
        }

        $standings[ $driver_id ]['points'] += $result->points_awarded;
        $standings[ $driver_id ]['races']++;

        if ( $result->has_pole ) { $standings[ $driver_id ]['poles']++; }
        if ( $result->has_fastest_lap ) { $standings[ $driver_id ]['fastest_laps']++; }

        // Stats only for classified and non-disqualified drivers
        if ( !$result->is_disqualified && !$result->is_nc ) {
            if ( $result->position == 1 ) $standings[ $driver_id ]['wins']++;
            if ( $result->position <= 3 ) $standings[ $driver_id ]['podiums']++;
            if ( $result->position <= 10 ) $standings[ $driver_id ]['positions'][$result->position]++;
        }
    }

    uasort( $standings, function( $a, $b ) {
        if ( $a['points'] != $b['points'] ) {
            return $b['points'] <=> $a['points'];
        }
        for ($i = 1; $i <= 10; $i++) {
            if ($a['positions'][$i] != $b['positions'][$i]) {
                return $b['positions'][$i] <=> $a['positions'][$i];
            }
        }
        return 0;
    });

    return $standings;
}

/**
 * Escribe un mensaje en un archivo de log personalizado dentro de la carpeta /logs/ del plugin.
 *
 * @param string $message El mensaje a registrar.
 * @param string $log_file_name El nombre del archivo de log (ej: 'recalculate.log').
 */
function srl_write_to_log( $message, $log_file_name = 'srl-main.log' ) {
    if ( ! defined( 'SRL_PLUGIN_PATH' ) ) return;
    
    $log_dir = SRL_PLUGIN_PATH . 'logs';
    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }

    $log_file = $log_dir . '/' . sanitize_file_name( $log_file_name );
    $timestamp = date("Y-m-d H:i:s");
    $formatted_message = "[" . $timestamp . "] " . print_r($message, true) . "\n";
    
    file_put_contents( $log_file, $formatted_message, FILE_APPEND );
}

/**
 * RECALCULA POSICIONES Y PUNTOS DE UNA SESIÓN.
 * Útil tras editar penalizaciones o DQs.
 */
function srl_recalculate_session_results( $session_id ) {
    global $wpdb;

    // 1. Obtener todos los resultados de la sesión
    $results = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}srl_results WHERE session_id = %d", $session_id) );
    if ( empty($results) ) return;

    // 2. Obtener reglas de puntuación del campeonato padre
    $event_id = $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}srl_sessions WHERE id = %d", $session_id));
    $championship_id = get_post_meta( $event_id, '_srl_parent_championship', true );
    $scoring_rules_json = get_post_meta( $championship_id, '_srl_scoring_rules', true );
    $scoring_rules = json_decode( $scoring_rules_json, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;
    $min_lap_percentage = $scoring_rules['rules']['min_lap_percentage_for_points'] ?? 0;

    // 3. Ordenar resultados actuales para determinar vueltas del ganador
    $temp_results = $results;
    usort($temp_results, function($a, $b) {
        if ($a->is_disqualified != $b->is_disqualified) return $a->is_disqualified <=> $b->is_disqualified;
        if ($a->is_dnf != $b->is_dnf) return $a->is_dnf <=> $b->is_dnf;
        if ($a->is_nc != $b->is_nc) return $a->is_nc <=> $b->is_nc;
        return $a->position <=> $b->position;
    });

    $winner_laps = !empty($temp_results) ? $temp_results[0]->laps_completed : 0;

    // 4. Actualizar puntos manteniendo posiciones actuales (que pudieron ser arrastradas)
    $affected_drivers = [];
    foreach ($results as $r) {
        $points = 0;
        $affected_drivers[] = $r->driver_id;

        // Auto-NC logic based on percentage if not manually forced
        $is_nc = $r->is_nc;
        if (!$r->is_nc_forced && !$r->is_disqualified && $min_lap_percentage > 0 && $winner_laps > 0) {
            $lap_percentage = ($r->laps_completed / $winner_laps) * 100;
            if ($lap_percentage < $min_lap_percentage) {
                $is_nc = 1;
                $wpdb->update($wpdb->prefix . 'srl_results', ['is_nc' => 1], ['id' => $r->id]);
            } else {
                $is_nc = 0;
                $wpdb->update($wpdb->prefix . 'srl_results', ['is_nc' => 0], ['id' => $r->id]);
            }
        }

        if (!$r->is_disqualified && !$is_nc) {
            // El DNF ahora puede puntuar si cumplió el % (ya manejado arriba al no ser NC)
            $points += ($points_map[$r->position] ?? 0);
            if ($r->has_pole) $points += $bonus_pole;
            if ($r->has_fastest_lap) $points += $bonus_fastest_lap;
        }

        $wpdb->update(
            $wpdb->prefix . 'srl_results',
            [ 'points_awarded' => $points ],
            [ 'id' => $r->id ]
        );
    }

    // 9. Recalcular estadísticas globales de pilotos involucrados
    foreach (array_unique($affected_drivers) as $driver_id) {
        srl_update_driver_global_stats($driver_id);
    }
}
