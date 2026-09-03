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
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 23,
        'menu_icon'             => 'dashicons-shield',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'rewrite'               => [ 'slug' => 'reclamo', 'with_front' => false ],
        'capability_type'       => 'post',
    ];

    register_post_type( 'srl_protest', $args );
}
add_action( 'init', 'srl_register_protest_post_type', 1 );

/**
 * Format protest title and slug automatically:
 * "[Evento] - [Apellido Demandante] vs [Apellido Acusado] #[N]"
 * Slug: "[evento]-[apellido-demandante]-[apellido-acusado]-[n]"
 */
function srl_format_protest_title_and_slug( $post_id ) {
    if ( get_post_type( $post_id ) !== 'srl_protest' ) {
        return;
    }

    global $wpdb;
    $event_id     = get_post_meta( $post_id, '_srl_event_id', true );
    $protester_id = get_post_meta( $post_id, '_srl_protesting_driver_id', true );
    $accused_id   = get_post_meta( $post_id, '_srl_accused_driver_id', true );

    $event_title = 'Evento';
    if ( $event_id ) {
        $event = get_post( $event_id );
        if ( $event && ! empty( $event->post_title ) ) {
            $event_title = trim( $event->post_title );
        }
    } else {
        $custom_event = get_post_meta( $post_id, '_srl_custom_event_name', true );
        if ( ! empty( $custom_event ) ) {
            $event_title = trim( $custom_event );
        }
    }

    $extract_last_name = function( $driver_id ) use ( $wpdb ) {
        if ( ! $driver_id ) return 'Piloto';
        $full_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $driver_id ) );
        if ( empty( $full_name ) ) return 'Piloto';
        $parts = preg_split( '/\s+/', trim( $full_name ) );
        return count( $parts ) > 1 ? end( $parts ) : $parts[0];
    };

    $protester_last = $extract_last_name( $protester_id );
    $accused_last   = $extract_last_name( $accused_id );

    // Count existing protests between this pair for this event (excluding current post)
    $meta_query = [
        'relation' => 'AND',
        [
            'key'   => '_srl_protesting_driver_id',
            'value' => $protester_id,
        ],
        [
            'key'   => '_srl_accused_driver_id',
            'value' => $accused_id,
        ],
    ];
    if ( $event_id ) {
        $meta_query[] = [
            'key'   => '_srl_event_id',
            'value' => $event_id,
        ];
    }

    $existing = new WP_Query( [
        'post_type'      => 'srl_protest',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'post__not_in'   => [ $post_id ],
        'meta_query'     => $meta_query,
        'fields'         => 'ids',
    ] );

    $index = ( $existing->post_count ) + 1;

    $formatted_title = sprintf( '%s - %s vs %s #%d', $event_title, $protester_last, $accused_last, $index );
    $formatted_slug  = sanitize_title( sprintf( '%s-%s-%s-%d', $event_title, $protester_last, $accused_last, $index ) );

    // Temporarily unhook save_post to prevent recursion
    remove_action( 'save_post_srl_protest', 'srl_format_protest_title_and_slug_hook', 30 );

    wp_update_post( [
        'ID'         => $post_id,
        'post_title' => $formatted_title,
        'post_name'  => $formatted_slug,
    ] );

    add_action( 'save_post_srl_protest', 'srl_format_protest_title_and_slug_hook', 30, 2 );
}

/**
 * Hook for save_post_srl_protest to keep title and slug synchronized.
 */
function srl_format_protest_title_and_slug_hook( $post_id, $post = null ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( get_post_type( $post_id ) !== 'srl_protest' ) return;

    // Only auto-format if driver metadata is present
    $protester_id = get_post_meta( $post_id, '_srl_protesting_driver_id', true );
    $accused_id   = get_post_meta( $post_id, '_srl_accused_driver_id', true );
    if ( $protester_id && $accused_id ) {
        srl_format_protest_title_and_slug( $post_id );
    }
}
add_action( 'save_post_srl_protest', 'srl_format_protest_title_and_slug_hook', 30, 2 );

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

            if ( $human_status === 'resolved' ) {
                echo '<span style="color: #28a745; font-weight: bold;">[Dictamen Aplicado]</span><br>';
            } elseif ( $human_status === 'dismissed' ) {
                echo '<span style="color: #dc3545; font-weight: bold;">[Desestimado]</span><br>';
            }

            if ( ! empty( $verdict['chief_steward'] ) ) {
                $cs = $verdict['chief_steward'];
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

/**
 * Ensure the comisario-ai user is provisioned.
 *
 * @return int User ID of comisario-ai
 */
function srl_ensure_ai_steward_user() {
    $user = get_user_by( 'login', 'comisario-ai' );
    if ( $user ) {
        if ( $user->display_name !== 'Comisario Virtual AI' ) {
            wp_update_user( [
                'ID'           => $user->ID,
                'display_name' => 'Comisario Virtual AI',
            ] );
        }
        return $user->ID;
    }

    $admin_email = get_option( 'admin_email', 'admin@simracinglatinoamerica.com' );
    $domain = 'simracinglatinoamerica.com';
    if ( strpos( $admin_email, '@' ) !== false ) {
        $parts = explode( '@', $admin_email );
        $domain = $parts[1];
    }
    $ai_email = 'comisario-ai@' . $domain;

    if ( email_exists( $ai_email ) ) {
        $ai_email = 'comisario-ai-' . wp_generate_password( 4, false ) . '@' . $domain;
    }

    $user_id = wp_insert_user( [
        'user_login'   => 'comisario-ai',
        'user_pass'    => wp_generate_password( 24, true, true ),
        'user_email'   => $ai_email,
        'display_name' => 'Comisario Virtual AI',
        'nickname'     => 'comisario-ai',
        'first_name'   => 'Comisario Virtual',
        'last_name'    => 'AI',
        'role'         => 'subscriber',
    ] );

    if ( ! is_wp_error( $user_id ) && $user_id ) {
        update_user_meta( $user_id, '_srl_is_ai_steward', 1 );
        return $user_id;
    }

    return 0;
}

