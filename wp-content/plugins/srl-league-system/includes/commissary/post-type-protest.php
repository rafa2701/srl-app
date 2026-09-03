<?php
/**
 * Custom Post Type for Incident Protests (Virtual Commissary)
 * Location: wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

/**
 * Register the srl_protest CPT.
 */
function srl_register_protest_post_type() {
    $labels = [
        'name'                  => _x( 'Reclamos', 'Post Type General Name', 'srl-league-system' ),
        'singular_name'         => _x( 'Reclamo', 'Post Type Singular Name', 'srl-league-system' ),
        'menu_name'             => __( 'Comisariato (Reclamos)', 'srl-league-system' ),
        'name_admin_bar'        => __( 'Reclamo', 'srl-league-system' ),
        'archives'              => __( 'Archivo de Reclamos', 'srl-league-system' ),
        'attributes'            => __( 'Atributos de Reclamo', 'srl-league-system' ),
        'parent_item_colon'     => __( 'Reclamo Padre:', 'srl-league-system' ),
        'all_items'             => __( 'Todos los Reclamos', 'srl-league-system' ),
        'add_new_item'          => __( 'Nuevo Reclamo', 'srl-league-system' ),
        'add_new'               => __( 'Añadir Nuevo', 'srl-league-system' ),
        'new_item'              => __( 'Nuevo Reclamo', 'srl-league-system' ),
        'edit_item'             => __( 'Revisar Reclamo', 'srl-league-system' ),
        'update_item'           => __( 'Actualizar Reclamo', 'srl-league-system' ),
        'view_item'             => __( 'Ver Reclamo', 'srl-league-system' ),
        'view_items'            => __( 'Ver Reclamos', 'srl-league-system' ),
        'search_items'          => __( 'Buscar Reclamos', 'srl-league-system' ),
        'not_found'             => __( 'No se encontraron reclamos', 'srl-league-system' ),
        'not_found_in_trash'    => __( 'No hay reclamos en la papelera', 'srl-league-system' ),
    ];

    $args = [
        'label'                 => __( 'Reclamo', 'srl-league-system' ),
        'description'           => __( 'Reclamos de incidentes en pista para análisis del Comisariato Virtual', 'srl-league-system' ),
        'labels'                => $labels,
        'supports'              => [ 'title', 'custom-fields' ],
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 23,
        'menu_icon'             => 'dashicons-shield',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
    ];

    register_post_type( 'srl_protest', $args );
}
add_action( 'init', 'srl_register_protest_post_type', 1 );

/**
 * Customize columns in admin protest list.
 */
function srl_set_protest_columns( $columns ) {
    $new_columns = [
        'cb'                => $columns['cb'],
        'title'             => __( 'Incidente / Título', 'srl-league-system' ),
        'protest_event'     => __( 'Evento / Campeonato', 'srl-league-system' ),
        'protest_drivers'   => __( 'Demandante vs Acusado', 'srl-league-system' ),
        'ai_status'         => __( 'Estado IA', 'srl-league-system' ),
        'steward_verdict'   => __( 'Veredicto Comisariato', 'srl-league-system' ),
        'date'              => __( 'Fecha', 'srl-league-system' ),
    ];
    return $new_columns;
}
add_filter( 'manage_srl_protest_posts_columns', 'srl_set_protest_columns' );

/**
 * Render content for custom columns.
 */
