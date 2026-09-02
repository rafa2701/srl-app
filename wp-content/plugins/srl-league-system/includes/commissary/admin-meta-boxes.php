<?php
/**
 * Admin Meta Boxes for Protest Review and AI Verdicts
 * Location: wp-content/plugins/srl-league-system/includes/commissary/admin-meta-boxes.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'add_meta_boxes_srl_protest', 'srl_add_protest_meta_boxes' );

function srl_add_protest_meta_boxes() {
    add_meta_box(
        'srl_protest_details_box',
        __( 'Detalles del Incidente', 'srl-league-system' ),
        'srl_render_protest_details_meta_box',
        'srl_protest',
        'normal',
        'high'
    );

    add_meta_box(
        'srl_protest_ai_verdict_box',
        __( 'Análisis del Comisariato Virtual AI (Gemini 1.5 Pro)', 'srl-league-system' ),
        'srl_render_protest_ai_verdict_meta_box',
        'srl_protest',
        'normal',
        'high'
    );

    add_meta_box(
        'srl_protest_human_decision_box',
        __( 'Resolución del Comisariato Humano', 'srl-league-system' ),
        'srl_render_protest_human_decision_meta_box',
        'srl_protest',
        'side',
        'high'
    );
}

/**
 * Meta box: Incident submission details.
 */
function srl_render_protest_details_meta_box( $post ) {
    global $wpdb;

    $event_id = get_post_meta( $post->ID, '_srl_event_id', true );
    $protester_id = get_post_meta( $post->ID, '_srl_protesting_driver_id', true );
    $accused_id = get_post_meta( $post->ID, '_srl_accused_driver_id', true );
    $lap_timecode = get_post_meta( $post->ID, '_srl_lap_timecode', true );
    $description = get_post_meta( $post->ID, '_srl_incident_description', true );
    $evidence_raw = get_post_meta( $post->ID, '_srl_evidence_urls', true );

    $evidence_urls = is_array( $evidence_raw ) ? $evidence_raw : array_filter( array_map( 'trim', explode( "\n", (string)$evidence_raw ) ) );

    $event = $event_id ? get_post( $event_id ) : null;
    $protester = $protester_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) ) : null;
    $accused = $accused_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) ) : null;
    ?>
    <div class="srl-protest-meta-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
        <div style="background: #f9f9f9; padding: 12px; border-left: 4px solid #28a745; border-radius: 4px;">
            <strong>🟢 Piloto Demandante:</strong><br>
            <span style="font-size: 1.1em;"><?php echo esc_html( $protester ? $protester->full_name : 'No asignado' ); ?></span>
        </div>
        <div style="background: #f9f9f9; padding: 12px; border-left: 4px solid #dc3545; border-radius: 4px;">
            <strong>🔴 Piloto Acusado:</strong><br>
            <span style="font-size: 1.1em;"><?php echo esc_html( $accused ? $accused->full_name : 'No asignado' ); ?></span>
        </div>
    </div>

    <table class="form-table" style="margin-top: 0;">
        <tr>
            <th scope="row" style="width: 150px;">Evento / Gran Premio:</th>
            <td><strong><?php echo esc_html( $event ? $event->post_title : 'No seleccionado' ); ?></strong></td>
        </tr>
        <tr>
            <th scope="row">Vuelta / Momento:</th>
            <td><code><?php echo esc_html( $lap_timecode ?: 'No especificado' ); ?></code></td>
        </tr>
        <tr>
            <th scope="row">Descripción de los hechos:</th>
            <td><div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px; white-space: pre-wrap;"><?php echo esc_html( $description ?: 'Sin descripción' ); ?></div></td>
        </tr>
        <tr>
            <th scope="row">Evidencias en Video:</th>
            <td>
                <?php if ( ! empty( $evidence_urls ) ) : ?>
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php foreach ( $evidence_urls as $url ) : ?>
                            <li style="margin-bottom: 8px;">
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-small" style="display: inline-flex; align-items: center; gap: 5px;">
                                    <span class="dashicons dashicons-video-alt3"></span> Ver Video: <?php echo esc_html( wp_trim_words( $url, 6 ) ); ?> ↗
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <span style="color: #999;">No se adjuntaron enlaces de video.</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Meta box: AI Verdict display and Dispatch Trigger.
 */
