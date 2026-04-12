<?php
/**
 * SRL Custom Theme functions and definitions
 */

function srl_theme_setup() {
    // Add default posts and comments RSS feed links to head.
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title.
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support( 'post-thumbnails' );

    // Register Navigation Menus
    register_nav_menus( array(
        'menu-1' => esc_html__( 'Primary', 'srl-theme' ),
    ) );

    // Switch default core markup for search form, comment form, and comments to output valid HTML5.
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );
}
add_action( 'after_setup_theme', 'srl_theme_setup' );

/**
 * Enqueue scripts and styles.
 */
function srl_theme_scripts() {
    wp_enqueue_style( 'srl-theme-style', get_stylesheet_uri(), array(), '1.0.0' );
    wp_enqueue_style( 'srl-theme-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap', array(), null );

    // Tailwind for those modern utility touches if needed
    wp_enqueue_script( 'tailwind-cdn', 'https://cdn.tailwindcss.com', array(), null, false );
}
add_action( 'wp_enqueue_scripts', 'srl_theme_scripts' );

/**
 * Auto-create and assign Main Menu on theme activation
 */
function srl_theme_init_menu() {
    $menu_name = 'Main Menu';
    $menu_exists = wp_get_nav_menu_object( $menu_name );

    // If it doesn't exist, let's create it.
    if ( ! $menu_exists ) {
        $menu_id = wp_create_nav_menu( $menu_name );

        // Set up default menu items
        wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-title'   =>  __( 'Home', 'srl-theme' ),
            'menu-item-classes' => 'home',
            'menu-item-url'     => home_url( '/' ),
            'menu-item-status'  => 'publish',
        ) );

        wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-title'   =>  __( 'Campeonatos', 'srl-theme' ),
            'menu-item-url'     => home_url( '/campeonatos/' ),
            'menu-item-status'  => 'publish',
        ) );

        wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-title'   =>  __( 'Pilotos', 'srl-theme' ),
            'menu-item-url'     => home_url( '/pilotos/' ),
            'menu-item-status'  => 'publish',
        ) );

        // Assign menu to the location
        $locations = get_theme_mod( 'nav_menu_locations' );
        $locations['menu-1'] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }
}
add_action( 'after_switch_theme', 'srl_theme_init_menu' );

/**
 * Filter to remove "Home" title from home page if it matches exactly
 */
add_filter( 'the_title', function( $title, $id = null ) {
    if ( is_front_page() && in_the_loop() && strtolower($title) == 'home' ) {
        return '';
    }
    return $title;
}, 10, 2 );
