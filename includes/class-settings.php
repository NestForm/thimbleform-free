<?php
/**
 * Plugin-wide settings (Forms → Settings).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Settings {

	const OPTION    = 'nestform_settings';
	const PAGE_SLUG = 'nestform-settings';

	const SITE_URL = 'https://thimbleform.app/';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_import_theme_captcha' ), 5 );
		add_action( 'admin_post_nestform_send_test_email', array( __CLASS__, 'send_test_email' ) );
		add_filter(
			'nestform_show_promotions',
			static function ( $show ) {
				if ( ! $show ) {
					return false;
				}
				return self::pro_promotions_enabled();
			},
			15
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'captcha_enabled'            => '0',
			'captcha_provider'           => 'recaptcha_v2',
			'captcha_site_key'           => '',
			'captcha_secret_key'         => '',
			'captcha_v3_score'           => '0.5',
			'captcha_keys'               => self::empty_captcha_keys(),
			'stripe_enabled'             => '0',
			'stripe_mode'                => 'test',
			'stripe_keys'                => self::empty_stripe_keys(),
			'hubspot_enabled'            => '0',
			'hubspot_access_token'       => '',
			'default_submit_label'       => '',
			'default_success_message'    => '',
			'email_from_name'            => '',
			'email_from_email'           => '',
			'email_log_retention_days'   => '30',
			'date_format'                => 'site',
			'auto_mark_read'             => '1',
			'delete_data_on_uninstall'   => '0',
			'entry_retention_days'       => '0',
			'hide_pro_promotions'        => '0',
			'credit_enabled'             => '0',
			'review_requests_enabled'    => '1',
			'role_caps'                  => array(
				'editor' => array( 'nestform_manage_forms', 'nestform_view_entries' ),
			),
			'rate_limit_enabled'         => '1',
			'rate_limit_max'             => '10',
			'rate_limit_window'          => '3600',
			'duplicate_check_enabled'    => '1',
			'duplicate_check_window'     => '300',
			'content_filter_enabled'     => '1',
			'max_links'                  => '5',
			'blocked_words'              => '',
			'blocked_email_domains'      => '',
			'blocked_ips'                => '',
			'spam_log_enabled'           => '1',
			'spam_log_retention_days'    => '30',
		);
	}

	/**
	 * Empty per-provider captcha key map.
	 *
	 * @return array<string, array{site:string,secret:string}>
	 */
	public static function empty_captcha_keys() {
		$keys = array();
		foreach ( array( 'recaptcha_v2', 'recaptcha_v3', 'turnstile', 'hcaptcha' ) as $slug ) {
			$keys[ $slug ] = array(
				'site'   => '',
				'secret' => '',
			);
		}
		return $keys;
	}

	/**
	 * Empty Stripe key map (test + live).
	 *
	 * @return array<string, array{publishable:string,secret:string}>
	 */
	public static function empty_stripe_keys() {
		return array(
			'test' => array(
				'publishable' => '',
				'secret'      => '',
			),
			'live' => array(
				'publishable' => '',
				'secret'      => '',
			),
		);
	}

	/**
	 * Normalize stripe_keys option.
	 *
	 * @param array<string, mixed> $settings Settings row.
	 * @return array<string, mixed>
	 */
	public static function sync_stripe_keys( array $settings ) {
		$mode = isset( $settings['stripe_mode'] ) ? sanitize_key( (string) $settings['stripe_mode'] ) : 'test';
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) {
			$mode = 'test';
		}

		$keys = self::empty_stripe_keys();
		$raw  = isset( $settings['stripe_keys'] ) && is_array( $settings['stripe_keys'] ) ? $settings['stripe_keys'] : array();
		foreach ( array( 'test', 'live' ) as $slug ) {
			$row = isset( $raw[ $slug ] ) && is_array( $raw[ $slug ] ) ? $raw[ $slug ] : array();
			$keys[ $slug ] = array(
				'publishable' => isset( $row['publishable'] ) ? sanitize_text_field( (string) $row['publishable'] ) : '',
				'secret'      => isset( $row['secret'] ) ? sanitize_text_field( (string) $row['secret'] ) : '',
			);
		}

		$settings['stripe_mode'] = $mode;
		$settings['stripe_keys'] = $keys;
		return $settings;
	}

	/**
	 * Normalize captcha_keys option and keep active site/secret in sync.
	 *
	 * @param array<string, mixed> $settings Settings row.
	 * @return array<string, mixed>
	 */
	public static function sync_captcha_keys( array $settings ) {
		$allowed  = array( 'recaptcha_v2', 'recaptcha_v3', 'turnstile', 'hcaptcha' );
		$provider = isset( $settings['captcha_provider'] ) ? sanitize_key( (string) $settings['captcha_provider'] ) : 'recaptcha_v2';
		if ( ! in_array( $provider, $allowed, true ) ) {
			$provider = 'recaptcha_v2';
		}

		$keys = self::empty_captcha_keys();
		$raw  = isset( $settings['captcha_keys'] ) && is_array( $settings['captcha_keys'] ) ? $settings['captcha_keys'] : array();
		foreach ( $allowed as $slug ) {
			$row = isset( $raw[ $slug ] ) && is_array( $raw[ $slug ] ) ? $raw[ $slug ] : array();
			$keys[ $slug ] = array(
				'site'   => isset( $row['site'] ) ? sanitize_text_field( (string) $row['site'] ) : '',
				'secret' => isset( $row['secret'] ) ? sanitize_text_field( (string) $row['secret'] ) : '',
			);
		}

		$legacy_site   = isset( $settings['captcha_site_key'] ) ? sanitize_text_field( (string) $settings['captcha_site_key'] ) : '';
		$legacy_secret = isset( $settings['captcha_secret_key'] ) ? sanitize_text_field( (string) $settings['captcha_secret_key'] ) : '';
		if ( ( '' !== $legacy_site || '' !== $legacy_secret ) && '' === $keys[ $provider ]['site'] && '' === $keys[ $provider ]['secret'] ) {
			$keys[ $provider ]['site']   = $legacy_site;
			$keys[ $provider ]['secret'] = $legacy_secret;
		}

		$settings['captcha_provider']   = $provider;
		$settings['captcha_keys']       = $keys;
		$settings['captcha_site_key']   = $keys[ $provider ]['site'];
		$settings['captcha_secret_key'] = $keys[ $provider ]['secret'];
		return $settings;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = self::sync_captcha_keys( array_merge( self::defaults(), $stored ) );
		$out    = self::sync_stripe_keys( $out );

		if ( defined( 'NESTFORM_RECAPTCHA_SITE_KEY' ) && NESTFORM_RECAPTCHA_SITE_KEY !== '' ) {
			$out['captcha_site_key'] = (string) NESTFORM_RECAPTCHA_SITE_KEY;
			$provider                = (string) $out['captcha_provider'];
			if ( isset( $out['captcha_keys'][ $provider ] ) && is_array( $out['captcha_keys'][ $provider ] ) ) {
				$out['captcha_keys'][ $provider ]['site'] = (string) NESTFORM_RECAPTCHA_SITE_KEY;
			}
		}
		if ( defined( 'NESTFORM_RECAPTCHA_SECRET_KEY' ) && NESTFORM_RECAPTCHA_SECRET_KEY !== '' ) {
			$out['captcha_secret_key'] = (string) NESTFORM_RECAPTCHA_SECRET_KEY;
			$provider                  = (string) $out['captcha_provider'];
			if ( isset( $out['captcha_keys'][ $provider ] ) && is_array( $out['captcha_keys'][ $provider ] ) ) {
				$out['captcha_keys'][ $provider ]['secret'] = (string) NESTFORM_RECAPTCHA_SECRET_KEY;
			}
		}

		/**
		 * Filter plugin-wide Thimbleform settings.
		 *
		 * @param array<string, mixed> $out Settings.
		 */
		return (array) apply_filters( 'nestform_settings', $out );
	}

	/**
	 * @return bool
	 */
	public static function pro_promotions_enabled() {
		$s = self::get();
		return '1' !== (string) ( $s['hide_pro_promotions'] ?? '0' );
	}

	/**
	 * @return bool
	 */
	public static function credit_enabled() {
		$s = self::get();
		return '1' === (string) ( $s['credit_enabled'] ?? '0' );
	}

	/**
	 * @return bool
	 */
	public static function review_requests_enabled() {
		$s = self::get();
		return '1' === (string) ( $s['review_requests_enabled'] ?? '1' );
	}

	/**
	 * @return string
	 */
	public static function default_submit_label() {
		return trim( (string) ( self::get()['default_submit_label'] ?? '' ) );
	}

	/**
	 * @return string
	 */
	public static function default_success_message() {
		return trim( (string) ( self::get()['default_success_message'] ?? '' ) );
	}

	/**
	 * Opt-in credit line under public forms.
	 *
	 * @return string
	 */
	public static function credit_html() {
		if ( ! self::credit_enabled() ) {
			return '';
		}
		return sprintf(
			'<p class="nest-form__credit"><a href="%1$s" rel="noopener noreferrer">%2$s</a></p>',
			esc_url( self::SITE_URL ),
			esc_html__( 'Powered by Thimbleform', 'nestform' )
		);
	}

	/**
	 * Whether plugin-level captcha is ready (toggle + keys).
	 *
	 * @return bool
	 */
	public static function captcha_ready() {
		$s = self::get();
		if ( '1' !== (string) ( $s['captcha_enabled'] ?? '0' ) ) {
			return false;
		}
		return (string) ( $s['captcha_site_key'] ?? '' ) !== '' && (string) ( $s['captcha_secret_key'] ?? '' ) !== '';
	}

	/**
	 * Whether Stripe is enabled and keys for the active mode are present.
	 *
	 * @return bool
	 */
	public static function stripe_ready() {
		$s = self::get();
		if ( '1' !== (string) ( $s['stripe_enabled'] ?? '0' ) ) {
			return false;
		}
		$pub = self::stripe_publishable_key();
		$sec = self::stripe_secret_key();
		return '' !== $pub && '' !== $sec;
	}

	/**
	 * Active Stripe mode (test|live).
	 *
	 * @return string
	 */
	public static function stripe_mode() {
		$s    = self::get();
		$mode = sanitize_key( (string) ( $s['stripe_mode'] ?? 'test' ) );
		return in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';
	}

	/**
	 * Publishable key for the active mode (safe for front-end).
	 *
	 * @return string
	 */
	public static function stripe_publishable_key() {
		$s    = self::get();
		$mode = self::stripe_mode();
		$keys = isset( $s['stripe_keys'][ $mode ] ) && is_array( $s['stripe_keys'][ $mode ] ) ? $s['stripe_keys'][ $mode ] : array();
		return trim( (string) ( $keys['publishable'] ?? '' ) );
	}

	/**
	 * Secret key for the active mode (server-side only — never localize).
	 *
	 * @return string
	 */
	public static function stripe_secret_key() {
		$s    = self::get();
		$mode = self::stripe_mode();
		$keys = isset( $s['stripe_keys'][ $mode ] ) && is_array( $s['stripe_keys'][ $mode ] ) ? $s['stripe_keys'][ $mode ] : array();
		return trim( (string) ( $keys['secret'] ?? '' ) );
	}

	/**
	 * Whether HubSpot is enabled and a Private App token is present.
	 *
	 * @return bool
	 */
	public static function hubspot_ready() {
		$s = self::get();
		if ( empty( $s['hubspot_enabled'] ) || '1' !== (string) $s['hubspot_enabled'] ) {
			return false;
		}
		return '' !== self::hubspot_access_token();
	}

	/**
	 * HubSpot Private App access token (server-side only).
	 *
	 * @return string
	 */
	public static function hubspot_access_token() {
		$s = self::get();
		return trim( (string) ( $s['hubspot_access_token'] ?? '' ) );
	}

	/**
	 * Default From name for notifications.
	 *
	 * @return string
	 */
	public static function mail_from_name() {
		$s = self::get();
		$n = trim( (string) ( $s['email_from_name'] ?? '' ) );
		return '' !== $n ? $n : (string) get_bloginfo( 'name' );
	}

	/**
	 * Default From email for notifications.
	 *
	 * @return string
	 */
	public static function mail_from_email() {
		$s = self::get();
		$e = trim( (string) ( $s['email_from_email'] ?? '' ) );
		if ( '' !== $e && is_email( $e ) ) {
			return $e;
		}
		$admin = get_option( 'admin_email' );
		return ( is_string( $admin ) && is_email( $admin ) ) ? $admin : '';
	}

	/**
	 * PHP date format for entry timestamps in admin.
	 *
	 * @return string
	 */
	public static function entry_date_format() {
		$s = self::get();
		$key = (string) ( $s['date_format'] ?? 'site' );
		$map = array(
			'site'     => trim( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'ymd_hi'   => 'Y-m-d H:i',
			'mdy_gia'  => 'M j, Y g:i a',
			'dmy_hi'   => 'd/m/Y H:i',
		);
		$format = isset( $map[ $key ] ) ? $map[ $key ] : $map['site'];
		return '' !== $format ? $format : 'Y-m-d H:i';
	}

	/**
	 * @param int|WP_Post $post Entry post.
	 * @return string
	 */
	public static function format_entry_datetime( $post ) {
		$ts = get_post_time( 'U', true, $post );
		if ( ! $ts ) {
			return '';
		}
		return wp_date( self::entry_date_format(), (int) $ts );
	}

	/**
	 * Whether opening an entry marks it read.
	 *
	 * @return bool
	 */
	public static function auto_mark_read_enabled() {
		$s = self::get();
		return '1' === (string) ( $s['auto_mark_read'] ?? '1' );
	}

	/**
	 * Whether to wipe Thimbleform data on uninstall.
	 *
	 * @return bool
	 */
	public static function delete_data_on_uninstall() {
		$s = self::get();
		return '1' === (string) ( $s['delete_data_on_uninstall'] ?? '0' );
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => Nestform_Post_Type::POST_TYPE,
					'page'      => self::PAGE_SLUG,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * @return array<string, array{label:string,desc:string}>
	 */
	private static function sections() {
		$sections = array(
			'general' => array(
				'label' => __( 'General', 'nestform' ),
				'desc'  => __( 'Defaults for new forms and site-wide preferences.', 'nestform' ),
			),
			'email'   => array(
				'label' => __( 'Email', 'nestform' ),
				'desc'  => __( 'Sender defaults and delivery checks.', 'nestform' ),
			),
			'entries' => array(
				'label' => __( 'Entries', 'nestform' ),
				'desc'  => __( 'How submissions appear in the admin.', 'nestform' ),
			),
			'security' => array(
				'label' => __( 'Security', 'nestform' ),
				'desc'  => __( 'Site-wide spam protection for every Thimbleform form.', 'nestform' ),
			),
			'privacy' => array(
				'label' => __( 'Privacy & data', 'nestform' ),
				'desc'  => __( 'Retention, uninstall, access, and public credit.', 'nestform' ),
			),
		);

		/**
		 * Filter Settings sections.
		 *
		 * @param array<string, array{label:string,desc:string}> $sections
		 */
		return (array) apply_filters( 'nestform_settings_sections', $sections );
	}

	public static function register() {
		register_setting(
			'nestform_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$stored  = get_option( self::OPTION, array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$out     = array_merge( self::defaults(), $stored );
		$section = isset( $input['_section'] ) ? sanitize_key( (string) $input['_section'] ) : 'general';

		if ( 'integrations' === $section || 'integrations_captcha' === $section ) {
			$provider = isset( $input['captcha_provider'] ) ? sanitize_key( (string) $input['captcha_provider'] ) : 'recaptcha_v2';
			$allowed  = array( 'recaptcha_v2', 'recaptcha_v3', 'turnstile', 'hcaptcha' );
			if ( ! in_array( $provider, $allowed, true ) ) {
				$provider = 'recaptcha_v2';
			}
			$score = isset( $input['captcha_v3_score'] ) ? (float) $input['captcha_v3_score'] : 0.5;
			$score = min( 1.0, max( 0.0, $score ) );

			$keys     = self::empty_captcha_keys();
			$raw_keys = isset( $input['captcha_keys'] ) && is_array( $input['captcha_keys'] ) ? $input['captcha_keys'] : array();
			foreach ( $allowed as $slug ) {
				$row = isset( $raw_keys[ $slug ] ) && is_array( $raw_keys[ $slug ] ) ? $raw_keys[ $slug ] : array();
				$keys[ $slug ] = array(
					'site'   => isset( $row['site'] ) ? sanitize_text_field( (string) $row['site'] ) : '',
					'secret' => isset( $row['secret'] ) ? sanitize_text_field( (string) $row['secret'] ) : '',
				);
			}

			// Backward-compatible single fields: map into the active provider when keyed panels are absent.
			if ( array() === $raw_keys ) {
				$keys[ $provider ]['site']   = isset( $input['captcha_site_key'] ) ? sanitize_text_field( (string) $input['captcha_site_key'] ) : '';
				$keys[ $provider ]['secret'] = isset( $input['captcha_secret_key'] ) ? sanitize_text_field( (string) $input['captcha_secret_key'] ) : '';
			}

			$out['captcha_enabled']    = ! empty( $input['captcha_enabled'] ) ? '1' : '0';
			$out['captcha_provider']   = $provider;
			$out['captcha_keys']       = $keys;
			$out['captcha_site_key']   = $keys[ $provider ]['site'];
			$out['captcha_secret_key'] = $keys[ $provider ]['secret'];
			$out['captcha_v3_score']   = (string) $score;

			if ( 'integrations_captcha' === $section ) {
				return $out;
			}
		}

		if ( 'integrations' === $section || 'integrations_stripe' === $section ) {
			$can_payments = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::PAYMENTS );
			if ( ! $can_payments ) {
				if ( 'integrations_stripe' === $section ) {
					return $out;
				}
			} else {
				$stripe_mode = isset( $input['stripe_mode'] ) ? sanitize_key( (string) $input['stripe_mode'] ) : 'test';
				if ( ! in_array( $stripe_mode, array( 'test', 'live' ), true ) ) {
					$stripe_mode = 'test';
				}
				$stripe_keys     = self::empty_stripe_keys();
				$raw_stripe_keys = isset( $input['stripe_keys'] ) && is_array( $input['stripe_keys'] ) ? $input['stripe_keys'] : array();
				foreach ( array( 'test', 'live' ) as $slug ) {
					$row = isset( $raw_stripe_keys[ $slug ] ) && is_array( $raw_stripe_keys[ $slug ] ) ? $raw_stripe_keys[ $slug ] : array();
					$stripe_keys[ $slug ] = array(
						'publishable' => isset( $row['publishable'] ) ? sanitize_text_field( (string) $row['publishable'] ) : '',
						'secret'      => isset( $row['secret'] ) ? sanitize_text_field( (string) $row['secret'] ) : '',
					);
				}
				$out['stripe_enabled'] = ! empty( $input['stripe_enabled'] ) ? '1' : '0';
				$out['stripe_mode']    = $stripe_mode;
				$out['stripe_keys']    = $stripe_keys;

				if ( 'integrations_stripe' === $section ) {
					return $out;
				}
			}
		}

		if ( 'integrations' === $section || 'integrations_hubspot' === $section ) {
			$can_hubspot = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::HUBSPOT );
			if ( ! $can_hubspot ) {
				return $out;
			}
			$out['hubspot_enabled']      = ! empty( $input['hubspot_enabled'] ) ? '1' : '0';
			$out['hubspot_access_token'] = isset( $input['hubspot_access_token'] )
				? sanitize_text_field( (string) $input['hubspot_access_token'] )
				: '';
			return $out;
		}

		if ( 'email' === $section ) {
			$from_email = isset( $input['email_from_email'] ) ? sanitize_email( (string) $input['email_from_email'] ) : '';
			$out['email_from_name']          = isset( $input['email_from_name'] ) ? sanitize_text_field( (string) $input['email_from_name'] ) : '';
			$out['email_from_email']         = $from_email;
			$out['email_log_retention_days'] = isset( $input['email_log_retention_days'] )
				? (string) max( 1, (int) $input['email_log_retention_days'] )
				: '30';
			return $out;
		}

		if ( 'entries' === $section ) {
			$date_key = isset( $input['date_format'] ) ? sanitize_key( (string) $input['date_format'] ) : 'site';
			if ( ! in_array( $date_key, array( 'site', 'ymd_hi', 'mdy_gia', 'dmy_hi' ), true ) ) {
				$date_key = 'site';
			}
			$out['date_format']    = $date_key;
			$out['auto_mark_read'] = ! empty( $input['auto_mark_read'] ) ? '1' : '0';
			return $out;
		}

		if ( 'access' === $section ) {
			$out['role_caps'] = class_exists( 'Nestform_Capabilities' )
				? Nestform_Capabilities::sanitize_role_caps( isset( $input['role_caps'] ) ? $input['role_caps'] : array() )
				: array();
			return $out;
		}

		if ( 'privacy' === $section ) {
			$out['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? '1' : '0';
			$out['entry_retention_days']     = isset( $input['entry_retention_days'] )
				? (string) max( 0, (int) $input['entry_retention_days'] )
				: '0';
			$out['credit_enabled']           = ! empty( $input['credit_enabled'] ) ? '1' : '0';
			$out['hide_pro_promotions']      = ! empty( $input['hide_pro_promotions'] ) ? '1' : '0';
			$out['review_requests_enabled']  = ! empty( $input['review_requests_enabled'] ) ? '1' : '0';
			return $out;
		}

		if ( 'security' === $section ) {
			$out['rate_limit_enabled']      = ! empty( $input['rate_limit_enabled'] ) ? '1' : '0';
			$out['rate_limit_max']          = isset( $input['rate_limit_max'] ) ? (string) max( 1, (int) $input['rate_limit_max'] ) : '10';
			$out['rate_limit_window']       = isset( $input['rate_limit_window'] ) ? (string) max( 60, (int) $input['rate_limit_window'] ) : '3600';
			$out['duplicate_check_enabled'] = ! empty( $input['duplicate_check_enabled'] ) ? '1' : '0';
			$out['duplicate_check_window']  = isset( $input['duplicate_check_window'] ) ? (string) max( 30, (int) $input['duplicate_check_window'] ) : '300';
			$out['content_filter_enabled']  = ! empty( $input['content_filter_enabled'] ) ? '1' : '0';
			$out['max_links']               = isset( $input['max_links'] ) ? (string) max( 0, (int) $input['max_links'] ) : '5';
			$out['blocked_words']           = class_exists( 'Nestform_Spam_Filter' )
				? Nestform_Spam_Filter::sanitize_lines( $input['blocked_words'] ?? '' )
				: sanitize_textarea_field( (string) ( $input['blocked_words'] ?? '' ) );
			$out['blocked_email_domains'] = class_exists( 'Nestform_Spam_Filter' )
				? Nestform_Spam_Filter::sanitize_lines( $input['blocked_email_domains'] ?? '' )
				: sanitize_textarea_field( (string) ( $input['blocked_email_domains'] ?? '' ) );
			$out['blocked_ips'] = class_exists( 'Nestform_Spam_Filter' )
				? Nestform_Spam_Filter::sanitize_lines( $input['blocked_ips'] ?? '' )
				: sanitize_textarea_field( (string) ( $input['blocked_ips'] ?? '' ) );
			$out['spam_log_enabled']        = ! empty( $input['spam_log_enabled'] ) ? '1' : '0';
			$out['spam_log_retention_days'] = isset( $input['spam_log_retention_days'] )
				? (string) max( 1, (int) $input['spam_log_retention_days'] )
				: '30';
			return $out;
		}

		$out['default_submit_label']    = isset( $input['default_submit_label'] ) ? sanitize_text_field( (string) $input['default_submit_label'] ) : '';
		$out['default_success_message'] = isset( $input['default_success_message'] ) ? sanitize_textarea_field( (string) $input['default_success_message'] ) : '';

		return $out;
	}

	/**
	 * Copy keys from theme Auth captcha once, if Thimbleform has none yet.
	 */
	public static function maybe_import_theme_captcha() {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		if ( ! defined( 'VITE_THEME_AUTH_CAPTCHA_OPTION' ) ) {
			return;
		}

		$theme = get_option( VITE_THEME_AUTH_CAPTCHA_OPTION, array() );
		if ( ! is_array( $theme ) ) {
			return;
		}

		$site   = (string) ( $theme['site_key'] ?? '' );
		$secret = (string) ( $theme['secret_key'] ?? '' );
		if ( $site === '' && $secret === '' ) {
			return;
		}

		$imported = self::sync_captcha_keys(
			array_merge(
				self::defaults(),
				array(
					'captcha_enabled'    => ! empty( $theme['enabled'] ) ? '1' : '0',
					'captcha_provider'   => in_array( (string) ( $theme['provider'] ?? '' ), array( 'recaptcha_v2', 'recaptcha_v3' ), true )
						? (string) $theme['provider']
						: 'recaptcha_v2',
					'captcha_site_key'   => sanitize_text_field( $site ),
					'captcha_secret_key' => sanitize_text_field( $secret ),
					'captcha_v3_score'   => isset( $theme['v3_score'] ) ? (string) $theme['v3_score'] : '0.5',
				)
			)
		);

		add_option( self::OPTION, $imported, '', false );
	}

	public static function send_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'nestform' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'nestform_send_test_email' );

		$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_email( $to ) ) {
			$user = wp_get_current_user();
			$to   = $user && is_email( $user->user_email ) ? $user->user_email : '';
		}

		$sent = false;
		if ( is_email( $to ) ) {
			$from_name  = self::mail_from_name();
			$from_email = self::mail_from_email();
			$headers    = array( 'Content-Type: text/plain; charset=UTF-8' );
			if ( is_email( $from_email ) ) {
				$headers[] = 'From: ' . sprintf( '%s <%s>', $from_name, $from_email );
			}
			$body = sprintf(
				/* translators: %s: site name */
				__( "This is a test email from Thimbleform on %s.\n\nIf you received it, wp_mail() is working on this site.", 'nestform' ),
				get_bloginfo( 'name' )
			);
			$sent = class_exists( 'Nestform_Email_Log' )
				? Nestform_Email_Log::send( 'test', $to, __( 'Thimbleform test email', 'nestform' ), $body, $headers )
				: wp_mail( $to, __( 'Thimbleform test email', 'nestform' ), $body, $headers );
		}

		wp_safe_redirect(
			add_query_arg(
				'nestform_test_email',
				$sent ? 'sent' : 'failed',
				self::url( array( 'section' => 'email' ) )
			)
		);
		exit;
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Settings', 'nestform' ),
			__( 'Settings', 'nestform' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			$classes .= ' nestform-admin-screen nestform-settings-screen';
		}
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver = (string) filemtime( nestform_admin_css_path() );
		wp_enqueue_style(
			'nestform-admin',
			nestform_admin_css_url(),
			nestform_admin_style_deps(),
			$ver ? $ver : NESTFORM_VERSION
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit these settings.', 'nestform' ) );
		}

		$sections = self::sections();
		$section  = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $sections[ $section ] ) ) {
			$section = 'general';
		}

		$s                = self::get();
		$opt              = self::OPTION;
		$integrations_url = class_exists( 'Nestform_Integrations' ) ? Nestform_Integrations::url() : '';
		$current          = wp_get_current_user();
		?>
		<div class="wrap nestform-admin nestform-settings">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Settings', 'nestform' ),
					'description' => (string) $sections[ $section ]['desc'],
					'icon'        => 'settings',
				)
			);
			settings_errors();
			self::render_test_email_notice();
			if ( isset( $_GET['theme-updated'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['theme-updated'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Admin appearance saved.', 'nestform' ) . '</p></div>';
			}
			?>

			<div class="nestform-settings__layout<?php echo in_array( $section, array( 'general', 'security' ), true ) ? '' : ' nestform-settings__layout--compact'; ?>">
				<nav class="nestform-settings__nav" aria-label="<?php esc_attr_e( 'Settings sections', 'nestform' ); ?>">
					<?php foreach ( $sections as $id => $meta ) : ?>
						<a
							class="nestform-settings__nav-item<?php echo $section === $id ? ' nestform-settings__nav-item--active' : ''; ?>"
							href="<?php echo esc_url( self::url( array( 'section' => $id ) ) ); ?>"
						>
							<?php echo esc_html( (string) $meta['label'] ); ?>
						</a>
					<?php endforeach; ?>
					<?php if ( $integrations_url ) : ?>
						<span class="nestform-settings__nav-sep" aria-hidden="true"></span>
						<a class="nestform-settings__nav-item nestform-settings__nav-item--external" href="<?php echo esc_url( $integrations_url ); ?>">
							<?php esc_html_e( 'Integrations', 'nestform' ); ?>
							<span class="nestform-settings__nav-hint"><?php esc_html_e( 'Captcha & payments', 'nestform' ); ?></span>
						</a>
					<?php endif; ?>
				</nav>

				<div class="nestform-settings__main">
					<h2 class="nestform-settings__section-title screen-reader-text"><?php echo esc_html( (string) $sections[ $section ]['label'] ); ?></h2>

					<?php if ( 'general' === $section ) : ?>
						<form method="post" action="options.php" class="nestform-settings__form">
							<?php settings_fields( 'nestform_settings' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="general" />
							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Form defaults', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Each form can override these in the builder.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><label for="nestform_default_submit_label"><?php esc_html_e( 'Submit button', 'nestform' ); ?></label></th>
										<td>
											<input type="text" class="regular-text nestform-admin__input" id="nestform_default_submit_label" name="<?php echo esc_attr( $opt ); ?>[default_submit_label]" value="<?php echo esc_attr( (string) $s['default_submit_label'] ); ?>" placeholder="<?php esc_attr_e( 'Send message', 'nestform' ); ?>" />
											<p class="description"><?php esc_html_e( 'Leave empty to use the built-in label.', 'nestform' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_default_success_message"><?php esc_html_e( 'Success message', 'nestform' ); ?></label></th>
										<td>
											<textarea class="large-text nestform-admin__input" id="nestform_default_success_message" name="<?php echo esc_attr( $opt ); ?>[default_success_message]" rows="3" placeholder="<?php esc_attr_e( 'Thank you. Your message has been sent.', 'nestform' ); ?>"><?php echo esc_textarea( (string) $s['default_success_message'] ); ?></textarea>
											<p class="description"><?php esc_html_e( 'Shown after submit when a form has no custom success text.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
								<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
									<?php nestform_admin_icon( 'save' ); ?>
									<?php esc_html_e( 'Save defaults', 'nestform' ); ?>
								</button>
							</div>
						</form>

						<?php Nestform_Admin_Theme::render_settings_card(); ?>

					<?php elseif ( 'email' === $section ) : ?>
						<?php
						$email_sub = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( ! in_array( $email_sub, array( 'settings', 'log' ), true ) ) {
							$email_sub = 'settings';
						}
						?>
						<nav class="nestform-settings__subnav" aria-label="<?php esc_attr_e( 'Email sections', 'nestform' ); ?>">
							<a class="nestform-settings__subnav-item<?php echo 'settings' === $email_sub ? ' nestform-settings__subnav-item--active' : ''; ?>" href="<?php echo esc_url( self::url( array( 'section' => 'email' ) ) ); ?>">
								<?php esc_html_e( 'Configuration', 'nestform' ); ?>
							</a>
							<a class="nestform-settings__subnav-item<?php echo 'log' === $email_sub ? ' nestform-settings__subnav-item--active' : ''; ?>" href="<?php echo esc_url( self::url( array( 'section' => 'email', 'sub' => 'log' ) ) ); ?>">
								<?php esc_html_e( 'Delivery log', 'nestform' ); ?>
							</a>
						</nav>

						<?php if ( 'log' === $email_sub ) : ?>
							<?php self::render_email_log(); ?>
						<?php else : ?>
						<form method="post" action="options.php" class="nestform-settings__form">
							<?php settings_fields( 'nestform_settings' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="email" />
							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Email defaults', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Used by wp_mail when a form does not override From. For reliability, use WP Mail SMTP or FluentSMTP.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><label for="nestform_email_from_name"><?php esc_html_e( 'From name', 'nestform' ); ?></label></th>
										<td>
											<input type="text" class="regular-text nestform-admin__input" id="nestform_email_from_name" name="<?php echo esc_attr( $opt ); ?>[email_from_name]" value="<?php echo esc_attr( (string) $s['email_from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_email_from_email"><?php esc_html_e( 'From email', 'nestform' ); ?></label></th>
										<td>
											<input type="email" class="regular-text nestform-admin__input" id="nestform_email_from_email" name="<?php echo esc_attr( $opt ); ?>[email_from_email]" value="<?php echo esc_attr( (string) $s['email_from_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_email_log_retention_days"><?php esc_html_e( 'Keep delivery log for', 'nestform' ); ?></label></th>
										<td>
											<div class="nestform-settings__qty">
												<input type="number" class="small-text nestform-admin__input nestform-settings__qty-input" id="nestform_email_log_retention_days" name="<?php echo esc_attr( $opt ); ?>[email_log_retention_days]" value="<?php echo esc_attr( (string) $s['email_log_retention_days'] ); ?>" min="1" step="1" />
												<span class="nestform-settings__unit"><?php esc_html_e( 'days', 'nestform' ); ?></span>
											</div>
											<p class="description"><?php esc_html_e( 'Stores recipient, subject, and status — never the message body.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
								<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
									<?php nestform_admin_icon( 'save' ); ?>
									<?php esc_html_e( 'Save', 'nestform' ); ?>
								</button>
							</div>
						</form>

						<div class="nestform-admin__surface nestform-settings__card nestform-settings__test-email">
							<div class="nestform-admin__panel-head">
								<div>
									<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Send a test', 'nestform' ); ?></h3>
									<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Confirms wp_mail() on this server — not a specific form template.', 'nestform' ); ?></p>
								</div>
							</div>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'nestform_send_test_email' ); ?>
								<input type="hidden" name="action" value="nestform_send_test_email" />
								<p class="nestform-settings__test-row">
									<label for="nestform_test_email"><?php esc_html_e( 'Send to', 'nestform' ); ?></label>
									<input type="email" class="regular-text nestform-admin__input" id="nestform_test_email" name="test_email" value="<?php echo esc_attr( $current && is_email( $current->user_email ) ? $current->user_email : '' ); ?>" />
									<button type="submit" class="nestform-btn nestform-btn--ghost"><?php esc_html_e( 'Send test email', 'nestform' ); ?></button>
								</p>
							</form>
						</div>
						<?php endif; ?>

					<?php elseif ( 'entries' === $section ) : ?>
						<form method="post" action="options.php" class="nestform-settings__form">
							<?php settings_fields( 'nestform_settings' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="entries" />
							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Entries inbox', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'How submissions look and behave in the admin.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><label for="nestform_date_format"><?php esc_html_e( 'Date format', 'nestform' ); ?></label></th>
										<td>
											<select id="nestform_date_format" class="nestform-admin__input" name="<?php echo esc_attr( $opt ); ?>[date_format]">
												<option value="site" <?php selected( (string) $s['date_format'], 'site' ); ?>><?php esc_html_e( 'Site default', 'nestform' ); ?></option>
												<option value="ymd_hi" <?php selected( (string) $s['date_format'], 'ymd_hi' ); ?>><?php echo esc_html( wp_date( 'Y-m-d H:i' ) ); ?></option>
												<option value="mdy_gia" <?php selected( (string) $s['date_format'], 'mdy_gia' ); ?>><?php echo esc_html( wp_date( 'M j, Y g:i a' ) ); ?></option>
												<option value="dmy_hi" <?php selected( (string) $s['date_format'], 'dmy_hi' ); ?>><?php echo esc_html( wp_date( 'd/m/Y H:i' ) ); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Mark as read', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_auto_mark_read">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[auto_mark_read]" value="0" />
												<input type="checkbox" id="nestform_auto_mark_read" name="<?php echo esc_attr( $opt ); ?>[auto_mark_read]" value="1" <?php checked( (string) $s['auto_mark_read'], '1' ); ?> />
												<span><?php esc_html_e( 'Automatically mark entries as read when opened', 'nestform' ); ?></span>
											</label>
										</td>
									</tr>
								</table>
								<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
									<?php nestform_admin_icon( 'save' ); ?>
									<?php esc_html_e( 'Save', 'nestform' ); ?>
								</button>
							</div>
						</form>

					<?php elseif ( 'security' === $section ) : ?>
						<?php
						$security_sub = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( ! in_array( $security_sub, array( 'settings', 'log' ), true ) ) {
							$security_sub = 'settings';
						}
						?>
						<nav class="nestform-settings__subnav" aria-label="<?php esc_attr_e( 'Security sections', 'nestform' ); ?>">
							<a class="nestform-settings__subnav-item<?php echo 'settings' === $security_sub ? ' nestform-settings__subnav-item--active' : ''; ?>" href="<?php echo esc_url( self::url( array( 'section' => 'security' ) ) ); ?>">
								<?php esc_html_e( 'Settings', 'nestform' ); ?>
							</a>
							<a class="nestform-settings__subnav-item<?php echo 'log' === $security_sub ? ' nestform-settings__subnav-item--active' : ''; ?>" href="<?php echo esc_url( self::url( array( 'section' => 'security', 'sub' => 'log' ) ) ); ?>">
								<?php esc_html_e( 'Blocked attempts', 'nestform' ); ?>
							</a>
						</nav>

						<?php if ( 'log' === $security_sub ) : ?>
							<?php self::render_spam_log(); ?>
						<?php else : ?>
						<form method="post" action="options.php" class="nestform-settings__form">
							<?php settings_fields( 'nestform_settings' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="security" />

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Rate limiting', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Caps how often one IP can submit any Thimbleform form.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Limit by IP', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_rate_limit_enabled">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[rate_limit_enabled]" value="0" />
												<input type="checkbox" id="nestform_rate_limit_enabled" name="<?php echo esc_attr( $opt ); ?>[rate_limit_enabled]" value="1" <?php checked( (string) $s['rate_limit_enabled'], '1' ); ?> />
												<span><?php esc_html_e( 'Enable site-wide rate limiting', 'nestform' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_rate_limit_max"><?php esc_html_e( 'Max submissions', 'nestform' ); ?></label></th>
										<td>
											<div class="nestform-settings__qty-stack">
												<div class="nestform-settings__qty">
													<input type="number" class="small-text nestform-admin__input nestform-settings__qty-input" id="nestform_rate_limit_max" name="<?php echo esc_attr( $opt ); ?>[rate_limit_max]" value="<?php echo esc_attr( (string) $s['rate_limit_max'] ); ?>" min="1" step="1" aria-label="<?php esc_attr_e( 'Max submissions', 'nestform' ); ?>" />
													<span class="nestform-settings__unit"><?php esc_html_e( 'per', 'nestform' ); ?></span>
												</div>
												<div class="nestform-settings__qty">
													<input type="number" class="small-text nestform-admin__input nestform-settings__qty-input" id="nestform_rate_limit_window" name="<?php echo esc_attr( $opt ); ?>[rate_limit_window]" value="<?php echo esc_attr( (string) $s['rate_limit_window'] ); ?>" min="60" step="60" aria-label="<?php esc_attr_e( 'Window in seconds', 'nestform' ); ?>" />
													<span class="nestform-settings__unit"><?php esc_html_e( 'seconds', 'nestform' ); ?></span>
												</div>
											</div>
											<p class="description"><?php esc_html_e( 'People on the same office network share one IP — raise the limit if colleagues submit often.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
							</div>

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Duplicate submissions', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Blocks the same payload from the same IP within a short window (double-clicks, retries).', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Duplicates', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_duplicate_check_enabled">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[duplicate_check_enabled]" value="0" />
												<input type="checkbox" id="nestform_duplicate_check_enabled" name="<?php echo esc_attr( $opt ); ?>[duplicate_check_enabled]" value="1" <?php checked( (string) $s['duplicate_check_enabled'], '1' ); ?> />
												<span><?php esc_html_e( 'Block repeated identical submissions', 'nestform' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_duplicate_check_window"><?php esc_html_e( 'Remember for', 'nestform' ); ?></label></th>
										<td>
											<div class="nestform-settings__qty">
												<input type="number" class="small-text nestform-admin__input nestform-settings__qty-input" id="nestform_duplicate_check_window" name="<?php echo esc_attr( $opt ); ?>[duplicate_check_window]" value="<?php echo esc_attr( (string) $s['duplicate_check_window'] ); ?>" min="30" step="30" />
												<span class="nestform-settings__unit"><?php esc_html_e( 'seconds', 'nestform' ); ?></span>
											</div>
										</td>
									</tr>
								</table>
							</div>

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Content filter', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Checks what was submitted — not only where it came from. Catches link farms and known junk domains.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Enable', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_content_filter_enabled">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[content_filter_enabled]" value="0" />
												<input type="checkbox" id="nestform_content_filter_enabled" name="<?php echo esc_attr( $opt ); ?>[content_filter_enabled]" value="1" <?php checked( (string) $s['content_filter_enabled'], '1' ); ?> />
												<span><?php esc_html_e( 'Check submission content', 'nestform' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_max_links"><?php esc_html_e( 'Maximum links', 'nestform' ); ?></label></th>
										<td>
											<input type="number" class="small-text nestform-admin__input" id="nestform_max_links" name="<?php echo esc_attr( $opt ); ?>[max_links]" value="<?php echo esc_attr( (string) $s['max_links'] ); ?>" min="0" step="1" />
											<p class="description"><?php esc_html_e( '0 turns the link check off. Blocklists below still apply.', 'nestform' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_blocked_words"><?php esc_html_e( 'Blocked words', 'nestform' ); ?></label></th>
										<td>
											<textarea class="large-text nestform-admin__input" id="nestform_blocked_words" name="<?php echo esc_attr( $opt ); ?>[blocked_words]" rows="4" placeholder="<?php esc_attr_e( 'One word or phrase per line', 'nestform' ); ?>"><?php echo esc_textarea( (string) $s['blocked_words'] ); ?></textarea>
											<p class="description"><?php esc_html_e( 'Whole words match on boundaries; phrases match as written.', 'nestform' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_blocked_email_domains"><?php esc_html_e( 'Blocked email domains', 'nestform' ); ?></label></th>
										<td>
											<textarea class="large-text nestform-admin__input" id="nestform_blocked_email_domains" name="<?php echo esc_attr( $opt ); ?>[blocked_email_domains]" rows="3" placeholder="mailinator.com"><?php echo esc_textarea( (string) $s['blocked_email_domains'] ); ?></textarea>
											<p class="description"><?php esc_html_e( 'One per line. Subdomains are included (example.com also blocks mail.example.com).', 'nestform' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_blocked_ips"><?php esc_html_e( 'Blocked IP addresses', 'nestform' ); ?></label></th>
										<td>
											<textarea class="large-text nestform-admin__input" id="nestform_blocked_ips" name="<?php echo esc_attr( $opt ); ?>[blocked_ips]" rows="3" placeholder="203.0.113.4&#10;203.0.113.*"><?php echo esc_textarea( (string) $s['blocked_ips'] ); ?></textarea>
											<p class="description"><?php esc_html_e( 'One per line. A trailing * blocks a prefix range. Applies to every form.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
							</div>

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Blocked attempts log', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Records why a submission was turned away so you can tune filters. Stores a hashed client handle — not the IP itself.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Logging', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_spam_log_enabled">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[spam_log_enabled]" value="0" />
												<input type="checkbox" id="nestform_spam_log_enabled" name="<?php echo esc_attr( $opt ); ?>[spam_log_enabled]" value="1" <?php checked( (string) $s['spam_log_enabled'], '1' ); ?> />
												<span><?php esc_html_e( 'Log blocked submission attempts', 'nestform' ); ?></span>
											</label>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="nestform_spam_log_retention_days"><?php esc_html_e( 'Keep entries for', 'nestform' ); ?></label></th>
										<td>
											<div class="nestform-settings__qty">
												<input type="number" class="small-text nestform-admin__input nestform-settings__qty-input" id="nestform_spam_log_retention_days" name="<?php echo esc_attr( $opt ); ?>[spam_log_retention_days]" value="<?php echo esc_attr( (string) $s['spam_log_retention_days'] ); ?>" min="1" step="1" />
												<span class="nestform-settings__unit"><?php esc_html_e( 'days', 'nestform' ); ?></span>
											</div>
										</td>
									</tr>
								</table>
								<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
									<?php nestform_admin_icon( 'save' ); ?>
									<?php esc_html_e( 'Save', 'nestform' ); ?>
								</button>
							</div>
						</form>
						<?php endif; ?>

					<?php elseif ( 'privacy' === $section ) : ?>
						<form method="post" action="options.php" class="nestform-settings__form">
							<?php settings_fields( 'nestform_settings' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="privacy" />
							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Data retention', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Automatically delete old entries. Privacy tools still export or erase by email request.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><label for="nestform_entry_retention_days"><?php esc_html_e( 'Keep entries for', 'nestform' ); ?></label></th>
										<td>
											<div class="nestform-settings__qty">
												<input type="number" class="small-text nestform-admin__input nestform-settings__qty-input" id="nestform_entry_retention_days" name="<?php echo esc_attr( $opt ); ?>[entry_retention_days]" value="<?php echo esc_attr( (string) $s['entry_retention_days'] ); ?>" min="0" step="1" />
												<span class="nestform-settings__unit"><?php esc_html_e( 'days', 'nestform' ); ?></span>
											</div>
											<p class="description"><?php esc_html_e( '0 keeps entries forever. Purge runs once a day.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
							</div>

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Uninstall', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Control what happens when Thimbleform is removed.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Delete data', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_delete_data_on_uninstall">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[delete_data_on_uninstall]" value="0" />
												<input type="checkbox" id="nestform_delete_data_on_uninstall" name="<?php echo esc_attr( $opt ); ?>[delete_data_on_uninstall]" value="1" <?php checked( (string) $s['delete_data_on_uninstall'], '1' ); ?> />
												<span><?php esc_html_e( 'Delete forms, entries, and settings when uninstalling the plugin', 'nestform' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'Off by default — keep data if you might reinstall.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
							</div>

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Public forms', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Optional line visitors may see under your forms.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Credit', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_credit_enabled">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[credit_enabled]" value="0" />
												<input type="checkbox" id="nestform_credit_enabled" name="<?php echo esc_attr( $opt ); ?>[credit_enabled]" value="1" <?php checked( (string) $s['credit_enabled'], '1' ); ?> />
												<span><?php esc_html_e( 'Show a small “Powered by Thimbleform” link under forms', 'nestform' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'Off by default. No tracking parameters — a plain link to thimbleform.app.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
							</div>

							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Admin notices', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Control in-plugin messages on your own screens.', 'nestform' ); ?></p>
									</div>
								</div>
								<table class="form-table nestform-settings__table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Pro pointers', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_hide_pro_promotions">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[hide_pro_promotions]" value="0" />
												<input type="checkbox" id="nestform_hide_pro_promotions" name="<?php echo esc_attr( $opt ); ?>[hide_pro_promotions]" value="1" <?php checked( (string) $s['hide_pro_promotions'], '1' ); ?> />
												<span><?php esc_html_e( 'Hide Pro promotion surfaces in the admin', 'nestform' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'For agency handoffs or white-label installs. Does not change form features.', 'nestform' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Review requests', 'nestform' ); ?></th>
										<td>
											<label class="nestform-admin__check" for="nestform_review_requests_enabled">
												<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[review_requests_enabled]" value="0" />
												<input type="checkbox" id="nestform_review_requests_enabled" name="<?php echo esc_attr( $opt ); ?>[review_requests_enabled]" value="1" <?php checked( (string) $s['review_requests_enabled'], '1' ); ?> />
												<span><?php esc_html_e( 'Show WordPress.org review prompts on Thimbleform screens', 'nestform' ); ?></span>
											</label>
											<p class="description"><?php esc_html_e( 'After enough real entries are collected, administrators may see a one-time notice with Not now and Don\'t ask again options. Never shown site-wide.', 'nestform' ); ?></p>
										</td>
									</tr>
								</table>
								<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
									<?php nestform_admin_icon( 'save' ); ?>
									<?php esc_html_e( 'Save', 'nestform' ); ?>
								</button>
							</div>
						</form>
						<?php if ( class_exists( 'Nestform_Capabilities' ) ) : ?>
						<form method="post" action="options.php" class="nestform-settings__form">
							<?php settings_fields( 'nestform_settings' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="access" />
							<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[role_caps][_submitted]" value="1" />
							<div class="nestform-admin__surface nestform-settings__card">
								<div class="nestform-admin__panel-head">
									<div>
										<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Access', 'nestform' ); ?></h3>
										<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Administrators can always manage forms and view entries. Grant other roles access without making them administrators.', 'nestform' ); ?></p>
									</div>
								</div>
								<?php
								$role_caps  = Nestform_Capabilities::role_settings();
								$cap_labels = Nestform_Capabilities::labels();
								?>
								<div class="nestform-settings__role-caps-wrap">
									<table class="nestform-settings__role-caps" role="presentation">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Role', 'nestform' ); ?></th>
												<?php foreach ( $cap_labels as $cap => $label ) : ?>
													<th scope="col"><?php echo esc_html( $label ); ?></th>
												<?php endforeach; ?>
											</tr>
										</thead>
										<tbody>
											<tr class="nestform-settings__role-caps-row nestform-settings__role-caps-row--admin">
												<th scope="row"><strong><?php echo esc_html( translate_user_role( 'Administrator' ) ); ?></strong></th>
												<?php foreach ( $cap_labels as $cap => $label ) : ?>
													<td>
														<label class="nestform-admin__check nestform-admin__check--center" title="<?php echo esc_attr( $label ); ?>">
															<input type="checkbox" checked disabled />
															<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
														</label>
													</td>
												<?php endforeach; ?>
											</tr>
											<?php foreach ( Nestform_Capabilities::assignable_roles() as $slug => $name ) : ?>
												<tr class="nestform-settings__role-caps-row">
													<th scope="row"><?php echo esc_html( $name ); ?></th>
													<?php foreach ( $cap_labels as $cap => $label ) : ?>
														<?php
														$id      = 'nestform_role_' . $slug . '_' . $cap;
														$checked = in_array( $cap, isset( $role_caps[ $slug ] ) ? $role_caps[ $slug ] : array(), true );
														?>
														<td>
															<label class="nestform-admin__check nestform-admin__check--center" for="<?php echo esc_attr( $id ); ?>" title="<?php echo esc_attr( $label ); ?>">
																<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $opt ); ?>[role_caps][<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $cap ); ?>]" value="1" <?php checked( $checked ); ?> />
																<span class="screen-reader-text">
																	<?php
																	printf(
																		/* translators: 1: capability name, 2: role name */
																		esc_html__( '%1$s for %2$s', 'nestform' ),
																		esc_html( $label ),
																		esc_html( $name )
																	);
																	?>
																</span>
															</label>
														</td>
													<?php endforeach; ?>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								<p class="description"><?php esc_html_e( 'Managing forms includes viewing entries. Role editors such as Members also see these capabilities.', 'nestform' ); ?></p>
								<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
									<?php nestform_admin_icon( 'save' ); ?>
									<?php esc_html_e( 'Save access', 'nestform' ); ?>
								</button>
							</div>
						</form>
						<?php endif; ?>
						<?php
						if ( class_exists( 'Nestform_Backup' ) ) {
							Nestform_Backup::render_card();
						}
						?>
					<?php endif; ?>
				</div>

				<?php if ( in_array( $section, array( 'general', 'security' ), true ) ) : ?>
				<aside class="nestform-settings__aside">
					<div class="nestform-admin__surface nestform-settings__aside-card">
						<h3 class="nestform-settings__aside-title"><?php esc_html_e( 'Always on', 'nestform' ); ?></h3>
						<p class="nestform-settings__aside-text"><?php esc_html_e( 'Every form gets a nonce, honeypot, and time trap by default.', 'nestform' ); ?></p>
						<?php if ( 'security' !== $section ) : ?>
							<p class="nestform-settings__aside-text">
								<a href="<?php echo esc_url( self::url( array( 'section' => 'security' ) ) ); ?>"><?php esc_html_e( 'Open Security settings', 'nestform' ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				</aside>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Email delivery log under Settings → Email.
	 */
	private static function render_email_log() {
		if ( ! class_exists( 'Nestform_Email_Log' ) ) {
			echo '<div class="nestform-admin__surface nestform-settings__card"><p>' . esc_html__( 'Email log is unavailable.', 'nestform' ) . '</p></div>';
			return;
		}

		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 25;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args     = array(
			'limit'  => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
			'status' => $status,
		);
		$entries     = Nestform_Email_Log::get_entries( $args );
		$total       = Nestform_Email_Log::count_entries( array( 'status' => $status ) );
		$total_pages = (int) ceil( $total / $per_page );
		$types       = Nestform_Email_Log::type_labels();
		$base_url    = self::url(
			array(
				'section' => 'email',
				'sub'     => 'log',
			)
		);
		?>
		<div class="nestform-admin__surface nestform-settings__card nestform-log nestform-log--email">
			<div class="nestform-admin__panel-head">
				<div>
					<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Delivery log', 'nestform' ); ?></h3>
					<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Recipient, subject, and status for each Thimbleform mail attempt. Message bodies are never stored.', 'nestform' ); ?></p>
				</div>
			</div>

			<form method="get" class="nestform-log__filters">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Nestform_Post_Type::POST_TYPE ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="section" value="email" />
				<input type="hidden" name="sub" value="log" />
				<label class="nestform-log__filter">
					<span class="screen-reader-text"><?php esc_html_e( 'Status', 'nestform' ); ?></span>
					<select class="nestform-admin__input" name="status" onchange="this.form.submit()">
						<option value=""><?php esc_html_e( 'All statuses', 'nestform' ); ?></option>
						<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'nestform' ); ?></option>
						<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'nestform' ); ?></option>
					</select>
				</label>
			</form>

			<div class="nestform-log__table-wrap">
				<table class="nestform-log__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'When', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'To', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Subject', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'nestform' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( array() === $entries ) : ?>
							<tr class="nestform-log__empty-row">
								<td colspan="5">
									<p class="nestform-log__empty"><?php esc_html_e( 'No mail logged yet. Send a test email or submit a form with notifications on.', 'nestform' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr class="nestform-log__row<?php echo 'failed' === $entry->status ? ' nestform-log__row--failed' : ''; ?>">
								<td class="nestform-log__cell nestform-log__cell--when">
									<?php
									echo esc_html(
										mysql2date(
											get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
											get_date_from_gmt( (string) $entry->created_at )
										)
									);
									?>
								</td>
								<td class="nestform-log__cell nestform-log__cell--type">
									<?php echo esc_html( isset( $types[ $entry->type ] ) ? $types[ $entry->type ] : (string) $entry->type ); ?>
								</td>
								<td class="nestform-log__cell nestform-log__cell--to"><?php echo esc_html( (string) $entry->recipient ); ?></td>
								<td class="nestform-log__cell nestform-log__cell--subject">
									<span class="nestform-log__subject"><?php echo esc_html( (string) $entry->subject ); ?></span>
									<?php if ( 'failed' === $entry->status && $entry->error ) : ?>
										<span class="nestform-log__error"><?php echo esc_html( (string) $entry->error ); ?></span>
									<?php endif; ?>
								</td>
								<td class="nestform-log__cell nestform-log__cell--status">
									<?php if ( 'failed' === $entry->status ) : ?>
										<span class="nestform-badge nestform-badge--danger"><?php esc_html_e( 'Failed', 'nestform' ); ?></span>
									<?php else : ?>
										<span class="nestform-badge nestform-badge--ok"><?php esc_html_e( 'Sent', 'nestform' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<nav class="nestform-log__pager" aria-label="<?php esc_attr_e( 'Delivery log pages', 'nestform' ); ?>">
					<?php
					echo wp_kses_post(
						(string) paginate_links(
							array(
								'base'      => add_query_arg(
									array(
										'paged'  => '%#%',
										'status' => $status ? $status : false,
									),
									$base_url
								),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'type'      => 'plain',
							)
						)
					);
					?>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Blocked attempts log under Settings → Security.
	 */
	private static function render_spam_log() {
		if ( ! class_exists( 'Nestform_Spam_Log' ) ) {
			echo '<div class="nestform-admin__surface nestform-settings__card"><p>' . esc_html__( 'Spam log is unavailable.', 'nestform' ) . '</p></div>';
			return;
		}

		if ( ! empty( $_GET['cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible nestform-log__flash"><p>' . esc_html__( 'Blocked attempts log cleared.', 'nestform' ) . '</p></div>';
		}

		$form_id  = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reason   = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 25;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $paged - 1 ) * $per_page,
			'form_id' => $form_id,
			'reason'  => $reason,
		);

		$entries     = Nestform_Spam_Log::get_entries( $args );
		$total       = Nestform_Spam_Log::count_entries(
			array(
				'form_id' => $form_id,
				'reason'  => $reason,
			)
		);
		$total_pages = (int) ceil( $total / $per_page );
		$summary     = Nestform_Spam_Log::summary( 7, $form_id );
		$labels      = Nestform_Spam_Log::reason_labels();

		$forms = get_posts(
			array(
				'post_type'              => Nestform_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$forms_map = array();
		foreach ( $forms as $f ) {
			$forms_map[ (int) $f->ID ] = $f;
		}

		$base_url = self::url(
			array(
				'section' => 'security',
				'sub'     => 'log',
			)
		);
		?>
		<div class="nestform-admin__surface nestform-settings__card nestform-log nestform-log--spam">
			<div class="nestform-admin__panel-head">
				<div>
					<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Blocked attempts', 'nestform' ); ?></h3>
					<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Why submissions were turned away. Client IPs are stored as a short hash — not the address itself.', 'nestform' ); ?></p>
				</div>
			</div>

			<?php if ( ! Nestform_Spam_Log::is_enabled() ) : ?>
				<p class="nestform-log__notice">
					<?php esc_html_e( 'Logging is switched off, so nothing new is being recorded.', 'nestform' ); ?>
					<a href="<?php echo esc_url( self::url( array( 'section' => 'security' ) ) ); ?>"><?php esc_html_e( 'Turn it back on under Settings.', 'nestform' ); ?></a>
				</p>
			<?php endif; ?>

			<div class="nestform-log__summary">
				<?php if ( $summary ) : ?>
					<p class="nestform-log__summary-title">
						<?php
						$sum = array_sum( $summary );
						echo esc_html(
							sprintf(
								/* translators: %s: number of blocked attempts */
								_n( '%s attempt blocked in the last 7 days', '%s attempts blocked in the last 7 days', $sum, 'nestform' ),
								number_format_i18n( $sum )
							)
						);
						?>
					</p>
					<div class="nestform-log__chips">
						<?php foreach ( $summary as $summary_reason => $count ) : ?>
							<span class="nestform-log__chip">
								<span class="nestform-log__chip-label"><?php echo esc_html( Nestform_Spam_Log::reason_label( $summary_reason ) ); ?></span>
								<span class="nestform-log__chip-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							</span>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="nestform-log__summary-empty"><?php esc_html_e( 'Nothing has been blocked in the last 7 days.', 'nestform' ); ?></p>
				<?php endif; ?>
			</div>

			<form method="get" class="nestform-log__filters">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Nestform_Post_Type::POST_TYPE ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="section" value="security" />
				<input type="hidden" name="sub" value="log" />
				<label class="nestform-log__filter">
					<span class="screen-reader-text"><?php esc_html_e( 'Form', 'nestform' ); ?></span>
					<select class="nestform-admin__input" name="form_id" onchange="this.form.submit()">
						<option value=""><?php esc_html_e( 'All forms', 'nestform' ); ?></option>
						<?php foreach ( $forms as $f ) : ?>
							<option value="<?php echo esc_attr( (string) $f->ID ); ?>" <?php selected( $form_id, (int) $f->ID ); ?>><?php echo esc_html( $f->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="nestform-log__filter">
					<span class="screen-reader-text"><?php esc_html_e( 'Reason', 'nestform' ); ?></span>
					<select class="nestform-admin__input" name="reason" onchange="this.form.submit()">
						<option value=""><?php esc_html_e( 'All reasons', 'nestform' ); ?></option>
						<?php foreach ( $labels as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $reason, $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>

			<div class="nestform-log__table-wrap">
				<table class="nestform-log__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'When', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Form', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Reason', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Details', 'nestform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Client', 'nestform' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( array() === $entries ) : ?>
							<tr class="nestform-log__empty-row">
								<td colspan="5">
									<p class="nestform-log__empty"><?php esc_html_e( 'No blocked attempts recorded yet.', 'nestform' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr class="nestform-log__row">
								<td class="nestform-log__cell nestform-log__cell--when">
									<?php
									echo esc_html(
										mysql2date(
											get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
											get_date_from_gmt( (string) $entry->created_at )
										)
									);
									?>
								</td>
								<td class="nestform-log__cell nestform-log__cell--form">
									<?php
									$entry_form = isset( $forms_map[ (int) $entry->form_id ] ) ? $forms_map[ (int) $entry->form_id ] : null;
									echo esc_html( $entry_form ? $entry_form->post_title : __( 'Unknown form', 'nestform' ) );
									?>
								</td>
								<td class="nestform-log__cell nestform-log__cell--reason">
									<span class="nestform-badge nestform-badge--draft"><?php echo esc_html( Nestform_Spam_Log::reason_label( (string) $entry->reason ) ); ?></span>
								</td>
								<td class="nestform-log__cell nestform-log__cell--detail"><?php echo esc_html( (string) $entry->detail ); ?></td>
								<td class="nestform-log__cell nestform-log__cell--client">
									<code class="nestform-log__hash"><?php echo esc_html( substr( (string) $entry->ip_hash, 0, 8 ) ?: '—' ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<nav class="nestform-log__pager" aria-label="<?php esc_attr_e( 'Blocked attempts pages', 'nestform' ); ?>">
					<?php
					$page_links = paginate_links(
						array(
							'base'      => add_query_arg(
								array(
									'paged'   => '%#%',
									'form_id' => $form_id ? $form_id : false,
									'reason'  => $reason ? $reason : false,
								),
								$base_url
							),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'type'      => 'plain',
						)
					);
					echo wp_kses_post( (string) $page_links );
					?>
				</nav>
			<?php endif; ?>

			<?php if ( $total > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nestform-log__clear">
					<?php wp_nonce_field( 'nestform_clear_spam_log' ); ?>
					<input type="hidden" name="action" value="nestform_clear_spam_log" />
					<button type="submit" class="nestform-btn nestform-btn--outline"><?php esc_html_e( 'Clear the log', 'nestform' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_test_email_notice() {
		$result = isset( $_GET['nestform_test_email'] ) ? sanitize_key( wp_unslash( $_GET['nestform_test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $result, array( 'sent', 'failed' ), true ) ) {
			return;
		}
		$class = 'sent' === $result ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
			<p>
				<?php
				echo 'sent' === $result
					? esc_html__( 'Test email handed to the mailer. Check the inbox (and spam) — delivery depends on your host or SMTP plugin.', 'nestform' )
					: esc_html__( 'Test email could not be sent. Check wp_mail() and your SMTP configuration.', 'nestform' );
				?>
			</p>
		</div>
		<?php
	}
}
