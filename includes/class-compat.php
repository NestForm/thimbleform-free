<?php
/**
 * Backward compatibility for Vite Forms and LiteForms identifiers.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Compat {

	public static function init() {
		self::class_aliases();
		self::deprecated_shortcode();
		self::deprecated_ajax();
		self::deprecated_admin_actions();
		self::deprecated_hooks();
		self::deprecated_filters();
	}

	private static function class_aliases() {
		$map = array(
			'Nestform_Post_Type'   => array( 'LiteForms_Post_Type', 'Vite_Forms_Post_Type' ),
			'Nestform_Submissions' => array( 'LiteForms_Submissions', 'Vite_Forms_Submissions' ),
			'Nestform_Dashboard'   => array( 'LiteForms_Dashboard', 'Vite_Forms_Dashboard' ),
			'Nestform_Admin_UI'    => array( 'LiteForms_Admin_UI', 'Vite_Forms_Admin_UI' ),
			'Nestform_Renderer'    => array( 'LiteForms_Renderer', 'Vite_Forms_Renderer' ),
			'Nestform_Submit'      => array( 'LiteForms_Submit', 'Vite_Forms_Submit' ),
			'Nestform_Captcha'     => array( 'LiteForms_Captcha', 'Vite_Forms_Captcha' ),
			'Nestform_Export'      => array( 'LiteForms_Export', 'Vite_Forms_Export' ),
			'Nestform_Webhook'     => array( 'LiteForms_Webhook', 'Vite_Forms_Webhook' ),
			'Nestform_Templates'   => array( 'LiteForms_Templates', 'Vite_Forms_Templates' ),
			'Nestform_Block'       => array( 'LiteForms_Block', 'Vite_Forms_Block' ),
			'Nestform_Form_Config' => array( 'LiteForms_Form_Config', 'Vite_Forms_Form_Config' ),
		);

		foreach ( $map as $class => $aliases ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			foreach ( $aliases as $alias ) {
				if ( ! class_exists( $alias, false ) ) {
					class_alias( $class, $alias );
				}
			}
		}

		if ( defined( 'NESTFORM_VERSION' ) ) {
			if ( ! defined( 'LITEFORMS_VERSION' ) ) {
				define( 'LITEFORMS_VERSION', NESTFORM_VERSION );
			}
			if ( ! defined( 'VITE_FORMS_VERSION' ) ) {
				define( 'VITE_FORMS_VERSION', NESTFORM_VERSION );
			}
		}
		if ( defined( 'NESTFORM_PATH' ) ) {
			if ( ! defined( 'LITEFORMS_PATH' ) ) {
				define( 'LITEFORMS_PATH', NESTFORM_PATH );
			}
			if ( ! defined( 'VITE_FORMS_PATH' ) ) {
				define( 'VITE_FORMS_PATH', NESTFORM_PATH );
			}
		}
		if ( defined( 'NESTFORM_URL' ) ) {
			if ( ! defined( 'LITEFORMS_URL' ) ) {
				define( 'LITEFORMS_URL', NESTFORM_URL );
			}
			if ( ! defined( 'VITE_FORMS_URL' ) ) {
				define( 'VITE_FORMS_URL', NESTFORM_URL );
			}
		}
	}

	private static function deprecated_shortcode() {
		add_shortcode( 'liteform', array( 'Nestform_Renderer', 'shortcode' ) );
		add_shortcode( 'vite_form', array( 'Nestform_Renderer', 'shortcode' ) );
	}

	private static function deprecated_ajax() {
		add_action( 'wp_ajax_liteforms_submit', array( 'Nestform_Submit', 'handle' ) );
		add_action( 'wp_ajax_nopriv_liteforms_submit', array( 'Nestform_Submit', 'handle' ) );
		add_action( 'wp_ajax_liteforms_preview', array( 'Nestform_Admin_UI', 'ajax_preview' ) );
		add_action( 'wp_ajax_vite_forms_submit', array( 'Nestform_Submit', 'handle' ) );
		add_action( 'wp_ajax_nopriv_vite_forms_submit', array( 'Nestform_Submit', 'handle' ) );
		add_action( 'wp_ajax_vite_forms_preview', array( 'Nestform_Admin_UI', 'ajax_preview' ) );
	}

	private static function deprecated_admin_actions() {
		add_action( 'admin_post_liteforms_duplicate', array( 'Nestform_Post_Type', 'handle_duplicate' ) );
		add_action( 'admin_post_liteforms_set_entry_status', array( 'Nestform_Submissions', 'handle_set_status' ) );
		add_action( 'admin_post_liteforms_export_csv', array( 'Nestform_Export', 'handle' ) );
		add_action( 'admin_post_liteforms_apply_template', array( 'Nestform_Templates', 'handle' ) );
		add_action( 'admin_post_vite_forms_duplicate', array( 'Nestform_Post_Type', 'handle_duplicate' ) );
		add_action( 'admin_post_vite_forms_set_entry_status', array( 'Nestform_Submissions', 'handle_set_status' ) );
		add_action( 'admin_post_vite_forms_export_csv', array( 'Nestform_Export', 'handle' ) );
		add_action( 'admin_post_vite_forms_apply_template', array( 'Nestform_Templates', 'handle' ) );
	}

	private static function deprecated_hooks() {
		$actions = array(
			'nestform_submitted'       => array( 'liteforms_submitted', 'vite_forms_submitted' ),
			'nestform_mail_sent'       => array( 'liteforms_mail_sent', 'vite_forms_mail_sent' ),
			'nestform_extra_mail_sent' => array( 'liteforms_extra_mail_sent', 'vite_forms_extra_mail_sent' ),
			'nestform_user_mail_sent'  => array( 'liteforms_user_mail_sent', 'vite_forms_user_mail_sent' ),
			'nestform_before_validate' => array( 'liteforms_before_validate', 'vite_forms_before_validate' ),
			'nestform_webhook_sent'    => array( 'liteforms_webhook_sent', 'vite_forms_webhook_sent' ),
		);

		foreach ( $actions as $new_hook => $old_hooks ) {
			add_action(
				$new_hook,
				static function () use ( $old_hooks ) {
					$args = func_get_args();
					foreach ( $old_hooks as $old_hook ) {
						do_action_ref_array( $old_hook, $args );
					}
				},
				999,
				99
			);
		}
	}

	private static function deprecated_filters() {
		$filters = array(
			'nestform_captcha_html'         => array( 'liteforms_captcha_html', 'vite_forms_captcha_html' ),
			'nestform_verify_captcha'       => array( 'liteforms_verify_captcha', 'vite_forms_verify_captcha' ),
			'nestform_entry_data'           => array( 'liteforms_entry_data', 'vite_forms_entry_data' ),
			'nestform_validate_field'       => array( 'liteforms_validate_field', 'vite_forms_validate_field' ),
			'nestform_field_html'           => array( 'liteforms_field_html', 'vite_forms_field_html' ),
			'nestform_field_classes'        => array( 'liteforms_field_classes', 'vite_forms_field_classes' ),
			'nestform_render_html'          => array( 'liteforms_render_html', 'vite_forms_render_html' ),
			'nestform_webhook_payload'      => array( 'liteforms_webhook_payload', 'vite_forms_webhook_payload' ),
			'nestform_webhook_request_args' => array( 'liteforms_webhook_request_args', 'vite_forms_webhook_request_args' ),
			'nestform_mail_args'            => array( 'liteforms_mail_args', 'vite_forms_mail_args' ),
			'nestform_extra_mail_args'      => array( 'liteforms_extra_mail_args', 'vite_forms_extra_mail_args' ),
			'nestform_user_mail_args'       => array( 'liteforms_user_mail_args', 'vite_forms_user_mail_args' ),
			'nestform_scoped_custom_css'    => array( 'liteforms_scoped_custom_css', 'vite_forms_scoped_custom_css' ),
			'nestform_style_inline_parts'   => array( 'liteforms_style_inline_parts', 'vite_forms_style_inline_parts' ),
			'nestform_style_form_classes'   => array( 'liteforms_style_form_classes', 'vite_forms_style_form_classes' ),
			'nestform_is_safe_outbound_url' => array( 'liteforms_is_safe_outbound_url', 'vite_forms_is_safe_outbound_url' ),
			'nestform_store_password_value' => array( 'liteforms_store_password_value', 'vite_forms_store_password_value' ),
			'nestform_akismet_key'          => array( 'liteforms_akismet_key', 'vite_forms_akismet_key' ),
		);

		foreach ( $filters as $new_hook => $old_hooks ) {
			add_filter(
				$new_hook,
				static function ( $value ) use ( $old_hooks ) {
					$args    = func_get_args();
					$args[0] = $value;
					foreach ( $old_hooks as $old_hook ) {
						$args[0] = apply_filters_ref_array( $old_hook, $args );
					}

					return $args[0];
				},
				1,
				99
			);
		}
	}
}
