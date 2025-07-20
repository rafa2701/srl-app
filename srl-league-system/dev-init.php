<?php
/**
 * Archivo de desarrollo para inicializar datos de prueba.
 * NO DEJAR ACTIVO EN PRODUCCIÓN.
 *
 * @package SRL_League_System
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Crea un campeonato y un evento de prueba en la base de datos.
 * La función comprueba si ya existen para no crear duplicados.
 */
function srl_create_test_data() {
    global $wpdb;

    $championship_table = $wpdb->prefix . 'srl_championships';
    $events_table = $wpdb->prefix . 'srl_events';

    // --- 1. Definir el Campeonato de Prueba ---
    $test_championship_name = 'SRL F1 - Temporada de Prueba';

    // Comprobar si el campeonato ya existe
    $championship_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $championship_table WHERE name = %s", $test_championship_name ) );

    if ( $championship_exists ) {
        // Si ya existe, no hacemos nada para evitar duplicados.
        // Puedes añadir un mensaje aquí si lo deseas.
        // error_log('SRL Plugin: Los datos de prueba ya existen.');
        return;
    }

    // Definir un sistema de puntuación básico tipo F1 en formato JSON
    $scoring_rules = [
        'points' => [
            1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10,
            6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1
        ],
        'bonuses' => [
            'pole' => 1,
            'fastest_lap' => 1
        ],
        'rules' => [
            'drop_worst_results' => 1
        ]
    ];

    // --- 2. Insertar el Campeonato ---
    $wpdb->insert(
        $championship_table,
        [
            'name' => $test_championship_name,
            'description' => 'Campeonato de prueba generado automáticamente.',
            'game' => 'Assetto Corsa',
            'scoring_rules' => json_encode( $scoring_rules ),
            'status' => 'active'
        ]
    );

    // Obtener el ID del campeonato que acabamos de crear
    $championship_id = $wpdb->insert_id;

    if ( ! $championship_id ) {
        error_log('SRL Plugin: Error al crear el campeonato de prueba.');
        return;
    }

    // --- 3. Insertar el Evento de Prueba ---
    $wpdb->insert(
        $events_table,
        [
            'championship_id' => $championship_id,
            'name' => 'Ronda 1 - Red Bull Ring',
            'track_name' => 'srl_red_bull_ring',
            'event_date' => '2025-06-22 22:00:00'
        ]
    );

    // Opcional: registrar en los logs que se crearon los datos.
    error_log('SRL Plugin: Se han creado los datos de prueba (Campeonato y Evento).');
}
