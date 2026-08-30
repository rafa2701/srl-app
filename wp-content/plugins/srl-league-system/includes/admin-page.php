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
            <a href="#history-import" class="nav-tab">Importar Historial (XLSX)</a>
            <a href="#settings" class="nav-tab">Ajustes del Sitio</a>
            <a href="#achievements" class="nav-tab">Hitos (Logros)</a>
            <a href="#commissary" class="nav-tab">Comisariato Virtual AI</a>
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
        <!-- NUEVO: Pestaña para la importación histórica -->
        <div id="history-import" class="srl-tab-content">
            <div id="srl-history-upload-wrapper" style="max-width: 600px;">
                <h2>Importar Archivo Histórico de Automobilista (.xlsx)</h2>
                <p>Esta herramienta leerá un archivo .xlsx completo, creará todos los campeonatos, eventos y resultados históricos de Automobilista.</p>
                <p><strong>Nota:</strong> Este proceso puede tardar varios minutos y no debe interrumpirse. Asegúrate de que el archivo tiene la estructura correcta ("Tablas de Orlando").</p>
                <form id="srl-history-upload-form" method="post" enctype="multipart/form-data">
                    <p><label><strong>1. Sube el archivo .xlsx completo:</strong><br><input type="file" name="history_file" id="srl-history-file" accept=".xlsx"></label></p>
                    <p><?php submit_button( 'Iniciar Migración Histórica', 'primary', 'srl-submit-history', false ); ?><span class="spinner" style="float: none; vertical-align: middle;"></span></p>
                </form>
                <div id="srl-history-response" style="margin-top: 20px;"></div>
            </div>
        </div>

        <div id="settings" class="srl-tab-content">
            <div id="srl-settings-wrapper" style="max-width: 600px;">
                <h2>Ajustes Visuales de Sim Racing Latinoamérica</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'srl_settings_group' );
                    do_settings_sections( 'srl-settings-page' );

                    $site_logo = get_option( 'srl_site_logo' );
                    $footer_logo = get_option( 'srl_footer_logo' );
                    $default_orderby = get_option( 'srl_championship_default_orderby', 'date' );
                    $default_order = get_option( 'srl_championship_default_order', 'DESC' );
                    $force_auto_update = get_option( 'srl_force_auto_update' );
                    ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Logo del Sitio (Navbar)</th>
                            <td>
                                <input type="text" name="srl_site_logo" id="srl_site_logo" value="<?php echo esc_attr( $site_logo ); ?>" class="regular-text" />
                                <input type="button" class="button srl-upload-button" value="Subir Imagen" data-target="srl_site_logo" />
                                <p class="description">URL de la imagen del logo para la barra de navegación.</p>
                                <?php if ( $site_logo ) : ?><img src="<?php echo esc_url( $site_logo ); ?>" style="max-width: 150px; display: block; margin-top: 10px;"><?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Logo del Pie de Página</th>
                            <td>
                                <input type="text" name="srl_footer_logo" id="srl_footer_logo" value="<?php echo esc_attr( $footer_logo ); ?>" class="regular-text" />
                                <input type="button" class="button srl-upload-button" value="Subir Imagen" data-target="srl_footer_logo" />
                                <p class="description">URL de la imagen del logo para el footer.</p>
                                <?php if ( $footer_logo ) : ?><img src="<?php echo esc_url( $footer_logo ); ?>" style="max-width: 150px; display: block; margin-top: 10px;"><?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Orden por defecto (Campeonatos)</th>
                            <td>
                                <select name="srl_championship_default_orderby">
                                    <option value="date" <?php selected( $default_orderby, 'date' ); ?>>Fecha de Creación</option>
                                    <option value="title" <?php selected( $default_orderby, 'title' ); ?>>Título</option>
                                </select>
                                <select name="srl_championship_default_order">
                                    <option value="DESC" <?php selected( $default_order, 'DESC' ); ?>>Descendente (Nuevos primero / Z-A)</option>
                                    <option value="ASC" <?php selected( $default_order, 'ASC' ); ?>>Ascendente (Viejos primero / A-Z)</option>
                                </select>
                                <p class="description">Establece cómo se ordenarán los campeonatos en las listas por defecto.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Actualizaciones Automáticas</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="srl_force_auto_update" value="1" <?php checked( 1, $force_auto_update ); ?> />
                                    Forzar actualizaciones automáticas en segundo plano (Plugin SRL League System)
                                </label>
                                <p class="description">Si está activado, el plugin se actualizará automáticamente sin intervención manual tan pronto como haya una nueva versión en GitHub.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>

        <div id="achievements" class="srl-tab-content">
            <div id="srl-achievements-settings-wrapper" style="max-width: 800px;">
                <h2>Configuración de Hitos (Logros)</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'srl_settings_group' );
                    $labels = SRL_Achievement_Manager::get_achievement_keys();
                    $settings = get_option( 'srl_achievement_settings', [] );
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Clave</th>
                                <th>Etiqueta (Español)</th>
                                <th style="width: 100px;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $labels as $key => $label ) :
                                $is_enabled = isset( $settings[$key]['enabled'] ) ? (bool) $settings[$key]['enabled'] : true;
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html( $key ); ?></code></td>
                                    <td>
                                        <input type="text" name="srl_achievement_labels[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $label ); ?>" class="regular-text">
                                    </td>
                                    <td>
                                        <label class="switch">
                                            <input type="hidden" name="srl_achievement_settings[<?php echo esc_attr( $key ); ?>][enabled]" value="0">
                                            <input type="checkbox" name="srl_achievement_settings[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $is_enabled ); ?>>
                                            Habilitado
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>

        <div id="commissary" class="srl-tab-content">
            <div id="srl-commissary-settings-wrapper" style="max-width: 850px;">
                <h2>Configuración del Comisariato Virtual (n8n + Gemini AI)</h2>
                <p class="description">Configura la conexión con tu instancia homelab de n8n y el reglamento deportivo de la liga.</p>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'srl_settings_group' );
                    $webhook_url = get_option( 'srl_virtual_commissary_webhook_url', '' );
                    $api_secret = get_option( 'srl_api_secret_key', '' );
                    if ( empty( $api_secret ) ) {
                        $api_secret = wp_generate_password( 32, false );
                    }
                    $rulebook = get_option( 'srl_rulebook_markdown', '' );
                    ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">URL del Webhook de n8n</th>
                            <td>
                                <input type="url" name="srl_virtual_commissary_webhook_url" value="<?php echo esc_attr( $webhook_url ); ?>" class="large-text" placeholder="https://n8n.tu-servidor.com/webhook/srl-virtual-commissary" />
                                <p class="description">URL del webhook de n8n que recibirá los incidentes a analizar.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">API Secret Key (Autenticación)</th>
                            <td>
                                <input type="text" name="srl_api_secret_key" id="srl_api_secret_key" value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text" style="font-family: monospace;" />
                                <button type="button" class="button" onclick="document.getElementById('srl_api_secret_key').value = Array.from(crypto.getRandomValues(new Uint8Array(24))).map(b=>b.toString(16).padStart(2,'0')).join('');">Regenerar</button>
                                <p class="description">Esta clave debe enviarse en la cabecera <code>X-SRL-API-KEY</code> desde n8n para acceder al reglamento y enviar los veredictos.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Endpoints REST API</th>
                            <td>
                                <p><strong>Obtener Reglamento:</strong> <code><?php echo esc_url( rest_url( 'srl/v1/rulebook' ) ); ?></code></p>
                                <p><strong>Callback de Veredicto:</strong> <code><?php echo esc_url( rest_url( 'srl/v1/protest-update' ) ); ?></code></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Modo de Selección de Gran Premio / Evento</th>
                            <td>
                                <?php $event_mode = get_option( 'srl_commissary_event_input_mode', 'hybrid' ); ?>
                                <select name="srl_commissary_event_input_mode">
                                    <option value="hybrid" <?php selected( $event_mode, 'hybrid' ); ?>>Híbrido Inteligente (Desplegable + Texto Libre si no figura) [Recomendado]</option>
                                    <option value="always_free_text" <?php selected( $event_mode, 'always_free_text' ); ?>>Siempre Texto Libre (Escribir GP manualmente)</option>
                                    <option value="dropdown_only" <?php selected( $event_mode, 'dropdown_only' ); ?>>Solo Desplegable de Eventos Existentes</option>
                                </select>
                                <p class="description">Permite que los pilotos ingresen el nombre del GP incluso si los comisarios aún no han creado el evento o subido los resultados.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Reglamento Deportivo y Código de Conducta (Markdown)</th>
                            <td>
                                <textarea name="srl_rulebook_markdown" rows="18" class="large-text code" placeholder="# Reglamento de Competición SRL&#10;&#10;## 1. Derecho a la trazada&#10;..."><?php echo esc_textarea( $rulebook ); ?></textarea>
                                <p class="description">Escribe o pega aquí el reglamento de la liga en formato Markdown. La IA consultará este texto para determinar la culpabilidad y sanciones.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">Almacenamiento Directo de Evidencias (Cloudflare R2)</h3>
                    <p class="description">Permite que los pilotos suban videos y capturas directamente a un bucket S3 de Cloudflare R2 (gratuito hasta 10GB). Si no se configura o se desactiva, las subidas se guardarán en la biblioteca local de WordPress.</p>
                    
                    <?php
                    $r2_enabled     = get_option( 'srl_r2_enabled', 0 );
                    $r2_account_id  = get_option( 'srl_r2_account_id', '' );
                    $r2_access_key  = get_option( 'srl_r2_access_key_id', '' );
                    $r2_secret_key  = get_option( 'srl_r2_secret_access_key', '' );
                    $r2_bucket_name = get_option( 'srl_r2_bucket_name', '' );
                    $r2_public_url  = get_option( 'srl_r2_public_url', '' );
                    ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Habilitar Cloudflare R2</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="srl_r2_enabled" value="1" <?php checked( $r2_enabled, 1 ); ?> />
                                    Subir archivos de evidencia directamente a Cloudflare R2
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Cloudflare Account ID</th>
                            <td>
                                <input type="text" name="srl_r2_account_id" value="<?php echo esc_attr( $r2_account_id ); ?>" class="regular-text code" placeholder="Ej: a1b2c3d4e5f6..." />
                                <p class="description">Encuentra tu Account ID en el panel principal de Cloudflare o en la sección R2.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">R2 Bucket Name</th>
                            <td>
                                <input type="text" name="srl_r2_bucket_name" value="<?php echo esc_attr( $r2_bucket_name ); ?>" class="regular-text code" placeholder="srl-incident-videos" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">S3 Access Key ID</th>
                            <td>
                                <input type="text" name="srl_r2_access_key_id" value="<?php echo esc_attr( $r2_access_key ); ?>" class="regular-text code" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">S3 Secret Access Key</th>
                            <td>
                                <input type="password" name="srl_r2_secret_access_key" value="<?php echo esc_attr( $r2_secret_key ); ?>" class="regular-text code" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Dominio Público / URL Base de R2</th>
                            <td>
                                <input type="url" name="srl_r2_public_url" value="<?php echo esc_attr( $r2_public_url ); ?>" class="large-text" placeholder="https://pub-xxxxxxxxxxxx.r2.dev o https://media.simracinglatinoamerica.com" />
                                <p class="description">Dominio público habilitado para el bucket (R2.dev o Dominio personalizado).</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( 'Guardar Configuración del Comisariato' ); ?>
                </form>
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
