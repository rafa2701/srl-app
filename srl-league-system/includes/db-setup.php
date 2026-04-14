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

    $sql = "CREATE TABLE {$table_prefix}championships (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      description text,
      game varchar(100),
      scoring_rules text NOT NULL,
      status varchar(50) NOT NULL DEFAULT 'scheduled',
      created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}drivers (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      user_id bigint(20) unsigned NULL,
      steam_id varchar(100),
      full_name varchar(255) NOT NULL,
      country_code varchar(10),
      victories_count int(11) NOT NULL DEFAULT 0,
      podiums_count int(11) NOT NULL DEFAULT 0,
      top_5_count int(11) NOT NULL DEFAULT 0,
      top_10_count int(11) NOT NULL DEFAULT 0,
      poles_count int(11) NOT NULL DEFAULT 0,
      fastest_laps_count int(11) NOT NULL DEFAULT 0,
      hat_tricks_count int(11) NOT NULL DEFAULT 0,
      championships_won_count int(11) NOT NULL DEFAULT 0,
      dnfs_count int(11) NOT NULL DEFAULT 0,
      dq_count int(11) NOT NULL DEFAULT 0,
      nationality varchar(100),
      photo_id bigint(20) unsigned,
      photo_url text,
      PRIMARY KEY  (id),
      KEY idx_steam_id (steam_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}events (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      championship_id bigint(20) unsigned NOT NULL,
      name varchar(255) NOT NULL,
      track_name varchar(255) NOT NULL,
      event_date datetime,
      status varchar(50) NOT NULL DEFAULT 'scheduled',
      PRIMARY KEY  (id),
      KEY idx_championship_id (championship_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}sessions (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      event_id bigint(20) unsigned NOT NULL,
      session_type varchar(50) NOT NULL,
      source_file varchar(255),
      imported_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY idx_event_id (event_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}results (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      session_id bigint(20) unsigned NOT NULL,
      driver_id bigint(20) unsigned NOT NULL,
      team_name varchar(255),
      car_model varchar(255),
      position int(11) NOT NULL,
      grid_position int(11) NOT NULL DEFAULT 0,
      best_lap_time int(11),
      total_time int(11),
      time_penalty int(11) NOT NULL DEFAULT 0,
      laps_completed int(11),
      has_pole tinyint(1) NOT NULL DEFAULT 0,
      has_fastest_lap tinyint(1) NOT NULL DEFAULT 0,
      is_dnf tinyint(1) NOT NULL DEFAULT 0,
      is_nc tinyint(1) NOT NULL DEFAULT 0,
      is_nc_forced tinyint(1) NOT NULL DEFAULT 0,
      is_disqualified tinyint(1) NOT NULL DEFAULT 0,
      points_awarded float NOT NULL DEFAULT 0,
      PRIMARY KEY  (id),
      UNIQUE KEY uk_session_driver (session_id, driver_id),
      KEY idx_session_id (session_id),
      KEY idx_driver_id (driver_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}achievements (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      driver_id bigint(20) unsigned NOT NULL,
      event_id bigint(20) unsigned NOT NULL,
      achievement_type varchar(50) NOT NULL,
      achieved_at date NOT NULL,
      PRIMARY KEY  (id),
      KEY idx_driver_id (driver_id),
      KEY idx_event_id (event_id)
    ) $charset_collate;
    ";

    // Ejecutar el SQL
    dbDelta( $sql );
}
