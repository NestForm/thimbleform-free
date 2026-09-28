<?php
/**
 * Security helpers: rate limits, redirects, private uploads.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Security {

	const UPLOAD_DIR   = 'nestform-private';
	const META_PRIVATE = '_nestform_private';
	const META_FORM    = '_nestform_upload_form';

	/** @var int Form ID for the current upload batch. */
	private static $upload_form_id = 0;

	public static function init() {
		add_action( 'admin_post_nestform_file', array( __CLASS__, 'serve_file' ) );
		add_action( 'admin_post_nopriv_nestform_file', array( __CLASS__, 'serve_file' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'hide_private_from_media' ) );
	}

	/**
	 * @param int $form_id Form ID.
	 */
	public static function set_upload_form_id( $form_id ) {
		self::$upload_form_id = max( 0, (int) $form_id );
	}

	/**
	 * Soft IP rate limit. Returns true when the bucket is exhausted.
	 *
	 * @param string $bucket Bucket key (no PII beyond hashed IP).
	 * @param int    $max    Max hits per window.
	 * @param int    $window Window seconds.
	 * @return bool
	 */
	public static function is_rate_limited( $bucket, $max = 30, $window = MINUTE_IN_SECONDS ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'nestform_sec_rl_' . md5( (string) $bucket . '|' . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= (int) $max ) {
			return true;
		}
		set_transient( $key, $count + 1, max( 10, (int) $window ) );
		return false;
	}

	/**
	 * Sanitize post-submit redirect.
	 * Allows same-site (incl. relative) and public http(s) thank-you URLs.
	 * Blocks javascript:/data:, localhost, and private/metadata hosts.
	 * Filters may only further restrict — never reopen a blocked URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function sanitize_redirect_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}
		$url = esc_url_raw( $url );
		if ( $url === '' ) {
			return '';
		}

		$validated = wp_validate_redirect( $url, '' );
		if ( $validated === '' ) {
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
				return '';
			}
			$scheme = strtolower( (string) $parts['scheme'] );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return '';
			}
			if ( class_exists( 'Nestform_Form_Config' ) && ! Nestform_Form_Config::is_safe_outbound_url( $url ) ) {
				return '';
			}
			$validated = $url;
		}

		/**
		 * Filter sanitized redirect URL (empty = blocked).
		 * May only further restrict — returning an unsafe URL is ignored.
		 *
		 * @param string $validated Validated URL.
		 * @param string $url       Original sanitized URL.
		 */
		$filtered = (string) apply_filters( 'nestform_sanitize_redirect_url', $validated, $url );
		if ( $filtered === '' ) {
			return '';
		}
		if ( $filtered === $validated ) {
			return $validated;
		}
		// Re-run the same rules on the filtered value (no open-redirect via filter).
		$again = wp_validate_redirect( $filtered, '' );
		if ( $again !== '' ) {
			return $again;
		}
		$parts = wp_parse_url( $filtered );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
		if ( class_exists( 'Nestform_Form_Config' ) && ! Nestform_Form_Config::is_safe_outbound_url( $filtered ) ) {
			return '';
		}
		return $filtered;
	}

	/**
	 * Sanitize a redirect URL template that may contain {field_name} merge tags.
	 * Validates URL structure with placeholder values; tokens are preserved as stored.
	 *
	 * @param string $url Raw redirect template.
	 * @return string
	 */
	public static function sanitize_redirect_template( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		$probe = preg_replace( '/\{[a-zA-Z0-9_]+\}/', 'nestform', $url );
		if ( ! is_string( $probe ) || self::sanitize_redirect_url( $probe ) === '' ) {
			return '';
		}

		return $url;
	}

	/**
	 * @param array<string, string> $dirs Upload dirs.
	 * @return array<string, string>
	 */
	public static function filter_upload_dir( $dirs ) {
		$subdir           = '/' . self::UPLOAD_DIR . ( isset( $dirs['subdir'] ) ? (string) $dirs['subdir'] : '' );
		$dirs['subdir']   = $subdir;
		$dirs['path']     = $dirs['basedir'] . $subdir;
		$dirs['url']      = $dirs['baseurl'] . $subdir;
		return $dirs;
	}

	/**
	 * Ensure private upload root denies direct HTTP access.
	 *
	 * @param string $basedir Uploads basedir.
	 */
	public static function ensure_private_dir( $basedir ) {
		$root = trailingslashit( $basedir ) . self::UPLOAD_DIR;
		if ( ! wp_mkdir_p( $root ) ) {
			return;
		}
		$htaccess = $root . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" );
		}
		$index = $root . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Run a callback with private upload directory active.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public static function with_private_uploads( $callback ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) ) {
			self::ensure_private_dir( (string) $uploads['basedir'] );
		}
		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		try {
			return $callback();
		} finally {
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		}
	}

	/**
	 * @param int $attach_id Attachment ID.
	 * @param int $form_id   Form ID.
	 * @return string
	 */
	public static function file_signature( $attach_id, $form_id ) {
		return hash_hmac( 'sha256', (int) $attach_id . '|' . (int) $form_id, wp_salt( 'auth' ) );
	}

	/**
	 * Public (gated) URL for a private attachment.
	 *
	 * @param int $attach_id Attachment ID.
	 * @param int $form_id   Form ID.
	 * @return string
	 */
	public static function file_url( $attach_id, $form_id ) {
		$attach_id = (int) $attach_id;
		$form_id   = (int) $form_id;
		if ( $attach_id <= 0 || $form_id <= 0 ) {
			return '';
		}
		return add_query_arg(
			array(
				'action' => 'nestform_file',
				'aid'    => $attach_id,
				'fid'    => $form_id,
				'sig'    => self::file_signature( $attach_id, $form_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Mark attachment private and return gated URL payload fields.
	 *
	 * @param int    $attach_id Attachment ID.
	 * @param string $file_path Absolute path.
	 * @param string $mime      MIME.
	 * @param int    $size      Bytes.
	 * @param int    $form_id   Form ID (0 = current batch).
	 * @return array{id:int,url:string,name:string,size:int,type:string}
	 */
	public static function finalize_private_attachment( $attach_id, $file_path, $mime, $size, $form_id = 0 ) {
		$attach_id = (int) $attach_id;
		$form_id   = $form_id > 0 ? (int) $form_id : self::$upload_form_id;
		if ( $attach_id > 0 && $form_id > 0 ) {
			update_post_meta( $attach_id, self::META_PRIVATE, '1' );
			update_post_meta( $attach_id, self::META_FORM, $form_id );
		}
		$url = ( $attach_id > 0 && $form_id > 0 )
			? self::file_url( $attach_id, $form_id )
			: '';
		return array(
			'id'   => $attach_id,
			'url'  => $url,
			'name' => basename( (string) $file_path ),
			'size' => (int) $size,
			'type' => (string) $mime,
		);
	}

	/**
	 * Serve a private Thimbleform upload.
	 */
	public static function serve_file() {
		$attach_id = isset( $_GET['aid'] ) ? (int) $_GET['aid'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id   = isset( $_GET['fid'] ) ? (int) $_GET['fid'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sig       = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $attach_id <= 0 || $form_id <= 0 || $sig === '' ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'nestform' ), '', array( 'response' => 404 ) );
		}

		if ( self::is_rate_limited( 'file_' . $attach_id, 60 ) ) {
			status_header( 429 );
			wp_die( esc_html__( 'Too many requests.', 'nestform' ), '', array( 'response' => 429 ) );
		}

		$expected = self::file_signature( $attach_id, $form_id );
		if ( ! hash_equals( $expected, $sig ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Forbidden.', 'nestform' ), '', array( 'response' => 403 ) );
		}

		$post = get_post( $attach_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'nestform' ), '', array( 'response' => 404 ) );
		}

		$meta_form = (int) get_post_meta( $attach_id, self::META_FORM, true );
		$private   = (string) get_post_meta( $attach_id, self::META_PRIVATE, true );
		if ( '1' !== $private || $meta_form !== $form_id ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'nestform' ), '', array( 'response' => 404 ) );
		}

		$path = get_attached_file( $attach_id );
		if ( ! is_string( $path ) || $path === '' || ! is_readable( $path ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'nestform' ), '', array( 'response' => 404 ) );
		}

		$mime = get_post_mime_type( $attach_id );
		if ( ! is_string( $mime ) || $mime === '' ) {
			$mime = 'application/octet-stream';
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( basename( $path ) ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * Hide Thimbleform private attachments from the Media Library picker.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public static function hide_private_from_media( $args ) {
		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => self::META_PRIVATE,
			'compare' => 'NOT EXISTS',
		);
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $args;
	}
}
