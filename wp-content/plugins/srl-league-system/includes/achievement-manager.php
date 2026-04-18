<?php
/**
 * Clase para gestionar los logros (Hitos) de los pilotos.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

class SRL_Achievement_Manager {

    /**
     * Calcula y guarda las rachas de un piloto.
     */
    public static function calculate_streaks( $driver_id ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.position, r.points_awarded, r.is_disqualified, r.is_nc, r.is_dnf, r.laps_completed, r.session_id, s.event_id
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            JOIN {$wpdb->prefix}posts p ON s.event_id = p.ID
            WHERE r.driver_id = %d AND s.session_type = 'Race'
            ORDER BY p.post_date ASC, s.imported_at ASC
        ", $driver_id ) );

        if ( empty( $results ) ) return;

        $max_win_streak = 0; $current_win_streak = 0;
        $max_podium_streak = 0; $current_podium_streak = 0;
        $max_points_streak = 0; $current_points_streak = 0;
        $max_iron_man = 0; $current_iron_man = 0;
        $max_swiss_watch = 0; $current_swiss_watch = 0;

        // Pre-fetch winner laps for all relevant sessions to avoid N+1 queries
        $session_ids = array_unique(array_column($results, 'session_id'));
        $winner_laps_map = [];
        if (!empty($session_ids)) {
            $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
            $winners = $wpdb->get_results($wpdb->prepare("SELECT session_id, laps_completed FROM {$wpdb->prefix}srl_results WHERE session_id IN ($placeholders) AND position = 1", $session_ids));
            foreach ($winners as $w) {
                $winner_laps_map[$w->session_id] = $w->laps_completed;
            }
        }

        foreach ( $results as $res ) {
            $is_win = ( $res->position == 1 && ! $res->is_disqualified && ! $res->is_nc );
            $is_podium = ( $res->position <= 3 && ! $res->is_disqualified && ! $res->is_nc );
            $is_points = ( $res->points_awarded > 0 );
            $is_finished = ( ! $res->is_dnf );

            // Swiss Watch Calculation (Lead Lap)
            $winner_laps = $winner_laps_map[$res->session_id] ?? 0;
            $on_lead_lap = ( $winner_laps > 0 && $res->laps_completed >= $winner_laps );

            // Update streaks
            $current_win_streak = $is_win ? $current_win_streak + 1 : 0;
            if ($current_win_streak > $max_win_streak) $max_win_streak = $current_win_streak;

            $current_podium_streak = $is_podium ? $current_podium_streak + 1 : 0;
            if ($current_podium_streak > $max_podium_streak) $max_podium_streak = $current_podium_streak;

            $current_points_streak = $is_points ? $current_points_streak + 1 : 0;
            if ($current_points_streak > $max_points_streak) $max_points_streak = $current_points_streak;

            $current_iron_man = $is_finished ? $current_iron_man + 1 : 0;
            if ($current_iron_man > $max_iron_man) $max_iron_man = $current_iron_man;

            $current_swiss_watch = $on_lead_lap ? $current_swiss_watch + 1 : 0;
            if ($current_swiss_watch > $max_swiss_watch) $max_swiss_watch = $current_swiss_watch;
        }

        self::save_achievement( $driver_id, 'max_win_streak', $max_win_streak );
        self::save_achievement( $driver_id, 'max_podium_streak', $max_podium_streak );
        self::save_achievement( $driver_id, 'point_stalker', $max_points_streak );
        self::save_achievement( $driver_id, 'iron_man', $max_iron_man );
        self::save_achievement( $driver_id, 'swiss_watch', $max_swiss_watch );
    }

    /**
     * Calcula y guarda la eficiencia y promedios de un piloto.
     */
    public static function calculate_efficiency( $driver_id ) {
        global $wpdb;

        $stats = $wpdb->get_row( $wpdb->prepare( "
            SELECT
                COUNT(*) as total_races,
                SUM(CASE WHEN position = 1 AND is_disqualified = 0 AND is_nc = 0 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN position <= 3 AND is_disqualified = 0 AND is_nc = 0 THEN 1 ELSE 0 END) as podiums,
                SUM(has_pole) as poles,
                AVG(grid_position) as avg_grid,
                AVG(position) as avg_finish
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE r.driver_id = %d AND s.session_type = 'Race' AND r.grid_position > 0
        ", $driver_id ) );

        if ( ! $stats || $stats->total_races < 10 ) {
            return;
        }

        $win_eff = ( $stats->wins / $stats->total_races ) * 100;
        $podium_eff = ( $stats->podiums / $stats->total_races ) * 100;
        $pole_eff = ( $stats->poles / $stats->total_races ) * 100;
        $sunday_diff = $stats->avg_grid - $stats->avg_finish;

        self::save_achievement( $driver_id, 'win_efficiency', round( $win_eff, 2 ) );
        self::save_achievement( $driver_id, 'podium_efficiency', round( $podium_eff, 2 ) );
        self::save_achievement( $driver_id, 'pole_efficiency', round( $pole_eff, 2 ) );
        self::save_achievement( $driver_id, 'qualifying_ace', round( $stats->avg_grid, 2 ) );
        self::save_achievement( $driver_id, 'sunday_driver', round( $sunday_diff, 2 ) );
        self::save_achievement( $driver_id, 'old_guard', $stats->total_races );
    }

    /**
     * Identifica hitos de parrilla y especiales para un evento.
     */
    public static function calculate_grid_heroics( $event_id ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.*
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

            // Grand Slam Check
            if ( $res->position == 1 && $res->has_pole && $res->has_fastest_lap && $res->led_every_lap ) {
                self::save_achievement( $res->driver_id, 'grand_slam', 1, $event_id );
            }

            // The Closer
            if ( $res->late_overtakes > 0 ) {
                self::update_best_achievement( $res->driver_id, 'closer', $res->late_overtakes, $event_id, 'max' );
            }

            // Giant Killer (calculated from 2026 onwards)
            $event_date = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM {$wpdb->posts} WHERE ID = %d", $event_id));
            if ( $event_date && strtotime($event_date) >= strtotime('2026-01-01') ) {
                $driver_wins = $wpdb->get_var($wpdb->prepare("SELECT victories_count FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $res->driver_id));
                // Find drivers finished behind him
                $beaten_drivers = $wpdb->get_results($wpdb->prepare("SELECT driver_id FROM {$wpdb->prefix}srl_results WHERE session_id = %d AND position > %d", $res->session_id, $res->position));
                foreach ($beaten_drivers as $beaten) {
                    $beaten_wins = $wpdb->get_var($wpdb->prepare("SELECT victories_count FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $beaten->driver_id));
                    if ($beaten_wins >= ($driver_wins * 2) && $beaten_wins >= 10) {
                        self::save_achievement($res->driver_id, 'giant_killer', 1, $event_id, null, $beaten->driver_id);
                    }
                }
            }
        }
    }

    /**
     * Calcula récords de tiempo para un evento.
     */
    public static function calculate_timing_records( $event_id ) {
        global $wpdb;

        // Smallest Margin of Victory (Nerves of Steel / Photo Finish)
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
                self::update_best_achievement( $top_two[0]->driver_id, 'nerves_of_steel', $margin, $event_id, 'min', $top_two[1]->driver_id );
            }
        }

        // One-Lap Wonder (Largest Pole Gap)
        $qualy_top_two = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.driver_id, r.best_lap_time as lap_time
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE s.event_id = %d AND s.session_type = 'Qualifying'
            ORDER BY r.position ASC
            LIMIT 2
        ", $event_id ) );

        if ( count($qualy_top_two) == 2 && $qualy_top_two[0]->lap_time > 0 && $qualy_top_two[1]->lap_time > 0 ) {
            $gap = $qualy_top_two[1]->lap_time - $qualy_top_two[0]->lap_time;
            self::update_best_achievement( $qualy_top_two[0]->driver_id, 'one_lap_wonder', $gap, $event_id, 'max' );
        }
    }

    /**
     * Calcula hitos a nivel de campeonato.
     */
    public static function calculate_championship_achievements( $championship_id ) {
        global $wpdb;

        $event_ids = get_posts(['post_type' => 'srl_event', 'meta_key' => '_srl_parent_championship', 'meta_value' => $championship_id, 'posts_per_page' => -1, 'fields' => 'ids']);
        if ( empty($event_ids) ) return;
        $event_ids_str = implode(',', $event_ids);

        // Speed Demon (Most fastest laps in a season)
        $speed_demons = $wpdb->get_results( "
            SELECT driver_id, SUM(has_fastest_lap) as fl_count
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE s.event_id IN ($event_ids_str) AND s.session_type = 'Race'
            GROUP BY driver_id
            ORDER BY fl_count DESC
        " );

        if ( !empty($speed_demons) && $speed_demons[0]->fl_count > 0 ) {
            self::save_achievement( $speed_demons[0]->driver_id, 'speed_demon', $speed_demons[0]->fl_count, null, $championship_id );
        }

        // Clean Sweep (Win every race)
        $total_events = count($event_ids);
        $clean_sweepers = $wpdb->get_results( "
            SELECT driver_id, COUNT(*) as wins
            FROM {$wpdb->prefix}srl_results r
            JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
            WHERE s.event_id IN ($event_ids_str) AND s.session_type = 'Race' AND r.position = 1
            GROUP BY driver_id
            HAVING wins = $total_events
        " );

        foreach ($clean_sweepers as $sweeper) {
            self::save_achievement($sweeper->driver_id, 'clean_sweep', 1, null, $championship_id);
        }
    }

    /**
     * Sincroniza todos los hitos para todos los pilotos y eventos.
     */
    public static function sync_all_achievements() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}srl_achievements" );

        $driver_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}srl_drivers" );
        foreach ( $driver_ids as $driver_id ) {
            self::calculate_streaks( $driver_id );
            self::calculate_efficiency( $driver_id );
        }

        $event_ids = $wpdb->get_col( "SELECT DISTINCT event_id FROM {$wpdb->prefix}srl_sessions WHERE session_type = 'Race'" );
        foreach ( $event_ids as $event_id ) {
            self::calculate_grid_heroics( $event_id );
            self::calculate_timing_records( $event_id );
        }

        $championship_ids = get_posts(['post_type' => 'srl_championship', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ( $championship_ids as $ch_id ) {
            self::calculate_championship_achievements( $ch_id );
        }
    }

    public static function get_achievements_leaderboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'srl_achievements';
        $drivers_table = $wpdb->prefix . 'srl_drivers';

        $keys = self::get_achievement_keys();
        $leaderboard = [];

        foreach ( $keys as $key => $label ) {
            if ( ! self::is_achievement_enabled( $key ) ) continue;
            if ( $key === 'giant_killer' ) continue;

            $order = 'DESC';
            if ( in_array( $key, ['nerves_of_steel', 'qualifying_ace'] ) ) { $order = 'ASC'; }

            $results = $wpdb->get_results( $wpdb->prepare( "
                SELECT a.*, d.full_name, (SELECT full_name FROM $drivers_table WHERE id = a.opponent_id) as opponent_name,
                (SELECT post_title FROM {$wpdb->posts} WHERE ID = a.championship_id) as championship_name
                FROM $table a
                JOIN $drivers_table d ON a.driver_id = d.id
                WHERE a.achievement_key = %s AND a.record_value > 0
                ORDER BY CAST(a.record_value AS DECIMAL(10,3)) $order
                LIMIT 5
            ", $key ) );

            if ( ! empty( $results ) ) {
                $leaderboard[$key] = [ 'label' => $label, 'records' => $results ];
            }
        }
        return $leaderboard;
    }

    private static function update_best_achievement( $driver_id, $key, $value, $event_id = null, $mode = 'max', $opponent_id = null, $championship_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'srl_achievements';
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT id, record_value FROM $table WHERE driver_id = %d AND achievement_key = %s", $driver_id, $key ) );
        $is_better = false;
        if ( ! $current ) { $is_better = true; } else {
            if ( $mode === 'max' && $value > $current->record_value ) $is_better = true;
            if ( $mode === 'min' && $value < $current->record_value ) $is_better = true;
        }
        if ( $is_better ) { self::save_achievement( $driver_id, $key, $value, $event_id, $championship_id, $opponent_id ); }
    }

    private static function save_achievement( $driver_id, $key, $value, $event_id = null, $championship_id = null, $opponent_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'srl_achievements';
        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE driver_id = %d AND achievement_key = %s", $driver_id, $key ) );
        // Si es por campeonato, permitimos múltiples (uno por campeonato)
        if ($championship_id && $key !== 'speed_demon') {
            $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE driver_id = %d AND achievement_key = %s AND championship_id = %d", $driver_id, $key, $championship_id ) );
        }
        $data = [ 'achievement_key' => $key, 'driver_id' => $driver_id, 'record_value' => $value, 'event_id' => $event_id, 'championship_id' => $championship_id, 'opponent_id' => $opponent_id, 'updated_at' => current_time( 'mysql' ) ];
        if ( $id ) { $wpdb->update( $table, $data, ['id' => $id] ); } else { $wpdb->insert( $table, $data ); }
    }

    private static function delete_achievement( $driver_id, $key ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'srl_achievements', [ 'driver_id' => $driver_id, 'achievement_key' => $key ] );
    }

    public static function get_achievement_keys() {
        $defaults = [
            'max_win_streak'             => 'Racha de Victorias',
            'max_podium_streak'          => 'Racha de Podios',
            'point_stalker'              => 'Cazapuntos (Racha de Puntos)',
            'win_efficiency'             => 'Efectividad de Victorias (%)',
            'podium_efficiency'          => 'Efectividad de Podios (%)',
            'pole_efficiency'            => 'Efectividad de Poles (%)',
            'iron_man'                   => 'Iron Man (Carreras sin DNF)',
            'swiss_watch'                => 'Reloj Suizo (Vueltas en el líder)',
            'grand_slam'                 => 'Grand Slam (Pole, Win, FL, Led all)',
            'qualifying_ace'             => 'As de la Clasificación (Parrilla Media)',
            'sunday_driver'              => 'Especialista en Carrera (Remontada Media)',
            'win_from_farthest_back'     => 'Victoria desde más atrás',
            'hard_charger'               => 'Remontada histórica (Posiciones)',
            'nerves_of_steel'            => 'Nervios de Acero (Photo Finish)',
            'one_lap_wonder'             => 'Maravilla a una Vuelta (Pole Gap)',
            'speed_demon'                => 'Demonio de la Velocidad (Más FL en una temporada)',
            'clean_sweep'                => 'Pleno (Ganar todo un campeonato)',
            'old_guard'                  => 'La Vieja Guardia (Total de carreras)',
            'giant_killer'               => 'Matagigantes (Derrotar leyendas)',
            'closer'                     => 'The Closer (Adelantamientos al final)',
        ];
        $custom_labels = get_option( 'srl_achievement_labels', [] );
        return array_merge( $defaults, $custom_labels );
    }

    public static function is_achievement_enabled( $key ) {
        $settings = get_option( 'srl_achievement_settings', [] );
        return isset( $settings[$key]['enabled'] ) ? (bool) $settings[$key]['enabled'] : true;
    }
}
