<?php
/**
 * Archivo para procesar los resultados en formato XLS de Automobilista.
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Función principal para procesar el archivo histórico completo de Automobilista.
 *
 * @param string $file_path La ruta temporal del archivo .xlsx subido.
 * @return array Un array con el estado y el registro de la migración.
 */
function srl_parse_automobilista_history_file( $file_path ) {
    $log_file = 'history-import-' . date('Y-m-d_H-i-s') . '.log';
    srl_write_to_log('--- INICIO DE MIGRACIÓN HISTÓRICA DE AUTOMOBILISTA ---', $log_file);

    try {
        $spreadsheet = IOFactory::load($file_path);
        $sheet_names = $spreadsheet->getSheetNames();
        srl_write_to_log('Archivo XLSX cargado. Hojas encontradas: ' . count($sheet_names), $log_file);

        $all_affected_drivers = [];
        global $wpdb;

        // 1. Identificar y procesar cada campeonato
        foreach ( $sheet_names as $sheet_name ) {
            if ( strpos( strtolower($sheet_name), 'temporada' ) === 0 ) {
                srl_write_to_log("Procesando hoja de campeonato: {$sheet_name}", $log_file);
                
                $champ_name = trim(str_ireplace('Temporada', '', $sheet_name));
                
                $championship_id = wp_insert_post([
                    'post_title'  => $champ_name,
                    'post_type'   => 'srl_championship',
                    'post_status' => 'publish',
                ]);

                if ( is_wp_error($championship_id) ) {
                    srl_write_to_log("Error: No se pudo crear el campeonato '{$champ_name}'.", $log_file);
                    continue;
                }
                
                update_post_meta($championship_id, '_srl_game', 'ams');
                update_post_meta($championship_id, '_srl_status', 'completed');
                update_post_meta($championship_id, '_srl_scoring_template', 'srl');
                $srl_rules = '{"points":{"1":30,"2":26,"3":24,"4":22,"5":20,"6":18,"7":16,"8":14,"9":12,"10":11,"11":10,"12":9,"13":8,"14":7,"15":6,"16":5,"17":4,"18":3,"19":2,"20":1},"bonuses":{"pole":1,"fastest_lap":1},"rules":{"drop_worst_results":0}}';
                update_post_meta($championship_id, '_srl_scoring_rules', $srl_rules);

                srl_write_to_log("Campeonato '{$champ_name}' (ID: {$championship_id}) creado.", $log_file);

                // 2. Leer los eventos de la hoja de temporada de forma más eficiente
                $worksheet = $spreadsheet->getSheetByName($sheet_name);
                $events_data = [];
                $highest_row = $worksheet->getHighestRow();
                
                for ($row_index = 3; $row_index <= $highest_row; $row_index++) {
                    $round_number = $worksheet->getCell('A' . $row_index)->getValue();
                    if ( empty($round_number) || !is_numeric($round_number) ) continue;

                    $circuit = $worksheet->getCell('B' . $row_index)->getValue();
                    $date_val = $worksheet->getCell('H' . $row_index)->getValue();
                    
                    if (!empty($date_val)) {
                        $date = srl_ams_parse_date($date_val);
                        if ($date) {
                            $events_data[(int)$round_number] = ['circuit' => $circuit, 'date' => $date];
                        } else {
                            srl_write_to_log("Advertencia: Fecha inválida ('{$date_val}') para la Ronda {$round_number} en la hoja '{$sheet_name}'. Se omitirá este evento.", $log_file);
                        }
                    } else {
                         srl_write_to_log("Advertencia: Celda de fecha vacía para la Ronda {$round_number} en la hoja '{$sheet_name}'. Se omitirá este evento.", $log_file);
                    }
                }
                srl_write_to_log("Se encontraron " . count($events_data) . " eventos para el campeonato '{$champ_name}'.", $log_file);

                // 3. Procesar las hojas de resultados para este campeonato
                foreach ($events_data as $round => $event_info) {
                    $event_sheet_name = srl_find_event_sheet($sheet_names, $round, $champ_name);
                    if ($event_sheet_name) {
                        $affected_drivers_in_event = srl_process_event_sheet($spreadsheet->getSheetByName($event_sheet_name), $championship_id, $event_info, $round, $log_file);
                        $all_affected_drivers = array_merge($all_affected_drivers, $affected_drivers_in_event);
                    } else {
                        srl_write_to_log("Advertencia: No se encontró la hoja de resultados para la Ronda {$round} del campeonato '{$champ_name}'.", $log_file);
                    }
                }
            }
        }

        // 4. Recálculo final
        $unique_affected_drivers = array_unique($all_affected_drivers);
        srl_write_to_log('Recalculando estadísticas para ' . count($unique_affected_drivers) . ' pilotos afectados.', $log_file);
        foreach ($unique_affected_drivers as $driver_id) {
            srl_update_driver_global_stats($driver_id);
        }

        srl_write_to_log('Recalculando todos los campeonatos ganados para asegurar consistencia.', $log_file);
        $wpdb->query( "UPDATE {$wpdb->prefix}srl_drivers SET championships_won_count = 0" );
        $completed_championships = get_posts([ 'post_type' => 'srl_championship', 'posts_per_page' => -1, 'meta_key' => '_srl_status', 'meta_value' => 'completed' ]);
        foreach ( $completed_championships as $champ ) {
            $standings = srl_calculate_championship_standings( $champ->ID );
            if ( ! empty( $standings ) ) {
                $winner_driver_id = array_key_first( $standings );
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}srl_drivers SET championships_won_count = championships_won_count + 1 WHERE id = %d", $winner_driver_id ) );
            }
        }
        srl_write_to_log('--- MIGRACIÓN HISTÓRICA COMPLETADA ---', $log_file);
        return ['status' => 'success', 'log' => file_get_contents(SRL_PLUGIN_PATH . 'logs/' . $log_file)];

    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        srl_write_to_log('Error de PhpSpreadsheet durante la migración: ' . $e->getMessage(), $log_file);
        return ['status' => 'error', 'log' => file_get_contents(SRL_PLUGIN_PATH . 'logs/' . $log_file)];
    } catch (Exception $e) {
        srl_write_to_log('Error fatal durante la migración: ' . $e->getMessage(), $log_file);
        return ['status' => 'error', 'log' => file_get_contents(SRL_PLUGIN_PATH . 'logs/' . $log_file)];
    }
}

