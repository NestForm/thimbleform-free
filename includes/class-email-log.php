<?php
/**
 * Email delivery log (thin record of wp_mail outcomes — never stores the body).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Email_Log {

	const DB_VERSION_OPTION = 'thimbleform_email_log_db_version';

	const DB_VERSION = 1;

	const CRON_HOOK = 'thimbleform_email_log_cleanup';

	const TYPES = array( 'admin', 'extra', 'user', 'test' );

	const DISMISSED_META = 'thimbleform_email_failure_dismissed';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_failure_notice' ) );
		add_action( 'admin_post_thimbleform_dismiss_email_failures', array( __CLASS__, 'handle_dismiss' ) );
		self::schedule_cleanup();
	}

	public static function maybe_install() {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}
		self::install();
	}

	public static function install() {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned DEFAULT NULL,
			entry_id bigint(20) unsigned DEFAULT NULL,
			type varchar(20) NOT NULL DEFAULT 'admin',
			recipient varchar(255) NOT NULL,
			subject varchar(500) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			error text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		self::schedule_cleanup();
	}

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'thimbleform_email_log';
	}

	public static function drop_table() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::DB_VERSION_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * @return int
	 */
	public static function retention_days() {
		if ( ! class_exists( 'Thimbleform_Settings' ) ) {
			return 30;
		}
		$s = Thimbleform_Settings::get();
		return max( 1, (int) ( $s['email_log_retention_days'] ?? 30 ) );
	}

	/**
	 * Send mail and record the outcome.
	 *
	 * @param string               $type        One of self::TYPES.
	 * @param string|array<string> $to          Recipient(s).
	 * @param string               $subject     Subject.
	 * @param string               $body        Body (not stored).
	 * @param array<int, string>   $headers     Headers.
	 * @param array<int, string>   $attachments Paths.
	 * @param int                  $form_id     Form ID.
	 * @param int                  $entry_id    Entry ID.
	 * @return bool
	 */
	public static function send( $type, $to, $subject, $body, array $headers = array(), array $attachments = array(), $form_id = 0, $entry_id = 0 ) {
		$type  = sanitize_key( (string) $type );
		$error = '';
		$capture = static function ( $wp_error ) use ( &$error ) {
			if ( is_wp_error( $wp_error ) ) {
				$error = $wp_error->get_error_message();
			}
		};

		add_action( 'wp_mail_failed', $capture );
		try {
			$sent = (bool) wp_mail( $to, $subject, $body, $headers, $attachments );
		} finally {
			remove_action( 'wp_mail_failed', $capture );
		}

		if ( ! $sent && $error === '' ) {
			$error = __( 'The mailer rejected the message without reporting a reason.', 'thimbleform' );
		}

		self::record(
			array(
				'form_id'   => (int) $form_id,
				'entry_id'  => (int) $entry_id,
				'type'      => in_array( $type, self::TYPES, true ) ? $type : 'admin',
				'recipient' => self::format_recipients( $to ),
				'subject'   => (string) $subject,
				'status'    => $sent ? 'sent' : 'failed',
				'error'     => $error,
			)
		);

		return $sent;
	}

	/**
	 * @param array{form_id?:int,entry_id?:int,type?:string,recipient?:string,subject?:string,status?:string,error?:string} $row Row.
	 */
	public static function record( array $row ) {
		self::maybe_install();
		global $wpdb;

		$subject = (string) ( $row['subject'] ?? '' );
		if ( function_exists( 'mb_substr' ) ) {
			$subject = mb_substr( $subject, 0, 500 );
		} else {
			$subject = substr( $subject, 0, 500 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array(
				'form_id'    => (int) ( $row['form_id'] ?? 0 ),
				'entry_id'   => (int) ( $row['entry_id'] ?? 0 ),
				'type'       => sanitize_key( (string) ( $row['type'] ?? 'admin' ) ),
				'recipient'  => sanitize_text_field( (string) ( $row['recipient'] ?? '' ) ),
				'subject'    => sanitize_text_field( $subject ),
				'status'     => ( ! empty( $row['status'] ) && 'failed' === $row['status'] ) ? 'failed' : 'sent',
				'error'      => sanitize_textarea_field( (string) ( $row['error'] ?? '' ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param string|array<string> $to Recipients.
	 * @return string
	 */
	private static function format_recipients( $to ) {
		if ( is_array( $to ) ) {
			$to = implode( ', ', array_map( 'strval', $to ) );
		}
		$to = (string) $to;
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $to, 0, 255 );
		}
		return substr( $to, 0, 255 );
	}

	/**
	 * @param array{form_id?:int,status?:string,limit?:int,offset?:int} $args Args.
	 * @return array<int, object>
	 */
	public static function get_entries( array $args = array() ) {
		global $wpdb;
		self::maybe_install();

		$table  = self::table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( (string) $args['status'], array( 'sent', 'failed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}

		$limit  = max( 1, (int) ( $args['limit'] ?? 50 ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$sql    = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array{form_id?:int,status?:string} $args Args.
	 * @return int
	 */
	public static function count_entries( array $args = array() ) {
		global $wpdb;
		self::maybe_install();

		$table  = self::table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( (string) $args['status'], array( 'sent', 'failed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}

		$sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', $where );
		if ( array() === $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var( $sql );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * @return object|null
	 */
	public static function latest_failure() {
		$rows = self::get_entries(
			array(
				'status' => 'failed',
				'limit'  => 1,
			)
		);
		return isset( $rows[0] ) ? $rows[0] : null;
	}

	public static function maybe_failure_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$view = function_exists( 'thimbleform_admin_current_view' ) ? thimbleform_admin_current_view() : '';
		if ( ! in_array( $view, array( 'dashboard', 'forms', 'entries', 'settings' ), true ) ) {
			return;
		}

		$failure = self::latest_failure();
		if ( ! $failure ) {
			return;
		}

		$dismissed = (int) get_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		if ( $dismissed >= (int) $failure->id ) {
			return;
		}

		$dismiss = wp_nonce_url(
			admin_url( 'admin-post.php?action=thimbleform_dismiss_email_failures' ),
			'thimbleform_dismiss_email_failures'
		);
		$log_url = class_exists( 'Thimbleform_Settings' )
			? Thimbleform_Settings::url(
				array(
					'section' => 'email',
					'sub'     => 'log',
				)
			)
			: '';
		?>
		<div class="notice notice-error thimbleform-email-failure-notice">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: recipient, 2: error */
						__( 'Thimbleform could not send mail to %1$s: %2$s', 'thimbleform' ),
						(string) $failure->recipient,
						(string) $failure->error
					)
				);
				?>
			</p>
			<p>
				<?php if ( $log_url ) : ?>
					<a href="<?php echo esc_url( $log_url ); ?>"><?php esc_html_e( 'Open delivery log', 'thimbleform' ); ?></a>
					<span aria-hidden="true"> · </span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'thimbleform' ); ?></a>
			</p>
		</div>
		<?php
	}

	public static function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'thimbleform' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'thimbleform_dismiss_email_failures' );
		$failure = self::latest_failure();
		update_user_meta( get_current_user_id(), self::DISMISSED_META, $failure ? (int) $failure->id : 0 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public static function cleanup() {
		global $wpdb;
		self::maybe_install();
		$days   = self::retention_days();
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $cutoff ) );
	}

	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function type_labels() {
		return array(
			'admin' => __( 'Admin notification', 'thimbleform' ),
			'extra' => __( 'Extra notification', 'thimbleform' ),
			'user'  => __( 'Visitor autoreply', 'thimbleform' ),
			'test'  => __( 'Test email', 'thimbleform' ),
		);
	}
}
