<?php
/**
 * Site-wide Thimbleform backup / restore (forms + plugin settings).
 *
 * Entries are not included — use CSV export per form. Spam and email logs are
 * operational journals and are also left out.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Backup {

	const FORMAT = 'thimbleform-backup';

	const FORMAT_VERSION = 1;

	const ACTION_EXPORT = 'thimbleform_export_backup';

	const ACTION_IMPORT = 'thimbleform_import_backup';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'import_notice' ) );
	}

	/**
	 * @return array{format:string,version:int,exported_at:string,plugin:string,settings:array,forms:array}
	 */
	public static function build() {
		$forms = array();
		$ids   = get_posts(
			array(
				'post_type'              => Thimbleform_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $ids as $form_id ) {
			$payload = Thimbleform_Form_IO::build_payload( (int) $form_id );
			if ( is_array( $payload ) ) {
				$forms[] = $payload;
			}
		}

		$settings = class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::get() : array();
		// Never put secrets into a downloadable backup by accident beyond what
		// the site already stores — keep keys, strip nothing else here.

		return array(
			'format'      => self::FORMAT,
			'version'     => self::FORMAT_VERSION,
			'exported_at' => gmdate( 'c' ),
			'plugin'      => defined( 'THIMBLEFORM_VERSION' ) ? THIMBLEFORM_VERSION : '',
			'settings'    => $settings,
			'forms'       => $forms,
		);
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'thimbleform' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION_EXPORT );

		$backup = self::build();
		$json   = wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || '' === $json ) {
			wp_die( esc_html__( 'Could not encode backup.', 'thimbleform' ), 500 );
		}

		$filename = 'thimbleform-backup-' . gmdate( 'Ymd-His' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $json ) );
		$download = fopen( 'php://output', 'wb' );
		if ( false !== $download ) {
			fwrite( $download, $json );
			fclose( $download );
		}
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'thimbleform' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION_IMPORT );

		$result = array(
			'ok'      => false,
			'forms'   => 0,
			'message' => __( 'Import failed.', 'thimbleform' ),
		);

		if ( empty( $_FILES['thimbleform_backup']['tmp_name'] ) || ! is_uploaded_file( $_FILES['thimbleform_backup']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$result['message'] = __( 'Choose a Thimbleform backup JSON file.', 'thimbleform' );
			self::redirect_with_result( $result );
		}

		$raw = file_get_contents( $_FILES['thimbleform_backup']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) || ( $data['format'] ?? '' ) !== self::FORMAT ) {
			$result['message'] = __( 'Not a Thimbleform backup file.', 'thimbleform' );
			self::redirect_with_result( $result );
		}

		$version = (int) ( $data['version'] ?? 0 );
		if ( $version < 1 || $version > self::FORMAT_VERSION ) {
			$result['message'] = __( 'This backup format is not supported.', 'thimbleform' );
			self::redirect_with_result( $result );
		}

		$imported = 0;
		$forms    = isset( $data['forms'] ) && is_array( $data['forms'] ) ? $data['forms'] : array();
		foreach ( $forms as $payload ) {
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$out = Thimbleform_Form_IO::import_payload( $payload );
			if ( ! empty( $out['ok'] ) ) {
				++$imported;
			}
		}

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) && class_exists( 'Thimbleform_Settings' ) ) {
			$merged = array_merge( Thimbleform_Settings::defaults(), Thimbleform_Settings::get(), $data['settings'] );
			// Re-sanitize sensitive textareas through Spam_Filter helpers when present.
			if ( class_exists( 'Thimbleform_Spam_Filter' ) ) {
				$merged['blocked_words']          = Thimbleform_Spam_Filter::sanitize_lines( $merged['blocked_words'] ?? '' );
				$merged['blocked_email_domains']  = Thimbleform_Spam_Filter::sanitize_lines( $merged['blocked_email_domains'] ?? '' );
				$merged['blocked_ips']            = Thimbleform_Spam_Filter::sanitize_lines( $merged['blocked_ips'] ?? '' );
			}
			$provider = sanitize_key( (string) ( $merged['captcha_provider'] ?? 'recaptcha_v2' ) );
			$allowed  = array( 'recaptcha_v2', 'recaptcha_v3', 'turnstile', 'hcaptcha' );
			if ( ! in_array( $provider, $allowed, true ) ) {
				$provider = 'recaptcha_v2';
			}
			$merged['captcha_provider'] = $provider;
			update_option( Thimbleform_Settings::OPTION, $merged, false );
		}

		$result = array(
			'ok'      => true,
			'forms'   => $imported,
			'message' => sprintf(
				/* translators: %d: number of forms */
				_n( 'Imported %d form as a draft.', 'Imported %d forms as drafts.', $imported, 'thimbleform' ),
				$imported
			),
		);
		self::redirect_with_result( $result );
	}

	/**
	 * @param array{ok:bool,forms?:int,message:string} $result Result.
	 */
	private static function redirect_with_result( array $result ) {
		set_transient(
			'thimbleform_backup_import_' . get_current_user_id(),
			$result,
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect(
			class_exists( 'Thimbleform_Settings' )
				? Thimbleform_Settings::url( array( 'section' => 'privacy' ) )
				: admin_url()
		);
		exit;
	}

	public static function import_notice() {
		$key    = 'thimbleform_backup_import_' . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );
		$class = ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( (string) ( $result['message'] ?? '' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render backup card for Privacy settings.
	 */
	public static function render_card() {
		?>
		<div class="thimbleform-admin__surface thimbleform-settings__card">
			<div class="thimbleform-admin__panel-head">
				<div>
					<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Backup & restore', 'thimbleform' ); ?></h3>
					<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Download all forms and Thimbleform settings as JSON. Entries are not included — export those as CSV per form.', 'thimbleform' ); ?></p>
				</div>
			</div>
			<p class="thimbleform-backup-import__export">
				<a class="thimbleform-btn thimbleform-btn--outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_EXPORT ), self::ACTION_EXPORT ) ); ?>">
					<?php thimbleform_admin_icon( 'download' ); ?>
					<?php esc_html_e( 'Download backup', 'thimbleform' ); ?>
				</a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="thimbleform-backup-import">
				<?php wp_nonce_field( self::ACTION_IMPORT ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>" />
				<div class="thimbleform-backup-import__field">
					<label class="thimbleform-admin__label" for="thimbleform_backup_file"><?php esc_html_e( 'Restore from file', 'thimbleform' ); ?></label>
					<input
						type="file"
						class="thimbleform-admin__input thimbleform-backup-import__file"
						id="thimbleform_backup_file"
						name="thimbleform_backup"
						accept="application/json,.json"
						required
					/>
				</div>
				<button type="submit" class="thimbleform-btn thimbleform-btn--ghost"><?php esc_html_e( 'Import backup', 'thimbleform' ); ?></button>
				<p class="description thimbleform-backup-import__hint"><?php esc_html_e( 'Imported forms are created as drafts. Existing forms are not overwritten.', 'thimbleform' ); ?></p>
			</form>
		</div>
		<?php
	}
}
