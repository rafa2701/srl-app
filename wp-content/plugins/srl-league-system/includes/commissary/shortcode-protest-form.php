<?php
/**
 * Frontend Shortcode for Incident Protests Form
 * Location: wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_shortcode( 'srl_protest_form', 'srl_render_protest_form_shortcode' );

/**
 * Render [srl_protest_form]
 */
function srl_render_protest_form_shortcode( $atts ) {
    global $wpdb;

    $championships = get_posts( [
        'post_type'      => 'srl_championship',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $drivers = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}srl_drivers ORDER BY full_name ASC" );

    ob_start();
    ?>
    <div class="srl-app-container srl-protest-form-wrapper">
        <div class="srl-section-header">
            <h2>🚨 Formulario de Denuncias</h2>
            <p style="color: #aaa; margin-top: 5px;">Envía una denuncia de incidente en pista para revisión del Comisariato. Proporciona enlaces o sube videos de evidencia claros.</p>
        </div>

        <form id="srl-public-protest-form" class="srl-form" method="post">
            <?php wp_nonce_field( 'srl-public-nonce', 'protest_nonce' ); ?>

            <div class="srl-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="srl-form-group">
                    <label for="srl_protest_champ_select"><strong>1. Campeonato *</strong></label>
                    <select name="championship_id" id="srl_protest_champ_select" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona el Campeonato --</option>
                        <?php foreach ( $championships as $champ ) : ?>
                            <option value="<?php echo esc_attr( $champ->ID ); ?>"><?php echo esc_html( $champ->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="srl-form-group">
                    <label for="srl_protest_event_select"><strong>2. Gran Premio / Evento *</strong></label>
                    <select name="event_id" id="srl_protest_event_select" required class="srl-input" style="width: 100%;" disabled>
                        <option value="">-- Primero selecciona campeonato --</option>
                    </select>
                </div>
            </div>

            <div class="srl-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="srl-form-group">
                    <label for="protesting_driver_id"><strong>3. Tu Nombre de Piloto (Demandante) *</strong></label>
                    <select name="protesting_driver_id" id="protesting_driver_id" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona tu piloto --</option>
                        <?php foreach ( $drivers as $d ) : ?>
                            <option value="<?php echo esc_attr( $d->id ); ?>"><?php echo esc_html( $d->full_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="srl-form-group">
                    <label for="accused_driver_id"><strong>4. Piloto Involucrado / Acusado *</strong></label>
                    <select name="accused_driver_id" id="accused_driver_id" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona el piloto acusado --</option>
                        <?php foreach ( $drivers as $d ) : ?>
                            <option value="<?php echo esc_attr( $d->id ); ?>"><?php echo esc_html( $d->full_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="srl-form-group" style="margin-bottom: 15px;">
                <label for="lap_timecode"><strong>5. Vuelta / Minuto de la carrera *</strong></label>
                <input type="text" name="lap_timecode" id="lap_timecode" required class="srl-input" style="width: 100%;" placeholder="Ej: Vuelta 7 curva 4 / Minuto 14:32" />
            </div>

            <div class="srl-form-group" style="margin-bottom: 15px;">
                <label for="incident_description"><strong>6. Descripción detallada de lo sucedido *</strong></label>
                <textarea name="incident_description" id="incident_description" rows="5" required class="srl-input" style="width: 100%;" placeholder="Explica la maniobra, trazada, intento de adelantamiento y por qué consideras que hubo infracción..."></textarea>
            </div>

            <div class="srl-form-group" style="margin-bottom: 20px;">
                <label for="evidence_urls"><strong>7. Enlaces de Video de Evidencia (YouTube, Twitch, Discord, Cloudflare R2) *</strong></label>
                <textarea name="evidence_urls" id="evidence_urls" rows="3" required class="srl-input" style="width: 100%; font-family: monospace;" placeholder="https://youtube.com/watch?v=...&#10;https://cdn.discordapp.com/attachments/..."></textarea>
                <small style="color: #888;">Pega uno o más enlaces directos a videos de repetición (onboard, TV cam, vista aérea). Un enlace por línea.</small>
            </div>

            <div class="srl-form-submit">
                <button type="submit" id="srl-submit-protest-btn" class="srl-button" style="padding: 12px 24px; font-size: 1.1em; background: #e60000; color: #fff; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;">
                    Enviar Denuncia al Comisariato
                </button>
            </div>
            <div id="srl-protest-response" style="margin-top: 15px;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler: Public protest submission.
 */
function srl_handle_submit_protest_form() {
    check_ajax_referer( 'srl-public-nonce', 'nonce' );

    $event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
    $protesting_id = isset( $_POST['protesting_driver_id'] ) ? intval( $_POST['protesting_driver_id'] ) : 0;
    $accused_id = isset( $_POST['accused_driver_id'] ) ? intval( $_POST['accused_driver_id'] ) : 0;
    $lap_timecode = isset( $_POST['lap_timecode'] ) ? sanitize_text_field( $_POST['lap_timecode'] ) : '';
    $description = isset( $_POST['incident_description'] ) ? sanitize_textarea_field( $_POST['incident_description'] ) : '';
    $evidence_raw = isset( $_POST['evidence_urls'] ) ? sanitize_textarea_field( $_POST['evidence_urls'] ) : '';

    if ( ! $event_id || ! $protesting_id || ! $accused_id || empty( $description ) || empty( $evidence_raw ) ) {
        wp_send_json_error( [ 'message' => 'Por favor completa todos los campos requeridos.' ] );
    }

    if ( $protesting_id === $accused_id ) {
        wp_send_json_error( [ 'message' => 'El piloto demandante y el acusado no pueden ser la misma persona.' ] );
    }

    global $wpdb;
    $p_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protesting_id ) );
    $a_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
    $event_post = get_post( $event_id );
    $event_title = $event_post ? $event_post->post_title : 'Evento #' . $event_id;

    $evidence_urls = array_values( array_filter( array_map( 'trim', explode( "\n", $evidence_raw ) ) ) );

    // Create srl_protest post
    $post_title = sprintf( 'Denuncia: %s vs %s (%s)', $p_name ?: 'Piloto', $a_name ?: 'Piloto', $event_title );
    $post_id = wp_insert_post( [
        'post_title'   => $post_title,
        'post_type'    => 'srl_protest',
        'post_status'  => 'publish',
    ] );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Error al guardar la denuncia en el sistema.' ] );
    }

    // Save post meta
    update_post_meta( $post_id, '_srl_event_id', $event_id );
    update_post_meta( $post_id, '_srl_protesting_driver_id', $protesting_id );
    update_post_meta( $post_id, '_srl_accused_driver_id', $accused_id );
    update_post_meta( $post_id, '_srl_lap_timecode', $lap_timecode );
    update_post_meta( $post_id, '_srl_incident_description', $description );
    update_post_meta( $post_id, '_srl_evidence_urls', $evidence_urls );
    update_post_meta( $post_id, '_srl_ai_status', 'pending' );
    update_post_meta( $post_id, '_srl_steward_action_status', 'under_review' );

    wp_send_json_success( [
        'message'    => '¡Denuncia registrada con éxito (ID #' . $post_id . ')! Ha sido enviada a los comisarios para su revisión.',
        'protest_id' => $post_id,
    ] );
}
add_action( 'wp_ajax_srl_submit_protest_form', 'srl_handle_submit_protest_form' );
add_action( 'wp_ajax_nopriv_srl_submit_protest_form', 'srl_handle_submit_protest_form' );
