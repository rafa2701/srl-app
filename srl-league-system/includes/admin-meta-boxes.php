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

    // Verificar si la columna 'id' existe antes de pedirla
    $table_results = $wpdb->prefix . 'srl_results';
    $has_id = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM $table_results LIKE %s", 'id' ) );
    $id_field = !empty($has_id) ? 'r.id' : '0 as id';

    $results = $wpdb->get_results( $wpdb->prepare("
        SELECT d.full_name, $id_field, r.position, r.grid_position, r.best_lap_time, r.total_time, r.laps_completed, r.points_awarded, r.is_dnf, r.is_nc, r.is_nc_forced, r.time_penalty, r.is_disqualified, r.session_id
        FROM {$wpdb->prefix}srl_results r
        JOIN {$wpdb->prefix}srl_drivers d ON r.driver_id = d.id
        JOIN {$wpdb->prefix}srl_sessions s ON r.session_id = s.id
        WHERE s.event_id = %d AND s.session_type = 'Race'
        ORDER BY r.position ASC
    ", $event_id) );

    if ( $wpdb->last_error ) {
        echo '<div class="notice notice-error"><p>Error de base de datos: ' . esc_html( $wpdb->last_error ) . '</p></div>';
    }

    wp_nonce_field( 'srl_save_penalties_nonce', 'srl_penalties_nonce' );
    $session_id = !empty($results) ? $results[0]->session_id : 0;

    // Si no hay resultados, pero hay una sesión creada, obtener el ID de la sesión
    if (!$session_id) {
        $session_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}srl_sessions WHERE event_id = %d AND session_type = 'Race' LIMIT 1", $event_id));
    }

    // FALLBACK: Si no hay sesión de carrera, ofrecer crearla para permitir carga manual
    if ( !$session_id && current_user_can('manage_options') ) {
        echo '<div id="srl-no-session-notice"><p>Este evento no tiene una sesión de carrera importada.</p>';
        echo '<button type="button" id="srl-create-manual-session-btn" class="button button-secondary" data-event-id="'.esc_attr($event_id).'">Crear Sesión para Carga Manual</button></div>';
        return;
    }
    ?>
    <div class="srl-results-container">
        <?php if ( empty( $results ) ) : ?>
            <p id="srl-empty-results-msg">Aún no se han importado resultados para este evento. Puedes añadir pilotos manualmente abajo.</p>
        <?php endif; ?>
        <table class="wp-list-table widefat striped srl-results-edit-table">
            <thead>
                <tr>
                    <th style="width: 30px;"></th>
                    <th style="width: 50px;">Pos</th>
                    <th>Piloto</th>
                    <th style="width: 60px;">Salida</th>
                    <th style="width: 60px;">Vueltas</th>
                    <th style="width: 100px;">Mejor Vuelta</th>
                    <th style="width: 110px;">Tiempo Total</th>
                    <th style="width: 80px;">Penalización</th>
                    <th style="width: 35px;">DNF</th>
                    <th style="width: 35px;">NC</th>
                    <th style="width: 35px;">DQ</th>
                    <th style="width: 60px;">Puntos</th>
                    <th style="width: 100px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="srl-results-sortable">
                <?php foreach ( $results as $result ) : ?>
                    <tr data-result-id="<?php echo esc_attr($result->id); ?>" class="result-row">
                        <td class="drag-handle" style="cursor: move;"><span class="dashicons dashicons-menu"></span></td>
                        <td class="col-pos"><strong><?php
                            if ($result->is_disqualified) echo 'DQ';
                            elseif ($result->is_dnf) echo 'DNF';
                            elseif ($result->is_nc) echo 'NC';
                            else echo esc_html( $result->position );
                        ?></strong></td>
                        <td class="col-name"><?php echo esc_html( $result->full_name ); ?></td>
                        <td class="col-grid">
                            <span class="view-value"><?php echo esc_html( $result->grid_position ); ?></span>
                            <input type="number" class="edit-input grid-input" value="<?php echo esc_attr($result->grid_position); ?>" style="display:none; width: 50px;">
                        </td>
                        <td class="col-laps">
                            <span class="view-value"><?php echo esc_html( $result->laps_completed ); ?></span>
                            <input type="number" class="edit-input laps-input" value="<?php echo esc_attr($result->laps_completed); ?>" style="display:none; width: 50px;">
                        </td>
                        <td class="col-best-lap">
                            <span class="view-value"><?php echo function_exists('srl_format_time') ? srl_format_time( $result->best_lap_time ) : '-'; ?></span>
                            <input type="text" class="edit-input best-lap-input" value="<?php echo srl_format_time_for_edit($result->best_lap_time); ?>" style="display:none; width: 90px;" placeholder="MM:SS.ms">
                        </td>
                        <td class="col-total-time">
                            <span class="view-value"><?php echo $result->is_dnf ? '-' : (function_exists('srl_format_time') ? srl_format_time( $result->total_time, true ) : '-'); ?></span>
                            <input type="text" class="edit-input total-time-input" value="<?php echo srl_format_time_for_edit($result->total_time, true); ?>" style="display:none; width: 100px;" placeholder="MM:SS.ms">
                        </td>
                        <td class="col-penalty">
                            <span class="view-value"><?php echo ($result->time_penalty / 1000); ?>s</span>
                            <input type="number" class="edit-input penalty-input" step="0.001" value="<?php echo ($result->time_penalty / 1000); ?>" style="display:none; width: 70px;">
                        </td>
                        <td class="col-dnf">
                            <input type="checkbox" class="dnf-checkbox" <?php checked($result->is_dnf, 1); ?> disabled>
                        </td>
                        <td class="col-nc">
                            <input type="checkbox" class="nc-checkbox" <?php checked($result->is_nc, 1); ?> disabled>
                        </td>
                        <td class="col-dq">
                            <input type="checkbox" class="dq-checkbox" <?php checked($result->is_disqualified, 1); ?> disabled>
                        </td>
                        <td class="col-points"><strong><?php echo esc_html( $result->points_awarded ); ?></strong></td>
                        <td>
                            <button type="button" class="button edit-result-btn">Editar</button>
                            <button type="button" class="button button-primary save-result-btn" style="display:none;">Guardar</button>
                            <button type="button" class="button cancel-result-btn" style="display:none;">X</button>
                            <button type="button" class="button delete-result-btn" style="display:none; color: red;">🗑️</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($session_id) : ?>
            <div style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 15px;">
                <h4>Añadir Piloto Manualmente</h4>
                <div class="srl-add-manual-row" style="display: flex; gap: 10px; align-items: center;">
                    <select id="srl-manual-driver-id" style="min-width: 200px;">
                        <option value="">-- Seleccionar Piloto --</option>
                        <?php
                        $all_drivers = $wpdb->get_results("SELECT id, full_name FROM {$wpdb->prefix}srl_drivers ORDER BY full_name ASC");
                        foreach ($all_drivers as $d) {
                            echo '<option value="'.esc_attr($d->id).'">'.esc_html($d->full_name).'</option>';
                        }
                        ?>
                    </select>
                    <button type="button" id="srl-add-manual-driver-btn" class="button button-secondary" data-session-id="<?php echo esc_attr($session_id); ?>">Añadir al Resultado</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <style>
        .srl-results-edit-table input { margin: 0; padding: 2px 5px; height: 28px; font-size: 12px; }
        .srl-results-edit-table .button { min-height: 28px; line-height: 26px; padding: 0 8px; }
        .srl-results-edit-table .drag-handle { color: #ccc; }
        .srl-results-edit-table .drag-handle:hover { color: #666; }
        .result-row.sortable-ghost { opacity: 0.4; background: #f0f0f0; }
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