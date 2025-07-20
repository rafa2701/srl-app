<?php
add_action( 'wp_enqueue_scripts', function() {
    if ( is_srl_context() ) {
        wp_enqueue_style( 'tailwind', 'https://cdn.tailwindcss.com' );
    }
    wp_enqueue_style( 'srl-style', get_stylesheet_uri() );
} );

function is_srl_context() {
    return is_post_type_archive( [ 'driver', 'srl_championship', 'srl_event', 'srl_session' ] )
        || is_singular( [ 'driver', 'srl_championship', 'srl_event', 'srl_session' ] );
}

/* Let WordPress manage the logo */
add_theme_support( 'custom-logo' );

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
               SUM(CASE WHEN position  = 1 THEN 1 ELSE 0 END) AS poles,   // TODO: split quali later
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

    if ( ! $rows ) return '<p class="p-4 text-gray-500">No results yet.</p>';

    ob_start();
    ?>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
       