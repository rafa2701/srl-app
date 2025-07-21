<?php
/**
 * Mejoras para la interfaz de administración.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

/**
 * Añade JavaScript al pie de página en las pantallas de edición de Campeonatos.
 * Este script maneja la lógica de las plantillas de puntuación.
 */
function srl_add_championship_edit_script() {
    $screen = get_current_screen();
    // Asegurarse de que el script solo se ejecute en la pantalla de edición del CPT 'srl_championship'
    if ( $screen && $screen->id === 'srl_championship' ) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Plantillas de puntuación predefinidas
                const scoreTemplates = {
                    'f1': {
                        "points": { "1": 25, "2": 18, "3": 15, "4": 12, "5": 10, "6": 8, "7": 6, "8": 4, "9": 2, "10": 1 },
                        "bonuses": { "pole": 0, "fastest_lap": 1 },
                        "rules": { "drop_worst_results": 0 }
                    },
                    'indycar': {
                        "points": { "1": 50, "2": 40, "3": 35, "4": 32, "5": 30, "6": 28, "7": 26, "8": 24, "9": 22, "10": 20 },
                        "bonuses": { "pole": 1, "fastest_lap": 2 },
                        "rules": { "drop_worst_results": 0 }
                    },
                    'srl': {
                        "points": { "1": 30, "2": 26, "3": 24, "4": 22, "5": 20, "6": 18, "7": 16, "8": 14, "9": 12, "10": 11, "11": 10, "12": 9, "13": 8, "14": 7, "15": 6, "16": 5, "17": 4, "18": 3, "19": 2, "20": 1 },
                        "bonuses": { "pole": 1, "fastest_lap": 1 },
                        "rules": { "drop_worst_results": 0 }
                    }
                };

                // La función asume que los IDs de los campos coinciden con sus nombres
                const templateSelector = $('[name="_srl_scoring_template"]');
                const rulesTextarea = $('[name="_srl_scoring_rules"]');

                templateSelector.on('change', function() {
                    const selectedTemplate = $(this).val();
                    if (selectedTemplate && scoreTemplates[selectedTemplate]) {
                        const jsonString = JSON.stringify(scoreTemplates[selectedTemplate], null, 2);
                        rulesTextarea.val(jsonString).trigger('change'); // Trigger change para que SCF detecte la modificación
                    }
                });
            });
        </script>
        <?php
    }
}
// Enganchar la función al footer de las páginas de edición y creación de posts
add_action( 'admin_footer-post.php', 'srl_add_championship_edit_script' );
add_action( 'admin_footer-post-new.php', 'srl_add_championship_edit_script' );
