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
add_shortcode( 'srl_championship_list', 'srl_render_championship_list_shortcode' );
add_shortcode( 'srl_driver_list', 'srl_render_driver_list_shortcode' );
add_shortcode( 'srl_event_results', 'srl_render_event_results_shortcode' );
add_shortcode( 'srl_main_menu', 'srl_render_main_menu_shortcode' );


/**
 * NUEVA FUNCIÓN CENTRAL: Calcula la clasificación final de un campeonato con desempates.
 *
 * @param int $championship_id El ID del post del campeonato.
 * @return array La clasificación final ordenada.
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
    $results = $wpdb->get_results( $wpdb->prepare( "SELECT d.id as driver_id, d.full_name, d.steam_id, r.position, r.has_pole, r.has_fastest_lap FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE s.event_id IN ($event_ids_placeholder) AND s.session_type = 'Race'", $event_ids ) );
    
    if ( empty( $results ) ) return [];

    $standings = [];
    foreach ( $results as $result ) {
        $driver_id = $result->driver_id;
        if ( ! isset( $standings[ $driver_id ] ) ) {
            $standings[ $driver_id ] = [ 'name' => $result->full_name, 'steam_id' => $result->steam_id, 'points' => 0, 'races' => 0, 'wins' => 0, 'podiums' => 0, 'poles' => 0, 'fastest_laps' => 0, 'positions' => array_fill(1, 10, 0) ];
        }
        $standings[ $driver_id ]['points'] += ($points_map[ $result->position ] ?? 0);
        if ( $result->has_pole ) { $standings[ $driver_id ]['points'] += $bonus_pole; $standings[ $driver_id ]['poles']++; }
        if ( $result->has_fastest_lap ) { $standings[ $driver_id ]['points'] += $bonus_fastest_lap; $standings[ $driver_id ]['fastest_laps']++; }
        $standings[ $driver_id ]['races']++;
        if ( $result->position == 1 ) $standings[ $driver_id ]['wins']++;
        if ( $result->position <= 3 ) $standings[ $driver_id ]['podiums']++;
        if ( $result->position <= 10 ) $standings[ $driver_id ]['positions'][$result->position]++;
    }

    uasort( $standings, function( $a, $b ) {
        if ( $a['points'] != $b['points'] ) {
            return $b['points'] <=> $a['points'];
        }
        // Desempate por posiciones
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
 * Renderiza la tabla de clasificación de un campeonato.
 */
