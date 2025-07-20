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