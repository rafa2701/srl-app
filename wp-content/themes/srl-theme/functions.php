<?php
/**
 * SRL Theme functions and definitions
 */

function srl_theme_setup() {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );

    register_nav_menus( [
        'main-menu'   => __( 'Main Menu', 'srl-theme' ),
        'footer-menu' => __( 'Footer Menu', 'srl-theme' ),
    ] );
}
add_action( 'after_setup_theme', 'srl_theme_setup' );

/**
 * Automatically create and assign the main menu if it doesn't exist
 */
function srl_setup_default_menus() {
    $menu_name = 'Main Menu';
    $menu_exists = wp_get_nav_menu_object( $menu_name );

    if ( ! $menu_exists ) {
        $menu_id = wp_create_nav_menu( $menu_name );

        // Add Home link
        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'  =>  __( 'Inicio', 'srl-theme' ),
            'menu-item-url'    => home_url( '/' ),
            'menu-item-status' => 'publish',
        ] );

        // Add Championships link
        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'  =>  __( 'Campeonatos', 'srl-theme' ),
            'menu-item-url'    => get_post_type_archive_link( 'srl_championship' ) ?: home_url( '/campeonatos/' ),
            'menu-item-status' => 'publish',
        ] );

        // Add Drivers link
        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'  =>  __( 'Pilotos', 'srl-theme' ),
            'menu-item-url'    => get_post_type_archive_link( 'driver' ) ?: home_url( '/pilotos/' ),
            'menu-item-status' => 'publish',
        ] );

        // Assign to location
        $locations = get_theme_mod( 'nav_menu_locations' );
        $locations['main-menu'] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }
}
add_action( 'after_setup_theme', 'srl_setup_default_menus' );

function srl_theme_scripts() {
    // Fonts
    wp_enqueue_style( 'google-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap', [], null );

    // Main Stylesheet
    wp_enqueue_style( 'srl-theme-style', get_stylesheet_uri(), [], '1.0.0' );

    // Ensure plugin styles are loaded if needed or override them
    if ( wp_style_is( 'srl-public-css', 'enqueued' ) ) {
        // Optionally add custom overrides for plugin styles here
    }
}
add_action( 'wp_enqueue_scripts', 'srl_theme_scripts' );

/**
 * Get the logo URL, falling back to theme default
 */
function srl_get_theme_logo_url($type = 'header') {
    $custom_logo_id = get_theme_mod( 'custom_logo' );

    // Plugin-specific logo overrides
    $plugin_logo = get_option('srl_site_logo');
    $footer_logo = get_option('srl_footer_logo');

    if ($type === 'footer' && !empty($footer_logo)) {
        return $footer_logo;
    }

    if (!empty($plugin_logo)) {
        return $plugin_logo;
    }

    if ( $custom_logo_id ) {
        return wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }

    return get_template_directory_uri() . '/assets/images/logo.png';
}

/**
 * Aggregate stats for a driver post ID
 */
function srl_get_driver_stats( $driver_id ) {
    global $wpdb;
    $stats = get_transient( "srl_stats_$driver_id" );
    if ( false === $stats ) {
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
               SUM(CASE WHEN position = 1 THEN 1 ELSE 0 END) AS wins,
               SUM(CASE WHEN position <= 3 THEN 1 ELSE 0 END) AS podiums,
               SUM(CASE WHEN position  = 1 THEN 1 ELSE 0 END) AS poles,
               SUM(CASE WHEN fastest_lap IS NOT NULL THEN 1 ELSE 0 END) AS fastest_laps
             FROM {$wpdb->prefix}srl_results WHERE driver_id = %d",
            $driver_id
        ), ARRAY_A );
        set_transient( "srl_stats_$driver_id", $stats, DAY_IN_SECONDS );
    }
    return $stats;
}

/**
 * Simple HTML table of last 10 results for the driver
 */
function srl_driver_results_table( $driver_id ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.position, r.laps, r.fastest_lap, p.post_title AS session_name
         FROM {$wpdb->prefix}srl_results r
         JOIN {$wpdb->posts} p ON p.ID = r.session_id
         WHERE r.driver_id = %d
         ORDER BY p.post_date DESC
         LIMIT 10",
        $driver_id
    ) );

    if ( ! $rows ) return '<p class="p-4 text-gray-500">No hay resultados todavía.</p>';

    ob_start();
    ?>
    <table class="srl-table">
        <thead>
            <tr>
                <th>Sesión</th>
                <th>Posición</th>
                <th>Vueltas</th>
                <th>Mejor Vuelta</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row->session_name ); ?></td>
                    <td><?php echo esc_html( $row->position ); ?></td>
                    <td><?php echo esc_html( $row->laps ); ?></td>
                    <td><?php echo esc_html( $row->fastest_lap ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Filter to remove "Home" title from front page
 */
add_filter( 'the_title', function( $title, $id = null ) {
    if ( is_front_page() && is_main_query() && in_the_loop() ) {
        return '';
    }
    return $title;
}, 10, 2 );
