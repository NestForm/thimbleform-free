<?php
/**
 * Uninstall cleanup when delete_data_on_uninstall is enabled.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wipe Thimbleform data when the user opted in via settings.
 *
 * @return void
 */
function thimbleform_uninstall_cleanup() {
	$settings = get_option( 'thimbleform_settings', array() );
	$wipe     = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) && '1' === (string) $settings['delete_data_on_uninstall'];

	if ( ! $wipe ) {
		return;
	}

	global $wpdb;

	$form_ids = get_posts(
		array(
			'post_type'              => 'thimbleform',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $form_ids as $form_id ) {
		wp_delete_post( (int) $form_id, true );
	}

	$entry_ids = get_posts(
		array(
			'post_type'              => 'thimbleform_entry',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $entry_ids as $entry_id ) {
		wp_delete_post( (int) $entry_id, true );
	}

	delete_option( 'thimbleform_settings' );

	$caps_file = dirname( __FILE__ ) . '/class-capabilities.php';
	if ( is_readable( $caps_file ) ) {
		require_once $caps_file;
		if ( class_exists( 'Thimbleform_Capabilities' ) ) {
			Thimbleform_Capabilities::remove_all();
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'thimbleform\\_%'" );

	$table = $wpdb->prefix . 'thimbleform_form_views';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

	$spam_log = $wpdb->prefix . 'thimbleform_spam_log';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$spam_log}" );
	delete_option( 'thimbleform_spam_log_db_version' );

	$email_log = $wpdb->prefix . 'thimbleform_email_log';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$email_log}" );
	delete_option( 'thimbleform_email_log_db_version' );

	wp_clear_scheduled_hook( 'thimbleform_spam_log_cleanup' );
	wp_clear_scheduled_hook( 'thimbleform_email_log_cleanup' );
	wp_clear_scheduled_hook( 'thimbleform_cleanup_entries' );
}
