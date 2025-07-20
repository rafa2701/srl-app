<?php
/**
 * Plugin Name:       SRL League System
 * Plugin URI:        https://simracinglatinoamerica.com/
 * Description:       Sistema de gestión de campeonatos, resultados y estadísticas para ligas de SimRacing.
 * Version:           1.0.0
 * Author:            Tu Nombre / Gemini AI
 * Author URI:        https://simracinglatinoamerica.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       srl-league-system
 * Domain Path:       /languages
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Definir constantes útiles del plugin
define( 'SRL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SRL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRL_PLUGIN_VERSION', '1.0.0' );

/**
 * La función principal que se ejecuta al activar el plugin.
 * Se encarga de llamar a la función que crea las tablas de la base de datos.
 */
function srl_activate_plugin() {
    // Requerir el archivo de configuración de la base de datos
    require_once SRL_PLUGIN_PATH . 'includes/db-setup.php';
    
    // Llamar a la función que crea las tablas
    srl_create_database_tables();
}
register_activation_hook( __FILE__, 'srl_activate_plugin' );

/**
 * Aquí incluiremos el resto de archivos del plugin a medida que los desarrollemos.
 * Por ejemplo:
 * require_once SRL_PLUGIN_PATH . 'includes/post-types.php';
 * require_once SRL_PLUGIN_PATH . 'includes/shortcodes.php';
 * require_once SRL_PLUGIN_PATH . 'includes/ajax-handlers.php';
 */

?>