/**
 * Procesa una única hoja de resultados de un evento.
 */
function srl_process_event_sheet($worksheet, $championship_id, $event_info, $round, $log_file) {
    global $wpdb;
    $event_title = "R{$round}: {$event_info['circuit']} - (" . date('d/m/Y', strtotime($event_info['date'])) . ")";
    $event_id = wp_insert_post([
        'post_title'  => $event_title,
        'post_type'   => 'srl_event',
        'post_status' => 'publish',
    ]);
    update_post_meta($event_id, '_srl_parent_championship', $championship_id);
    update_post_meta($event_id, '_srl_track_name', $event_info['circuit']);
    update_post_meta($event_id, '_srl_event_date', $event_info['date']);

    $wpdb->insert($wpdb->prefix . 'srl_sessions', ['event_id' => $event_id, 'session_type' => 'Race', 'source_file' => 'history.xlsx']);
    $session_id = $wpdb->insert_id;
    srl_write_to_log("Evento '{$event_title}' (ID: {$event_id}) creado para la sesión ID {$session_id}.", $log_file);

    $results_data = [];
    $affected_drivers = [];
    for ($row_index = 2; $row_index <= $worksheet->getHighestRow(); $row_index++) {
        $driver_name = trim($worksheet->getCell('C' . $row_index)->getValue());
        if (empty($driver_name)) continue;

        $pos = (int)$worksheet->getCell('A' . $row_index)->getValue();
        $pole = (int)$worksheet->getCell('G' . $row_index)->getValue() === 1;
        $vr = (int)$worksheet->getCell('H' . $row_index)->getValue() === 1;
        $best_lap = srl_ams_parse_time_to_ms($worksheet->getCell('I' . $row_index)->getValue());
        $total_time = srl_ams_parse_time_to_ms($worksheet->getCell('K' . $row_index)->getValue());
        
        $driver_name_key = strtolower($driver_name);
        if (isset($results_data[$driver_name_key])) {
            if ($pos < $results_data[$driver_name_key]['position']) {
                $results_data[$driver_name_key] = ['original_name' => $driver_name, 'position' => $pos, 'has_pole' => $pole, 'has_fastest_lap' => $vr, 'best_lap_time' => $best_lap, 'total_time' => $total_time];
            }
        } else {
            $results_data[$driver_name_key] = ['original_name' => $driver_name, 'position' => $pos, 'has_pole' => $pole, 'has_fastest_lap' => $vr, 'best_lap_time' => $best_lap, 'total_time' => $total_time];
        }
    }

    foreach ($results_data as $name_key => $data) {
        $driver_id = srl_ams_get_or_create_driver_by_name($data['original_name']);
        if (!$driver_id) continue;
        $affected_drivers[] = $driver_id;
        
        $grid_pos = $data['has_pole'] ? 1 : $data['position'];

        $wpdb->insert($wpdb->prefix . 'srl_results', [
            'session_id' => $session_id, 'driver_id' => $driver_id, 'position' => $data['position'], 'grid_position' => $grid_pos,
            'has_pole' => $data['has_pole'], 'has_fastest_lap' => $data['has_fastest_lap'], 'best_lap_time' => $data['best_lap_time'],
            'total_time' => $data['total_time'], 'is_dnf' => ($data['total_time'] == 0)
        ]);
    }
    return $affected_drivers;
}

