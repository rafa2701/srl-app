<?php
/**
 * Archivo para registrar Meta Boxes adicionales en el área de administración.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'add_meta_boxes', 'srl_add_event_results_meta_box' );

/**
 * Registra el meta box para mostrar los resultados en la página de edición de un evento.
 */
function srl_add_event_results_meta_box() {
    add_meta_box(
        'srl_event_results_viewer',
        'Resultados Importados',
        'srl_render_event_results_meta_box',
        'srl_event', // CPT al que se aplica
        'normal',
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