function srl_render_standings_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'championship_id' => get_the_ID(), 'profile_page_url' => '/driver-profile/', ], $atts, 'srl_standings' );
    $championship_id = intval( $atts['championship_id'] );
    if ( ! $championship_id ) return '<p>ID de campeonato no encontrado.</p>';
    
    $championship_post = get_post( $championship_id );
    if ( ! $championship_post || 'srl_championship' !== get_post_type( $championship_post ) ) return '<p>Error: Campeonato no encontrado.</p>';
    
    $standings = srl_calculate_championship_standings( $championship_id );
    
    if ( empty( $standings ) ) return '<p>Aún no hay resultados para este campeonato.</p>';
    
    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Clasificación de Pilotos</h2>
        <table class="srl-table srl-sortable-table">
            <thead>
                <tr>
                    <th class="position">Pos</th>
                    <th>Piloto</th>
                    <th class="numeric">Victorias</th>
                    <th class="numeric">Podios</th>
                    <th class="numeric">Poles</th>
                    <th class="numeric">V. Rápidas</th>
                    <th class="points numeric">Puntos</th>
                </tr>
            </thead>
            <tbody>
                <?php $pos = 1; foreach ( $standings as $driver ) : ?>
                <tr>
                    <td class="position"><?php echo $pos++; ?></td>
                    <td><a href="<?php echo esc_url( rtrim($atts['profile_page_url'], '/') . '/?steam_id=' . $driver['steam_id'] ); ?>"><?php echo esc_html( $driver['name'] ); ?></a></td>
                    <td class="numeric"><?php echo esc_html( $driver['wins'] ); ?></td>
                    <td class="numeric"><?php echo esc_html( $driver['podiums'] ); ?></td>
                    <td class="numeric"><?php echo esc_html( $driver['poles'] ); ?></td>
                    <td class="numeric"><?php echo esc_html( $driver['fastest_laps'] ); ?></td>
                    <td class="points numeric"><?php echo esc_html( $driver['points'] ); ?></td>
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
 */
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

    // CORRECCIÓN: Usar una consulta con meta_query para obtener el historial de campeonatos.
    $participated_event_ids = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT s.event_id FROM {$wpdb->prefix}srl_results r JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id WHERE r.driver_id = %d", $driver->id) );
    $championship_ids = [];
    if ( !empty($participated_event_ids) ) {
        foreach($participated_event_ids as $event_id) {
            $champ_id = get_post_meta($event_id, '_srl_parent_championship', true);
            if ($champ_id) {
                $championship_ids[$champ_id] = $champ_id;
            }
        }
    }
    $championship_history = [];
    if (!empty($championship_ids)) {
        $championship_history = get_posts(['post_type' => 'srl_championship', 'post__in' => $championship_ids, 'posts_per_page' => -1]);
    }
    
    ob_start();
    ?>
    <div class="srl-app-container srl-driver-profile">
        <h1>Palmarés de <?php echo esc_html( $driver->full_name ); ?></h1>
        <p class="srl-steam-id">SteamID: <?php echo esc_html( $driver->steam_id ); ?></p>
        
        <h2>Estadísticas Globales</h2>
        <div class="srl-stats-grid">
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->championships_won_count; ?></div><div class="stat-label">Campeonatos</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $total_starts; ?></div><div class="stat-label">Carreras</div></div>
            <button class="srl-stat-card interactive" data-stat="victories" data-driver-id="<?php echo $driver->id; ?>"><div class="stat-value"><?php echo $driver->victories_count; ?></div><div class="stat-label">Victorias</div></button>
            <button class="srl-stat-card interactive" data-stat="podiums" data-driver-id="<?php echo $driver->id; ?>"><div class="stat-value"><?php echo $driver->podiums_count; ?></div><div class="stat-label">Podios</div></button>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->top_5_count; ?></div><div class="stat-label">Top 5</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->top_10_count; ?></div><div class="stat-label">Top 10</div></div>
            <button class="srl-stat-card interactive" data-stat="poles" data-driver-id="<?php echo $driver->id; ?>"><div class="stat-value"><?php echo $driver->poles_count; ?></div><div class="stat-label">Poles</div></button>
            <button class="srl-stat-card interactive" data-stat="fastest_laps" data-driver-id="<?php echo $driver->id; ?>"><div class="stat-value"><?php echo $driver->fastest_laps_count; ?></div><div class="stat-label">Vueltas Rápidas</div></button>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['avg_grid'], 2 ); ?></div><div class="stat-label">Pos. Salida Prom.</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['avg_finish'], 2 ); ?></div><div class="stat-label">Pos. Llegada Prom.</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->dnfs_count; ?></div><div class="stat-label">Abandonos (DNF)</div></div>
        </div>

        <?php if ( ! empty( $championship_history ) ) : ?>
            <h2 style="margin-top: 40px;">Historial de Campeonatos</h2>
            <table class="srl-table">
                <thead><tr><th>Campeonato</th><th class="numeric">Posición Final</th><th class="points numeric">Puntos</th></tr></thead>
                <tbody>
                    <?php foreach ( $championship_history as $champ ) : ?>
                        <?php
                        $standings = srl_calculate_championship_standings( $champ->id );
                        $final_pos = '-';
                        $final_points = '-';
                        $pos = 1;
                        foreach($standings as $driver_id => $data) {
                            if ($driver_id == $driver->id) {
                                $final_pos = $pos;
                                $final_points = $data['points'];
                                break;
                            }
                            $pos++;
                        }
                        ?>
                        <tr>
                            <td><a href="<?php echo get_permalink($champ->id); ?>"><?php echo esc_html( $champ->name ); ?></a></td>
                            <td class="numeric"><?php echo $final_pos; ?></td>
                            <td class="points numeric"><?php echo $final_points; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div id="srl-achievements-modal" class="srl-modal-overlay" style="display: none;">
            <div class="srl-modal-content">
                <button class="srl-modal-close">&times;</button>
                <h3 id="srl-modal-title"></h3>
                <div id="srl-modal-body"><p class="loading">Cargando...</p></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza la lista de campeonatos.
 */
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
                $link = get_permalink( $champ->ID );
                ?>
                <a href="<?php echo esc_url( $link ); ?>" class="srl-list-card">
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

/**
 * Renderiza la lista de pilotos.
 */
