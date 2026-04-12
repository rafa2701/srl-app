<?php
/**
 * Archivo para la configuración y creación de las tablas de la base de datos.
 *
 * @package SRL_League_System
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Crea las tablas personalizadas para el sistema de ligas.
 * Utiliza la función dbDelta de WordPress para crear o actualizar las tablas de forma segura.
 */
function srl_create_database_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // Prefijo para nuestras tablas
    $table_prefix = $wpdb->prefix . 'srl_';

    // --- Script SQL para crear las tablas ---
    // Usamos el esquema que definimos anteriormente.

    $sql = "
    CREATE TABLE {$table_prefix}championships (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(255) NOT NULL,
      description TEXT,
      game VARCHAR(100),
      scoring_rules JSON NOT NULL,
      status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}drivers (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT(20) UNSIGNED NULL,
      steam_id VARCHAR(100) UNIQUE,
      full_name VARCHAR(255) NOT NULL,
      country_code VARCHAR(10),
      victories_count INT UNSIGNED NOT NULL DEFAULT 0,
      podiums_count INT UNSIGNED NOT NULL DEFAULT 0,
      top_5_count INT UNSIGNED NOT NULL DEFAULT 0,
      top_10_count INT UNSIGNED NOT NULL DEFAULT 0,
      poles_count INT UNSIGNED NOT NULL DEFAULT 0,
      fastest_laps_count INT UNSIGNED NOT NULL DEFAULT 0,
      hat_tricks_count INT UNSIGNED NOT NULL DEFAULT 0,
      championships_won_count INT UNSIGNED NOT NULL DEFAULT 0,
      dnfs_count INT UNSIGNED NOT NULL DEFAULT 0,
      dq_count INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      KEY idx_steam_id (steam_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}events (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      championship_id INT UNSIGNED NOT NULL,
      name VARCHAR(255) NOT NULL,
      track_name VARCHAR(255) NOT NULL,
      event_date DATETIME,
      status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
      PRIMARY KEY (id),
      KEY idx_championship_id (championship_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}sessions (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      event_id INT UNSIGNED NOT NULL,
      session_type VARCHAR(50) NOT NULL,
      source_file VARCHAR(255),
      imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_event_id (event_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}results (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_id INT UNSIGNED NOT NULL,
      driver_id INT UNSIGNED NOT NULL,
      team_name VARCHAR(255),
      car_model VARCHAR(255),
      position SMALLINT UNSIGNED NOT NULL,
      grid_position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      best_lap_time INT UNSIGNED,
      total_time INT UNSIGNED,
      time_penalty INT UNSIGNED NOT NULL DEFAULT 0,
      laps_completed SMALLINT UNSIGNED,
      has_pole BOOLEAN NOT NULL DEFAULT FALSE,
      has_fastest_lap BOOLEAN NOT NULL DEFAULT FALSE,
      is_dnf BOOLEAN NOT NULL DEFAULT FALSE,
      is_disqualified BOOLEAN NOT NULL DEFAULT FALSE,
      points_awarded FLOAT NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      UNIQUE KEY uk_session_driver (session_id, driver_id),
      KEY idx_session_id (session_id),
      KEY idx_driver_id (driver_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}achievements (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      driver_id INT UNSIGNED NOT NULL,
      event_id INT UNSIGNED NOT NULL,
      achievement_type VARCHAR(50) NOT NULL,
      achieved_at DATE NOT NULL,
      PRIMARY KEY (id),
      KEY idx_driver_id (driver_id),
      KEY idx_event_id (event_id)
    ) $charset_collate;
    ";

    // Ejecutar el SQL
    dbDelta( $sql );
}
