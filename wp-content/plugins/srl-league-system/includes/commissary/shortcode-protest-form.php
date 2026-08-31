<?php
/**
 * Frontend Shortcode for Incident Denuncias Form with Searchable Drivers & R2 Direct Uploads
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

    // 1. Filtrar campeonatos activos del año en curso
    $current_year = date( 'Y' );
    $championships = get_posts( [
        'post_type'      => 'srl_championship',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'date_query'     => [
            [
                'after'     => $current_year . '-01-01 00:00:00',
                'inclusive' => true,
            ],
        ],
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => '_srl_status',
                'value'   => 'completed',
                'compare' => '!=',
            ],
            [
                'key'     => '_srl_status',
                'compare' => 'NOT EXISTS',
            ],
        ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    // Fallback: Si no hay campeonatos creados este año, mostrar todos los no finalizados
    if ( empty( $championships ) ) {
        $championships = get_posts( [
            'post_type'      => 'srl_championship',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_srl_status',
                    'value'   => 'completed',
                    'compare' => '!=',
                ],
                [
                    'key'     => '_srl_status',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'orderby'        => 'post_date',
            'order'          => 'DESC',
        ] );
    }

    $drivers = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}srl_drivers ORDER BY full_name ASC" );

    ob_start();
    ?>
    <div class="srl-app-container srl-protest-form-wrapper">
        <div class="srl-section-header">
            <h2>🚨 Formulario de Reclamos</h2>
            <p style="color: #aaa; margin-top: 5px;">Envía un reclamo de incidente en pista para revisión del Comisariato. Proporciona enlaces o sube videos de evidencia claros.</p>
        </div>

        <form id="srl-public-protest-form" class="srl-form" method="post">
            <?php wp_nonce_field( 'srl-public-nonce', 'protest_nonce' ); ?>

            <div class="srl-form-row srl-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="srl-form-group">
                    <label for="srl_protest_champ_select"><strong>1. Campeonato Activo *</strong></label>
                    <select name="championship_id" id="srl_protest_champ_select" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona el Campeonato --</option>
                        <?php foreach ( $championships as $champ ) : ?>
                            <option value="<?php echo esc_attr( $champ->ID ); ?>"><?php echo esc_html( $champ->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="srl-form-group" id="srl-event-selector-group">
                    <label for="srl_protest_event_select"><strong>2. Gran Premio / Evento *</strong></label>
                    <?php $event_mode = get_option( 'srl_commissary_event_input_mode', 'hybrid' ); ?>
                    
                    <?php if ( $event_mode === 'always_free_text' ) : ?>
                        <input type="text" name="custom_event_name" id="srl_custom_event_name" required class="srl-input" style="width: 100%;" placeholder="Ej: Fecha 2 - Spa-Francorchamps / GP de Monza" />
                        <input type="hidden" name="event_id" value="custom" />
                    <?php else : ?>
                        <select name="event_id" id="srl_protest_event_select" required class="srl-input" style="width: 100%;" disabled>
                            <option value="">-- Primero selecciona campeonato --</option>
                        </select>
                        <div id="srl-custom-event-wrapper" style="display: none; margin-top: 8px;">
                            <input type="text" name="custom_event_name" id="srl_custom_event_name" class="srl-input" style="width: 100%;" placeholder="Ej: Fecha 2 - Spa-Francorchamps / GP de Monza" />
                            <small style="color: #aaa; display: block; margin-top: 4px;">Escribe el nombre del Gran Premio si no figura en la lista.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="srl-form-row srl-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <!-- Demandante Combobox -->
                <div class="srl-form-group">
                    <label><strong>3. Tu Nombre de Piloto (Demandante) *</strong></label>
                    <div class="srl-driver-combobox" id="combobox-protesting-driver" data-field-name="protesting_driver_id">
                        <div class="srl-combobox-selected-badge" style="display: none;">
                            <span class="srl-selected-name"></span>
                            <button type="button" class="srl-combobox-clear-btn" title="Cambiar piloto">&times;</button>
                        </div>
                        <div class="srl-combobox-search-box">
                            <input type="text" class="srl-input srl-combobox-input" placeholder="🔍 Escribe para buscar tu piloto..." autocomplete="off" />
                            <div class="srl-combobox-dropdown" style="display: none;">
                                <?php foreach ( $drivers as $d ) : ?>
                                    <div class="srl-combobox-item" data-id="<?php echo esc_attr( $d->id ); ?>" data-name="<?php echo esc_attr( $d->full_name ); ?>">
                                        <?php echo esc_html( $d->full_name ); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" name="protesting_driver_id" id="protesting_driver_id" required value="" />
                    </div>
                </div>

                <!-- Acusado Combobox -->
                <div class="srl-form-group">
                    <label><strong>4. Piloto Involucrado / Acusado *</strong></label>
                    <div class="srl-driver-combobox" id="combobox-accused-driver" data-field-name="accused_driver_id">
                        <div class="srl-combobox-selected-badge" style="display: none;">
                            <span class="srl-selected-name"></span>
                            <button type="button" class="srl-combobox-clear-btn" title="Cambiar piloto">&times;</button>
                        </div>
                        <div class="srl-combobox-search-box">
                            <input type="text" class="srl-input srl-combobox-input" placeholder="🔍 Escribe para buscar piloto acusado..." autocomplete="off" />
                            <div class="srl-combobox-dropdown" style="display: none;">
                                <?php foreach ( $drivers as $d ) : ?>
                                    <div class="srl-combobox-item" data-id="<?php echo esc_attr( $d->id ); ?>" data-name="<?php echo esc_attr( $d->full_name ); ?>">
                                        <?php echo esc_html( $d->full_name ); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" name="accused_driver_id" id="accused_driver_id" required value="" />
                    </div>
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
                <label for="evidence_urls"><strong>7. Enlaces y Videos de Evidencia *</strong></label>
                
                <!-- Direct Upload Dropzone -->
                <?php
                $r2_active = class_exists( 'SRL_R2_Uploader' ) && SRL_R2_Uploader::is_enabled();
                $upload_limit_label = $r2_active ? 'Hasta 100MB (Cloudflare R2)' : 'Límite de este servidor: ' . size_format( wp_max_upload_size() );
                ?>
                <div class="srl-evidence-uploader-zone" id="srl-evidence-dropzone" style="margin-bottom: 10px; border: 2px dashed #444; border-radius: 6px; padding: 15px; text-align: center; background: rgba(255,255,255,0.02); cursor: pointer;">
                    <div class="srl-uploader-prompt">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e60000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p style="margin: 0; font-size: 14px; color: #eee;"><strong>Arrastra un archivo de video/captura aquí</strong> o haz clic para seleccionarlo</p>
                        <small style="color: #888;">Formatos permitidos: MP4, WebM, MOV, AVI, MKV, PNG, JPG (<?php echo esc_html( $upload_limit_label ); ?>)</small>
                    </div>
                    
                    <div class="srl-upload-progress-container" style="display: none; margin-top: 10px;">
                        <div class="srl-upload-progress-bar" style="height: 6px; background: #222; border-radius: 3px; overflow: hidden;">
                            <div class="srl-upload-progress-fill" style="width: 0%; height: 100%; background: #e60000; transition: width 0.2s;"></div>
                        </div>
                        <small class="srl-upload-status-text" style="color: #aaa; margin-top: 5px; display: block;">Subiendo evidencia...</small>
                    </div>
                </div>
                <input type="file" id="srl-evidence-file-input" accept="video/*,image/*,.mkv,.avi,.mov,.mp4,.webm,.png,.jpg,.jpeg" style="display: none;" />

                <textarea name="evidence_urls" id="evidence_urls" rows="3" required class="srl-input" style="width: 100%; font-family: monospace;" placeholder="https://youtube.com/watch?v=...&#10;https://media.simracinglatinoamerica.com/..."></textarea>
                <small style="color: #888;">Pega uno o más enlaces directos a videos de repetición (YouTube, Twitch, Discord) o usa el botón de subida superior. Un enlace por línea.</small>
            </div>

            <div class="srl-form-submit">
                <button type="submit" id="srl-submit-protest-btn" class="srl-button" style="padding: 12px 24px; font-size: 1.1em; background: #e60000; color: #fff; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;">
                    Enviar Reclamo al Comisariato
                </button>
            </div>
            <div id="srl-protest-response" style="margin-top: 15px;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Filter to allow common video and image evidence upload MIME types.
 */
