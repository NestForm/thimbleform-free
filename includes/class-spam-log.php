<?php
/**
 * Log of submissions turned away by spam checks.
 *
 * Stores an HMAC of the client IP (not the address) so repeat clients group
 * together without creating personal data that needs a privacy exporter.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Spam_Log {

	const DB_VERSION_OPTION = 'thimbleform_spam_log_db_version';

	const DB_VERSION = 1;

	const CRON_HOOK = 'thimbleform_spam_log_cleanup';

	const REASONS = array(
		'ip_blocked',
		'honeypot',
		'too_fast',
		'rate_limited',
		'akismet',
		'captcha',
		'links',
		'keyword',
		'email_domain',
		'duplicate',
	);

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_post_thimbleform_clear_spam_log', array( __CLASS__, 'handle_clear' ) );
		self::schedule_cleanup();
	}

	/**
	 * Create / upgrade the spam log table when needed.
	 */
	public static function maybe_install() {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * Create the spam log table.
	 */
	public static function install() {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned DEFAULT NULL,
			reason varchar(32) NOT NULL,
			detail varchar(191) DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY reason (reason),
			KEY ip_hash (ip_hash),
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
		return $wpdb->prefix . 'thimbleform_spam_log';
	}

	/**
	 * Drop the table (uninstall).
	 */
	public static function drop_table() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::DB_VERSION_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'Thimbleform_Settings' ) ) {
			return true;
		}
		$s = Thimbleform_Settings::get();
		return ! isset( $s['spam_log_enabled'] ) || '1' === (string) ( $s['spam_log_enabled'] ?? '1' );
	}

	/**
	 * @return int
	 */
	public static function retention_days() {
		if ( ! class_exists( 'Thimbleform_Settings' ) ) {
			return 30;
		}
		$s = Thimbleform_Settings::get();
		return max( 1, (int) ( $s['spam_log_retention_days'] ?? 30 ) );
	}

	/**
	 * Record one rejected attempt. Never throws.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $reason  One of self::REASONS.
	 * @param string $detail  Short non-PII detail (matched word, etc.).
	 * @param string $ip      Client IP (hashed before store).
	 */
	public static function record( $form_id, $reason, $detail = '', $ip = '' ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$reason = sanitize_key( (string) $reason );
		if ( ! in_array( $reason, self::REASONS, true ) ) {
			$reason = 'honeypot';
		}

		self::maybe_install();

		global $wpdb;
		$detail = function_exists( 'mb_substr' )
			? mb_substr( sanitize_text_field( (string) $detail ), 0, 190 )
			: substr( sanitize_text_field( (string) $detail ), 0, 190 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array(
				'form_id'    => (int) $form_id,
				'reason'     => $reason,
				'detail'     => $detail,
				'ip_hash'    => self::client_hash( $ip ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param string $ip Client IP.
	 * @return string
	 */
	private static function client_hash( $ip ) {
		$ip = trim( (string) $ip );
		if ( $ip === '' ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'thimbleform_spam' ) );
	}

	/**
	 * @param array{form_id?:int,reason?:string,limit?:int,offset?:int} $args Args.
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
		if ( ! empty( $args['reason'] ) && in_array( (string) $args['reason'], self::REASONS, true ) ) {
			$where[]  = 'reason = %s';
			$params[] = (string) $args['reason'];
		}

		$limit  = max( 1, (int) ( $args['limit'] ?? 50 ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql      = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array{form_id?:int,reason?:string,since?:string} $args Args.
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
		if ( ! empty( $args['reason'] ) && in_array( (string) $args['reason'], self::REASONS, true ) ) {
			$where[]  = 'reason = %s';
			$params[] = (string) $args['reason'];
		}
		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = (string) $args['since'];
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
	 * @param int $days    Lookback days.
	 * @param int $form_id Optional form filter.
	 * @return array<string, int>
	 */
	public static function summary( $days = 7, $form_id = 0 ) {
		global $wpdb;

		self::maybe_install();
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS ) );
		$form_id = (int) $form_id;

		if ( $form_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT reason, COUNT(*) AS total FROM ' . $table . ' WHERE created_at >= %s AND form_id = %d GROUP BY reason', $since, $form_id ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT reason, COUNT(*) AS total FROM ' . $table . ' WHERE created_at >= %s GROUP BY reason', $since ) );
		}

		$summary = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$summary[ (string) $row->reason ] = (int) $row->total;
			}
		}
		arsort( $summary );
		return $summary;
	}

	/**
	 * @return array<string, string>
	 */
	public static function reason_labels() {
		return array(
			'ip_blocked'   => __( 'Blocked IP address', 'thimbleform' ),
			'honeypot'     => __( 'Honeypot filled in', 'thimbleform' ),
			'too_fast'     => __( 'Submitted too quickly', 'thimbleform' ),
			'rate_limited' => __( 'Rate limited', 'thimbleform' ),
			'akismet'      => __( 'Flagged by Akismet', 'thimbleform' ),
			'captcha'      => __( 'Failed captcha', 'thimbleform' ),
			'links'        => __( 'Too many links', 'thimbleform' ),
			'keyword'      => __( 'Blocked word', 'thimbleform' ),
			'email_domain' => __( 'Blocked email domain', 'thimbleform' ),
			'duplicate'    => __( 'Duplicate submission', 'thimbleform' ),
		);
	}

	/**
	 * @param string $reason Reason key.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = self::reason_labels();
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : (string) $reason;
	}

	/**
	 * @param int $form_id Optional form filter.
	 * @return int Rows deleted.
	 */
	public static function clear( $form_id = 0 ) {
		global $wpdb;

		self::maybe_install();
		$table = self::table();
		$form_id = (int) $form_id;

		if ( $form_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE form_id = %d', $form_id ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( 'DELETE FROM ' . $table );
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

	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'thimbleform' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'thimbleform_clear_spam_log' );
		self::clear();
		wp_safe_redirect(
			add_query_arg(
				array(
					'section' => 'security',
					'sub'     => 'log',
					'cleared' => '1',
				),
				class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::url() : admin_url()
			)
		);
		exit;
	}
}
