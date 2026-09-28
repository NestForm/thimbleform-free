<?php
/**
 * One-time DB migration from Vite Forms / LiteForms identifiers to Thimbleform.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Migration {

	const OPTION  = 'thimbleform_db_version';
	const VERSION = 3;

	public static function init() {
		self::maybe_run();
	}

	public static function maybe_run() {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		self::run();
		update_option( self::OPTION, self::VERSION, false );
	}

	private static function run() {
		global $wpdb;

		$post_types = array(
			'vite_form'       => 'thimbleform',
			'liteform'        => 'thimbleform',
			'nestform'        => 'thimbleform',
			'vite_form_entry' => 'thimbleform_entry',
			'liteform_entry'  => 'thimbleform_entry',
			'nestform_entry'  => 'thimbleform_entry',
		);

		foreach ( $post_types as $from => $to ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
					$to,
					$from
				)
			);
		}

		$meta_map = array(
			'_vite_forms_fields'   => '_thimbleform_fields',
			'_liteforms_fields'    => '_thimbleform_fields',
			'_vite_forms_messages' => '_thimbleform_messages',
			'_liteforms_messages'  => '_thimbleform_messages',
			'_vite_forms_mail'     => '_thimbleform_mail',
			'_liteforms_mail'      => '_thimbleform_mail',
			'_vite_forms_settings' => '_thimbleform_settings',
			'_liteforms_settings'  => '_thimbleform_settings',
			'_vite_forms_form_id'  => '_thimbleform_form_id',
			'_liteforms_form_id'   => '_thimbleform_form_id',
			'_vite_forms_payload'  => '_thimbleform_payload',
			'_liteforms_payload'   => '_thimbleform_payload',
			'_vite_forms_ip'       => '_thimbleform_ip',
			'_liteforms_ip'        => '_thimbleform_ip',
			'_vite_forms_status'   => '_thimbleform_status',
			'_liteforms_status'    => '_thimbleform_status',
		);

		foreach ( $meta_map as $old_key => $new_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$new_key,
					$old_key
				)
			);
		}

		self::rename_prefixed_keys( $wpdb->postmeta, 'meta_key' );
		self::rename_prefixed_keys( $wpdb->usermeta, 'meta_key' );
		self::rename_prefixed_keys( $wpdb->options, 'option_name' );
		self::rename_tables();
		self::rename_embedded_markup();
		flush_rewrite_rules( false );
	}

	/**
	 * @param string $table Table name.
	 * @param string $column Column that stores the identifier.
	 */
	private static function rename_prefixed_keys( $table, $column ) {
		global $wpdb;

		if ( ! in_array( $column, array( 'meta_key', 'option_name' ), true ) ) {
			return;
		}

		$like = '%' . $wpdb->esc_like( 'nestform' ) . '%';
		$skip = '%' . $wpdb->esc_like( 'thimbleform' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$column} FROM {$table} WHERE {$column} LIKE %s AND {$column} NOT LIKE %s",
				$like,
				$skip
			)
		);

		foreach ( $names as $name ) {
			$renamed = str_replace( 'nestform', 'thimbleform', (string) $name );
			if ( $renamed === $name ) {
				continue;
			}
			if ( 'option_name' === $column ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $renamed ) );
				if ( $exists ) {
					continue;
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET {$column} = %s WHERE {$column} = %s",
					$renamed,
					$name
				)
			);
		}
	}

	private static function rename_tables() {
		global $wpdb;

		$tables = array(
			'nestform_spam_log'   => 'thimbleform_spam_log',
			'nestform_email_log'  => 'thimbleform_email_log',
			'nestform_form_views' => 'thimbleform_form_views',
		);

		foreach ( $tables as $old => $new ) {
			$old_table = $wpdb->prefix . $old;
			$new_table = $wpdb->prefix . $new;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );
			if ( $old_exists && ! $new_exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
			}
		}
	}

	private static function rename_embedded_markup() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				'wp:nestform/form',
				'wp:thimbleform/form',
				'%' . $wpdb->esc_like( 'wp:nestform/form' ) . '%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key = %s AND meta_value LIKE %s",
				'"widgetType":"nestform"',
				'"widgetType":"thimbleform"',
				'_elementor_data',
				'%' . $wpdb->esc_like( '"widgetType":"nestform"' ) . '%'
			)
		);
	}
}
