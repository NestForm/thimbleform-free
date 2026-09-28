<?php
/**
 * Site-wide spam / content checks for submissions.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Spam_Filter {

	/**
	 * @return array{enabled:bool,max:int,window:int}
	 */
	public static function rate_limit_config() {
		$s = class_exists( 'Nestform_Settings' ) ? Nestform_Settings::get() : array();
		return array(
			'enabled' => ! isset( $s['rate_limit_enabled'] ) || '1' === (string) ( $s['rate_limit_enabled'] ?? '1' ),
			'max'     => isset( $s['rate_limit_max'] ) ? max( 1, (int) $s['rate_limit_max'] ) : 10,
			'window'  => isset( $s['rate_limit_window'] ) ? max( 60, (int) $s['rate_limit_window'] ) : 3600,
		);
	}

	/**
	 * @return array{enabled:bool,window:int}
	 */
	public static function duplicate_config() {
		$s = class_exists( 'Nestform_Settings' ) ? Nestform_Settings::get() : array();
		return array(
			'enabled' => ! isset( $s['duplicate_check_enabled'] ) || '1' === (string) ( $s['duplicate_check_enabled'] ?? '1' ),
			'window'  => isset( $s['duplicate_check_window'] ) ? max( 30, (int) $s['duplicate_check_window'] ) : 300,
		);
	}

	/**
	 * @return bool
	 */
	public static function content_filter_enabled() {
		$s = class_exists( 'Nestform_Settings' ) ? Nestform_Settings::get() : array();
		return ! isset( $s['content_filter_enabled'] ) || '1' === (string) ( $s['content_filter_enabled'] ?? '1' );
	}

	/**
	 * @param string $ip Client IP.
	 * @return bool
	 */
	public static function is_blocked_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( $ip === '' ) {
			return false;
		}
		$s    = class_exists( 'Nestform_Settings' ) ? Nestform_Settings::get() : array();
		$list = self::lines( (string) ( $s['blocked_ips'] ?? '' ) );
		foreach ( $list as $entry ) {
			if ( $entry === $ip ) {
				return true;
			}
			$star = strpos( $entry, '*' );
			if ( false !== $star && 0 === strncmp( $entry, $ip, $star ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Soft IP rate limit (counted attempts in a window).
	 *
	 * @param string $ip IP.
	 * @return bool True when exhausted.
	 */
	public static function is_rate_limited( $ip ) {
		$cfg = self::rate_limit_config();
		if ( ! $cfg['enabled'] ) {
			return false;
		}
		$bucket = $ip !== '' ? $ip : 'unknown';
		$key    = 'nestform_rl_' . md5( $bucket );
		$count  = (int) get_transient( $key );
		return $count >= $cfg['max'];
	}

	/**
	 * @param string $ip IP.
	 */
	public static function bump_rate_limit( $ip ) {
		$cfg = self::rate_limit_config();
		if ( ! $cfg['enabled'] ) {
			return;
		}
		$bucket = $ip !== '' ? $ip : 'unknown';
		$key    = 'nestform_rl_' . md5( $bucket );
		$count  = (int) get_transient( $key );
		set_transient( $key, $count + 1, $cfg['window'] );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Validated data.
	 * @param string               $ip      IP.
	 * @return bool True when duplicate.
	 */
	public static function is_duplicate( $form_id, array $data, $ip ) {
		$cfg = self::duplicate_config();
		if ( ! $cfg['enabled'] ) {
			return false;
		}
		$key = self::duplicate_key( $form_id, $data, $ip );
		return (bool) get_transient( $key );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Validated data.
	 * @param string               $ip      IP.
	 */
	public static function remember_submission( $form_id, array $data, $ip ) {
		$cfg = self::duplicate_config();
		if ( ! $cfg['enabled'] ) {
			return;
		}
		set_transient( self::duplicate_key( $form_id, $data, $ip ), 1, $cfg['window'] );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Validated data.
	 * @param string               $ip      IP.
	 * @return string
	 */
	private static function duplicate_key( $form_id, array $data, $ip ) {
		$payload = wp_json_encode( array( (int) $form_id, $data, (string) $ip ) );
		return 'nestform_dup_' . md5( (string) $payload );
	}

	/**
	 * Content checks after field validation.
	 *
	 * @param array<string, mixed> $data Validated data.
	 * @return array{reason:string,detail:string}|null Null when clean.
	 */
	public static function check_content( array $data ) {
		if ( ! self::content_filter_enabled() ) {
			return null;
		}

		$s    = class_exists( 'Nestform_Settings' ) ? Nestform_Settings::get() : array();
		$text = self::flatten( $data );

		$max_links = isset( $s['max_links'] ) ? max( 0, (int) $s['max_links'] ) : 5;
		$links     = self::count_links( $text );
		if ( $max_links > 0 && $links > $max_links ) {
			return array(
				'reason' => 'links',
				'detail' => sprintf(
					/* translators: 1: link count, 2: max allowed */
					__( '%1$d links, limit is %2$d', 'nestform' ),
					$links,
					$max_links
				),
			);
		}

		$word = self::matched_word( $text, (string) ( $s['blocked_words'] ?? '' ) );
		if ( null !== $word ) {
			return array(
				'reason' => 'keyword',
				'detail' => $word,
			);
		}

		$domain = self::matched_email_domain( $data, (string) ( $s['blocked_email_domains'] ?? '' ) );
		if ( null !== $domain ) {
			return array(
				'reason' => 'email_domain',
				'detail' => $domain,
			);
		}

		/**
		 * Filter content-filter outcome (null = clean).
		 *
		 * Return a string reason key, or array { reason, detail }.
		 *
		 * @param string|array{reason?:string,detail?:string}|null $result Filter result.
		 * @param array<string, mixed>                             $data   Submission data.
		 */
		$filtered = apply_filters( 'nestform_spam_filter_result', null, $data );
		if ( is_string( $filtered ) && $filtered !== '' ) {
			return array(
				'reason' => $filtered,
				'detail' => '',
			);
		}
		if ( is_array( $filtered ) && ! empty( $filtered['reason'] ) ) {
			return array(
				'reason' => (string) $filtered['reason'],
				'detail' => (string) ( $filtered['detail'] ?? '' ),
			);
		}

		return null;
	}

	/**
	 * @param string $text Haystack.
	 * @return int
	 */
	public static function count_links( $text ) {
		return (int) preg_match_all( '#(?:https?://|www\.)[^\s<>"\']+#i', (string) $text );
	}

	/**
	 * @param string $text  Haystack.
	 * @param string $raw   Blocklist textarea.
	 * @return string|null
	 */
	private static function matched_word( $text, $raw ) {
		$words = self::lines( $raw );
		if ( array() === $words ) {
			return null;
		}
		$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		foreach ( $words as $word ) {
			$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word );
			if ( $needle === '' ) {
				continue;
			}
			$bounded = (bool) preg_match( '/^[\p{L}\p{N}]+$/u', $needle );
			$pattern = $bounded
				? '/\b' . preg_quote( $needle, '/' ) . '\b/u'
				: '/' . preg_quote( $needle, '/' ) . '/u';
			if ( preg_match( $pattern, $haystack ) ) {
				return $word;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @param string               $raw  Domains textarea.
	 * @return string|null
	 */
	private static function matched_email_domain( array $data, $raw ) {
		$domains = self::lines( $raw );
		if ( array() === $domains ) {
			return null;
		}
		$blocked = array();
		foreach ( $domains as $entry ) {
			$blocked[] = ltrim( strtolower( $entry ), '@' );
		}
		foreach ( self::email_domains( $data ) as $domain ) {
			foreach ( $blocked as $entry ) {
				if ( $domain === $entry || substr( $domain, -strlen( '.' . $entry ) ) === '.' . $entry ) {
					return $entry;
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	private static function email_domains( array $data ) {
		$domains = array();
		foreach ( self::values( $data ) as $value ) {
			if ( ! preg_match_all( '/[^\s<>()\[\],;:"\']+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/u', $value, $matches ) ) {
				continue;
			}
			foreach ( $matches[1] as $domain ) {
				$domains[] = strtolower( rtrim( (string) $domain, '.' ) );
			}
		}
		return array_values( array_unique( $domains ) );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return string
	 */
	private static function flatten( array $data ) {
		return implode( "\n", self::values( $data ) );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	private static function values( array $data ) {
		$out = array();
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$out = array_merge( $out, self::values( $value ) );
				continue;
			}
			if ( is_scalar( $value ) && (string) $value !== '' ) {
				$out[] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * @param string $raw Multiline list.
	 * @return array<int, string>
	 */
	public static function lines( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line !== '' ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * @param string $raw Multiline.
	 * @return string
	 */
	public static function sanitize_lines( $raw ) {
		$lines = self::lines( $raw );
		return implode( "\n", array_map( 'sanitize_text_field', $lines ) );
	}
}