function srl_render_protest_ai_verdict_meta_box( $post ) {
    $status = get_post_meta( $post->ID, '_srl_ai_status', true ) ?: 'pending';
    $error = get_post_meta( $post->ID, '_srl_ai_error', true );
    $verdict_raw = get_post_meta( $post->ID, '_srl_ai_verdict', true );
    $verdict = is_string( $verdict_raw ) ? json_decode( $verdict_raw, true ) : $verdict_raw;
    $processed_at = get_post_meta( $post->ID, '_srl_ai_processed_at', true );
    $last_probe = get_post_meta( $post->ID, '_srl_last_probe_log', true );

    wp_nonce_field( 'srl_commissary_dispatch_nonce', 'srl_commissary_nonce' );
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 10px;">
        <div>
            <strong>Estado IA: </strong>
            <?php
            switch ( $status ) {
                case 'completed': echo '<span class="dashicons dashicons-yes" style="color: green;"></span> <strong style="color: green;">Completado</strong> (' . esc_html( $processed_at ) . ')'; break;
                case 'processing': echo '<span class="spinner is-active" style="float:none;"></span> <strong style="color: #0073aa;">Procesando en n8n...</strong>'; break;
                case 'failed': echo '<span class="dashicons dashicons-no" style="color: red;"></span> <strong style="color: red;">Error en procesamiento</strong>'; break;
                default: echo '<strong style="color: #856404;">Pendiente de envío</strong>'; break;
            }
            ?>
            <?php if ( ! empty( $last_probe ) ) : ?>
                <div id="srl-last-probe-info" style="font-size: 11px; color: #555; margin-top: 5px;">
                    <strong>Último sondeo:</strong> <?php echo esc_html( $last_probe['time'] ); ?> &mdash;
                    <code>HTTP <?php echo esc_html( $last_probe['http_code'] ); ?></code> &mdash;
                    <em><?php echo esc_html( $last_probe['message'] ); ?></em>
                </div>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php if ( $status === 'processing' || $status === 'failed' ) : ?>
                <button type="button" id="srl-check-verdict-now-btn" class="button" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <span class="dashicons dashicons-search" style="vertical-align: middle; font-size: 15px;"></span>
                    Comprobar Veredicto Ahora
                </button>
            <?php endif; ?>

            <button type="button" id="srl-dispatch-ai-btn" class="button button-primary" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                <span class="dashicons dashicons-update" style="vertical-align: middle; font-size: 16px;"></span>
                <?php echo ( $status === 'completed' ) ? 'Re-analizar con Comisariato Virtual' : 'Enviar a Comisariato Virtual (n8n)'; ?>
            </button>
            <span id="srl-dispatch-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
        </div>
    </div>
    <div id="srl-dispatch-msg"></div>

    <?php if ( $status === 'failed' && ! empty( $error ) ) : ?>
        <div class="notice notice-error inline"><p><strong>Error retornado:</strong> <?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $verdict ) && is_array( $verdict ) ) : ?>
        <?php
        $chief = ! empty( $verdict['chief_steward'] ) ? $verdict['chief_steward'] : $verdict;
        $strict = $verdict['persona_strict'] ?? [];
        $lax = $verdict['persona_lax'] ?? [];
        $balanced = $verdict['persona_balanced'] ?? [];
        $has_personas = ! empty( $strict ) || ! empty( $lax ) || ! empty( $balanced );
        ?>
        <!-- Chief Steward Final Consensus Card -->
        <div style="background: linear-gradient(135deg, #1e1e2f 0%, #2a2a40 100%); color: #fff; padding: 18px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #ff4757; font-size: 1.3em;">👨‍⚖️ Dictamen Consensuado: Comisario Jefe</h3>
                <?php if ( ! empty( $chief['insufficient_evidence'] ) ) : ?>
                    <span style="background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">⚠️ Falta de Pruebas / Desestimado</span>
                <?php endif; ?>
            </div>

            <!-- Blame Percentage Bar -->
            <?php
            $blame_p = intval( $chief['fault_protesting'] ?? $chief['blame_protesting'] ?? 0 );
            $blame_a = intval( $chief['fault_accused'] ?? $chief['blame_accused'] ?? 0 );
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: bold;">
                    <span style="color: #2ed573;">🟢 Demandante: <?php echo $blame_p; ?>%</span>
                    <span style="color: #ff6b81;">🔴 Acusado: <?php echo $blame_a; ?>%</span>
                </div>
                <div style="width: 100%; height: 16px; background: #ff4757; border-radius: 8px; overflow: hidden; display: flex;">
                    <div style="width: <?php echo $blame_p; ?>%; background: #2ed573; height: 100%;"></div>
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <strong style="color: #ffd32a;">Sanción Recomendada:</strong>
                <p style="margin: 4px 0 0; font-size: 1.1em; color: #fff; font-weight: 600;"><?php echo esc_html( $chief['penalty'] ?? $chief['recommended_penalty'] ?? 'Sin sanción' ); ?></p>
            </div>

            <div>
                <strong style="color: #ffd32a;">Fundamentación del Dictamen:</strong>
                <p style="margin: 4px 0 0; line-height: 1.5; color: #dcdde1;"><?php echo esc_html( $chief['rationale'] ?? $chief['argument'] ?? '' ); ?></p>
            </div>
        </div>

        <?php if ( $has_personas ) : ?>
        <!-- 3 Persona Cards Grid -->
        <h4 style="margin: 15px 0 10px; font-size: 1.1em;">🎭 Evaluaciones Individuales por Persona</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 15px;">
            <!-- Strict Persona -->
            <div style="background: #fff; border: 1px solid #ddd; border-top: 4px solid #e74c3c; border-radius: 4px; padding: 12px;">
                <h4 style="margin: 0 0 8px; color: #c0392b;">📏 Persona A: Estricto</h4>
                <p style="font-size: 0.85em; color: #7f8c8d; margin-top: 0;">Apego estricto y literal al reglamento.</p>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Reparto de Culpa:</strong><br>
                    🟢 <?php echo intval($strict['fault_protesting'] ?? $strict['blame_protesting'] ?? 0); ?>% / 🔴 <?php echo intval($strict['fault_accused'] ?? $strict['blame_accused'] ?? 0); ?>%
                </div>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Sanción:</strong> <em><?php echo esc_html($strict['recommended_penalty'] ?? $strict['penalty'] ?? '-'); ?></em>
                </div>
                <div style="font-size: 0.85em; color: #444; max-height: 150px; overflow-y: auto; background: #fdfdfd; padding: 6px; border: 1px solid #f0f0f0;">
                    <?php echo esc_html($strict['argument'] ?? ''); ?>
                </div>
            </div>

            <!-- Lax Persona -->
            <div style="background: #fff; border: 1px solid #ddd; border-top: 4px solid #f39c12; border-radius: 4px; padding: 12px;">
                <h4 style="margin: 0 0 8px; color: #d35400;">🏁 Persona B: Permisivo</h4>
                <p style="font-size: 0.85em; color: #7f8c8d; margin-top: 0;">Filosofía "Turismo Carretera" / "Rubbing is racing".</p>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Reparto de Culpa:</strong><br>
                    🟢 <?php echo intval($lax['fault_protesting'] ?? $lax['blame_protesting'] ?? 0); ?>% / 🔴 <?php echo intval($lax['fault_accused'] ?? $lax['blame_accused'] ?? 0); ?>%
                </div>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Sanción:</strong> <em><?php echo esc_html($lax['recommended_penalty'] ?? $lax['penalty'] ?? '-'); ?></em>
                </div>
                <div style="font-size: 0.85em; color: #444; max-height: 150px; overflow-y: auto; background: #fdfdfd; padding: 6px; border: 1px solid #f0f0f0;">
                    <?php echo esc_html($lax['argument'] ?? ''); ?>
                </div>
            </div>

            <!-- Balanced Persona -->
            <div style="background: #fff; border: 1px solid #ddd; border-top: 4px solid #3498db; border-radius: 4px; padding: 12px;">
                <h4 style="margin: 0 0 8px; color: #2980b9;">⚖️ Persona C: Equilibrado</h4>
                <p style="font-size: 0.85em; color: #7f8c8d; margin-top: 0;">Métricas de solapamiento y derechos de curva.</p>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Reparto de Culpa:</strong><br>
                    🟢 <?php echo intval($balanced['fault_protesting'] ?? $balanced['blame_protesting'] ?? 0); ?>% / 🔴 <?php echo intval($balanced['fault_accused'] ?? $balanced['blame_accused'] ?? 0); ?>%
                </div>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Sanción:</strong> <em><?php echo esc_html($balanced['recommended_penalty'] ?? $balanced['penalty'] ?? '-'); ?></em>
                </div>
                <div style="font-size: 0.85em; color: #444; max-height: 150px; overflow-y: auto; background: #fdfdfd; padding: 6px; border: 1px solid #f0f0f0;">
                    <?php echo esc_html($balanced['argument'] ?? ''); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

