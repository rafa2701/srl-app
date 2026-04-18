<?php
/**
 * Archivo para registrar los Custom Post Types (CPTs) del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

/**
 * Registra los CPTs para Campeonatos y Eventos.
 */
function srl_register_post_types() {

    // --- Íconos SVG codificados en Base64 (Color Blanco) ---
    $trophy_icon_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNmZmZmZmYiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik02IDlINC41YTIuNSAyLjUgMCAwIDEgMC01SDYiLz48cGF0aCBkPSJNMTggOWgxLjVhMi41IDIuNSAwIDAgMCAwLTVIMTgiLz48cGF0aCBkPSJNNCAyMmgaDE2Ii8+PHBhdGggZD0iTTEwIDE0LjY2VjE3YzAgLjU1LS40Ny45OC0uOTcgMS4yMUEzLjQ4IDMuNDggMCAwIDEgOCAxOS44NlYyMiIvPjxwYXRoIGQ9Ik0xNCAxNC42NlYxN2MwIC41NS40Ny45OC45NyAxLjIxQTMuNDggMy44IDAgMCAwIDE2IDE5Ljg2VjIyIi8+PHBhdGggZD0iTTE4IDJSDZ2N2E2IDYgMCAwIDAgMTIgMFYyWiIvPjwvc3ZnPg==';
    $flag_icon_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNmZmZmZmYiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik00IDE1czEtMSA0LTEgNSAyIDggMiA0LTEgNC0xVjNzLTEgMS00IDEtNS0yLTgtMi00IDEtNCAxek00IDIyVjE1Ii8+PC9zdmc+';

    // --- CPT para Campeonatos ---
    $labels_championship = [
        'name'                  => _x( 'Campeonatos', 'Post Type General Name', 'srl-league-system' ),
        'singular_name'         => _x( 'Campeonato', 'Post Type Singular Name', 'srl-league-system' ),
        'menu_name'             => __( 'Campeonatos', 'srl-league-system' ),
        'add_new_item'          => __( 'Añadir Nuevo Campeonato', 'srl-league-system' ),
        'edit_item'             => __( 'Editar Campeonato', 'srl-league-system' ),
    ];
    $args_championship = [
        'label'                 => __( 'Campeonato', 'srl-league-system' ),
        'labels'                => $labels_championship,
        'supports'              => [ 'title', 'editor', 'thumbnail' ],
        'hierarchical'          => true, // Importante para relaciones padre-hijo
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 21,
        'menu_icon'             => "dashicons-awards",
        'can_export'            => true,
        'has_archive'           => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    ];
    register_post_type( 'srl_championship', $args_championship );

    // --- CPT para Eventos ---
    $labels_event = [
        'name'                  => _x( 'Eventos', 'Post Type General Name', 'srl-league-system' ),
        'singular_name'         => _x( 'Evento', 'Post Type Singular Name', 'srl-league-system' ),
        'menu_name'             => __( 'Eventos', 'srl-league-system' ),
        'add_new_item'          => __( 'Añadir Nuevo Evento', 'srl-league-system' ),
        'edit_item'             => __( 'Editar Evento', 'srl-league-system' ),
    ];
    $args_event = [
        'label'                 => __( 'Evento', 'srl-league-system' ),
        'labels'                => $labels_event,
        'supports'              => [ 'title' ], // Eliminamos 'page-attributes'
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => 'edit.php?post_type=srl_championship',
        'menu_icon'             => $flag_icon_svg,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    ];
    register_post_type( 'srl_event', $args_event );

    // --- CPT para Pilotos (Drivers) ---
    $labels_driver = [
        'name'                  => _x( 'Pilotos', 'Post Type General Name', 'srl-league-system' ),
        'singular_name'         => _x( 'Piloto', 'Post Type Singular Name', 'srl-league-system' ),
        'menu_name'             => __( 'Pilotos', 'srl-league-system' ),
    ];
    $args_driver = [
        'label'                 => __( 'Piloto', 'srl-league-system' ),
        'labels'                => $labels_driver,
        'supports'              => [ 'title', 'thumbnail', 'custom-fields' ],
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => false, // Oculto del menú, usamos tabla personalizada srl_drivers
        'menu_icon'             => 'dashicons-admin-users',
        'has_archive'           => true,
        'rewrite'               => [ 'slug' => 'pilotos' ],
    ];
    register_post_type( 'driver', $args_driver );

    // --- CPT para Sesiones (Legacy) ---
    register_post_type( 'srl_session', [
        'labels'        => [ 'name' => 'Sesiones', 'singular_name' => 'Sesión' ],
        'public'        => true,
        'rewrite'       => [ 'slug' => 'sessions' ],
        'supports'      => [ 'title', 'editor', 'custom-fields' ],
        'show_in_menu'  => false, // Oculto del menú, usamos tabla personalizada srl_sessions
    ] );

}
add_action( 'init', 'srl_register_post_types', 0 );

/**
 * Añade un filtro de campeonato a la lista de eventos en el admin.
 */
function srl_add_event_filters() {
    global $typenow;
    if ( $typenow === 'srl_event' ) {
        $selected      = isset( $_GET['championship_id'] ) ? $_GET['championship_id'] : '';
        $championships = get_posts( [
            'post_type'      => 'srl_championship',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        echo '<select name="championship_id">';
        echo '<option value="">' . __( 'Filtrar por Campeonato', 'srl-league-system' ) . '</option>';
        foreach ( $championships as $championship ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $championship->ID ),
                selected( $selected, $championship->ID, false ),
                esc_html( $championship->post_title )
            );
        }
        echo '</select>';
    }
}
add_action( 'restrict_manage_posts', 'srl_add_event_filters' );

/**
 * Filtra la consulta de eventos según el campeonato seleccionado.
 */
function srl_filter_events_by_championship( $query ) {
    global $pagenow;
    $post_type = isset( $query->query_vars['post_type'] ) ? $query->query_vars['post_type'] : '';

    if ( is_admin() && $pagenow === 'edit.php' && $post_type === 'srl_event' && isset( $_GET['championship_id'] ) && $_GET['championship_id'] !== '' ) {
        $query->query_vars['meta_key']   = '_srl_parent_championship';
        $query->query_vars['meta_value'] = $_GET['championship_id'];
    }
}
add_filter( 'parse_query', 'srl_filter_events_by_championship' );
