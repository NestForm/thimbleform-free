<?php
/**
 * Per-user Thimbleform admin theme (light / dark / system).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Admin_Theme {

	const META_KEY = 'thimbleform_admin_theme';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ), 20 );
		add_action( 'admin_post_thimbleform_save_admin_theme', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function choices() {
		return array(
			'auto'  => __( 'Match system', 'thimbleform' ),
			'light' => __( 'Light', 'thimbleform' ),
			'dark'  => __( 'Dark', 'thimbleform' ),
		);
	}

	/**
	 * @param int|null $user_id User ID.
	 * @return string auto|light|dark
	 */
	public static function get( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id <= 0 ) {
			return 'auto';
		}

		$value = get_user_meta( $user_id, self::META_KEY, true );
		$value = is_string( $value ) ? sanitize_key( $value ) : 'auto';

		return array_key_exists( $value, self::choices() ) ? $value : 'auto';
	}

	/**
	 * @param string   $theme   auto|light|dark.
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	public static function set( $theme, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		$theme = sanitize_key( (string) $theme );
		if ( ! array_key_exists( $theme, self::choices() ) ) {
			$theme = 'auto';
		}

		return (bool) update_user_meta( $user_id, self::META_KEY, $theme );
	}

	/**
	 * Whether the resolved admin UI should use dark tokens.
	 *
	 * @param int|null $user_id User ID.
	 * @return bool|null True/false when forced; null when OS decides.
	 */
	public static function is_dark_resolved( $user_id = null ) {
		$theme = self::get( $user_id );
		if ( 'dark' === $theme ) {
			return true;
		}
		if ( 'light' === $theme ) {
			return false;
		}

		return null;
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( '' === thimbleform_admin_current_view() ) {
			return $classes;
		}

		$theme = self::get();
		if ( 'light' === $theme ) {
			$classes .= ' thimbleform-theme-light';
		} elseif ( 'dark' === $theme ) {
			$classes .= ' thimbleform-theme-dark';
		}

		return $classes;
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change this setting.', 'thimbleform' ) );
		}

		check_admin_referer( 'thimbleform_save_admin_theme' );

		$theme = isset( $_POST['admin_theme'] ) ? sanitize_key( wp_unslash( (string) $_POST['admin_theme'] ) ) : 'auto'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::set( $theme );

		$redirect = class_exists( 'Thimbleform_Settings' )
			? Thimbleform_Settings::url( array( 'section' => 'general', 'theme-updated' => '1' ) )
			: admin_url( 'edit.php?post_type=thimbleform&page=thimbleform-settings&section=general&theme-updated=1' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Settings card markup.
	 *
	 * @return void
	 */
	public static function render_settings_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = self::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="thimbleform-settings__form">
			<?php wp_nonce_field( 'thimbleform_save_admin_theme' ); ?>
			<input type="hidden" name="action" value="thimbleform_save_admin_theme" />
			<div class="thimbleform-admin__surface thimbleform-settings__card">
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Admin appearance', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Choose how the Thimbleform admin looks for your account. WordPress menu and toolbar stay unchanged.', 'thimbleform' ); ?></p>
					</div>
				</div>
				<fieldset class="thimbleform-settings__theme-fieldset">
					<legend class="screen-reader-text"><?php esc_html_e( 'Admin theme', 'thimbleform' ); ?></legend>
					<div class="thimbleform-settings__theme-options">
						<?php foreach ( self::choices() as $value => $label ) : ?>
							<label class="thimbleform-settings__theme-option">
								<input
									type="radio"
									name="admin_theme"
									value="<?php echo esc_attr( $value ); ?>"
									<?php checked( $current, $value ); ?>
								/>
								<span class="thimbleform-settings__theme-option-label"><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<button type="submit" class="thimbleform-btn thimbleform-btn--primary" name="submit" value="1">
					<?php thimbleform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Save appearance', 'thimbleform' ); ?>
				</button>
			</div>
		</form>
		<?php
	}
}
