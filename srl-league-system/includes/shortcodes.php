<?php
/**
 * Archivo para definir los shortcodes del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_shortcode( 'srl_standings', 'srl_render_standings_shortcode' );
add_shortcode( 'srl_driver_profile', 'srl_render_driver_profile_shortcode' );

function srl_render_standings_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'championship_id' => 0, 'profile_page_url' => '/driver-profile/', ], $atts, 'srl_standings' );
    $championship_id = intval( $atts['championship_id'] );
    if ( ! $championship_id ) return '<p>Error: Debes especificar un ID de campeonato. Ej: [srl_standings championship_id="1"]</p>';
    
    global $wpdb;
    $championship_post = get_post( $championship_id );
    if ( ! $championship_post || 'srl_championship' !== get_post_type( $championship_post ) ) return '<p>Error: Campeonato no encontrado.</p>';
    
    $scoring_rules_json = get_post_meta( $championship_id, '_srl_scoring_rules', true );
    $scoring_rules = json_decode( $scoring_rules_json, true );
    $points_map = $scoring_rules['points'] ?? [];
    $bonus_pole = $scoring_rules['bonuses']['pole'] ?? 0;
    $bonus_fastest_lap = $scoring_rules['bonuses']['fastest_lap'] ?? 0;
    
    $event_ids = get_posts(['post_type' => 'srl_event', 'post_parent' => $championship_id, 'posts_per_page' => -1, 'fields' => 'ids']);
    if ( empty($event_ids) ) return '<p>Aún no hay resultados para este campeonato.</p>';
    
    $event_ids_placeholder = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
    $query = $wpdb->prepare( "SELECT d.id as driver_id, d.full_name, d.steam_id, r.position, r.has_pole, r.has_fastest_lap FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE s.event_id IN ($event_ids_placeholder) AND s.session_type = 'Race'", $event_ids );
    $results = $wpdb->get_results( $query );
    
    if ( empty( $results ) ) return '<p>Aún no hay resultados para este campeonato.</p>';
    
    $standings = [];
    foreach ( $results as $result ) {
        $driver_id = $result->driver_id;
        if ( ! isset( $standings[ $driver_id ] ) ) $standings[ $driver_id ] = [ 'name' => $result->full_name, 'steam_id' => $result->steam_id, 'points' => 0, 'races' => 0, 'wins' => 0 ];
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
        <h2>Clasificación de Pilotos - <?php echo esc_html( $championship_post->post_title ); ?></h2>
        <table class="srl-table">
            <thead><tr><th class="position">Pos</th><th>Piloto</th><th>Victorias</th><th>Carreras</th><th class="points">Puntos</th></tr></thead>
            <tbody>
                <?php $pos = 1; foreach ( $standings as $driver ) : ?>
                <tr>
                    <td class="position"><?php echo $pos++; ?></td>
                    <td><a href="<?php echo esc_url( rtrim($atts['profile_page_url'], '/') . '/?steam_id=' . $driver['steam_id'] ); ?>"><?php echo esc_html( $driver['name'] ); ?></a></td>
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

function srl_render_driver_profile_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'steam_id' => '' ], $atts, 'srl_driver_profile' );
    $steam_id = ! empty( $atts['steam_id'] ) ? $atts['steam_id'] : ( $_GET['steam_id'] ?? '' );
    if ( empty( $steam_id ) ) return '<p>Error: No se ha especificado un piloto.</p>';

    global $wpdb;
    $steam_id = sanitize_text_field( $steam_id );
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE steam_id = %s", $steam_id ) );
    if ( ! $driver ) return '<p>Piloto no encontrado.</p>';

    $all_race_results = $wpdb->get_results( $wpdb->prepare( "SELECT r.position, r.grid_position, r.is_dnf FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE r.driver_id = %d AND s.session_type = 'Race'", $driver->id ) );
    $total_starts = count( $all_race_results );
    $finished_races = array_filter( $all_race_results, fn($r) => !$r->is_dnf );
    $total_finished = count( $finished_races );
    $stats = [
        'win_percentage' => $total_starts > 0 ? ( $driver->victories_count / $total_starts ) * 100 : 0,
        'pole_percentage' => $total_starts > 0 ? ( $driver->poles_count / $total_starts ) * 100 : 0,
        'avg_grid' => $total_starts > 0 ? array_sum( array_column( $all_race_results, 'grid_position' ) ) / $total_starts : 0,
        'avg_finish' => $total_finished > 0 ? array_sum( array_column( $finished_races, 'position' ) ) / $total_finished : 0,
    ];

    $championship_stats = $wpdb->get_results( $wpdb->prepare("
        SELECT champ.ID as id, champ.post_title as name, COUNT(r.id) as races, SUM(CASE WHEN r.position = 1 THEN 1 ELSE 0 END) as wins, SUM(r.has_pole) as poles, SUM(r.has_fastest_lap) as fastest_laps
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        JOIN {$wpdb->prefix}posts event_post ON s.event_id = event_post.ID AND event_post.post_type = 'srl_event'
        JOIN {$wpdb->prefix}posts champ ON event_post.post_parent = champ.ID AND champ.post_type = 'srl_championship'
        WHERE r.driver_id = %d AND s.session_type = 'Race'
        GROUP BY champ.ID ORDER BY champ.post_title DESC
    ", $driver->id) );
    
    ob_start();
    ?>
    <div class="srl-app-container srl-driver-profile">
        <h1>Palmarés de <?php echo esc_html( $driver->full_name ); ?></h1>
        <p class="srl-steam-id">SteamID: <?php echo esc_html( $driver->steam_id ); ?></p>
        <h2>Estadísticas Globales</h2>
        <div class="srl-stats-grid">
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->victories_count; ?></div><div class="stat-label">Victorias</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->podiums_count; ?></div><div class="stat-label">Podios</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->poles_count; ?></div><div class="stat-label">Poles</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->fastest_laps_count; ?></div><div class="stat-label">Vueltas Rápidas</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['win_percentage'], 2 ); ?>%</div><div class="stat-label">% Victorias</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['pole_percentage'], 2 ); ?>%</div><div class="stat-label">% Poles</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['avg_grid'], 2 ); ?></div><div class="stat-label">Pos. Salida Prom.</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['avg_finish'], 2 ); ?></div><div class="stat-label">Pos. Llegada Prom.</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->dnfs_count; ?></div><div class="stat-label">Abandonos (DNF)</div></div>
        </div>

        <?php if ( ! empty( $championship_stats ) ) : ?>
            <h2 style="margin-top: 40px;">Desempeño por Campeonato</h2>
            <table class="srl-table">
                <thead><tr><th>Campeonato</th><th>Carreras</th><th>Victorias</th><th>Poles</th><th>V. Rápidas</th><th class="points">Puntos Totales</th></tr></thead>
                <tbody>
                    <?php foreach ( $championship_stats as $champ_stat ) : ?>
                        <?php
                        $champ_rules_json = get_post_meta( $champ_stat->id, '_srl_scoring_rules', true );
                        $champ_rules = json_decode( $champ_rules_json, true );
                        $champ_points_map = $champ_rules['points'] ?? [];
                        $champ_bonus_pole = $champ_rules['bonuses']['pole'] ?? 0;
                        $champ_bonus_fl = $champ_rules['bonuses']['fastest_lap'] ?? 0;
                        
                        $champ_results = $wpdb->get_results( $wpdb->prepare("SELECT r.position, r.has_pole, r.has_fastest_lap FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id JOIN {$wpdb->prefix}posts e ON s.event_id = e.ID WHERE r.driver_id = %d AND e.post_parent = %d AND s.session_type = 'Race'", $driver->id, $champ_stat->id) );
                        
                        $total_points = 0;
                        foreach($champ_results as $res) {
                            $total_points += ($champ_points_map[$res->position] ?? 0);
                            if ($res->has_pole) $total_points += $champ_bonus_pole;
                            if ($res->has_fastest_lap) $total_points += $champ_bonus_fl;
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( $champ_stat->name ); ?></td>
                            <td><?php echo esc_html( $champ_stat->races ); ?></td>
                            <td><?php echo esc_html( $champ_stat->wins ); ?></td>
                            <td><?php echo esc_html( $champ_stat->poles ); ?></td>
                            <td><?php echo esc_html( $champ_stat->fastest_laps ); ?></td>
                            <td class="points"><?php echo esc_html( $total_points ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
// Registrar el nuevo shortcode
add_shortcode( 'srl_championship_list', 'srl_render_championship_list_shortcode' );

function srl_render_championship_list_shortcode( $atts ) {
    $championship_posts = get_posts([
        'post_type' => 'srl_championship',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    if ( empty( $championship_posts ) ) {
        return '<p>No hay campeonatos disponibles en este momento.</p>';
    }

    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Campeonatos</h2>
        <div class="srl-list-grid">
            <?php foreach ( $championship_posts as $champ ) : ?>
                <?php
                $game = get_post_meta( $champ->ID, '_srl_game', true );
                $status = get_post_meta( $champ->ID, '_srl_status', true );
                ?>
                <a href="<?php echo get_permalink( $champ->ID ); ?>" class="srl-list-card">
                    <?php if ( has_post_thumbnail( $champ->ID ) ) : ?>
                        <?php echo get_the_post_thumbnail( $champ->ID, 'medium' ); ?>
                    <?php endif; ?>
                    <h3><?php echo esc_html( $champ->post_title ); ?></h3>
                    <span class="srl-card-meta"><?php echo esc_html( strtoupper( $game ) ); ?></span>
                    <span class="srl-card-status srl-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
// Registrar el nuevo shortcode
add_shortcode( 'srl_driver_list', 'srl_render_driver_list_shortcode' );

function srl_render_driver_list_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'profile_page_url' => '/driver-profile/' ], $atts, 'srl_driver_list' );
    
    global $wpdb;
    $drivers = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}srl_drivers ORDER BY full_name ASC" );

    if ( empty( $drivers ) ) {
        return '<p>No hay pilotos registrados.</p>';
    }

    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Lista de Pilotos</h2>
        <table class="srl-table">
            <thead>
                <tr>
                    <th>Nombre del Piloto</th>
                    <th>Victorias</th>
                    <th>Podios</th>
                    <th>Poles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $drivers as $driver ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( rtrim($atts['profile_page_url'], '/') . '/?steam_id=' . $driver->steam_id ); ?>">
                                <?php echo esc_html( $driver->full_name ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $driver->victories_count ); ?></td>
                        <td><?php echo esc_html( $driver->podiums_count ); ?></td>
                        <td><?php echo esc_html( $driver->poles_count ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
