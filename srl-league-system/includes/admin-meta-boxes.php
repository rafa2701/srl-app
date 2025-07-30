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
        SELECT d.full_name, r.position, r.grid_position, r.best_lap_time, r.total_time, r.points_awarded, r.is_dnf
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        WHERE s.event_id = %d AND s.session_type = 'Race'
        ORDER BY r.position ASC
    ", $event_id) );

    if ( empty( $results ) ) {
        echo '<p>Aún no se han importado resultados para este evento.</p>';
        return;
    }
    ?>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th style="width: 50px;">Pos</th>
                <th>Piloto</th>
                <th style="width: 80px;">Salida</th>
                <th>Mejor Vuelta</th>
                <th>Tiempo Total</th>
                <th style="width: 80px;">Puntos</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $results as $result ) : ?>
                <tr>
                    <td><strong><?php echo $result->is_dnf ? 'DNF' : esc_html( $result->position ); ?></strong></td>
                    <td><?php echo esc_html( $result->full_name ); ?></td>
                    <td><?php echo esc_html( $result->grid_position ); ?></td>
                    <td><?php echo function_exists('srl_format_time') ? srl_format_time( $result->best_lap_time ) : '-'; ?></td>
                    <td><?php echo $result->is_dnf ? '-' : (function_exists('srl_format_time') ? srl_format_time( $result->total_time, true ) : '-'); ?></td>
                    <td><strong><?php echo esc_html( $result->points_awarded ); ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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