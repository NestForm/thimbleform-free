<?php
/**
 * Captcha for Thimbleform (reCAPTCHA, Turnstile, hCaptcha).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Captcha {

	const ACTION = 'nestform';

	/** @var bool */
	private static $needs_assets = false;

	/** @var string */
	private static $provider = '';

	/** @var string */
	private static $site_key = '';

	public static function init() {
		add_filter( 'nestform_captcha_html', array( __CLASS__, 'filter_html' ), 10, 3 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_enqueue' ), 5 );
	}

	/**
	 * Supported providers and their endpoints.
	 *
	 * @return array<string, array{label:string,short:string,script:string,verify:string,field:string,keys_url:string}>
	 */
	public static function providers() {
		// phpcs:disable PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- provider SDKs required for captcha.
		return array(
			'recaptcha_v2' => array(
				'label'    => __( 'reCAPTCHA v2 (checkbox)', 'nestform' ),
				'short'    => __( 'reCAPTCHA v2', 'nestform' ),
				'script'   => 'https://www.google.com/recaptcha/api.js',
				'verify'   => 'https://www.google.com/recaptcha/api/siteverify',
				'field'    => 'g-recaptcha-response',
				'keys_url' => 'https://www.google.com/recaptcha/admin',
			),
			'recaptcha_v3' => array(
				'label'    => __( 'reCAPTCHA v3 (invisible)', 'nestform' ),
				'short'    => __( 'reCAPTCHA v3', 'nestform' ),
				'script'   => 'https://www.google.com/recaptcha/api.js',
				'verify'   => 'https://www.google.com/recaptcha/api/siteverify',
				'field'    => 'g-recaptcha-response',
				'keys_url' => 'https://www.google.com/recaptcha/admin',
			),
			'turnstile'    => array(
				'label'    => __( 'Cloudflare Turnstile', 'nestform' ),
				'short'    => __( 'Turnstile', 'nestform' ),
				'script'   => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
				'verify'   => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				'field'    => 'cf-turnstile-response',
				'keys_url' => 'https://dash.cloudflare.com/?to=/:account/turnstile',
			),
			'hcaptcha'     => array(
				'label'    => __( 'hCaptcha', 'nestform' ),
				'short'    => __( 'hCaptcha', 'nestform' ),
				'script'   => 'https://js.hcaptcha.com/1/api.js',
				'verify'   => 'https://api.hcaptcha.com/siteverify',
				'field'    => 'h-captcha-response',
				'keys_url' => 'https://dashboard.hcaptcha.com/sites',
			),
		);
		// phpcs:enable PluginCheck.CodeAnalysis.Offloading.OffloadedContent
	}

	/**
	 * @return string Provider slug or empty.
	 */
	public static function provider() {
		if ( ! class_exists( 'Nestform_Settings' ) || ! Nestform_Settings::captcha_ready() ) {
			return '';
		}
		$s        = Nestform_Settings::get();
		$provider = (string) ( $s['captcha_provider'] ?? 'recaptcha_v2' );
		$all      = self::providers();
		return isset( $all[ $provider ] ) ? $provider : 'recaptcha_v2';
	}

	/**
	 * @param string $provider Provider slug.
	 * @param bool   $short    Prefer short tab label.
	 * @return string
	 */
	public static function provider_label( $provider, $short = false ) {
		$all = self::providers();
		if ( ! isset( $all[ $provider ] ) ) {
			return (string) $provider;
		}
		if ( $short && ! empty( $all[ $provider ]['short'] ) ) {
			return (string) $all[ $provider ]['short'];
		}
		return (string) $all[ $provider ]['label'];
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $config  Optional preloaded config.
	 * @return bool
	 */
	public static function enabled_for_form( $form_id, array $config = array() ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return false;
		}

		if ( array() === $config ) {
			$config = Nestform_Form_Config::get( $form_id );
		}

		$settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();
		if ( empty( $settings['enable_captcha'] ) || '0' === (string) $settings['enable_captcha'] ) {
			return false;
		}

		return class_exists( 'Nestform_Settings' ) && Nestform_Settings::captcha_ready();
	}

	/**
	 * @param string               $html    Existing HTML.
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $config  Config.
	 * @return string
	 */
	public static function filter_html( $html, $form_id, $config ) {
		if ( ! self::enabled_for_form( $form_id, is_array( $config ) ? $config : array() ) ) {
			return (string) $html;
		}

		$settings = Nestform_Settings::get();
		$site_key = (string) ( $settings['captcha_site_key'] ?? '' );
		$provider = self::provider();

		if ( $site_key === '' || $provider === '' ) {
			return (string) $html;
		}

		self::$needs_assets = true;
		self::$provider     = $provider;
		self::$site_key     = $site_key;

		ob_start();
		if ( 'recaptcha_v3' === $provider ) {
			echo '<input type="hidden" name="g-recaptcha-response" value="" data-nest-form-captcha-token />';
		} elseif ( 'turnstile' === $provider ) {
			printf(
				'<div class="cf-turnstile" data-sitekey="%s"></div>',
				esc_attr( $site_key )
			);
		} elseif ( 'hcaptcha' === $provider ) {
			printf(
				'<div class="h-captcha" data-sitekey="%s"></div>',
				esc_attr( $site_key )
			);
		} else {
			printf(
				'<div class="g-recaptcha" data-sitekey="%s"></div>',
				esc_attr( $site_key )
			);
		}

		return (string) $html . (string) ob_get_clean();
	}

	/**
	 * Verify captcha token from POST.
	 *
	 * @return true|WP_Error
	 */
	public static function verify() {
		if ( ! class_exists( 'Nestform_Settings' ) || ! Nestform_Settings::captcha_ready() ) {
			return new WP_Error( 'nestform_captcha_unavailable', __( 'Captcha is not available.', 'nestform' ) );
		}

		$settings = Nestform_Settings::get();
		$provider = self::provider();
		$all      = self::providers();
		if ( ! isset( $all[ $provider ] ) ) {
			return new WP_Error( 'nestform_captcha_provider', __( 'Captcha is not available.', 'nestform' ) );
		}

		$field = $all[ $provider ]['field'];
		$token = '';
		if ( isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- form nonce checked in submit handler
			$token = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		}
		// Shared fallback used by some caches / older markup.
		if ( $token === '' && isset( $_POST['g-recaptcha-response'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$token = sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) );
		}

		if ( $token === '' ) {
			return new WP_Error( 'nestform_captcha_missing', __( 'Please complete the captcha and try again.', 'nestform' ) );
		}

		$secret = (string) $settings['captcha_secret_key'];
		$ip     = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$body = array(
			'secret'   => $secret,
			'response' => $token,
		);
		if ( $ip !== '' ) {
			$body['remoteip'] = $ip;
		}

		$response = wp_remote_post(
			$all[ $provider ]['verify'],
			array(
				'timeout' => 8,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'nestform_captcha_http', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['success'] ) ) {
			return new WP_Error( 'nestform_captcha_invalid', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
		}

		if ( 'recaptcha_v3' === $provider ) {
			$score     = isset( $decoded['score'] ) ? (float) $decoded['score'] : 0.0;
			$min_score = (float) ( $settings['captcha_v3_score'] ?? 0.5 );
			$action    = isset( $decoded['action'] ) ? (string) $decoded['action'] : '';

			if ( $action !== '' && $action !== self::ACTION ) {
				return new WP_Error( 'nestform_captcha_action', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
			}

			if ( $score < $min_score ) {
				return new WP_Error( 'nestform_captcha_score', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
			}
		}

		return true;
	}

	/**
	 * Enqueue provider script when a captcha-enabled form was rendered.
	 */
	public static function maybe_enqueue() {
		if ( ! self::$needs_assets || self::$site_key === '' || self::$provider === '' ) {
			return;
		}

		$all = self::providers();
		if ( ! isset( $all[ self::$provider ] ) ) {
			return;
		}

		$handle = 'nestform-captcha-' . self::$provider;
		$src    = $all[ self::$provider ]['script'];

		if ( 'recaptcha_v3' === self::$provider ) {
			$src .= '?render=' . rawurlencode( self::$site_key );
		}

		wp_enqueue_script( $handle, $src, array(), defined( 'NESTFORM_VERSION' ) ? NESTFORM_VERSION : '1.0.0', true );
		wp_localize_script(
			'nestform-front',
			'nestformCaptcha',
			array(
				'provider' => self::$provider,
				'siteKey'  => self::$site_key,
				'action'   => self::ACTION,
				'field'    => $all[ self::$provider ]['field'],
			)
		);
	}

	/**
	 * Admin hint status for the form builder.
	 *
	 * @return array{available:bool,global_on:bool,provider:string,provider_label:string,url:string,message:string}
	 */
	public static function admin_status() {
		if ( ! class_exists( 'Nestform_Settings' ) ) {
			return array(
				'available'      => false,
				'global_on'      => false,
				'provider'       => '',
				'provider_label' => '',
				'url'            => '',
				'message'        => __( 'Thimbleform settings are not loaded.', 'nestform' ),
			);
		}

		$s        = Nestform_Settings::get();
		$ready    = Nestform_Settings::captcha_ready();
		$url      = class_exists( 'Nestform_Integrations' ) ? Nestform_Integrations::url( array( 'section' => 'captcha' ) ) : Nestform_Settings::url();
		$provider = (string) ( $s['captcha_provider'] ?? 'recaptcha_v2' );
		$all      = self::providers();
		if ( ! isset( $all[ $provider ] ) ) {
			$provider = 'recaptcha_v2';
		}
		$label = self::provider_label( $provider, true );

		if ( $ready ) {
			return array(
				'available'      => true,
				'global_on'      => true,
				'provider'       => $provider,
				'provider_label' => $label,
				'url'            => $url,
				'message'        => sprintf(
					/* translators: %s: provider name */
					__( 'Site captcha is ready: %s. Turn it on below to use it on this form only. Keys stay in Forms → Integrations.', 'nestform' ),
					$label
				),
			);
		}

		$master   = (string) ( $s['captcha_enabled'] ?? '0' ) === '1';
		$has_keys = (string) ( $s['captcha_site_key'] ?? '' ) !== '' && (string) ( $s['captcha_secret_key'] ?? '' ) !== '';

		if ( ! $master ) {
			$msg = __( 'Captcha is off site-wide. Pick a provider and enable it under Forms → Integrations first.', 'nestform' );
		} elseif ( ! $has_keys ) {
			$msg = sprintf(
				/* translators: %s: provider name */
				__( '%s is selected but site/secret keys are missing. Add them under Forms → Integrations.', 'nestform' ),
				$label
			);
		} else {
			$msg = __( 'Captcha is not ready yet. Check Forms → Integrations.', 'nestform' );
		}

		return array(
			'available'      => true,
			'global_on'      => false,
			'provider'       => $provider,
			'provider_label' => $label,
			'url'            => $url,
			'message'        => $msg,
		);
	}
}
