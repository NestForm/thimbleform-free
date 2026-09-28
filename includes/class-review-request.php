<?php
/**
 * WordPress.org review prompt (free plugin only — gates nothing).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Review_Request {

	const REVIEW_URL = 'https://wordpress.org/support/plugin/nestform/reviews/#new-post';

	const DISMISSED_META = 'nestform_review_notice_dismissed';

	const SNOOZED_META = 'nestform_review_notice_snoozed_until';

	const SNOOZE_SECONDS = 90 * DAY_IN_SECONDS;

	const SUBMISSIONS_BEFORE_NOTICE = 25;

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );
		add_action( 'admin_post_nestform_review_notice', array( __CLASS__, 'handle_response' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'Nestform_Settings' ) || ! Nestform_Settings::review_requests_enabled() ) {
			return false;
		}

		/**
		 * Filters whether Thimbleform asks for a WordPress.org review.
		 *
		 * @param bool $show Whether review prompts are allowed.
		 */
		return (bool) apply_filters( 'nestform_show_review_request', true );
	}

	/**
	 * @return bool
	 */
	public static function notice_is_due() {
		if ( ! self::is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$view = function_exists( 'nestform_admin_current_view' ) ? nestform_admin_current_view() : '';
		if ( ! in_array( $view, array( 'forms', 'entries', 'dashboard' ), true ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return false;
		}
		if ( (int) get_user_meta( $user_id, self::SNOOZED_META, true ) > time() ) {
			return false;
		}

		if ( ! class_exists( 'Nestform_Submissions' ) ) {
			return false;
		}

		return Nestform_Submissions::count_entries(
			array(
				'skip_access_check' => true,
			)
		) >= self::SUBMISSIONS_BEFORE_NOTICE;
	}

	public static function maybe_render_notice() {
		if ( ! self::notice_is_due() ) {
			return;
		}
		?>
		<div class="notice notice-info nestform-review-notice">
			<p>
				<?php esc_html_e( 'You have been collecting entries with Thimbleform for a while. If it has been useful, would you write a short review on WordPress.org? It helps other site owners find the plugin.', 'nestform' ); ?>
			</p>
			<p class="nestform-review-notice__actions">
				<a href="<?php echo esc_url( self::response_url( 'review' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Write a review', 'nestform' ); ?>
				</a>
				<a class="nestform-review-notice__secondary" href="<?php echo esc_url( self::response_url( 'later' ) ); ?>">
					<?php esc_html_e( 'Not now', 'nestform' ); ?>
				</a>
				<a class="nestform-review-notice__secondary" href="<?php echo esc_url( self::response_url( 'never' ) ); ?>">
					<?php esc_html_e( 'Don\'t ask again', 'nestform' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * @param string $choice review|later|never.
	 * @return string
	 */
	private static function response_url( $choice ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=nestform_review_notice&choice=' . sanitize_key( $choice ) ),
			'nestform_review_notice'
		);
	}

	public static function handle_response() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nestform' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'nestform_review_notice' );

		$choice  = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		$user_id = get_current_user_id();

		if ( 'later' === $choice ) {
			update_user_meta( $user_id, self::SNOOZED_META, time() + self::SNOOZE_SECONDS );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=nestform-dashboard' ) );
			exit;
		}

		update_user_meta( $user_id, self::DISMISSED_META, 1 );

		if ( 'review' === $choice ) {
			wp_redirect( self::REVIEW_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed wordpress.org URL.
			exit;
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=nestform-dashboard' ) );
		exit;
	}

	/**
	 * @param array<int|string, string> $links Row meta links.
	 * @param string                    $file  Plugin basename.
	 * @return array<int|string, string>
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( NESTFORM_PATH . 'nestform.php' ) !== $file || ! self::is_enabled() ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( self::REVIEW_URL ),
			esc_html__( 'Rate Thimbleform', 'nestform' )
		);

		return $links;
	}
}