/**
 * Meta box: Human Commissary Decision & Override.
 */
function srl_render_protest_human_decision_meta_box( $post ) {
    wp_nonce_field( 'srl_save_protest_human_decision', 'srl_human_decision_nonce' );

    $action_status = get_post_meta( $post->ID, '_srl_steward_action_status', true ) ?: 'under_review';
    $notes = get_post_meta( $post->ID, '_srl_steward_notes', true );
    ?>
    <p>
        <label for="srl_steward_action_status"><strong>Resolución Oficial:</strong></label>
        <select name="srl_steward_action_status" id="srl_steward_action_status" style="width: 100%; margin-top: 5px;">
            <option value="under_review" <?php selected( $action_status, 'under_review' ); ?>>⏳ En Revisión</option>
            <option value="resolved" <?php selected( $action_status, 'resolved' ); ?>>✅ Sanción / Veredicto Aplicado</option>
            <option value="racing_incident" <?php selected( $action_status, 'racing_incident' ); ?>>🏎️ Incidente de Carrera</option>
            <option value="dismissed" <?php selected( $action_status, 'dismissed' ); ?>>❌ Desestimado / Sin lugar</option>
        </select>
    </p>
    <p>
        <label for="srl_steward_notes"><strong>Notas del Comisario Humano:</strong></label>
        <textarea name="srl_steward_notes" id="srl_steward_notes" rows="6" style="width: 100%; margin-top: 5px;" placeholder="Agrega aquí notas internas o comentarios para la resolución final..."><?php echo esc_textarea( $notes ); ?></textarea>
    </p>
    <?php
}