function srl_allow_commissary_evidence_mimes( $mimes ) {
    $mimes['mp4']  = 'video/mp4';
    $mimes['webm'] = 'video/webm';
    $mimes['mov']  = 'video/quicktime';
    $mimes['avi']  = 'video/x-msvideo';
    $mimes['mkv']  = 'video/x-matroska';
    $mimes['png']  = 'image/png';
    $mimes['jpg|jpeg|jpe'] = 'image/jpeg';
    return $mimes;
}

/**
 * AJAX Handler: Public evidence file direct upload (Cloudflare R2 or local WP).
 */
function srl_handle_upload_evidence_file() {
    $max_formatted = size_format( wp_max_upload_size() );

    // Check if POST was truncated by PHP due to post_max_size
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && empty( $_POST ) && empty( $_FILES ) && isset( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > 0 ) {
        wp_send_json_error( [
            'message' => sprintf( 'El archivo seleccionado supera el tamaño máximo permitido por el servidor (%s). Pega un enlace de video abajo (YouTube/Drive/Twitch) o sube un archivo más liviano.', $max_formatted )
        ] );
    }

    $nonce = '';
    if ( ! empty( $_REQUEST['nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
    } elseif ( ! empty( $_REQUEST['protest_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['protest_nonce'] ) );
    } elseif ( ! empty( $_REQUEST['_ajax_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
    } elseif ( ! empty( $_REQUEST['_wpnonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    }

    if ( ! srl_verify_public_nonce( $nonce, 'srl-public-nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Sesión de seguridad expirada. Por favor recarga la página e intenta de nuevo.' ] );
    }

    if ( empty( $_FILES['evidence_file'] ) ) {
        wp_send_json_error( [
            'message' => sprintf( 'No se recibió el archivo en el servidor. Puede haber superado el límite de subida permitido (%s).', $max_formatted )
        ] );
    }

    $file = $_FILES['evidence_file'];

    if ( ! empty( $file['error'] ) ) {
        switch ( $file['error'] ) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                wp_send_json_error( [ 'message' => sprintf( 'El archivo excede el tamaño máximo permitido por el servidor (%s).', $max_formatted ) ] );
                break;
            case UPLOAD_ERR_PARTIAL:
                wp_send_json_error( [ 'message' => 'La subida del archivo se interrumpió y quedó incompleta.' ] );
                break;
            case UPLOAD_ERR_NO_FILE:
                wp_send_json_error( [ 'message' => 'No se seleccionó ningún archivo.' ] );
                break;
            default:
                wp_send_json_error( [ 'message' => 'Error al subir el archivo (Código de error PHP: ' . intval( $file['error'] ) . ').' ] );
                break;
        }
    }

    $allowed_extensions = [ 'mp4', 'webm', 'mov', 'avi', 'mkv', 'png', 'jpg', 'jpeg' ];
    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

    if ( ! in_array( $ext, $allowed_extensions, true ) ) {
        wp_send_json_error( [ 'message' => 'Formato de archivo no permitido (.' . $ext . '). Sube un archivo de video o imagen permitido (' . implode( ', ', $allowed_extensions ) . ').' ] );
    }

    // Check Cloudflare R2
    if ( class_exists( 'SRL_R2_Uploader' ) && SRL_R2_Uploader::is_enabled() ) {
        $mime_type = ! empty( $file['type'] ) ? $file['type'] : 'video/' . $ext;
        $r2_result = SRL_R2_Uploader::upload_file( $file['tmp_name'], $file['name'], $mime_type );

        if ( ! empty( $r2_result['success'] ) && ! empty( $r2_result['url'] ) ) {
            wp_send_json_success( [
                'url'      => $r2_result['url'],
                'filename' => $file['name'],
                'storage'  => 'r2',
            ] );
        } else {
            error_log( 'SRL R2 Upload Error: ' . ( $r2_result['error'] ?? 'Unknown R2 error' ) );
        }
    }

    // Fallback: WordPress Media Upload
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    add_filter( 'upload_mimes', 'srl_allow_commissary_evidence_mimes' );
    $upload_overrides = [ 'test_form' => false ];
    $movefile = wp_handle_upload( $file, $upload_overrides );
    remove_filter( 'upload_mimes', 'srl_allow_commissary_evidence_mimes' );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        wp_send_json_success( [
            'url'      => $movefile['url'],
            'filename' => $file['name'],
            'storage'  => 'local',
        ] );
    } else {
        wp_send_json_error( [ 'message' => $movefile['error'] ?? 'Error al guardar el archivo en el servidor.' ] );
    }
}
add_action( 'wp_ajax_srl_upload_evidence_file', 'srl_handle_upload_evidence_file' );
add_action( 'wp_ajax_nopriv_srl_upload_evidence_file', 'srl_handle_upload_evidence_file' );

/**
 * AJAX Handler: Public protest submission.
 */
function srl_handle_submit_protest_form() {
    $nonce = '';
    if ( ! empty( $_REQUEST['nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
    } elseif ( ! empty( $_REQUEST['protest_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['protest_nonce'] ) );
    } elseif ( ! empty( $_REQUEST['_ajax_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
    } elseif ( ! empty( $_REQUEST['_wpnonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    }

    if ( ! srl_verify_public_nonce( $nonce, 'srl-public-nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Sesión de seguridad expirada. Por favor recarga la página e intenta de nuevo.' ] );
    }

    $championship_id = isset( $_POST['championship_id'] ) ? intval( $_POST['championship_id'] ) : 0;
    $raw_event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( $_POST['event_id'] ) : '';
    $custom_event_name = isset( $_POST['custom_event_name'] ) ? sanitize_text_field( $_POST['custom_event_name'] ) : '';
    $protesting_id = isset( $_POST['protesting_driver_id'] ) ? intval( $_POST['protesting_driver_id'] ) : 0;
    $accused_id = isset( $_POST['accused_driver_id'] ) ? intval( $_POST['accused_driver_id'] ) : 0;
    $lap_timecode = isset( $_POST['lap_timecode'] ) ? sanitize_text_field( $_POST['lap_timecode'] ) : '';
    $description = isset( $_POST['incident_description'] ) ? sanitize_textarea_field( $_POST['incident_description'] ) : '';
    $evidence_raw = isset( $_POST['evidence_urls'] ) ? sanitize_textarea_field( $_POST['evidence_urls'] ) : '';

    if ( ! $protesting_id || ! $accused_id || empty( $description ) || empty( $evidence_raw ) ) {
        wp_send_json_error( [ 'message' => 'Por favor completa todos los campos requeridos.' ] );
    }

    if ( $protesting_id === $accused_id ) {
        wp_send_json_error( [ 'message' => 'El piloto demandante y el acusado no pueden ser la misma persona.' ] );
    }

    $event_id = 0;
    $event_title = '';

    // Handle Event resolution (existing ID vs custom text)
    if ( is_numeric( $raw_event_id ) && intval( $raw_event_id ) > 0 ) {
        $event_id = intval( $raw_event_id );
        $event_post = get_post( $event_id );
        $event_title = $event_post ? $event_post->post_title : 'Evento #' . $event_id;
    } elseif ( ! empty( $custom_event_name ) ) {
        $event_title = $custom_event_name;
        // Search if event with this exact title already exists for this championship
        $existing_events = get_posts( [
            'post_type'      => 'srl_event',
            'title'          => $custom_event_name,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_srl_parent_championship',
                    'value'   => $championship_id,
                    'compare' => '=',
                ],
            ],
        ] );

        if ( ! empty( $existing_events ) ) {
            $event_id = $existing_events[0]->ID;
        } else {
            // Automatically create event stub linked to the championship
            $new_event_id = wp_insert_post( [
                'post_title'   => $custom_event_name,
                'post_type'    => 'srl_event',
                'post_status'  => 'publish',
            ] );
            if ( $new_event_id && ! is_wp_error( $new_event_id ) ) {
                update_post_meta( $new_event_id, '_srl_parent_championship', $championship_id );
                $event_id = $new_event_id;
            }
        }
    } else {
        wp_send_json_error( [ 'message' => 'Por favor selecciona o escribe el Gran Premio / Evento.' ] );
    }

    global $wpdb;
    $p_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protesting_id ) );
    $a_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );

    $evidence_urls = array_values( array_filter( array_map( 'trim', explode( "\n", $evidence_raw ) ) ) );

    // Create srl_protest post
    $post_title = sprintf( 'Reclamo: %s vs %s (%s)', $p_name ?: 'Piloto', $a_name ?: 'Piloto', $event_title ?: 'Evento' );
    $post_id = wp_insert_post( [
        'post_title'   => $post_title,
        'post_type'    => 'srl_protest',
        'post_status'  => 'publish',
    ] );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Error al guardar el reclamo en el sistema.' ] );
    }

    // Save post meta
    update_post_meta( $post_id, '_srl_event_id', $event_id );
    if ( ! empty( $custom_event_name ) ) {
        update_post_meta( $post_id, '_srl_custom_event_name', $custom_event_name );
    }
    update_post_meta( $post_id, '_srl_protesting_driver_id', $protesting_id );
    update_post_meta( $post_id, '_srl_accused_driver_id', $accused_id );
    update_post_meta( $post_id, '_srl_lap_timecode', $lap_timecode );
    update_post_meta( $post_id, '_srl_incident_description', $description );
    update_post_meta( $post_id, '_srl_evidence_urls', $evidence_urls );
    update_post_meta( $post_id, '_srl_ai_status', 'pending' );
    update_post_meta( $post_id, '_srl_steward_action_status', 'under_review' );

    wp_send_json_success( [
        'message'    => '¡Reclamo registrado con éxito (ID #' . $post_id . ')! Ha sido enviado a los comisarios para su revisión.',
        'protest_id' => $post_id,
    ] );
}
add_action( 'wp_ajax_srl_submit_protest_form', 'srl_handle_submit_protest_form' );
add_action( 'wp_ajax_nopriv_srl_submit_protest_form', 'srl_handle_submit_protest_form' );