/**
 * Busca una hoja de evento basándose en el número de ronda y el nombre del campeonato.
 */
function srl_find_event_sheet($sheet_names, $round, $champ_name) {
    preg_match('/(\d{4})$/', $champ_name, $matches);
    $year = $matches[1] ?? '';
    $search_pattern = "/^{$round}[-.\s]/";
    foreach ($sheet_names as $sheet_name) {
        if (preg_match($search_pattern, $sheet_name) && strpos($sheet_name, $year) !== false) {
            return $sheet_name;
        }
    }
    return null;
}

/**
 * Obtiene o crea un piloto por nombre (insensible a mayúsculas/minúsculas).
 */
function srl_ams_get_or_create_driver_by_name($name) {
    global $wpdb;
    $drivers_table = $wpdb->prefix . 'srl_drivers';
    $name = trim($name);
    if (empty($name)) return false;

    $driver_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $drivers_table WHERE LOWER(full_name) = LOWER(%s)", $name ) );
    if ($driver_id) {
        return (int)$driver_id;
    } else {
        $wpdb->insert($drivers_table, ['full_name' => $name], ['%s']);
        return $wpdb->insert_id ?: false;
    }
}

/**
 * Convierte un string de tiempo (ej: 1:29:040 o 1:06:01:592) a milisegundos.
 */
function srl_ams_parse_time_to_ms($time_str) {
    if (empty($time_str) || !is_string($time_str)) return 0;
    $time_str = trim($time_str);
    $parts = preg_split('/[:.]/', $time_str);
    $ms = 0;
    if (count($parts) == 3) { // M:S.ms
        $ms = (intval($parts[0]) * 60 * 1000) + (intval($parts[1]) * 1000) + intval($parts[2]);
    } elseif (count($parts) == 4) { // H:M:S.ms
        $ms = (intval($parts[0]) * 3600 * 1000) + (intval($parts[1]) * 60 * 1000) + (intval($parts[2]) * 1000) + intval($parts[3]);
    }
    return $ms;
}

/**
 * Convierte un valor de fecha de Excel o un string DD/MM/YY a YYYY-MM-DD.
 */
function srl_ams_parse_date($date_val) {
    if (is_numeric($date_val)) {
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date_val)->format('Y-m-d');
    }
    if (is_string($date_val)) {
        // Intentar con varios formatos comunes
        $formats_to_try = ['d/m/y', 'd-m-y', 'Y-m-d', 'd/m/Y'];
        foreach ($formats_to_try as $format) {
            $date = DateTime::createFromFormat($format, $date_val);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
    }
    return false;
}