/**
 * Save human decision metadata when post is saved.
 */
function srl_save_protest_meta( $post_id ) {
    if ( ! isset( $_POST['srl_human_decision_nonce'] ) || ! wp_verify_nonce( $_POST['srl_human_decision_nonce'], 'srl_save_protest_human_decision' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['srl_steward_action_status'] ) ) {
        update_post_meta( $post_id, '_srl_steward_action_status', sanitize_text_field( $_POST['srl_steward_action_status'] ) );
    }
    if ( isset( $_POST['srl_steward_notes'] ) ) {
        update_post_meta( $post_id, '_srl_steward_notes', sanitize_textarea_field( $_POST['srl_steward_notes'] ) );
    }
}
add_action( 'save_post_srl_protest', 'srl_save_protest_meta' );

/**
 * AJAX Handler: Dispatch protest to n8n Virtual Commissary Webhook.
 */
function srl_handle_dispatch_virtual_commissary() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'No tienes permisos para realizar esta acción.' ] );
    }

    $protest_id = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
    if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
        wp_send_json_error( [ 'message' => 'ID de protesta no válido.' ] );
    }

    $webhook_url = get_option( 'srl_virtual_commissary_webhook_url', '' );
    if ( empty( $webhook_url ) ) {
        wp_send_json_error( [ 'message' => 'No has configurado la URL del Webhook de n8n en los ajustes de Gestión SRL.' ] );
    }

    global $wpdb;
    $event_id = get_post_meta( $protest_id, '_srl_event_id', true );
    $protester_id = get_post_meta( $protest_id, '_srl_protesting_driver_id', true );
    $accused_id = get_post_meta( $protest_id, '_srl_accused_driver_id', true );
    $lap_timecode = get_post_meta( $protest_id, '_srl_lap_timecode', true );
    $description = get_post_meta( $protest_id, '_srl_incident_description', true );
    $evidence_raw = get_post_meta( $protest_id, '_srl_evidence_urls', true );
    $evidence_urls = is_array( $evidence_raw ) ? $evidence_raw : array_filter( array_map( 'trim', explode( "\n", (string)$evidence_raw ) ) );

    $event = $event_id ? get_post( $event_id ) : null;
    $champ_id = $event ? get_post_meta( $event->ID, '_srl_parent_championship', true ) : null;
    $champ = $champ_id ? get_post( $champ_id ) : null;

    $protester_name = 'Desconocido';
    $accused_name = 'Desconocido';
    if ( $protester_id ) {
        $p = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) );
        if ( $p ) $protester_name = $p->full_name;
    }
    if ( $accused_id ) {
        $a = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
        if ( $a ) $accused_name = $a->full_name;
    }

    $api_secret = get_option( 'srl_api_secret_key', '' );

    $payload = [
        'protest_id'        => $protest_id,
        'event_id'          => $event_id,
        'event_name'        => $event ? $event->post_title : '',
        'championship_name' => $champ ? $champ->post_title : '',
        'protesting_driver' => [
            'id'   => $protester_id,
            'name' => $protester_name,
        ],
        'accused_driver'    => [
            'id'   => $accused_id,
            'name' => $accused_name,
        ],
        'lap_timecode'      => $lap_timecode,
        'description'       => $description,
        'evidence_urls'     => array_values( $evidence_urls ),
        'rulebook_url'      => rest_url( 'srl/v1/rulebook' ),
        'callback_url'      => rest_url( 'srl/v1/protest-update' ),
        'api_key'           => $api_secret,
    ];

    // Update status to processing
    update_post_meta( $protest_id, '_srl_ai_status', 'processing' );
    update_post_meta( $protest_id, '_srl_ai_error', '' );

    // Dispatch HTTP POST to n8n webhook
    $response = wp_remote_post( $webhook_url, [
        'headers'     => [
            'Content-Type'   => 'application/json; charset=utf-8',
            'X-SRL-API-KEY'  => $api_secret,
        ],
        'body'        => wp_json_encode( $payload ),
        'timeout'     => 15,
        'blocking'    => false, // Asynchronous to avoid blocking admin UI
    ] );

    if ( is_wp_error( $response ) ) {
        update_post_meta( $protest_id, '_srl_ai_status', 'failed' );
        update_post_meta( $protest_id, '_srl_ai_error', $response->get_error_message() );
        wp_send_json_error( [ 'message' => 'Error al conectar con n8n: ' . $response->get_error_message() ] );
    }

    wp_send_json_success( [
        'message' => '¡Reclamo enviado exitosamente al Comisariato Virtual! El análisis de Gemini IA se procesará en segundo plano.',
        'status'  => 'processing',
    ] );
}
add_action( 'wp_ajax_srl_dispatch_virtual_commissary', 'srl_handle_dispatch_virtual_commissary' );
