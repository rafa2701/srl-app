<?php
/**
 * Renderiza el contenido de la página de administración del plugin.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

function srl_render_admin_page() {
    $championship_posts = get_posts([
        'post_type' => 'srl_championship',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="#manual-import" class="nav-tab nav-tab-active">Importación Manual</a>
            <a href="#bulk-import" class="nav-tab">Importación en Lote (AC)</a>
            <a href="#tools" class="nav-tab">Herramientas</a>
        </h2>

        <div id="manual-import" class="srl-tab-content active">
            <div id="srl-upload-form-wrapper" style="max-width: 600px;">
                <h2>Importar Resultado Único</h2>
                <form id="srl-results-upload-form" method="post" enctype="multipart/form-data">
                    <p><label><strong>1. Selecciona el Campeonato:</strong><br><select name="championship_id" id="srl-championship-select" style="width: 100%;"><option value="">-- Elige un campeonato --</option><?php foreach ( $championship_posts as $champ ) : ?><option value="<?php echo esc_attr( $champ->ID ); ?>"><?php echo esc_html( $champ->post_title ); ?></option><?php endforeach; ?></select></label></p>
                    <p><label><strong>2. Selecciona el Evento:</strong><br><select name="event_id" id="srl-event-select" style="width: 100%;" disabled><option value="">-- Primero elige un campeonato --</option></select></label></p>
                    <p><label><strong>3. Selecciona la Sesión:</strong><br><select name="session_type" id="srl-session-select" style="width: 100%;" disabled><option value="">-- Primero elige un evento --</option><option value="Qualifying">Clasificación (Qualy)</option><option value="Race">Carrera (Race)</option></select></label></p>
                    <p><label><strong>4. Sube el archivo de resultados:</strong><br><input type="file" name="results_file" id="srl-results-file" accept=".json,.xls,.xlsx"></label></p>
                    <p><?php submit_button( 'Importar Resultado', 'primary', 'srl-submit-results', false ); ?><span class="spinner" style="float: none; vertical-align: middle;"></span></p>
                </form>
                <div id="srl-ajax-response" style="margin-top: 20px;"></div>
            </div>
        </div>

        <div id="bulk-import" class="srl-tab-content">
            <div id="srl-bulk-upload-wrapper" style="max-width: 600px;">
                <h2>Importar Múltiples Resultados (Solo Assetto Corsa)</h2>
                <p>Esta herramienta creará los eventos automáticamente basándose en las fechas y nombres de pista de los archivos JSON.</p>
                <form id="srl-bulk-upload-form" method="post" enctype="multipart/form-data">
                    <p><label><strong>1. Selecciona el Campeonato de Destino:</strong><br><select name="championship_id" id="srl-bulk-championship-select" style="width: 100%;"><option value="">-- Elige un campeonato --</option><?php foreach ( $championship_posts as $champ ) : ?><option value="<?php echo esc_attr( $champ->ID ); ?>"><?php echo esc_html( $champ->post_title ); ?></option><?php endforeach; ?></select></label></p>
                    <p><label><strong>2. Sube todos los archivos .json de la temporada:</strong><br><input type="file" name="bulk_results_files[]" id="srl-bulk-results-files" accept=".json" multiple></label></p>
                    <p><?php submit_button( 'Iniciar Importación en Lote', 'primary', 'srl-submit-bulk-results', false ); ?><span class="spinner" style="float: none; vertical-align: middle;"></span></p>
                </form>
                <div id="srl-bulk-response" style="margin-top: 20px;"></div>
            </div>
        </div>

        <div id="tools" class="srl-tab-content">
            <div id="srl-admin-tools-wrapper" style="max-width: 600px;">
                <h2>Herramientas Administrativas</h2>
                <p>Usa estas herramientas para mantenimiento de la base de datos.</p>
                <div class="srl-tool-card">
                    <h4>Recalcular Todas las Estadísticas de Pilotos</h4>
                    <p>Recalcula victorias, podios, poles y campeonatos ganados para todos los pilotos desde cero.</p>
                    <button id="srl-recalculate-stats-btn" class="button button-secondary">Iniciar Recálculo</button>
                    <span class="spinner" style="float: none; vertical-align: middle;"></span>
                </div>
                <div id="srl-recalculate-response" style="margin-top: 20px;"></div>
            </div>
        </div>
    </div>
    <style>.srl-tab-content { display: none; } .srl-tab-content.active { display: block; }</style>
    <?php
}
