<?php
/**
 * Form JSON export / import (local → production).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Form_IO {

	const SCHEMA         = 'thimbleform/form';
	const VERSION        = 1;
	const ACTION_EXPORT  = 'thimbleform_export_form';
	const ACTION_IMPORT  = 'thimbleform_import_form';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'import_notice' ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function export_url( $form_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION_EXPORT,
					'form_id' => (int) $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_EXPORT . '_' . (int) $form_id
		);
	}

	/**
	 * Build portable payload (no entries, no site-specific IDs).
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>|null
	 */
	public static function build_payload( $form_id ) {
		$form_id = (int) $form_id;
		$post    = get_post( $form_id );
		if ( ! $post || Thimbleform_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$config = Thimbleform_Form_Config::get( $form_id );
		return array(
			'schema'      => self::SCHEMA,
			'version'     => self::VERSION,
			'exported_at' => gmdate( 'c' ),
			'plugin'      => defined( 'THIMBLEFORM_VERSION' ) ? THIMBLEFORM_VERSION : '',
			'form'        => array(
				'title'    => (string) $post->post_title,
				'status'   => in_array( $post->post_status, array( 'publish', 'draft', 'private' ), true ) ? $post->post_status : 'draft',
				'fields'   => $config['fields'],
				'messages' => $config['messages'],
				'mail'     => $config['mail'],
				'settings' => $config['settings'],
			),
		);
	}

	public static function handle_export() {
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $form_id <= 0 || Thimbleform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'thimbleform' ), 400 );
		}
		check_admin_referer( self::ACTION_EXPORT . '_' . $form_id );
		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to export this form.', 'thimbleform' ), 403 );
		}

		$payload = self::build_payload( $form_id );
		if ( null === $payload ) {
			wp_die( esc_html__( 'Could not export form.', 'thimbleform' ), 500 );
		}

		$slug = sanitize_title( (string) ( $payload['form']['title'] ?? '' ) );
		if ( '' === $slug ) {
			$slug = 'form-' . $form_id;
		}
		$filename = 'thimbleform-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.json';
		$json     = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || '' === $json ) {
			wp_die( esc_html__( 'Could not encode form JSON.', 'thimbleform' ), 500 );
		}

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

	/**
	 * @param array<string, mixed> $payload Decoded JSON.
	 * @return array{ok:bool,form_id:int,message:string}
	 */
	public static function import_payload( array $payload ) {
		if ( ( $payload['schema'] ?? '' ) !== self::SCHEMA ) {
			return array(
				'ok'      => false,
				'form_id' => 0,
				'message' => __( 'Not a Thimbleform export file.', 'thimbleform' ),
			);
		}

		$form = isset( $payload['form'] ) && is_array( $payload['form'] ) ? $payload['form'] : array();
		if ( array() === $form ) {
			return array(
				'ok'      => false,
				'form_id' => 0,
				'message' => __( 'Export file is missing form data.', 'thimbleform' ),
			);
		}

		$title = isset( $form['title'] ) ? sanitize_text_field( (string) $form['title'] ) : '';
		if ( '' === $title ) {
			$title = __( 'Imported form', 'thimbleform' );
		}

		$status = isset( $form['status'] ) ? sanitize_key( (string) $form['status'] ) : 'draft';
		if ( ! in_array( $status, array( 'publish', 'draft', 'private' ), true ) ) {
			$status = 'draft';
		}
		// Safer default on import: always land as draft; user publishes on prod.
		$status = 'draft';

		$new_id = wp_insert_post(
			array(
				'post_type'   => Thimbleform_Post_Type::POST_TYPE,
				'post_status' => $status,
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return array(
				'ok'      => false,
				'form_id' => 0,
				'message' => __( 'Could not create the imported form.', 'thimbleform' ),
			);
		}

		$config = array(
			'fields'   => isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array(),
			'messages' => isset( $form['messages'] ) && is_array( $form['messages'] ) ? $form['messages'] : array(),
			'mail'     => isset( $form['mail'] ) && is_array( $form['mail'] ) ? $form['mail'] : array(),
			'settings' => isset( $form['settings'] ) && is_array( $form['settings'] ) ? $form['settings'] : array(),
		);

		Thimbleform_Form_Config::save( (int) $new_id, $config );

		return array(
			'ok'      => true,
			'form_id' => (int) $new_id,
			'message' => __( 'Form imported as a draft.', 'thimbleform' ),
		);
	}

	public static function handle_import() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to import forms.', 'thimbleform' ), 403 );
		}
		check_admin_referer( self::ACTION_IMPORT );

		$hub = Thimbleform_Post_Type::hub_url();

		if ( empty( $_FILES['thimbleform_import_file'] ) || ! is_array( $_FILES['thimbleform_import_file'] ) ) {
			wp_safe_redirect( add_query_arg( 'thimbleform_import', 'nofile', $hub ) );
			exit;
		}

		$file = $_FILES['thimbleform_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'thimbleform_import', 'upload', $hub ) );
			exit;
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > 2 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'thimbleform_import', 'size', $hub ) );
			exit;
		}

		$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $raw ) || '' === $raw ) {
			wp_safe_redirect( add_query_arg( 'thimbleform_import', 'empty', $hub ) );
			exit;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			wp_safe_redirect( add_query_arg( 'thimbleform_import', 'json', $hub ) );
			exit;
		}

		$result = self::import_payload( $decoded );
		if ( ! $result['ok'] ) {
			wp_safe_redirect( add_query_arg( 'thimbleform_import', 'invalid', $hub ) );
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'thimbleform_import' => 'ok',
					'thimbleform_imported' => (int) $result['form_id'],
				),
				$hub
			)
		);
		exit;
	}

	public static function import_notice() {
		if ( ! is_admin() || empty( $_GET['page'] ) || Thimbleform_Post_Type::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['thimbleform_import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$code = sanitize_key( wp_unslash( $_GET['thimbleform_import'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'ok' === $code ) {
			$new_id = isset( $_GET['thimbleform_imported'] ) ? (int) $_GET['thimbleform_imported'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$edit   = $new_id > 0 ? get_edit_post_link( $new_id, 'raw' ) : '';
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Form imported as a draft.', 'thimbleform' );
			if ( $edit ) {
				echo ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit form', 'thimbleform' ) . '</a>';
			}
			echo '</p></div>';
			return;
		}

		$messages = array(
			'nofile'  => __( 'Choose a Thimbleform JSON file to import.', 'thimbleform' ),
			'upload'  => __( 'Upload failed. Try again.', 'thimbleform' ),
			'size'    => __( 'File is too large (max 2 MB).', 'thimbleform' ),
			'empty'   => __( 'The file is empty.', 'thimbleform' ),
			'json'    => __( 'Could not parse JSON.', 'thimbleform' ),
			'invalid' => __( 'This file is not a valid Thimbleform export.', 'thimbleform' ),
		);
		$msg = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Import failed.', 'thimbleform' );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Compact import control for the Forms hub header (JSON file).
	 *
	 * @param string $label Optional button label.
	 * @param string $class Optional extra label classes.
	 * @return string
	 */
	public static function hub_import_html( $label = '', $class = '' ) {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return '';
		}
		$label = $label !== '' ? (string) $label : __( 'Thimbleform JSON', 'thimbleform' );
		$class = trim( 'thimbleform-btn thimbleform-btn--ghost thimbleform-hub__import-label ' . (string) $class );
		ob_start();
		?>
		<form class="thimbleform-hub__import" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>" />
			<?php wp_nonce_field( self::ACTION_IMPORT ); ?>
			<label class="<?php echo esc_attr( $class ); ?>">
				<?php echo wp_kses( thimbleform_admin_icon_html( 'download' ), thimbleform_svg_allowed_html() ); ?>
				<span><?php echo esc_html( $label ); ?></span>
				<input type="file" name="thimbleform_import_file" accept="application/json,.json" required class="thimbleform-hub__import-file" />
			</label>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Combined Import dropdown for the Forms hub (JSON + CF7/WPForms).
	 *
	 * @return string
	 */
	public static function hub_import_menu_html() {
		$json = self::hub_import_html();
		$ext  = class_exists( 'Thimbleform_Importer' ) ? Thimbleform_Importer::hub_link_html( true ) : '';
		if ( $json === '' && $ext === '' ) {
			return '';
		}
		ob_start();
		?>
		<div class="thimbleform-hub__import-menu" data-thimbleform-hub-import>
			<button
				type="button"
				class="thimbleform-btn thimbleform-btn--outline thimbleform-hub__import-toggle"
				aria-expanded="false"
				aria-haspopup="true"
			>
				<?php echo wp_kses( thimbleform_admin_icon_html( 'download' ), thimbleform_svg_allowed_html() ); ?>
				<?php esc_html_e( 'Import', 'thimbleform' ); ?>
			</button>
			<div class="thimbleform-hub__import-panel" hidden>
				<?php echo wp_kses( $json, thimbleform_admin_allowed_html() ); ?>
				<?php echo wp_kses( $ext, thimbleform_admin_allowed_html() ); ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
