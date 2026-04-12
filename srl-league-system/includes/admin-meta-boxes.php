<?php
/**
 * Archivo para registrar Meta Boxes adicionales en el área de administración.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'add_meta_boxes', 'srl_add_event_meta_boxes' );

/**
 * Registra todos los meta boxes para la pantalla de edición de un evento.
 */
function srl_add_event_meta_boxes() {
    // Meta Box para mostrar los resultados
    add_meta_box(
        'srl_event_results_viewer',
        'Resultados Importados',
        'srl_render_event_results_meta_box',
        'srl_event',
        'normal',
        'high'
    );

    // NUEVO: Meta Box para acciones administrativas
    add_meta_box(
        'srl_event_actions',
        'Acciones del Evento',
        'srl_render_event_actions_meta_box',
        'srl_event',
        'side', // Lo colocamos en la barra lateral
        'high'
    );
    // NUEVO: Meta Box para acciones en la vista de Campeonato
    add_meta_box(
        'srl_championship_actions',
        'Acciones del Campeonato',
        'srl_render_championship_actions_meta_box',
        'srl_championship', // Aplicar al CPT de Campeonatos
        'side',
        'high'
    );
}

/**
 * Renderiza el contenido del meta box de resultados del evento.
 */
function srl_render_event_results_meta_box( $post ) {
    global $wpdb;
    $event_id = $post->ID;

    $results = $wpdb->get_results( $wpdb->prepare("
        SELECT d.full_name, r.id, r.position, r.grid_position, r.best_lap_time, r.total_time, r.points_awarded, r.is_dnf, r.time_penalty, r.is_disqualified
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        WHERE s.event_id = %d AND s.session_type = 'Race'
        ORDER BY r.position ASC
    ", $event_id) );

    if ( $wpdb->last_error ) {
        echo '<div class="notice notice-error"><p>Error de base de datos: ' . esc_html( $wpdb->last_error ) . '</p></div>';
    }

    if ( empty( $results ) ) {
        echo '<p>Aún no se han importado resultados para este evento.</p>';

        // Debug info for admin
        if (current_user_can('manage_options')) {
            $session_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d", $event_id));
            echo '<p><small>Debug: Sesiones encontradas para este evento: ' . intval($session_count) . '</small></p>';
            if ($session_count > 0) {
                $sessions = $wpdb->get_results($wpdb->prepare("SELECT id, session_type FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d", $event_id));
                echo '<ul>';
                foreach ($sessions as $sess) {
                    $res_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}srl_results WHERE session_id = %d", $sess->id));
                    echo '<li><small>Sesión ID ' . $sess->id . ' (' . $sess->session_type . '): ' . $res_count . ' resultados.</small></li>';
                }
                echo '</ul>';
            }
        }
        return;
    }

    wp_nonce_field( 'srl_save_penalties_nonce', 'srl_penalties_nonce' );
    ?>
    <table class="wp-list-table widefat striped srl-results-edit-table">
        <thead>
            <tr>
                <th style="width: 50px;">Pos</th>
                <th>Piloto</th>
                <th style="width: 60px;">Salida</th>
                <th>Mejor Vuelta</th>
                <th>Tiempo Total</th>
                <th style="width: 80px;">Penalización</th>
                <th style="width: 40px;">DQ</th>
                <th style="width: 60px;">Puntos</th>
                <th style="width: 80px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $results as $result ) : ?>
                <tr data-result-id="<?php echo esc_attr($result->id); ?>">
                    <td><strong><?php
                        if ($result->is_disqualified) echo 'DQ';
                        elseif ($result->is_dnf) echo 'DNF';
                        else echo esc_html( $result->position );
                    ?></strong></td>
                    <td><?php echo esc_html( $result->full_name ); ?></td>
                    <td><?php echo esc_html( $result->grid_position ); ?></td>
                    <td><?php echo function_exists('srl_format_time') ? srl_format_time( $result->best_lap_time ) : '-'; ?></td>
                    <td><?php echo $result->is_dnf ? '-' : (function_exists('srl_format_time') ? srl_format_time( $result->total_time, true ) : '-'); ?></td>
                    <td class="col-penalty">
                        <span class="penalty-value"><?php echo ($result->time_penalty / 1000); ?>s</span>
                        <input type="number" class="penalty-input" step="0.001" value="<?php echo ($result->time_penalty / 1000); ?>" style="display:none; width: 70px;">
                    </td>
                    <td class="col-dq">
                        <input type="checkbox" class="dq-checkbox" <?php checked($result->is_disqualified, 1); ?> disabled>
                    </td>
                    <td><strong><?php echo esc_html( $result->points_awarded ); ?></strong></td>
                    <td>
                        <button type="button" class="button edit-result-btn">Editar</button>
                        <button type="button" class="button button-primary save-result-btn" style="display:none;">Guardar</button>
                        <button type="button" class="button cancel-result-btn" style="display:none;">X</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <style>
        .srl-results-edit-table input[type="number"] { margin: 0; padding: 2px 5px; height: 28px; }
        .srl-results-edit-table .button { min-height: 28px; line-height: 26px; padding: 0 8px; }
    </style>
    <?php
}

/**
 * NUEVO: Renderiza el contenido del meta box de acciones del evento.
 */
function srl_render_event_actions_meta_box( $post ) {
    // Añadir un nonce para seguridad
    wp_nonce_field( 'srl_delete_event_results_nonce', 'srl_event_actions_nonce' );
    ?>
    <p>Usa este botón para eliminar permanentemente todos los resultados y sesiones asociadas a este evento.</p>
    <p><strong>¡Esta acción no se puede deshacer!</strong></p>
    
    <button type="button" id="srl-delete-results-btn" class="button button-danger" data-event-id="<?php echo esc_attr( $post->ID ); ?>">
        Eliminar Resultados
    </button>
    <span class="spinner" style="float: none; vertical-align: middle;"></span>
    <div id="srl-delete-response" style="margin-top: 10px;"></div>
    <?php
}
/**
 * NUEVO: Renderiza el contenido del meta box de acciones del campeonato.
 */
function srl_render_championship_actions_meta_box( $post ) {
    wp_nonce_field( 'srl_recalculate_points_nonce', 'srl_championship_actions_nonce' );
    ?>
    <p>Usa este botón si has cambiado el sistema de puntuación y necesitas actualizar los puntos de todos los eventos de este campeonato.</p>
    <p><strong>Nota:</strong> Esto también recalculará las estadísticas globales de los pilotos afectados.</p>
    
    <button type="button" id="srl-recalculate-points-btn" class="button button-secondary" data-championship-id="<?php echo esc_attr( $post->ID ); ?>">
        Recalcular Puntos
    </button>
    <span class="spinner" style="float: none; vertical-align: middle;"></span>
    <div id="srl-recalculate-points-response" style="margin-top: 10px;"></div>
    <?php
}