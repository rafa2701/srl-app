<?php
/**
 * Archivo para definir los shortcodes del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

// --- REGISTRO DE SHORTCODES ---
add_shortcode( 'srl_standings', 'srl_render_standings_shortcode' );
add_shortcode( 'srl_driver_profile', 'srl_render_driver_profile_shortcode' );


/**
 * Renderiza la tabla de clasificación de un campeonato.
 *
 * @param array $atts Atributos: 'championship_id', 'profile_page_url'.
 * @return string El HTML de la tabla de clasificación.
 */
function srl_render_standings_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'championship_id' => 0,
        'profile_page_url' => '/driver-profile/', // URL base para los perfiles de piloto
    ], $atts, 'srl_standings' );

    $championship_id = intval( $atts['championship_id'] );
    if ( ! $championship_id ) {
        return '<p>Error: Debes especificar un ID de campeonato. Ej: [srl_standings championship_id="1"]</p>';
    }

    global $wpdb;

    $championship = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_championships WHERE id = %d", $championship_id ) );
    if ( ! $championship ) return '<p>Error: Campeonato no encontrado.</p>';
    
    $scoring_rules = json_decode( $championship->scoring_rules, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;

    $results = $wpdb->get_results( $wpdb->prepare( "
        SELECT d.id as driver_id, d.full_name, d.steam_id, r.position, r.has_pole, r.has_fastest_lap
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        JOIN {$wpdb->prefix}srl_events e ON s.event_id = e.id
        WHERE e.championship_id = %d AND s.session_type = 'Race'
    ", $championship_id ) );

    if ( empty( $results ) ) return '<p>Aún no hay resultados para este campeonato.</p>';

    $standings = [];
    foreach ( $results as $result ) {
        $driver_id = $result->driver_id;
        if ( ! isset( $standings[ $driver_id ] ) ) {
            $standings[ $driver_id ] = [ 'name' => $result->full_name, 'steam_id' => $result->steam_id, 'points' => 0, 'races' => 0, 'wins' => 0 ];
        }
        $standings[ $driver_id ]['points'] += ($points_map[ $result->position ] ?? 0);
        if ( $result->has_pole ) $standings[ $driver_id ]['points'] += $bonus_pole;
        if ( $result->has_fastest_lap ) $standings[ $driver_id ]['points'] += $bonus_fastest_lap;
        $standings[ $driver_id ]['races']++;
        if ( $result->position == 1 ) $standings[ $driver_id ]['wins']++;
    }

    uasort( $standings, fn($a, $b) => $b['points'] <=> $a['points'] ?: $b['wins'] <=> $a['wins'] );

    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Clasificación de Pilotos - <?php echo esc_html( $championship->name ); ?></h2>
        <table class="srl-table">
            <thead><tr><th class="position">Pos</th><th>Piloto</th><th>Victorias</th><th>Carreras</th><th class="points">Puntos</th></tr></thead>
            <tbody>
                <?php $pos = 1; foreach ( $standings as $driver ) : ?>
                <tr>
                    <td class="position"><?php echo $pos++; ?></td>
                    <td>
                        <a href="<?php echo esc_url( rtrim($atts['profile_page_url'], '/') . '/?steam_id=' . $driver['steam_id'] ); ?>">
                            <?php echo esc_html( $driver['name'] ); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( $driver['wins'] ); ?></td>
                    <td><?php echo esc_html( $driver['races'] ); ?></td>
                    <td class="points"><?php echo esc_html( $driver['points'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza el perfil y palmarés de un piloto.
 *
 * @param array $atts Atributos: 'steam_id'.
 * @return string El HTML del perfil del piloto.
 */
function srl_render_driver_profile_shortcode( $atts ) {
    // Obtener steam_id del shortcode o de la URL
    $atts = shortcode_atts( [ 'steam_id' => '' ], $atts, 'srl_driver_profile' );
    $steam_id = ! empty( $atts['steam_id'] ) ? $atts['steam_id'] : ( $_GET['steam_id'] ?? '' );

    if ( empty( $steam_id ) ) {
        return '<p>Error: No se ha especificado un piloto.</p>';
    }

    global $wpdb;
    $steam_id = sanitize_text_field( $steam_id );

    // --- 1. Obtener datos del piloto y estadísticas globales pre-calculadas ---
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE steam_id = %s", $steam_id ) );
    if ( ! $driver ) return '<p>Piloto no encontrado.</p>';

    // --- 2. Calcular estadísticas detalladas (promedios, porcentajes) ---
    $all_races = $wpdb->get_results( $wpdb->prepare( "
        SELECT r.position, r.grid_position
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        WHERE r.driver_id = %d AND s.session_type = 'Race' AND r.is_dnf = 0
    ", $driver->id ) );

    $total_races = count($all_races);
    $stats = [
        'win_percentage' => $total_races > 0 ? ( $driver->victories_count / $total_races ) * 100 : 0,
        'pole_percentage' => $total_races > 0 ? ( $driver->poles_count / $total_races ) * 100 : 0,
        'avg_grid' => $total_races > 0 ? array_sum( array_column( $all_races, 'grid_position' ) ) / $total_races : 0,
        'avg_finish' => $total_races > 0 ? array_sum( array_column( $all_races, 'position' ) ) / $total_races : 0,
    ];
    
    ob_start();
    ?>
    <div class="srl-app-container srl-driver-profile">
        <h1>Palmarés de <?php echo esc_html( $driver->full_name ); ?></h1>
        <p class="srl-steam-id">SteamID: <?php echo esc_html( $driver->steam_id ); ?></p>
        
        <h2>Estadísticas Globales</h2>
        <div class="srl-stats-grid">
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo $driver->victories_count; ?></div>
                <div class="stat-label">Victorias</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo $driver->podiums_count; ?></div>
                <div class="stat-label">Podios</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo $driver->poles_count; ?></div>
                <div class="stat-label">Poles</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo $driver->fastest_laps_count; ?></div>
                <div class="stat-label">Vueltas Rápidas</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo number_format( $stats['win_percentage'], 2 ); ?>%</div>
                <div class="stat-label">% Victorias</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo number_format( $stats['pole_percentage'], 2 ); ?>%</div>
                <div class="stat-label">% Poles</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo number_format( $stats['avg_grid'], 2 ); ?></div>
                <div class="stat-label">Pos. Salida Prom.</div>
            </div>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo number_format( $stats['avg_finish'], 2 ); ?></div>
                <div class="stat-label">Pos. Llegada Prom.</div>
            </div>
             <div class="srl-stat-card">
                <div class="stat-value"><?php echo $driver->dnfs_count; ?></div>
                <div class="stat-label">Abandonos (DNF)</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
