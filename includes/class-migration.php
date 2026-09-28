<?php
/**
 * One-time DB migration from Vite Forms / LiteForms identifiers to Thimbleform.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Migration {

	const OPTION  = 'nestform_db_version';
	const VERSION = 2;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_run' ), 4 );
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
			'vite_form'       => 'nestform',
			'liteform'        => 'nestform',
			'vite_form_entry' => 'nestform_entry',
			'liteform_entry'  => 'nestform_entry',
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
			'_vite_forms_fields'   => '_nestform_fields',
			'_liteforms_fields'    => '_nestform_fields',
			'_vite_forms_messages' => '_nestform_messages',
			'_liteforms_messages'  => '_nestform_messages',
			'_vite_forms_mail'     => '_nestform_mail',
			'_liteforms_mail'      => '_nestform_mail',
			'_vite_forms_settings' => '_nestform_settings',
			'_liteforms_settings'  => '_nestform_settings',
			'_vite_forms_form_id'  => '_nestform_form_id',
			'_liteforms_form_id'   => '_nestform_form_id',
			'_vite_forms_payload'  => '_nestform_payload',
			'_liteforms_payload'   => '_nestform_payload',
			'_vite_forms_ip'       => '_nestform_ip',
			'_liteforms_ip'        => '_nestform_ip',
			'_vite_forms_status'   => '_nestform_status',
			'_liteforms_status'    => '_nestform_status',
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
	}
}
