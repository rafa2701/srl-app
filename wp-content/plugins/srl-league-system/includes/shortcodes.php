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
add_shortcode( 'srl_achievements_leaderboard', 'srl_achievements_leaderboard_shortcode' );
add_shortcode( 'srl_main_menu', 'srl_render_main_menu_shortcode' );


/**
 * Renderiza la tabla de clasificación de un campeonato.
 */
function srl_render_standings_shortcode( $atts ) {
    $profile_page = get_page_by_path('pilotos');
    $default_url = $profile_page ? get_permalink($profile_page->ID) : home_url('/pilotos/');
    $atts = shortcode_atts( [ 'championship_id' => get_the_ID(), 'profile_page_url' => $default_url, ], $atts, 'srl_standings' );
    $championship_id = intval( $atts['championship_id'] );
    if ( ! $championship_id ) return '<p>ID de campeonato no encontrado.</p>';
    
    $championship_post = get_post( $championship_id );
    if ( ! $championship_post || 'srl_championship' !== get_post_type( $championship_post ) ) return '<p>Error: Campeonato no encontrado.</p>';
    
    $standings = srl_calculate_championship_standings( $championship_id );
    
    if ( empty( $standings ) ) return '<p>Aún no hay resultados para este campeonato.</p>';
    
    ob_start();
    ?>
    <div class="srl-app-container">
        <div class="srl-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Clasificación de Pilotos</h2>
            <button id="srl-toggle-detailed" class="srl-button">RESULTADOS DETALLADOS</button>
        </div>

        <table class="srl-table srl-sortable-table">
            <thead>
                <tr>
                    <th class="position" data-sort-method="number">Pos</th>
                    <th>Piloto</th>
                    <th class="numeric" data-sort-method="number">Victorias</th>
                    <th class="numeric" data-sort-method="number">Podios</th>
                    <th class="numeric" data-sort-method="number">Poles</th>
                    <th class="numeric" data-sort-method="number">V. Rápidas</th>
                    <th class="points numeric" data-sort-method="number">Puntos</th>
                </tr>
            </thead>
            <tbody>
                <?php $pos = 1; foreach ( $standings as $driver_id => $driver ) : ?>
                <?php
                    $profile_url = rtrim($atts['profile_page_url'], '/');
                    if ( ! empty( $driver['steam_id'] ) ) {
                        $profile_link = esc_url( $profile_url . '/?steam_id=' . $driver['steam_id'] );
                    } else {
                        $profile_link = esc_url( $profile_url . '/?driver_id=' . $driver_id );
                    }
                ?>
                <tr>
                    <td class="position"><?php echo $pos++; ?></td>
                    <td><a href="<?php echo $profile_link; ?>"><?php echo esc_html( $driver['name'] ); ?></a></td>
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

    <div id="srl-detailed-standings-container" class="srl-app-container" style="display: none; margin-top: 40px;">
        <div class="srl-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Resultados Detallados</h2>
            <div class="srl-toggle-group">
                <button class="srl-button srl-toggle-view active" data-view="points">Puntos</button>
                <button class="srl-button srl-toggle-view" data-view="positions">Posiciones</button>
            </div>
        </div>
        <?php echo srl_render_detailed_standings($championship_id, $atts); ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza la tabla detallada (Wikipedia style) de un campeonato.
 */
function srl_render_detailed_standings( $championship_id, $atts ) {
    global $wpdb;

    // 1. Obtener eventos y sesiones de carrera
    $events = get_posts([
        'post_type' => 'srl_event',
        'meta_key' => '_srl_parent_championship',
        'meta_value' => $championship_id,
        'posts_per_page' => -1,
        'orderby' => 'menu_order post_date',
        'order' => 'ASC'
    ]);

    if ( empty($events) ) return '<p>No hay eventos registrados.</p>';

    $race_sessions = [];
    foreach ($events as $event) {
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT id, session_type FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d AND session_type = 'Race' LIMIT 1",
            $event->ID
        ));
        if ($session) {
            $race_sessions[] = [
                'session_id' => $session->id,
                'event_title' => $event->post_title,
                'event_id' => $event->ID
            ];
        }
    }

    if ( empty($race_sessions) ) return '<p>No hay sesiones de carrera importadas.</p>';

    // 2. Obtener todos los resultados para estas sesiones
    $session_ids = array_column($race_sessions, 'session_id');
    $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, d.full_name, d.steam_id FROM {$wpdb->prefix}srl_results r
         JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id
         WHERE r.session_id IN ($placeholders)",
        $session_ids
    ));

    // 3. Organizar datos por piloto
    $standings_data = [];
    foreach ($results as $r) {
        if (!isset($standings_data[$r->driver_id])) {
            $standings_data[$r->driver_id] = [
                'name' => $r->full_name,
                'steam_id' => $r->steam_id,
                'total_points' => 0,
                'results' => []
            ];
        }
        $standings_data[$r->driver_id]['results'][$r->session_id] = $r;
        $standings_data[$r->driver_id]['total_points'] += $r->points_awarded;
    }

    // Ordenar por puntos totales (descendente)
    uasort($standings_data, function($a, $b) {
        return $b['total_points'] <=> $a['total_points'];
    });

    ob_start();
    ?>
    <div class="srl-table-responsive srl-detailed-table-wrapper">
        <table class="srl-table srl-detailed-table">
            <thead>
                <tr>
                    <th class="sticky-col first-col">Pos</th>
                    <th class="sticky-col second-col">Piloto</th>
                    <?php foreach ($race_sessions as $index => $session) : ?>
                        <th title="<?php echo esc_attr($session['event_title']); ?>" class="round-col">
                            R<?php echo ($index + 1); ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="points-col">Pts</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($standings_data as $driver_id => $data) : ?>
                    <tr>
                        <td class="sticky-col first-col"><?php echo $rank++; ?></td>
                        <td class="sticky-col second-col">
                            <?php
                                $profile_url = rtrim($atts['profile_page_url'], '/');
                                $profile_link = !empty($data['steam_id'])
                                    ? $profile_url . '/?steam_id=' . $data['steam_id']
                                    : $profile_url . '/?driver_id=' . $driver_id;
                            ?>
                            <a href="<?php echo esc_url($profile_link); ?>"><?php echo esc_html($data['name']); ?></a>
                        </td>
                        <?php foreach ($race_sessions as $session) : ?>
                            <?php
                                $res = $data['results'][$session['session_id']] ?? null;
                                $cell_class = '';
                                if ($res) {
                                    if ($res->is_disqualified) $cell_class = 'srl-res-dq';
                                    elseif ($res->is_dnf) $cell_class = 'srl-res-dnf';
                                    elseif ($res->is_nc) $cell_class = 'srl-res-nc';
                                    elseif ($res->position == 1) $cell_class = 'srl-res-gold';
                                    elseif ($res->position == 2) $cell_class = 'srl-res-silver';
                                    elseif ($res->position == 3) $cell_class = 'srl-res-bronze';
                                    elseif ($res->position <= 10) $cell_class = 'srl-res-top10';
                                }
                            ?>
                            <td class="<?php echo $cell_class; ?>">
                                <?php if ($res) : ?>
                                    <?php
                                        $style = '';
                                        if ($res->has_pole) $style .= 'font-weight: bold;';
                                        if ($res->has_fastest_lap) $style .= 'font-style: italic;';
                                    ?>
                                    <span class="srl-val-points" style="<?php echo $style; ?>">
                                        <?php echo $res->points_awarded; ?>
                                    </span>
                                    <span class="srl-val-position" style="display: none; <?php echo $style; ?>">
                                        <?php
                                            if ($res->is_disqualified) echo 'DSQ';
                                            elseif ($res->is_dnf) echo 'DNF';
                                            elseif ($res->is_nc) echo 'NC';
                                            else echo $res->position;
                                        ?>
                                    </span>
                                <?php else : ?>
                                    <span class="srl-val-points">-</span>
                                    <span class="srl-val-position" style="display: none;">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="points-col"><strong><?php echo $data['total_points']; ?></strong></td>
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
    $atts = shortcode_atts( [ 'steam_id' => '', 'driver_id' => '' ], $atts, 'srl_driver_profile' );
    $steam_id = ! empty( $atts['steam_id'] ) ? $atts['steam_id'] : ( $_GET['steam_id'] ?? '' );
    $driver_id = ! empty( $atts['driver_id'] ) ? $atts['driver_id'] : ( $_GET['driver_id'] ?? '' );

    global $wpdb;
    $driver = null;

    if ( ! empty( $steam_id ) ) {
        $steam_id = sanitize_text_field( $steam_id );
        $driver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE steam_id = %s", $steam_id ) );
    } elseif ( ! empty( $driver_id ) ) {
        $driver_id = intval( $driver_id );
        $driver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $driver_id ) );
    }

    if ( ! $driver ) return '<p>Error: No se ha especificado un piloto válido o no se encontró.</p>';

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

    $championship_ids_query = $wpdb->get_col( $wpdb->prepare("
        SELECT DISTINCT pm.meta_value
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        JOIN {$wpdb->prefix}postmeta pm ON s.event_id = pm.post_id
        WHERE r.driver_id = %d AND pm.meta_key = '_srl_parent_championship'
    ", $driver->id) );
    
    $championship_history = [];
    if (!empty($championship_ids_query)) {
        $championship_history = get_posts([
            'post_type' => 'srl_championship',
            'post__in' => $championship_ids_query,
            'posts_per_page' => -1,
            'orderby' => 'post_date',
            'order' => 'DESC'
        ]);
    }
    
    ob_start();
    ?>
    <div class="srl-app-container srl-driver-profile">
        <div class="srl-profile-actions" style="margin-bottom: 20px;">
            <a href="<?php echo esc_url( strtok($_SERVER["REQUEST_URI"], '?') ); ?>" class="srl-button secondary">← Volver a la lista</a>
        </div>
        <div class="srl-profile-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
            <div class="srl-profile-photo">
                <?php
                $img_src = $driver->photo_url;
                if ( $driver->photo_id ) {
                    $img_src = wp_get_attachment_image_url($driver->photo_id, 'medium') ?: $img_src;
                }
                if ( $img_src ) : ?>
                    <img src="<?php echo esc_url($img_src); ?>" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #ff0000; box-shadow: 0 0 15px rgba(255,0,0,0.3);">
                <?php else : ?>
                    <div style="width: 120px; height: 120px; border-radius: 50%; background: #222; display: flex; align-items: center; justify-content: center; border: 3px solid #444;">
                        <span class="dashicons dashicons-admin-users" style="font-size: 60px; width: 60px; height: 60px; color: #666;"></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="srl-profile-info">
                <h1 style="margin: 0; line-height: 1;"><?php echo esc_html( $driver->full_name ); ?></h1>
                <p class="srl-steam-id" style="margin: 5px 0 0; opacity: 0.7;">
                    <?php if ($driver->nationality) : ?>
                        <span class="srl-nationality" style="margin-right: 15px;">🏁 <?php echo esc_html($driver->nationality); ?></span>
                    <?php endif; ?>
                    SteamID: <?php echo esc_html( $driver->steam_id ); ?>
                </p>
            </div>
        </div>
        
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
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['win_percentage'], 2 ); ?>%</div><div class="stat-label">% Victorias</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['pole_percentage'], 2 ); ?>%</div><div class="stat-label">% Poles</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['avg_grid'], 2 ); ?></div><div class="stat-label">Pos. Salida Prom.</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo number_format( $stats['avg_finish'], 2 ); ?></div><div class="stat-label">Pos. Llegada Prom.</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->dnfs_count; ?></div><div class="stat-label">Abandonos (DNF)</div></div>
            <div class="srl-stat-card"><div class="stat-value"><?php echo $driver->dq_count; ?></div><div class="stat-label">Descalificaciones (DQ)</div></div>
        </div>

        <?php if ( ! empty( $championship_history ) ) : ?>
            <h2 style="margin-top: 40px;">Historial de Campeonatos</h2>
            <table class="srl-table">
                <thead><tr><th>Campeonato</th><th class="numeric">Posición Final</th><th class="points numeric">Puntos</th></tr></thead>
                <tbody>
                    <?php foreach ( $championship_history as $champ ) : ?>
                        <?php
                        $standings = srl_calculate_championship_standings( $champ->ID );
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
                            <td><a href="<?php echo get_permalink($champ->ID); ?>"><?php echo esc_html( $champ->post_title ); ?></a></td>
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
    // Si se pasa un steam_id o driver_id por la URL, mostramos el perfil en lugar de la lista
    if ( ! empty( $_GET['steam_id'] ) || ! empty( $_GET['driver_id'] ) ) {
        return srl_render_driver_profile_shortcode( $atts );
    }

    // Intentar autodetectar la URL del perfil si es posible
    $profile_page = get_page_by_path('pilotos');
    $default_url = $profile_page ? get_permalink($profile_page->ID) : home_url('/pilotos/');

    $atts = shortcode_atts( [ 'profile_page_url' => $default_url ], $atts, 'srl_driver_list' );
    
    global $wpdb;
    $table = $wpdb->prefix . 'srl_drivers';
    $drivers = $wpdb->get_results( "SELECT * FROM $table WHERE full_name != '' ORDER BY victories_count DESC, full_name ASC" );

    if ( empty( $drivers ) ) {
        return '<p style="text-align: center; padding: 20px;">No se encontraron pilotos con estadísticas registradas.</p>';
    }

    ob_start();
    ?>
    <div class="srl-app-container">
        <h2>Lista de Pilotos</h2>
        <table class="srl-table srl-sortable-table">
            <thead>
                <tr>
                    <th>Nombre del Piloto</th>
                    <th class="numeric" data-sort-method="number">Campeonatos</th>
                    <th class="numeric" data-sort-method="number">Victorias</th>
                    <th class="numeric" data-sort-method="number">Podios</th>
                    <th class="numeric" data-sort-method="number">Poles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $drivers as $driver ) : ?>
                    <?php
                        $profile_url = rtrim($atts['profile_page_url'], '/');
                        if ( ! empty( $driver->steam_id ) ) {
                            $profile_link = esc_url( $profile_url . '/?steam_id=' . $driver->steam_id );
                        } else {
                            $profile_link = esc_url( $profile_url . '/?driver_id=' . $driver->id );
                        }
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo $profile_link; ?>">
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
    $profile_page = get_page_by_path('pilotos');
    $default_url = $profile_page ? get_permalink($profile_page->ID) : home_url('/pilotos/');
    $atts = shortcode_atts( [ 'event_id' => get_the_ID(), 'profile_page_url' => $default_url ], $atts, 'srl_event_results' );
    $event_id = intval( $atts['event_id'] );
    if ( ! $event_id ) return '<p>ID de evento no encontrado.</p>';

    global $wpdb;
    $results = $wpdb->get_results( $wpdb->prepare("
        SELECT d.id as driver_id, d.full_name, d.steam_id, r.position, r.grid_position, r.best_lap_time, r.total_time, r.is_dnf, r.is_nc, r.is_disqualified, r.has_fastest_lap
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
                    <th class="position" data-sort-method="number">Pos</th>
                    <th>Piloto</th>
                    <th class="numeric" data-sort-method="number">Pos. Salida</th>
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
                        <td class="position"><?php
                            if ($result->is_disqualified) echo 'DQ';
                            elseif ($result->is_dnf) echo 'DNF';
                            elseif ($result->is_nc) echo 'NC';
                            else echo esc_html( $result->position );
                        ?></td>
                        <?php
                            $profile_url = rtrim($atts['profile_page_url'], '/');
                            if ( ! empty( $result->steam_id ) ) {
                                $profile_link = esc_url( $profile_url . '/?steam_id=' . $result->steam_id );
                            } else {
                                $profile_link = esc_url( $profile_url . '/?driver_id=' . $result->driver_id );
                            }
                        ?>
                        <td><a href="<?php echo $profile_link; ?>"><?php echo esc_html( $result->full_name ); ?></a></td>
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
        'achievements_url' => '/hitos/',
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
            <a href="<?php echo esc_url( home_url( $atts['achievements_url'] ) ); ?>" class="srl-menu-card">
                <div class="srl-card-icon">
                   <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.89 1.45l.2-.05c.24-.05.48-.05.72 0l.22.05L15.3 2l.3.15c.1.05.2.1.3.15l2.12 1.22.18.1c.16.1.3.2.42.34l.12.13L20 5.18l.14.22c.08.13.15.27.2.4l.08.24 1.22 2.12.05.2c.05.24.05.48 0 .72l-.05.22L21.5 11l-.15.3c-.05.1-.1.2-.15.3l-1.22 2.12-.1.18c-.1.16-.2.3-.34.42l-.13.12-1.22 1.22-.22.14c.13-.08.27-.15.4-.2l.24-.08 2.12-1.22z"/><path d="M12 8v4l2 2"/></svg>
                </div>
                <div class="srl-card-text">
                    <h3>Hitos</h3>
                    <p>Récords históricos, rachas y logros destacados.</p>
                </div>
            </a>
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
 * Formatea milisegundos a MM:SS.ms para edición.
 */
function srl_format_time_for_edit( $ms, $is_total_time = false ) {
    if ( ! $ms || $ms <= 0 ) return '';
    $seconds_total = $ms / 1000;
    $hours = floor($seconds_total / 3600);
    $minutes = floor(($seconds_total % 3600) / 60);
    $seconds = floor($seconds_total % 60);
    $milliseconds = $ms % 1000;

    if ($is_total_time && $hours > 0) {
        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
    }
    return sprintf('%02d:%02d.%03d', $minutes, $seconds, $milliseconds);
}

/**
 * Convierte MM:SS.ms a milisegundos.
 */
function srl_parse_edit_time( $time_str ) {
    if ( empty($time_str) ) return 0;

    // Formatos posibles: HH:MM:SS.ms o MM:SS.ms o SS.ms
    $parts = explode(':', $time_str);
    $hours = 0;
    $minutes = 0;
    $seconds_with_ms = 0;

    if ( count($parts) === 3 ) {
        $hours = intval($parts[0]);
        $minutes = intval($parts[1]);
        $seconds_with_ms = floatval($parts[2]);
    } elseif ( count($parts) === 2 ) {
        $minutes = intval($parts[0]);
        $seconds_with_ms = floatval($parts[1]);
    } else {
        $seconds_with_ms = floatval($parts[0]);
    }

    return (int)round(($hours * 3600 + $minutes * 60 + $seconds_with_ms) * 1000);
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

/**
 * Shortcode para mostrar el salón de la fama / tabla de récords de hitos.
 */
function srl_achievements_leaderboard_shortcode() {
    $leaderboard = SRL_Achievement_Manager::get_achievements_leaderboard();

    if ( empty( $leaderboard ) ) {
        return '<div class="srl-achievements-empty">Aún no hay hitos registrados.</div>';
    }

    ob_start();
    ?>
    <div class="srl-achievements-hall-of-fame">
        <h2 class="srl-section-title">Hitos Históricos</h2>
        <div class="srl-achievements-grid">
            <?php foreach ( $leaderboard as $key => $data ) : ?>
                <div class="srl-achievement-card">
                    <div class="srl-achievement-header">
                        <span class="srl-achievement-icon">🏆</span>
                        <h3 class="srl-achievement-label"><?php echo esc_html( $data['label'] ); ?></h3>
                    </div>
                    <table class="srl-achievement-table">
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ( $data['records'] as $record ) :
                                $value = $record->record_value;
                                // Formatear valores según la clave
                                if ( strpos($key, 'efficiency') !== false ) {
                                    $value .= '%';
                                } elseif ( strpos($key, 'margin') !== false || strpos($key, 'gap') !== false || $key === 'nerves_of_steel' || $key === 'one_lap_wonder' ) {
                                    $value = srl_format_time($value);
                                }
                            ?>
                                <tr class="rank-<?php echo $rank; ?>">
                                    <td class="rank-col">#<?php echo $rank; ?></td>
                                    <td class="driver-col">
                                        <a href="<?php
                                            // Enlace al perfil individual en la misma página de pilotos
                                            $profile_page = get_page_by_path('pilotos');
                                            $base_url = $profile_page ? get_permalink($profile_page->ID) : home_url('/pilotos/');

                                            // Preferimos steam_id si está disponible en el registro del hito (si lo añadimos al query)
                                            // pero por simplicidad usamos driver_id
                                            echo esc_url( add_query_arg('driver_id', $record->driver_id, $base_url) );
                                        ?>">
                                            <?php echo esc_html( $record->full_name ); ?>
                                        </a>
                                    </td>
                                    <td class="value-col">
                                        <?php echo esc_html( $value ); ?>
                                        <?php if ( $key === 'nerves_of_steel' && $record->opponent_name ) : ?>
                                            <br><small style="color: #888; font-weight: normal;">vs <?php echo esc_html($record->opponent_name); ?></small>
                                        <?php endif; ?>
                                        <?php if ( $record->championship_name ) : ?>
                                            <br><small style="color: #888; font-weight: normal;"><?php echo esc_html($record->championship_name); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="event-col">
                                        <?php if ( $record->event_id ) : ?>
                                            <a href="<?php echo esc_url( get_permalink( $record->event_id ) ); ?>" title="Ver Evento">
                                                📅
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ( strpos($key, 'efficiency') !== false ) : ?>
                        <div class="srl-achievement-note">* Mínimo 10 carreras</div>
                    <?php endif; ?>
                    <?php if ( $key === 'largest_pole_gap' ) : ?>
                        <div class="srl-achievement-note">* Pendiente de implementación completa</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
        .srl-achievements-hall-of-fame { margin-top: 30px; }
        .srl-achievements-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .srl-achievement-card { background: #1a1a1a; border: 1px solid #333; padding: 20px; border-radius: 8px; }
        .srl-achievement-header { display: flex; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e60000; padding-bottom: 10px; }
        .srl-achievement-icon { font-size: 24px; margin-right: 10px; }
        .srl-achievement-label { margin: 0; font-size: 1.1rem; color: #fff; }
        .srl-achievement-table { width: 100%; border-collapse: collapse; }
        .srl-achievement-table td { padding: 8px 5px; border-bottom: 1px solid #222; font-size: 0.9rem; color: #ccc; }
        .rank-col { width: 30px; color: #888; font-weight: bold; }
        .value-col { text-align: right; font-weight: 700; color: #e60000; }
        .event-col { width: 30px; text-align: center; }
        .rank-1 .driver-col a { color: #ffd700 !important; font-weight: bold; }
        .srl-achievement-table a { color: #fff; text-decoration: none; }
        .srl-achievement-table a:hover { color: #e60000; }
        .srl-achievement-note { font-size: 0.75rem; color: #666; margin-top: 10px; font-style: italic; }
    </style>
    <?php
    return ob_get_clean();
}
