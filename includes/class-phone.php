<?php
/**
 * Phone country codes + E.164 helpers.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Phone {

	/**
	 * @return array<int, array{iso:string,dial:string,name:string,flag:string}>
	 */
	public static function countries() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$rows = array(
			array( 'iso' => 'RU', 'dial' => '7', 'name' => __( 'Russia', 'nestform' ) ),
			array( 'iso' => 'BY', 'dial' => '375', 'name' => __( 'Belarus', 'nestform' ) ),
			array( 'iso' => 'KZ', 'dial' => '7', 'name' => __( 'Kazakhstan', 'nestform' ) ),
			array( 'iso' => 'UA', 'dial' => '380', 'name' => __( 'Ukraine', 'nestform' ) ),
			array( 'iso' => 'UZ', 'dial' => '998', 'name' => __( 'Uzbekistan', 'nestform' ) ),
			array( 'iso' => 'KG', 'dial' => '996', 'name' => __( 'Kyrgyzstan', 'nestform' ) ),
			array( 'iso' => 'AM', 'dial' => '374', 'name' => __( 'Armenia', 'nestform' ) ),
			array( 'iso' => 'GE', 'dial' => '995', 'name' => __( 'Georgia', 'nestform' ) ),
			array( 'iso' => 'AZ', 'dial' => '994', 'name' => __( 'Azerbaijan', 'nestform' ) ),
			array( 'iso' => 'MD', 'dial' => '373', 'name' => __( 'Moldova', 'nestform' ) ),
			array( 'iso' => 'TJ', 'dial' => '992', 'name' => __( 'Tajikistan', 'nestform' ) ),
			array( 'iso' => 'TM', 'dial' => '993', 'name' => __( 'Turkmenistan', 'nestform' ) ),
			array( 'iso' => 'US', 'dial' => '1', 'name' => __( 'United States', 'nestform' ) ),
			array( 'iso' => 'CA', 'dial' => '1', 'name' => __( 'Canada', 'nestform' ) ),
			array( 'iso' => 'GB', 'dial' => '44', 'name' => __( 'United Kingdom', 'nestform' ) ),
			array( 'iso' => 'DE', 'dial' => '49', 'name' => __( 'Germany', 'nestform' ) ),
			array( 'iso' => 'FR', 'dial' => '33', 'name' => __( 'France', 'nestform' ) ),
			array( 'iso' => 'IT', 'dial' => '39', 'name' => __( 'Italy', 'nestform' ) ),
			array( 'iso' => 'ES', 'dial' => '34', 'name' => __( 'Spain', 'nestform' ) ),
			array( 'iso' => 'PL', 'dial' => '48', 'name' => __( 'Poland', 'nestform' ) ),
			array( 'iso' => 'NL', 'dial' => '31', 'name' => __( 'Netherlands', 'nestform' ) ),
			array( 'iso' => 'BE', 'dial' => '32', 'name' => __( 'Belgium', 'nestform' ) ),
			array( 'iso' => 'AT', 'dial' => '43', 'name' => __( 'Austria', 'nestform' ) ),
			array( 'iso' => 'CH', 'dial' => '41', 'name' => __( 'Switzerland', 'nestform' ) ),
			array( 'iso' => 'CZ', 'dial' => '420', 'name' => __( 'Czechia', 'nestform' ) ),
			array( 'iso' => 'SK', 'dial' => '421', 'name' => __( 'Slovakia', 'nestform' ) ),
			array( 'iso' => 'HU', 'dial' => '36', 'name' => __( 'Hungary', 'nestform' ) ),
			array( 'iso' => 'RO', 'dial' => '40', 'name' => __( 'Romania', 'nestform' ) ),
			array( 'iso' => 'BG', 'dial' => '359', 'name' => __( 'Bulgaria', 'nestform' ) ),
			array( 'iso' => 'GR', 'dial' => '30', 'name' => __( 'Greece', 'nestform' ) ),
			array( 'iso' => 'PT', 'dial' => '351', 'name' => __( 'Portugal', 'nestform' ) ),
			array( 'iso' => 'IE', 'dial' => '353', 'name' => __( 'Ireland', 'nestform' ) ),
			array( 'iso' => 'SE', 'dial' => '46', 'name' => __( 'Sweden', 'nestform' ) ),
			array( 'iso' => 'NO', 'dial' => '47', 'name' => __( 'Norway', 'nestform' ) ),
			array( 'iso' => 'FI', 'dial' => '358', 'name' => __( 'Finland', 'nestform' ) ),
			array( 'iso' => 'DK', 'dial' => '45', 'name' => __( 'Denmark', 'nestform' ) ),
			array( 'iso' => 'EE', 'dial' => '372', 'name' => __( 'Estonia', 'nestform' ) ),
			array( 'iso' => 'LV', 'dial' => '371', 'name' => __( 'Latvia', 'nestform' ) ),
			array( 'iso' => 'LT', 'dial' => '370', 'name' => __( 'Lithuania', 'nestform' ) ),
			array( 'iso' => 'IL', 'dial' => '972', 'name' => __( 'Israel', 'nestform' ) ),
			array( 'iso' => 'AE', 'dial' => '971', 'name' => __( 'United Arab Emirates', 'nestform' ) ),
			array( 'iso' => 'SA', 'dial' => '966', 'name' => __( 'Saudi Arabia', 'nestform' ) ),
			array( 'iso' => 'TR', 'dial' => '90', 'name' => __( 'Turkey', 'nestform' ) ),
			array( 'iso' => 'IN', 'dial' => '91', 'name' => __( 'India', 'nestform' ) ),
			array( 'iso' => 'CN', 'dial' => '86', 'name' => __( 'China', 'nestform' ) ),
			array( 'iso' => 'JP', 'dial' => '81', 'name' => __( 'Japan', 'nestform' ) ),
			array( 'iso' => 'KR', 'dial' => '82', 'name' => __( 'South Korea', 'nestform' ) ),
			array( 'iso' => 'AU', 'dial' => '61', 'name' => __( 'Australia', 'nestform' ) ),
			array( 'iso' => 'NZ', 'dial' => '64', 'name' => __( 'New Zealand', 'nestform' ) ),
			array( 'iso' => 'BR', 'dial' => '55', 'name' => __( 'Brazil', 'nestform' ) ),
			array( 'iso' => 'MX', 'dial' => '52', 'name' => __( 'Mexico', 'nestform' ) ),
			array( 'iso' => 'AR', 'dial' => '54', 'name' => __( 'Argentina', 'nestform' ) ),
			array( 'iso' => 'ZA', 'dial' => '27', 'name' => __( 'South Africa', 'nestform' ) ),
			array( 'iso' => 'EG', 'dial' => '20', 'name' => __( 'Egypt', 'nestform' ) ),
			array( 'iso' => 'TH', 'dial' => '66', 'name' => __( 'Thailand', 'nestform' ) ),
			array( 'iso' => 'VN', 'dial' => '84', 'name' => __( 'Vietnam', 'nestform' ) ),
			array( 'iso' => 'ID', 'dial' => '62', 'name' => __( 'Indonesia', 'nestform' ) ),
			array( 'iso' => 'MY', 'dial' => '60', 'name' => __( 'Malaysia', 'nestform' ) ),
			array( 'iso' => 'SG', 'dial' => '65', 'name' => __( 'Singapore', 'nestform' ) ),
			array( 'iso' => 'PH', 'dial' => '63', 'name' => __( 'Philippines', 'nestform' ) ),
		);
		$seen = array();
		$out  = array();
		foreach ( $rows as $row ) {
			$iso = strtoupper( (string) $row['iso'] );
			if ( isset( $seen[ $iso ] ) ) {
				continue;
			}
			$seen[ $iso ] = true;
			$row['iso']   = $iso;
			$row['dial']  = preg_replace( '/\D+/', '', (string) $row['dial'] );
			$row['flag']  = self::flag_emoji( $iso );
			$out[]        = $row;
		}

		/**
		 * Filter phone countries.
		 *
		 * @param array<int, array{iso:string,dial:string,name:string,flag:string}> $out Countries.
		 */
		$cached = array_values( (array) apply_filters( 'nestform_phone_countries', $out ) );
		return $cached;
	}

	/**
	 * @return array<int, string>
	 */
	public static function preferred_isos() {
		return array( 'RU', 'BY', 'KZ', 'UA', 'UZ', 'US', 'GB', 'DE', 'TR' );
	}

	/**
	 * @param string $iso ISO code.
	 * @return string
	 */
	public static function flag_emoji( $iso ) {
		$iso = strtoupper( substr( preg_replace( '/[^a-zA-Z]/', '', (string) $iso ), 0, 2 ) );
		if ( 2 !== strlen( $iso ) ) {
			return '';
		}
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$out .= html_entity_decode( '&#' . ( 127397 + ord( $iso[ $i ] ) ) . ';', ENT_NOQUOTES, 'UTF-8' );
		}
		return $out;
	}

	/**
	 * Local SVG flag URL (emoji flags do not render reliably on Windows).
	 *
	 * @param string $iso ISO code.
	 * @return string
	 */
	public static function flag_url( $iso ) {
		$iso = strtolower( substr( preg_replace( '/[^a-zA-Z]/', '', (string) $iso ), 0, 2 ) );
		if ( 2 !== strlen( $iso ) ) {
			return '';
		}

		$rel  = 'assets/flags/' . $iso . '.svg';
		$path = NESTFORM_PATH . $rel;
		$url  = is_readable( $path ) ? NESTFORM_URL . $rel : '';

		/**
		 * Filter phone flag image URL.
		 *
		 * @param string $url Flag URL (plugin-local SVG, or empty).
		 * @param string $iso Lowercase ISO.
		 */
		return (string) apply_filters( 'nestform_phone_flag_url', $url, $iso );
	}

	/**
	 * @param string $iso   ISO.
	 * @param string $class Class on wrapper.
	 * @return string
	 */
	public static function flag_html( $iso, $class = 'nest-form-phone__flag' ) {
		$url = self::flag_url( $iso );
		if ( '' === $url ) {
			return '';
		}
		$attr = 'nest-form-phone__flag' === $class ? ' data-nestform-phone-flag' : '';
		return sprintf(
			'<span class="%1$s"%2$s><img class="nest-form-phone__flag-img" src="%3$s" alt="" width="20" height="15" loading="lazy" decoding="async" /></span>',
			esc_attr( $class ),
			$attr,
			esc_url( $url )
		);
	}

	/**
	 * Valid ISO or empty (no default fallback).
	 *
	 * @param string $iso Raw ISO.
	 * @return string
	 */
	public static function parse_iso( $iso ) {
		$iso = strtoupper( substr( preg_replace( '/[^a-zA-Z]/', '', (string) $iso ), 0, 2 ) );
		if ( 2 !== strlen( $iso ) ) {
			return '';
		}
		foreach ( self::countries() as $country ) {
			if ( $country['iso'] === $iso ) {
				return $iso;
			}
		}
		return '';
	}

	/**
	 * Whether the front country picker is on (options stores a valid ISO).
	 *
	 * @param array<string, mixed> $field Field.
	 * @return bool
	 */
	public static function is_picker_enabled( array $field ) {
		return '' !== self::parse_iso( (string) ( $field['options'] ?? '' ) );
	}

	/**
	 * @param string $iso Raw ISO.
	 * @return string
	 */
	public static function sanitize_iso( $iso ) {
		$parsed = self::parse_iso( $iso );
		return '' !== $parsed ? $parsed : self::default_iso();
	}

	/**
	 * @return string
	 */
	public static function default_iso() {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
		$code   = strtoupper( substr( $locale, 0, 2 ) );
		if ( 'EN' === $code ) {
			$code = 'US';
		}
		foreach ( self::countries() as $country ) {
			if ( $country['iso'] === $code ) {
				return $code;
			}
		}
		return 'US';
	}

	/**
	 * @param string $iso ISO.
	 * @return array{iso:string,dial:string,name:string,flag:string}|null
	 */
	public static function country( $iso ) {
		$iso = strtoupper( (string) $iso );
		foreach ( self::countries() as $country ) {
			if ( $country['iso'] === $iso ) {
				return $country;
			}
		}
		return null;
	}

	/**
	 * @param string $iso      ISO.
	 * @param string $national National digits / mixed.
	 * @return string E.164-like +digits.
	 */
	public static function to_e164( $iso, $national ) {
		$country = self::country( $iso );
		$digits  = preg_replace( '/\D+/', '', (string) $national );
		$digits  = is_string( $digits ) ? $digits : '';
		if ( '' === $digits ) {
			return '';
		}
		$dial = $country ? (string) $country['dial'] : '';
		if ( '' !== $dial && 0 === strpos( $digits, $dial ) ) {
			return '+' . $digits;
		}
		if ( '7' === $dial && strlen( $digits ) >= 10 && ( '8' === $digits[0] || '7' === $digits[0] ) ) {
			$digits = substr( $digits, 1 );
		}
		return '+' . $dial . $digits;
	}

	/**
	 * Split stored value into iso + national.
	 *
	 * @param string $value Stored phone.
	 * @param string $iso   Fallback ISO.
	 * @return array{iso:string,national:string,e164:string}
	 */
	public static function split( $value, $iso = '' ) {
		$iso     = $iso !== '' ? self::sanitize_iso( $iso ) : self::default_iso();
		$digits  = preg_replace( '/\D+/', '', (string) $value );
		$digits  = is_string( $digits ) ? $digits : '';
		$country = self::country( $iso );
		$dial    = $country ? (string) $country['dial'] : '';
		$national = $digits;
		if ( '' !== $dial && 0 === strpos( $digits, $dial ) ) {
			$national = substr( $digits, strlen( $dial ) );
		}
		$e164 = self::to_e164( $iso, $national !== '' ? $national : $digits );
		return array(
			'iso'      => $iso,
			'national' => $national,
			'e164'     => $e164,
		);
	}

	/**
	 * @param string $e164 E.164.
	 * @return bool
	 */
	public static function is_valid_e164( $e164 ) {
		$digits = preg_replace( '/\D+/', '', (string) $e164 );
		if ( ! is_string( $digits ) ) {
			return false;
		}
		$len = strlen( $digits );
		return $len >= 7 && $len <= 15;
	}

	/**
	 * Front widget for tel fields.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $id    Input id.
	 * @param string               $name  Name.
	 * @param bool                 $req   Required.
	 * @param string               $ph    Placeholder.
	 * @param string               $def   Default.
	 */
	public static function render_field( array $field, $id, $name, $req, $ph, $def ) {
		$iso     = self::sanitize_iso( (string) ( $field['options'] ?? '' ) );
		$split   = self::split( (string) $def, $iso );
		$iso     = $split['iso'];
		$country = self::country( $iso );
		$dial    = $country ? $country['dial'] : '';
		$e164    = $split['e164'];
		$national = $split['national'];

		echo '<div class="nest-form-phone" data-nestform-phone data-iso="' . esc_attr( $iso ) . '" data-dial="' . esc_attr( $dial ) . '">';
		echo '<button type="button" class="nest-form-phone__cc" data-nestform-phone-toggle aria-expanded="false" aria-haspopup="listbox">';
		echo self::flag_html( $iso, 'nest-form-phone__flag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="nest-form-phone__dial" data-nestform-phone-dial>+' . esc_html( $dial ) . '</span>';
		echo '</button>';
		printf(
			'<input type="tel" class="input nest-form__input nest-form-phone__national" name="%1$s" id="%2$s" value="%3$s" placeholder="%4$s" autocomplete="tel-national" inputmode="tel"%5$s data-nestform-phone-national />',
			esc_attr( $name . '__national' ),
			esc_attr( $id ),
			esc_attr( $national ),
			esc_attr( $ph ),
			$req ? ' required' : ''
		);
		printf(
			'<input type="hidden" class="nest-form-phone__value" name="%1$s" value="%2$s" data-nestform-phone-value />',
			esc_attr( $name ),
			esc_attr( $e164 )
		);
		printf(
			'<input type="hidden" name="%1$s" value="%2$s" data-nestform-phone-iso />',
			esc_attr( $name . '__iso' ),
			esc_attr( $iso )
		);
		echo '<div class="nest-form-phone__panel" data-nestform-phone-panel hidden>';
		echo '<input type="search" class="nest-form-phone__search" data-nestform-phone-search placeholder="' . esc_attr__( 'Search country', 'nestform' ) . '" autocomplete="off" />';
		echo '<ul class="nest-form-phone__list" role="listbox">';
		$preferred = self::preferred_isos();
		$pref_map  = array_flip( $preferred );
		$pref_rows = array();
		foreach ( $preferred as $pref_iso ) {
			$pref_country = self::country( $pref_iso );
			if ( $pref_country ) {
				$pref_rows[] = $pref_country;
			}
		}
		$rest_rows = array();
		foreach ( self::countries() as $row ) {
			if ( ! isset( $pref_map[ $row['iso'] ] ) ) {
				$rest_rows[] = $row;
			}
		}
		foreach ( $pref_rows as $row ) {
			self::render_country_option( $row, $iso );
		}
		if ( array() !== $pref_rows && array() !== $rest_rows ) {
			echo '<li class="nest-form-phone__sep" aria-hidden="true"></li>';
		}
		foreach ( $rest_rows as $row ) {
			self::render_country_option( $row, $iso );
		}
		echo '</ul></div></div>';
	}

	/**
	 * @param array{iso:string,dial:string,name:string,flag:string} $row Country.
	 * @param string                                                $iso Active ISO.
	 */
	private static function render_country_option( array $row, $iso ) {
		$active = $row['iso'] === $iso ? ' is-active' : '';
		printf(
			'<li><button type="button" class="nest-form-phone__opt%1$s" data-iso="%2$s" data-dial="%3$s" data-flag="%4$s" data-search="%5$s">',
			esc_attr( $active ),
			esc_attr( $row['iso'] ),
			esc_attr( $row['dial'] ),
			esc_url( self::flag_url( $row['iso'] ) ),
			esc_attr( strtolower( $row['iso'] . ' ' . $row['name'] . ' +' . $row['dial'] ) )
		);
		echo self::flag_html( $row['iso'], 'nest-form-phone__opt-flag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="nest-form-phone__opt-name">' . esc_html( $row['name'] ) . '</span>';
		echo '<span class="nest-form-phone__opt-dial">+' . esc_html( $row['dial'] ) . '</span>';
		echo '</button></li>';
	}
}
