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
    $event_multiplier = floatval( get_post_meta( $event_id, '_srl_event_points_multiplier', true ) ?: 1.0 );
    $scoring_rules_json = get_post_meta( $championship_id, '_srl_scoring_rules', true );
    $scoring_rules = json_decode( $scoring_rules_json, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;
    $bonus_hat_trick = $scoring_rules['bonuses']['hat_trick'] ?? 0;
    $bonus_grand_chelem = $scoring_rules['bonuses']['grand_chelem'] ?? 0;
    $min_lap_percentage = $scoring_rules['rules']['min_lap_percentage_for_points'] ?? 0;

    // 3. Determinar Pole y VR automáticamente (si no están forzados)
    $auto_pole_driver_id = 0;
    $min_qualy_time = PHP_INT_MAX;
    $min_grid_pos = PHP_INT_MAX;

    $auto_vr_driver_id = 0;
    $min_best_lap = PHP_INT_MAX;

    foreach ($results as $r) {
        // Lógica de Pole
        if ($r->qualy_time > 0 && $r->qualy_time < $min_qualy_time) {
            $min_qualy_time = $r->qualy_time;
            $auto_pole_driver_id = $r->driver_id;
        }
        // Fallback grid position if no qualy times
        if ($min_qualy_time === PHP_INT_MAX && $r->grid_position > 0 && $r->grid_position < $min_grid_pos) {
            $min_grid_pos = $r->grid_position;
            $auto_pole_driver_id = $r->driver_id;
        }

        // Lógica de VR
        if ($r->best_lap_time > 0 && $r->best_lap_time < $min_best_lap) {
            $min_best_lap = $r->best_lap_time;
            $auto_vr_driver_id = $r->driver_id;
        }
    }

    // Aplicar lógica automática respetando bloqueos manuales
    foreach ($results as $r) {
        $updates = [];
        if (!$r->is_pole_forced) {
            $new_has_pole = ($r->driver_id === $auto_pole_driver_id) ? 1 : 0;
            if ($new_has_pole !== (int)$r->has_pole) {
                $updates['has_pole'] = $new_has_pole;
                $r->has_pole = $new_has_pole; // Actualizar en el objeto para el cálculo de puntos posterior
            }
        }
        if (!$r->is_fastest_lap_forced) {
            $new_has_vr = ($r->driver_id === $auto_vr_driver_id) ? 1 : 0;
            if ($new_has_vr !== (int)$r->has_fastest_lap) {
                $updates['has_fastest_lap'] = $new_has_vr;
                $r->has_fastest_lap = $new_has_vr; // Actualizar en el objeto
            }
        }
        if (!empty($updates)) {
            $wpdb->update($wpdb->prefix . 'srl_results', $updates, ['id' => $r->id]);
        }
    }

    // 4. Ordenar resultados actuales para determinar vueltas del ganador
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

        // Manual override takes precedence
        if ($r->is_points_manual) {
            $points = $r->manual_points;
            $wpdb->update(
                $wpdb->prefix . 'srl_results',
                [ 'points_awarded' => $points ],
                [ 'id' => $r->id ]
            );
            continue;
        }

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
            $position_points = ($points_map[$r->position] ?? 0);

            // Apply multiplier only to position points
            $points += ($position_points * $event_multiplier);

            // Bonuses are NOT multiplied
            if ($r->has_pole) $points += $bonus_pole;
            if ($r->has_fastest_lap) $points += $bonus_fastest_lap;

            // Hattrick & Grand Chelem logic
            $is_winner = ($r->position == 1);
            $has_hat_trick = ($is_winner && $r->has_pole && $r->has_fastest_lap);
            $has_grand_chelem = ($has_hat_trick && $r->led_every_lap);

            if ($has_hat_trick) $points += $bonus_hat_trick;
            if ($has_grand_chelem) $points += $bonus_grand_chelem;
        }

        // Subtract penalty (penalty can lead to negative points)
        $points -= $r->point_penalty;

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

    // 10. Recalcular hitos del evento
    SRL_Achievement_Manager::calculate_grid_heroics( $event_id );
    SRL_Achievement_Manager::calculate_timing_records( $event_id );
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
            SUM(CASE WHEN r.position = 1 AND r.has_pole = 1 AND r.has_fastest_lap = 1 AND r.led_every_lap = 1 AND r.is_disqualified = 0 AND r.is_nc = 0 THEN 1 ELSE 0 END) as grand_chelems_count,
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
                'grand_chelems_count'=> $stats->grand_chelems_count,
                'dnfs_count'         => $stats->dnfs_count,
                'dq_count'           => $stats->dq_count,
            ],
            [ 'id' => $driver_id ],
            [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ],
            [ '%d' ]
        );

        // Actualizar hitos del piloto
        SRL_Achievement_Manager::calculate_streaks( $driver_id );
        SRL_Achievement_Manager::calculate_efficiency( $driver_id );
    }
}

/**
 * Registra variables de consulta personalizadas para asegurar que WP y plugins de SEO
 * no las eliminen (redirección canónica) al consultar perfiles en la página de pilotos.
 */
function srl_register_query_vars( $vars ) {
    $vars[] = 'steam_id';
    $vars[] = 'driver_id';
    return $vars;
}
add_filter( 'query_vars', 'srl_register_query_vars' );
