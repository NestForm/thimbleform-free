<?php
/**
 * Backward compatibility for Vite Forms and LiteForms identifiers.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Compat {

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
			'Thimbleform_Post_Type'   => array( 'LiteForms_Post_Type', 'Vite_Forms_Post_Type' ),
			'Thimbleform_Submissions' => array( 'LiteForms_Submissions', 'Vite_Forms_Submissions' ),
			'Thimbleform_Dashboard'   => array( 'LiteForms_Dashboard', 'Vite_Forms_Dashboard' ),
			'Thimbleform_Admin_UI'    => array( 'LiteForms_Admin_UI', 'Vite_Forms_Admin_UI' ),
			'Thimbleform_Renderer'    => array( 'LiteForms_Renderer', 'Vite_Forms_Renderer' ),
			'Thimbleform_Submit'      => array( 'LiteForms_Submit', 'Vite_Forms_Submit' ),
			'Thimbleform_Captcha'     => array( 'LiteForms_Captcha', 'Vite_Forms_Captcha' ),
			'Thimbleform_Export'      => array( 'LiteForms_Export', 'Vite_Forms_Export' ),
			'Thimbleform_Webhook'     => array( 'LiteForms_Webhook', 'Vite_Forms_Webhook' ),
			'Thimbleform_Templates'   => array( 'LiteForms_Templates', 'Vite_Forms_Templates' ),
			'Thimbleform_Block'       => array( 'LiteForms_Block', 'Vite_Forms_Block' ),
			'Thimbleform_Form_Config' => array( 'LiteForms_Form_Config', 'Vite_Forms_Form_Config' ),
		);

		foreach ( $map as $class => $aliases ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$legacy = 'Nestform_' . substr( $class, strlen( 'Thimbleform_' ) );
			if ( ! class_exists( $legacy, false ) ) {
				class_alias( $class, $legacy );
			}
			foreach ( $aliases as $alias ) {
				if ( ! class_exists( $alias, false ) ) {
					class_alias( $class, $alias );
				}
			}
		}

		foreach ( get_declared_classes() as $class ) {
			if ( 0 !== strpos( $class, 'Thimbleform_' ) || 0 === strpos( $class, 'Thimbleform_Pro' ) ) {
				continue;
			}
			$alias = 'Nestform_' . substr( $class, strlen( 'Thimbleform_' ) );
			if ( ! class_exists( $alias, false ) ) {
				class_alias( $class, $alias );
			}
		}

		if ( defined( 'THIMBLEFORM_VERSION' ) ) {
			if ( ! defined( 'LITEFORMS_VERSION' ) ) {
				define( 'LITEFORMS_VERSION', THIMBLEFORM_VERSION );
			}
			if ( ! defined( 'VITE_FORMS_VERSION' ) ) {
				define( 'VITE_FORMS_VERSION', THIMBLEFORM_VERSION );
			}
		}
		if ( defined( 'THIMBLEFORM_PATH' ) ) {
			if ( ! defined( 'LITEFORMS_PATH' ) ) {
				define( 'LITEFORMS_PATH', THIMBLEFORM_PATH );
			}
			if ( ! defined( 'VITE_FORMS_PATH' ) ) {
				define( 'VITE_FORMS_PATH', THIMBLEFORM_PATH );
			}
		}
		if ( defined( 'THIMBLEFORM_URL' ) ) {
			if ( ! defined( 'LITEFORMS_URL' ) ) {
				define( 'LITEFORMS_URL', THIMBLEFORM_URL );
			}
			if ( ! defined( 'VITE_FORMS_URL' ) ) {
				define( 'VITE_FORMS_URL', THIMBLEFORM_URL );
			}
		}
	}

	private static function deprecated_shortcode() {
		add_shortcode( 'liteform', array( 'Thimbleform_Renderer', 'shortcode' ) );
		add_shortcode( 'vite_form', array( 'Thimbleform_Renderer', 'shortcode' ) );
	}

	private static function deprecated_ajax() {
		add_action( 'wp_ajax_liteforms_submit', array( 'Thimbleform_Submit', 'handle' ) );
		add_action( 'wp_ajax_nopriv_liteforms_submit', array( 'Thimbleform_Submit', 'handle' ) );
		add_action( 'wp_ajax_liteforms_preview', array( 'Thimbleform_Admin_UI', 'ajax_preview' ) );
		add_action( 'wp_ajax_vite_forms_submit', array( 'Thimbleform_Submit', 'handle' ) );
		add_action( 'wp_ajax_nopriv_vite_forms_submit', array( 'Thimbleform_Submit', 'handle' ) );
		add_action( 'wp_ajax_vite_forms_preview', array( 'Thimbleform_Admin_UI', 'ajax_preview' ) );
	}

	private static function deprecated_admin_actions() {
		add_action( 'admin_post_liteforms_duplicate', array( 'Thimbleform_Post_Type', 'handle_duplicate' ) );
		add_action( 'admin_post_liteforms_set_entry_status', array( 'Thimbleform_Submissions', 'handle_set_status' ) );
		add_action( 'admin_post_liteforms_export_csv', array( 'Thimbleform_Export', 'handle' ) );
		add_action( 'admin_post_liteforms_apply_template', array( 'Thimbleform_Templates', 'handle' ) );
		add_action( 'admin_post_vite_forms_duplicate', array( 'Thimbleform_Post_Type', 'handle_duplicate' ) );
		add_action( 'admin_post_vite_forms_set_entry_status', array( 'Thimbleform_Submissions', 'handle_set_status' ) );
		add_action( 'admin_post_vite_forms_export_csv', array( 'Thimbleform_Export', 'handle' ) );
		add_action( 'admin_post_vite_forms_apply_template', array( 'Thimbleform_Templates', 'handle' ) );
	}

	private static function deprecated_hooks() {
		$actions = array(
			'thimbleform_submitted'       => array( 'liteforms_submitted', 'vite_forms_submitted' ),
			'thimbleform_mail_sent'       => array( 'liteforms_mail_sent', 'vite_forms_mail_sent' ),
			'thimbleform_extra_mail_sent' => array( 'liteforms_extra_mail_sent', 'vite_forms_extra_mail_sent' ),
			'thimbleform_user_mail_sent'  => array( 'liteforms_user_mail_sent', 'vite_forms_user_mail_sent' ),
			'thimbleform_before_validate' => array( 'liteforms_before_validate', 'vite_forms_before_validate' ),
			'thimbleform_webhook_sent'    => array( 'liteforms_webhook_sent', 'vite_forms_webhook_sent' ),
		);

		foreach ( $actions as $new_hook => $old_hooks ) {
			$old_hooks[] = 'nestform_' . substr( $new_hook, strlen( 'thimbleform_' ) );
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
			'thimbleform_captcha_html'         => array( 'liteforms_captcha_html', 'vite_forms_captcha_html' ),
			'thimbleform_verify_captcha'       => array( 'liteforms_verify_captcha', 'vite_forms_verify_captcha' ),
			'thimbleform_entry_data'           => array( 'liteforms_entry_data', 'vite_forms_entry_data' ),
			'thimbleform_validate_field'       => array( 'liteforms_validate_field', 'vite_forms_validate_field' ),
			'thimbleform_field_html'           => array( 'liteforms_field_html', 'vite_forms_field_html' ),
			'thimbleform_field_classes'        => array( 'liteforms_field_classes', 'vite_forms_field_classes' ),
			'thimbleform_render_html'          => array( 'liteforms_render_html', 'vite_forms_render_html' ),
			'thimbleform_webhook_payload'      => array( 'liteforms_webhook_payload', 'vite_forms_webhook_payload' ),
			'thimbleform_webhook_request_args' => array( 'liteforms_webhook_request_args', 'vite_forms_webhook_request_args' ),
			'thimbleform_mail_args'            => array( 'liteforms_mail_args', 'vite_forms_mail_args' ),
			'thimbleform_extra_mail_args'      => array( 'liteforms_extra_mail_args', 'vite_forms_extra_mail_args' ),
			'thimbleform_user_mail_args'       => array( 'liteforms_user_mail_args', 'vite_forms_user_mail_args' ),
			'thimbleform_scoped_custom_css'    => array( 'liteforms_scoped_custom_css', 'vite_forms_scoped_custom_css' ),
			'thimbleform_style_inline_parts'   => array( 'liteforms_style_inline_parts', 'vite_forms_style_inline_parts' ),
			'thimbleform_style_form_classes'   => array( 'liteforms_style_form_classes', 'vite_forms_style_form_classes' ),
			'thimbleform_is_safe_outbound_url' => array( 'liteforms_is_safe_outbound_url', 'vite_forms_is_safe_outbound_url' ),
			'thimbleform_store_password_value' => array( 'liteforms_store_password_value', 'vite_forms_store_password_value' ),
			'thimbleform_akismet_key'          => array( 'liteforms_akismet_key', 'vite_forms_akismet_key' ),
		);

		foreach ( $filters as $new_hook => $old_hooks ) {
			$old_hooks[] = 'nestform_' . substr( $new_hook, strlen( 'thimbleform_' ) );
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
