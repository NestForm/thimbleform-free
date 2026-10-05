<?php
/**
 * Phone country codes + E.164 helpers.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Phone {

	/**
	 * @return array<int, array{iso:string,dial:string,name:string,flag:string}>
	 */
	public static function countries() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$rows = array(
			array( 'iso' => 'RU', 'dial' => '7', 'name' => __( 'Russia', 'thimbleform' ) ),
			array( 'iso' => 'BY', 'dial' => '375', 'name' => __( 'Belarus', 'thimbleform' ) ),
			array( 'iso' => 'KZ', 'dial' => '7', 'name' => __( 'Kazakhstan', 'thimbleform' ) ),
			array( 'iso' => 'UA', 'dial' => '380', 'name' => __( 'Ukraine', 'thimbleform' ) ),
			array( 'iso' => 'UZ', 'dial' => '998', 'name' => __( 'Uzbekistan', 'thimbleform' ) ),
			array( 'iso' => 'KG', 'dial' => '996', 'name' => __( 'Kyrgyzstan', 'thimbleform' ) ),
			array( 'iso' => 'AM', 'dial' => '374', 'name' => __( 'Armenia', 'thimbleform' ) ),
			array( 'iso' => 'GE', 'dial' => '995', 'name' => __( 'Georgia', 'thimbleform' ) ),
			array( 'iso' => 'AZ', 'dial' => '994', 'name' => __( 'Azerbaijan', 'thimbleform' ) ),
			array( 'iso' => 'MD', 'dial' => '373', 'name' => __( 'Moldova', 'thimbleform' ) ),
			array( 'iso' => 'TJ', 'dial' => '992', 'name' => __( 'Tajikistan', 'thimbleform' ) ),
			array( 'iso' => 'TM', 'dial' => '993', 'name' => __( 'Turkmenistan', 'thimbleform' ) ),
			array( 'iso' => 'US', 'dial' => '1', 'name' => __( 'United States', 'thimbleform' ) ),
			array( 'iso' => 'CA', 'dial' => '1', 'name' => __( 'Canada', 'thimbleform' ) ),
			array( 'iso' => 'GB', 'dial' => '44', 'name' => __( 'United Kingdom', 'thimbleform' ) ),
			array( 'iso' => 'DE', 'dial' => '49', 'name' => __( 'Germany', 'thimbleform' ) ),
			array( 'iso' => 'FR', 'dial' => '33', 'name' => __( 'France', 'thimbleform' ) ),
			array( 'iso' => 'IT', 'dial' => '39', 'name' => __( 'Italy', 'thimbleform' ) ),
			array( 'iso' => 'ES', 'dial' => '34', 'name' => __( 'Spain', 'thimbleform' ) ),
			array( 'iso' => 'PL', 'dial' => '48', 'name' => __( 'Poland', 'thimbleform' ) ),
			array( 'iso' => 'NL', 'dial' => '31', 'name' => __( 'Netherlands', 'thimbleform' ) ),
			array( 'iso' => 'BE', 'dial' => '32', 'name' => __( 'Belgium', 'thimbleform' ) ),
			array( 'iso' => 'AT', 'dial' => '43', 'name' => __( 'Austria', 'thimbleform' ) ),
			array( 'iso' => 'CH', 'dial' => '41', 'name' => __( 'Switzerland', 'thimbleform' ) ),
			array( 'iso' => 'CZ', 'dial' => '420', 'name' => __( 'Czechia', 'thimbleform' ) ),
			array( 'iso' => 'SK', 'dial' => '421', 'name' => __( 'Slovakia', 'thimbleform' ) ),
			array( 'iso' => 'HU', 'dial' => '36', 'name' => __( 'Hungary', 'thimbleform' ) ),
			array( 'iso' => 'RO', 'dial' => '40', 'name' => __( 'Romania', 'thimbleform' ) ),
			array( 'iso' => 'BG', 'dial' => '359', 'name' => __( 'Bulgaria', 'thimbleform' ) ),
			array( 'iso' => 'GR', 'dial' => '30', 'name' => __( 'Greece', 'thimbleform' ) ),
			array( 'iso' => 'PT', 'dial' => '351', 'name' => __( 'Portugal', 'thimbleform' ) ),
			array( 'iso' => 'IE', 'dial' => '353', 'name' => __( 'Ireland', 'thimbleform' ) ),
			array( 'iso' => 'SE', 'dial' => '46', 'name' => __( 'Sweden', 'thimbleform' ) ),
			array( 'iso' => 'NO', 'dial' => '47', 'name' => __( 'Norway', 'thimbleform' ) ),
			array( 'iso' => 'FI', 'dial' => '358', 'name' => __( 'Finland', 'thimbleform' ) ),
			array( 'iso' => 'DK', 'dial' => '45', 'name' => __( 'Denmark', 'thimbleform' ) ),
			array( 'iso' => 'EE', 'dial' => '372', 'name' => __( 'Estonia', 'thimbleform' ) ),
			array( 'iso' => 'LV', 'dial' => '371', 'name' => __( 'Latvia', 'thimbleform' ) ),
			array( 'iso' => 'LT', 'dial' => '370', 'name' => __( 'Lithuania', 'thimbleform' ) ),
			array( 'iso' => 'IL', 'dial' => '972', 'name' => __( 'Israel', 'thimbleform' ) ),
			array( 'iso' => 'AE', 'dial' => '971', 'name' => __( 'United Arab Emirates', 'thimbleform' ) ),
			array( 'iso' => 'SA', 'dial' => '966', 'name' => __( 'Saudi Arabia', 'thimbleform' ) ),
			array( 'iso' => 'TR', 'dial' => '90', 'name' => __( 'Turkey', 'thimbleform' ) ),
			array( 'iso' => 'IN', 'dial' => '91', 'name' => __( 'India', 'thimbleform' ) ),
			array( 'iso' => 'CN', 'dial' => '86', 'name' => __( 'China', 'thimbleform' ) ),
			array( 'iso' => 'JP', 'dial' => '81', 'name' => __( 'Japan', 'thimbleform' ) ),
			array( 'iso' => 'KR', 'dial' => '82', 'name' => __( 'South Korea', 'thimbleform' ) ),
			array( 'iso' => 'AU', 'dial' => '61', 'name' => __( 'Australia', 'thimbleform' ) ),
			array( 'iso' => 'NZ', 'dial' => '64', 'name' => __( 'New Zealand', 'thimbleform' ) ),
			array( 'iso' => 'BR', 'dial' => '55', 'name' => __( 'Brazil', 'thimbleform' ) ),
			array( 'iso' => 'MX', 'dial' => '52', 'name' => __( 'Mexico', 'thimbleform' ) ),
			array( 'iso' => 'AR', 'dial' => '54', 'name' => __( 'Argentina', 'thimbleform' ) ),
			array( 'iso' => 'ZA', 'dial' => '27', 'name' => __( 'South Africa', 'thimbleform' ) ),
			array( 'iso' => 'EG', 'dial' => '20', 'name' => __( 'Egypt', 'thimbleform' ) ),
			array( 'iso' => 'TH', 'dial' => '66', 'name' => __( 'Thailand', 'thimbleform' ) ),
			array( 'iso' => 'VN', 'dial' => '84', 'name' => __( 'Vietnam', 'thimbleform' ) ),
			array( 'iso' => 'ID', 'dial' => '62', 'name' => __( 'Indonesia', 'thimbleform' ) ),
			array( 'iso' => 'MY', 'dial' => '60', 'name' => __( 'Malaysia', 'thimbleform' ) ),
			array( 'iso' => 'SG', 'dial' => '65', 'name' => __( 'Singapore', 'thimbleform' ) ),
			array( 'iso' => 'PH', 'dial' => '63', 'name' => __( 'Philippines', 'thimbleform' ) ),
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
		$cached = array_values( (array) apply_filters( 'thimbleform_phone_countries', $out ) );
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
		$path = THIMBLEFORM_PATH . $rel;
		$url  = is_readable( $path ) ? THIMBLEFORM_URL . $rel : '';

		/**
		 * Filter phone flag image URL.
		 *
		 * @param string $url Flag URL (plugin-local SVG, or empty).
		 * @param string $iso Lowercase ISO.
		 */
		return (string) apply_filters( 'thimbleform_phone_flag_url', $url, $iso );
	}

	/**
	 * @param string $iso   ISO.
	 * @param string $class Class on wrapper.
	 * @return string
	 */
	public static function flag_html( $iso, $class = 'thimbleform-phone__flag' ) {
		$url = self::flag_url( $iso );
		if ( '' === $url ) {
			return '';
		}
		$attr = 'thimbleform-phone__flag' === $class ? ' data-thimbleform-phone-flag' : '';
		return sprintf(
			'<span class="%1$s"%2$s><img class="thimbleform-phone__flag-img" src="%3$s" alt="" width="20" height="15" loading="lazy" decoding="async" /></span>',
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

		echo '<div class="thimbleform-phone" data-thimbleform-phone data-iso="' . esc_attr( $iso ) . '" data-dial="' . esc_attr( $dial ) . '">';
		echo '<button type="button" class="thimbleform-phone__cc" data-thimbleform-phone-toggle aria-expanded="false" aria-haspopup="listbox">';
		echo wp_kses( self::flag_html( $iso, 'thimbleform-phone__flag' ), thimbleform_form_allowed_html() );
		echo '<span class="thimbleform-phone__dial" data-thimbleform-phone-dial>+' . esc_html( $dial ) . '</span>';
		echo '</button>';
		printf(
			'<input type="tel" class="input thimbleform__input thimbleform-phone__national" name="%1$s" id="%2$s" value="%3$s" placeholder="%4$s" autocomplete="tel-national" inputmode="tel"%5$s data-thimbleform-phone-national />',
			esc_attr( $name . '__national' ),
			esc_attr( $id ),
			esc_attr( $national ),
			esc_attr( $ph ),
			$req ? ' required' : ''
		);
		printf(
			'<input type="hidden" class="thimbleform-phone__value" name="%1$s" value="%2$s" data-thimbleform-phone-value />',
			esc_attr( $name ),
			esc_attr( $e164 )
		);
		printf(
			'<input type="hidden" name="%1$s" value="%2$s" data-thimbleform-phone-iso />',
			esc_attr( $name . '__iso' ),
			esc_attr( $iso )
		);
		echo '<div class="thimbleform-phone__panel" data-thimbleform-phone-panel hidden>';
		echo '<input type="search" class="thimbleform-phone__search" data-thimbleform-phone-search placeholder="' . esc_attr__( 'Search country', 'thimbleform' ) . '" autocomplete="off" />';
		echo '<ul class="thimbleform-phone__list" role="listbox">';
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
			echo '<li class="thimbleform-phone__sep" aria-hidden="true"></li>';
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
			'<li><button type="button" class="thimbleform-phone__opt%1$s" data-iso="%2$s" data-dial="%3$s" data-flag="%4$s" data-search="%5$s">',
			esc_attr( $active ),
			esc_attr( $row['iso'] ),
			esc_attr( $row['dial'] ),
			esc_url( self::flag_url( $row['iso'] ) ),
			esc_attr( strtolower( $row['iso'] . ' ' . $row['name'] . ' +' . $row['dial'] ) )
		);
		echo wp_kses( self::flag_html( $row['iso'], 'thimbleform-phone__opt-flag' ), thimbleform_form_allowed_html() );
		echo '<span class="thimbleform-phone__opt-name">' . esc_html( $row['name'] ) . '</span>';
		echo '<span class="thimbleform-phone__opt-dial">+' . esc_html( $row['dial'] ) . '</span>';
		echo '</button></li>';
	}
}
