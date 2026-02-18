<?php

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Nettoyage complet lors de la désinstallation
global $wpdb;

// 1. Supprimer les tables
$tables = [
	'postal_servers',
	'postal_logs',
	'postal_stats',
	'postal_stats_daily',
	'postal_stats_history',
	'postal_templates',
	'postal_template_folders',
	'postal_template_tags',
	'postal_template_tag_relations',
	'postal_template_versions',
	'postal_queue',
	'postal_isps',
	'postal_server_isp_stats',
	'postal_strategies',
	'postal_metrics',
	'postal_mailto_clicks'
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// 2. Supprimer les options
$options = [
	'pw_version',
	'pw_settings',
	'pw_webhook_secret',
	'pw_activation_notice',
	'pw_db_version' // If used
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// 3. Supprimer les transients
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_pw_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_pw_%'" );

// 4. Supprimer les tâches planifiées
$crons = [
	'pw_process_queue',
	'pw_warmup_daily_increment',
	'pw_daily_report',
	'pw_cleanup_old_logs',
	'pw_cleanup_old_stats',
	'pw_daily_stats_aggregation',
	'pw_cleanup_queue',
	'pw_advisor_check'
];

foreach ( $crons as $cron ) {
	$timestamp = wp_next_scheduled( $cron );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $cron );
	}
}
