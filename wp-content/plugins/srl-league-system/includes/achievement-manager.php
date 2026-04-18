<?php
/**
 * Clase para gestionar los logros (Hitos) de los pilotos.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

class SRL_Achievement_Manager {

    /**
     * Calcula y guarda las rachas de un piloto (victorias, podios, puntos).
     */
    public static function calculate_streaks( $driver_id ) {
        global $wpdb;

        // Obtener resultados de carreras en orden cronológico
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.position, r.points_awarded, r.is_disqualified, r.is_nc, s.imported_at, s.event_id
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            JOIN {$wpdb->prefix}posts p ON s.event_id = p.ID
            WHERE r.driver_id = %d AND s.session_type = 'Race'
            ORDER BY p.post_date ASC, s.imported_at ASC
        ", $driver_id ) );

        if ( empty( $results ) ) return;

        $max_win_streak = 0;
        $current_win_streak = 0;

        $max_podium_streak = 0;
        $current_podium_streak = 0;

        $max_points_streak = 0;
        $current_points_streak = 0;

        foreach ( $results as $res ) {
            $is_win = ( $res->position == 1 && ! $res->is_disqualified && ! $res->is_nc );
            $is_podium = ( $res->position <= 3 && ! $res->is_disqualified && ! $res->is_nc );
            $is_points = ( $res->points_awarded > 0 );

            // Wins
            if ( $is_win ) {
                $current_win_streak++;
                if ( $current_win_streak > $max_win_streak ) $max_win_streak = $current_win_streak;
            } else {
                $current_win_streak = 0;
            }

            // Podiums
            if ( $is_podium ) {
                $current_podium_streak++;
                if ( $current_podium_streak > $max_podium_streak ) $max_podium_streak = $current_podium_streak;
            } else {
                $current_podium_streak = 0;
            }

            // Points
            if ( $is_points ) {
                $current_points_streak++;
                if ( $current_points_streak > $max_points_streak ) $max_points_streak = $current_points_streak;
            } else {
                $current_points_streak = 0;
            }
        }

        self::save_achievement( $driver_id, 'max_win_streak', $max_win_streak );
        self::save_achievement( $driver_id, 'max_podium_streak', $max_podium_streak );
        self::save_achievement( $driver_id, 'max_points_streak', $max_points_streak );
    }

    /**
     * Calcula y guarda la eficiencia de un piloto (Win%, Pole%, Podium%).
     */
    public static function calculate_efficiency( $driver_id ) {
        global $wpdb;

        $stats = $wpdb->get_row( $wpdb->prepare( "
            SELECT
                COUNT(*) as total_races,
                SUM(CASE WHEN position = 1 AND is_disqualified = 0 AND is_nc = 0 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN position <= 3 AND is_disqualified = 0 AND is_nc = 0 THEN 1 ELSE 0 END) as podiums,
                SUM(has_pole) as poles
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE r.driver_id = %d AND s.session_type = 'Race'
        ", $driver_id ) );

        if ( ! $stats || $stats->total_races < 10 ) {
            // Eliminar si existían (o no guardar)
            self::delete_achievement( $driver_id, 'win_efficiency' );
            self::delete_achievement( $driver_id, 'podium_efficiency' );
            self::delete_achievement( $driver_id, 'pole_efficiency' );
            return;
        }

        $win_eff = ( $stats->wins / $stats->total_races ) * 100;
        $podium_eff = ( $stats->podiums / $stats->total_races ) * 100;
        $pole_eff = ( $stats->poles / $stats->total_races ) * 100;

        self::save_achievement( $driver_id, 'win_efficiency', round( $win_eff, 2 ) );
        self::save_achievement( $driver_id, 'podium_efficiency', round( $podium_eff, 2 ) );
        self::save_achievement( $driver_id, 'pole_efficiency', round( $pole_eff, 2 ) );
    }

    /**
     * Identifica hitos de parrilla para un evento (Victoria desde más atrás, Remontada).
     */
    public static function calculate_grid_heroics( $event_id ) {
        global $wpdb;

        // Obtener resultados de la carrera
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.driver_id, r.position, r.grid_position, r.is_disqualified, r.is_nc
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE s.event_id = %d AND s.session_type = 'Race'
        ", $event_id ) );

        if ( empty( $results ) ) return;

        foreach ( $results as $res ) {
            if ( $res->is_disqualified || $res->is_nc ) continue;

            // Victoria desde más atrás
            if ( $res->position == 1 && $res->grid_position > 1 ) {
                self::update_best_achievement( $res->driver_id, 'win_from_farthest_back', $res->grid_position, $event_id, 'max' );
            }

            // Remontada (Hard Charger)
            $gained = $res->grid_position - $res->position;
            if ( $gained > 0 ) {
                self::update_best_achievement( $res->driver_id, 'hard_charger', $gained, $event_id, 'max' );
            }
        }
    }

    /**
     * Calcula récords de tiempo para un evento (Margen de victoria más estrecho).
     */
    public static function calculate_timing_records( $event_id ) {
        global $wpdb;

        // Smallest Margin of Victory
        $top_two = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.driver_id, r.total_time, r.position
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE s.event_id = %d AND s.session_type = 'Race' AND r.is_disqualified = 0 AND r.is_nc = 0
            ORDER BY r.position ASC
            LIMIT 2
        ", $event_id ) );

        if ( count( $top_two ) == 2 ) {
            $margin = abs( $top_two[0]->total_time - $top_two[1]->total_time );
            if ( $margin > 0 ) {
                // El hito se asocia al ganador
                self::update_best_achievement( $top_two[0]->driver_id, 'smallest_margin_of_victory', $margin, $event_id, 'min' );
            }
        }

        // Largest Pole Gap - Placeholder / WIP as requested
        // self::update_best_achievement( $poleman_id, 'largest_pole_gap', $gap, $event_id, 'max' );
    }

    /**
     * Sincroniza todos los hitos para todos los pilotos y eventos.
     */
    public static function sync_all_achievements() {
        global $wpdb;

        // Limpiar tabla antes de re-sincronizar
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}srl_achievements" );

        // 1. Rachas y Eficiencia (por piloto)
        $driver_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}srl_drivers" );
        foreach ( $driver_ids as $driver_id ) {
            self::calculate_streaks( $driver_id );
            self::calculate_efficiency( $driver_id );
        }

        // 2. Hitos de Evento
        $event_ids = $wpdb->get_col( "SELECT DISTINCT event_id FROM {$wpdb->prefix}srl_sessions WHERE session_type = 'Race'" );
        foreach ( $event_ids as $event_id ) {
            self::calculate_grid_heroics( $event_id );
            self::calculate_timing_records( $event_id );
        }
    }

    /**
     * Obtiene el ranking de poseedores de récords para cada hito.
     */
    public static function get_achievements_leaderboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'srl_achievements';
        $drivers_table = $wpdb->prefix . 'srl_drivers';

        $keys = self::get_achievement_keys();
        $leaderboard = [];

        foreach ( $keys as $key => $label ) {
            if ( ! self::is_achievement_enabled( $key ) ) continue;

            // Determinar si buscamos el valor más alto o más bajo
            $order = 'DESC';
            if ( in_array( $key, ['smallest_margin_of_victory'] ) ) {
                $order = 'ASC';
            }

            $results = $wpdb->get_results( $wpdb->prepare( "
                SELECT a.*, d.full_name
                FROM $table a
                JOIN $drivers_table d ON a.driver_id = d.id
                WHERE a.achievement_key = %s AND a.record_value > 0
                ORDER BY CAST(a.record_value AS DECIMAL(10,3)) $order
                LIMIT 5
            ", $key ) );

            if ( ! empty( $results ) ) {
                $leaderboard[$key] = [
                    'label' => $label,
                    'records' => $results
                ];
            }
        }

        return $leaderboard;
    }

    /**
     * Guarda o actualiza un hito si el nuevo valor es mejor que el anterior.
     */
    private static function update_best_achievement( $driver_id, $key, $value, $event_id, $mode = 'max' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'srl_achievements';

        $current = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, record_value FROM $table
            WHERE driver_id = %d AND achievement_key = %s
        ", $driver_id, $key ) );

        $is_better = false;
        if ( ! $current ) {
            $is_better = true;
        } else {
            if ( $mode === 'max' && $value > $current->record_value ) $is_better = true;
            if ( $mode === 'min' && $value < $current->record_value ) $is_better = true;
        }

        if ( $is_better ) {
            self::save_achievement( $driver_id, $key, $value, $event_id );
        }
    }

    private static function save_achievement( $driver_id, $key, $value, $event_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'srl_achievements';

        // Usamos una consulta para ver si ya existe y actualizar o insertar
        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE driver_id = %d AND achievement_key = %s", $driver_id, $key ) );

        $data = [
            'achievement_key' => $key,
            'driver_id'       => $driver_id,
            'record_value'    => $value,
            'event_id'        => $event_id,
            'updated_at'      => current_time( 'mysql' )
        ];

        if ( $id ) {
            $wpdb->update( $table, $data, ['id' => $id] );
        } else {
            $wpdb->insert( $table, $data );
        }
    }

    private static function delete_achievement( $driver_id, $key ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'srl_achievements', [ 'driver_id' => $driver_id, 'achievement_key' => $key ] );
    }

    public static function get_achievement_keys() {
        $defaults = [
            'max_win_streak'             => 'Racha de Victorias',
            'max_podium_streak'          => 'Racha de Podios',
            'max_points_streak'          => 'Racha de Puntos',
            'win_efficiency'             => 'Efectividad de Victorias (%)',
            'podium_efficiency'          => 'Efectividad de Podios (%)',
            'pole_efficiency'            => 'Efectividad de Poles (%)',
            'win_from_farthest_back'     => 'Victoria desde más atrás',
            'hard_charger'               => 'Remontada histórica (Posiciones)',
            'smallest_margin_of_victory' => 'Final más apretado (ms)',
            'largest_pole_gap'           => 'Pole más dominante (ms)',
        ];

        $custom_labels = get_option( 'srl_achievement_labels', [] );
        return array_merge( $defaults, $custom_labels );
    }

    public static function is_achievement_enabled( $key ) {
        $settings = get_option( 'srl_achievement_settings', [] );
        return isset( $settings[$key]['enabled'] ) ? (bool) $settings[$key]['enabled'] : true;
    }
}
