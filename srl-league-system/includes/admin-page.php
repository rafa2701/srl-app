<?php
/**
 * Renderiza el contenido de la página de administración del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

function srl_render_admin_page() {
    // Obtener campeonatos (CPT) para el selector
    $championship_posts = get_posts([
        'post_type' => 'srl_championship',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <div id="srl-upload-form-wrapper" style="max-width: 600px;">
            <h2>Importar Resultados de Carrera</h2>
            <form id="srl-results-upload-form" method="post" enctype="multipart/form-data">
                
                <p>
                    <label for="srl-championship-select"><strong>1. Selecciona el Campeonato:</strong></label><br>
                    <select name="championship_id" id="srl-championship-select" style="width: 100%;">
                        <option value="">-- Elige un campeonato --</option>
                        <?php foreach ( $championship_posts as $champ ) : ?>
                            <option value="<?php echo esc_attr( $champ->ID ); ?>"><?php echo esc_html( $champ->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="srl-event-select"><strong>2. Selecciona el Evento:</strong></label><br>
                    <select name="event_id" id="srl-event-select" style="width: 100%;" disabled>
                        <option value="">-- Primero elige un campeonato --</option>
                    </select>
                </p>

                 <p>
                    <label for="srl-session-select"><strong>3. Selecciona la Sesión:</strong></label><br>
                    <select name="session_type" id="srl-session-select" style="width: 100%;" disabled>
                        <option value="">-- Primero elige un evento --</option>
                         <option value="Qualifying">Clasificación (Qualy)</option>
                        <option value="Race">Carrera (Race)</option>
                    </select>
                </p>

                <p>
                    <label for="srl-results-file"><strong>4. Sube el archivo de resultados:</strong></label><br>
                    <input type="file" name="results_file" id="srl-results-file" accept=".json,.xls,.xlsx">
                </p>

                <p>
                    <?php submit_button( 'Importar Resultados', 'primary', 'srl-submit-results', false ); ?>
                    <span class="spinner" style="float: none; vertical-align: middle;"></span>
                </p>
            </form>
            
            <div id="srl-ajax-response" style="margin-top: 20px;"></div>
        </div>
         <hr style="margin: 40px 0;">

        <!-- NUEVA SECCIÓN DE HERRAMIENTAS -->
        <div id="srl-admin-tools-wrapper" style="max-width: 600px;">
            <h2>Herramientas Administrativas</h2>
            <p>Usa estas herramientas para mantenimiento de la base de datos.</p>
            
            <div class="srl-tool-card">
                <h4>Recalcular Todas las Estadísticas de Pilotos</h4>
                <p>Si has modificado o eliminado resultados manualmente, esta función recorrerá todos los pilotos y recalculará sus estadísticas globales (victorias, podios, poles, etc.) desde cero para asegurar que los datos sean correctos.</p>
                <button id="srl-recalculate-stats-btn" class="button button-secondary">Iniciar Recálculo</button>
                <span class="spinner" style="float: none; vertical-align: middle;"></span>
            </div>
            <div id="srl-recalculate-response" style="margin-top: 20px;"></div>
        </div>

    </div>
    <?php
}