function srl_render_driver_list_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'profile_page_url' => '/driver-profile/' ], $atts, 'srl_driver_list' );
    
    global $wpdb;
    $drivers = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}srl_drivers ORDER BY victories_count DESC, full_name ASC" );

    if ( empty( $drivers ) ) return '<p>No hay pilotos registrados.</p>';

    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Lista de Pilotos</h2>
        <table class="srl-table srl-sortable-table">
            <thead>
                <tr>
                    <th>Nombre del Piloto</th>
                    <th class="numeric">Campeonatos</th>
                    <th class="numeric">Victorias</th>
                    <th class="numeric">Podios</th>
                    <th class="numeric">Poles</th>
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
                        <td class="numeric"><?php echo esc_html( $driver->championships_won_count ); ?></td>
                        <td class="numeric"><?php echo esc_html( $driver->victories_count ); ?></td>
                        <td class="numeric"><?php echo esc_html( $driver->podiums_count ); ?></td>
                        <td class="numeric"><?php echo esc_html( $driver->poles_count ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza los resultados de un evento.
 */
function srl_render_event_results_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'event_id' => get_the_ID(), 'profile_page_url' => '/driver-profile/' ], $atts, 'srl_event_results' );
    $event_id = intval( $atts['event_id'] );
    if ( ! $event_id ) return '<p>ID de evento no encontrado.</p>';

    global $wpdb;
    $results = $wpdb->get_results( $wpdb->prepare("
        SELECT d.full_name, d.steam_id, r.position, r.grid_position, r.best_lap_time, r.total_time, r.is_dnf, r.has_fastest_lap
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        WHERE s.event_id = %d AND s.session_type = 'Race'
        ORDER BY r.position ASC
    ", $event_id) );

    if ( empty( $results ) ) return '<p>Aún no se han importado los resultados para este evento.</p>';

    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Resultados de Carrera</h2>
        <table class="srl-table srl-sortable-table">
            <thead>
                <tr>
                    <th class="position">Pos</th>
                    <th>Piloto</th>
                    <th class="numeric">Pos. Salida</th>
                    <th class="numeric">Mejor Vuelta</th>
                    <th class="numeric">Tiempo Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $results as $result ) : ?>
                    <?php
                    $row_classes = [];
                    if ( $result->has_fastest_lap ) $row_classes[] = 'srl-fastest-lap';
                    if ( $result->grid_position == 1 ) $row_classes[] = 'srl-pole-position';
                    ?>
                    <tr class="<?php echo implode(' ', $row_classes); ?>">
                        <td class="position"><?php echo $result->is_dnf ? 'DNF' : esc_html( $result->position ); ?></td>
                        <td><a href="<?php echo esc_url( rtrim($atts['profile_page_url'], '/') . '/?steam_id=' . $result->steam_id ); ?>"><?php echo esc_html( $result->full_name ); ?></a></td>
                        <td class="numeric"><?php echo esc_html( $result->grid_position ); ?></td>
                        <td class="numeric"><?php echo srl_format_time( $result->best_lap_time ); ?></td>
                        <td class="numeric"><?php echo $result->is_dnf ? '-' : srl_format_time( $result->total_time, true ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza el menú principal.
 */
function srl_render_main_menu_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'pilots_url' => '/pilotos/',
        'championships_url' => '/campeonatos/',
    ], $atts, 'srl_main_menu' );

    ob_start();
    ?>
    <div class="srl-app-container">
        <div class="srl-main-menu-grid">
            <a href="<?php echo esc_url( home_url( $atts['pilots_url'] ) ); ?>" class="srl-menu-card">
                <div class="srl-card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <div class="srl-card-text">
                    <h3>Palmarés de Pilotos</h3>
                    <p>Explora las estadísticas y logros de cada piloto.</p>
                </div>
            </a>
            <a href="<?php echo esc_url( home_url( $atts['championships_url'] ) ); ?>" class="srl-menu-card">
                <div class="srl-card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21A3.48 3.48 0 0 1 8 19.86V22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21A3.48 3.48 0 0 0 16 19.86V22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                </div>
                <div class="srl-card-text">
                    <h3>Campeonatos</h3>
                    <p>Consulta las clasificaciones y eventos de cada torneo.</p>
                </div>
            </a>
            <div class="srl-menu-card disabled">
                 <div class="srl-card-badge">Próximamente</div>
                <div class="srl-card-icon">
                   <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.89 1.45l.2-.05c.24-.05.48-.05.72 0l.22.05L15.3 2l.3.15c.1.05.2.1.3.15l2.12 1.22.18.1c.16.1.3.2.42.34l.12.13L20 5.18l.14.22c.08.13.15.27.2.4l.08.24 1.22 2.12.05.2c.05.24.05.48 0 .72l-.05.22L21.5 11l-.15.3c-.05.1-.1.2-.15.3l-1.22 2.12-.1.18c-.1.16-.2.3-.34.42l-.13.12-1.22 1.22-.22.14c.13-.08.27-.15.4-.2l.24-.08 2.12-1.22z"/><path d="M12 8v4l2 2"/></svg>
                </div>
                <div class="srl-card-text">
                    <h3>Hitos</h3>
                    <p>Récords históricos, rachas y logros destacados.</p>
                </div>
            </div>
            <div class="srl-menu-card disabled">
                <div class="srl-card-badge">Próximamente</div>
                <div class="srl-card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><path d="M9 5a3 3 0 0 1 0 6h12a3 3 0 0 1 0 6H9"/></svg>
                </div>
                <div class="srl-card-text">
                    <h3>Circuitos</h3>
                    <p>Estadísticas y vueltas rápidas de cada pista.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Función de ayuda para formatear el tiempo.
 */
function srl_format_time( $ms, $is_total_time = false ) {
    if ( ! $ms || $ms <= 0 ) return '-';
    $seconds_total = $ms / 1000;
    $hours = floor($seconds_total / 3600);
    $minutes = floor(($seconds_total % 3600) / 60);
    $seconds = floor($seconds_total % 60);
    $milliseconds = $ms % 1000;
    
    if ($is_total_time && $hours > 0) {
        return sprintf('%d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
    }
    return sprintf('%d:%02d.%03d', $minutes, $seconds, $milliseconds);
}
