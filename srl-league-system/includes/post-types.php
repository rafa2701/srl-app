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

}
add_action( 'init', 'srl_register_post_types', 0 );
