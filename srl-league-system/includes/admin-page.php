<?php
/**
 * Renderiza el contenido de la página de administración del plugin.
 *
 * @package SRL_League_System
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function srl_render_admin_page() {
    global $wpdb;
    // Obtener campeonatos para el selector
    $championships = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}srl_championships ORDER BY name ASC" );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <!-- Formulario para subir resultados -->
        <div id="srl-upload-form-wrapper" style="max-width: 600px;">
            <h2>Importar Resultados de Carrera</h2>
            <form id="srl-results-upload-form" method="post" enctype="multipart/form-data">
                
                <!-- Selector de Campeonato -->
                <p>
                    <label for="srl-championship-select"><strong>1. Selecciona el Campeonato:</strong></label><br>
                    <select name="championship_id" id="srl-championship-select" style="width: 100%;">
                        <option value="">-- Elige un campeonato --</option>
                        <?php foreach ( $championships as $champ ) : ?>
                            <option value="<?php echo esc_attr( $champ->id ); ?>"><?php echo esc_html( $champ->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <!-- Selector de Evento (se rellena con JS) -->
                <p>
                    <label for="srl-event-select"><strong>2. Selecciona el Evento:</strong></label><br>
                    <select name="event_id" id="srl-event-select" style="width: 100%;" disabled>
                        <option value="">-- Primero elige un campeonato --</option>
                    </select>
                </p>

                <!-- Selector de Sesión (se rellena con JS) -->
                 <p>
                    <label for="srl-session-select"><strong>3. Selecciona la Sesión:</strong></label><br>
                    <select name="session_type" id="srl-session-select" style="width: 100%;" disabled>
                        <option value="">-- Primero elige un evento --</option>
                        <!-- Opciones se añadirán con JS, ej: Qualy, Race -->
                    </select>
                </p>

                <!-- Campo para subir archivo -->
                <p>
                    <label for="srl-results-file"><strong>4. Sube el archivo JSON de resultados:</strong></label><br>
                    <input type="file" name="results_file" id="srl-results-file" accept=".json">
                </p>

                <!-- Botón de envío -->
                <p>
                    <?php submit_button( 'Importar Resultados', 'primary', 'srl-submit-results', false ); ?>
                    <span class="spinner" style="float: none; vertical-align: middle;"></span>
                </p>
            </form>
            
            <!-- Contenedor para mensajes de respuesta -->
            <div id="srl-ajax-response" style="margin-top: 20px;"></div>
        </div>
    </div>
    <?php
}