function srl_render_protest_columns( $column, $post_id ) {
    global $wpdb;

    switch ( $column ) {
        case 'protest_event':
            $event_id = get_post_meta( $post_id, '_srl_event_id', true );
            if ( $event_id ) {
                $event = get_post( $event_id );
                if ( $event ) {
                    $champ_id = get_post_meta( $event_id, '_srl_parent_championship', true );
                    $champ = $champ_id ? get_post( $champ_id ) : null;
                    echo '<strong>' . esc_html( $event->post_title ) . '</strong>';
                    if ( $champ ) {
                        echo '<br><small style="color: #666;">' . esc_html( $champ->post_title ) . '</small>';
                    }
                } else {
                    echo '<span style="color: #999;">Evento #' . intval( $event_id ) . '</span>';
                }
            } else {
                echo '<span style="color: #999;">-</span>';
            }
            break;

        case 'protest_drivers':
            $protester_id = get_post_meta( $post_id, '_srl_protesting_driver_id', true );
            $accused_id = get_post_meta( $post_id, '_srl_accused_driver_id', true );

            $protester_name = '-';
            $accused_name = '-';

            if ( $protester_id ) {
                $p = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) );
                if ( $p ) $protester_name = $p->full_name;
            }
            if ( $accused_id ) {
                $a = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
                if ( $a ) $accused_name = $a->full_name;
            }

            echo '🟢 <strong>' . esc_html( $protester_name ) . '</strong><br>';
            echo '🔴 <strong>' . esc_html( $accused_name ) . '</strong>';
            break;

        case 'ai_status':
            $status = get_post_meta( $post_id, '_srl_ai_status', true ) ?: 'pending';
            $badge_style = 'display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; text-transform: uppercase;';
            switch ( $status ) {
                case 'completed':
                    echo '<span style="' . $badge_style . ' background: #d4edda; color: #155724;">✔ Completado</span>';
                    break;
                case 'processing':
                    echo '<span style="' . $badge_style . ' background: #cce5ff; color: #004085;">⏳ Procesando...</span>';
                    break;
                case 'failed':
                    echo '<span style="' . $badge_style . ' background: #f8d7da; color: #721c24;">✖ Fallido</span>';
                    break;
                case 'pending':
                default:
                    echo '<span style="' . $badge_style . ' background: #fff3cd; color: #856404;">⏸ Pendiente</span>';
                    break;
            }
            break;

        case 'steward_verdict':
            $human_status = get_post_meta( $post_id, '_srl_steward_action_status', true ) ?: 'under_review';
            $verdict_json = get_post_meta( $post_id, '_srl_ai_verdict', true );
            $verdict = is_string( $verdict_json ) ? json_decode( $verdict_json, true ) : $verdict_json;
            if ( function_exists( 'srl_clean_verdict_utf8' ) && is_array( $verdict ) ) {
                $verdict = srl_clean_verdict_utf8( $verdict );
            }

            if ( $human_status === 'resolved' ) {
                echo '<span style="color: #28a745; font-weight: bold;">[Dictamen Aplicado]</span><br>';
            } elseif ( $human_status === 'dismissed' ) {
                echo '<span style="color: #dc3545; font-weight: bold;">[Desestimado]</span><br>';
            }

            $cs = ! empty( $verdict['chief_steward'] ) ? $verdict['chief_steward'] : ( ( ! empty( $verdict['fault_protesting'] ) || ! empty( $verdict['penalty'] ) ) ? $verdict : [] );
            if ( ! empty( $cs ) ) {
                $blame_p = isset( $cs['fault_protesting'] ) ? $cs['fault_protesting'] : 0;
                $blame_a = isset( $cs['fault_accused'] ) ? $cs['fault_accused'] : 0;
                $penalty = isset( $cs['penalty'] ) ? $cs['penalty'] : ( $cs['recommended_penalty'] ?? 'Sin sanción' );
                echo '<small>Culpa: ' . intval( $blame_p ) . '% vs ' . intval( $blame_a ) . '%</small><br>';
                echo '<small style="color: #333;">' . esc_html( wp_trim_words( $penalty, 6 ) ) . '</small>';
            } else {
                echo '<small style="color: #999;">Sin análisis IA aún</small>';
            }
            break;
    }
}
add_action( 'manage_srl_protest_posts_custom_column', 'srl_render_protest_columns', 10, 2 );

/**
 * Filter protests by AI status in admin.
 */
function srl_filter_protests_by_status() {
    global $typenow;
    if ( $typenow === 'srl_protest' ) {
        $selected = isset( $_GET['srl_ai_status_filter'] ) ? sanitize_text_field( $_GET['srl_ai_status_filter'] ) : '';
        $statuses = [
            ''           => 'Todos los estados IA',
            'pending'    => 'Pendiente',
            'processing' => 'Procesando',
            'completed'  => 'Completado',
            'failed'     => 'Fallido',
        ];

        echo '<select name="srl_ai_status_filter">';
        foreach ( $statuses as $val => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $selected, $val, false ), esc_html( $label ) );
        }
        echo '</select>';
    }
}
add_action( 'restrict_manage_posts', 'srl_filter_protests_by_status' );

/**
 * Modify admin query for AI status filtering.
 */
function srl_filter_protests_query( $query ) {
    global $pagenow;
    $post_type = isset( $query->query_vars['post_type'] ) ? $query->query_vars['post_type'] : '';

    if ( is_admin() && $pagenow === 'edit.php' && $post_type === 'srl_protest' && ! empty( $_GET['srl_ai_status_filter'] ) ) {
        $status = sanitize_text_field( $_GET['srl_ai_status_filter'] );
        $query->query_vars['meta_query'] = [
            [
                'key'     => '_srl_ai_status',
                'value'   => $status,
                'compare' => '=',
            ],
        ];
    }
}
add_filter( 'parse_query', 'srl_filter_protests_query' );
