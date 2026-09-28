<?php
/**
 * First-run redirect after activation + Plugins row helpers.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Onboarding {

	const REDIRECT_KEY = 'thimbleform_activation_redirect';

	const REDIRECT_TTL = 60;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
	}

	/**
	 * Record that the activator should land on Thimbleform next.
	 */
	public static function schedule_redirect() {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient( self::REDIRECT_KEY, $user_id, self::REDIRECT_TTL );
		}
	}

	/**
	 * One-time redirect after activation.
	 */
	public static function maybe_redirect() {
		$user_id = (int) get_transient( self::REDIRECT_KEY );
		if ( ! $user_id || $user_id !== get_current_user_id() ) {
			return;
		}

		delete_transient( self::REDIRECT_KEY );

		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( is_network_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$target = class_exists( 'Thimbleform_Post_Type' )
			? Thimbleform_Post_Type::hub_url()
			: admin_url( 'edit.php?post_type=thimbleform' );

		wp_safe_redirect( $target );
		exit;
	}
}
