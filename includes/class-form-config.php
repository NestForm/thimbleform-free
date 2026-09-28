<?php
/**
 * Form config helpers (defaults, get/save meta).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Form_Config {

	const META_FIELDS   = '_thimbleform_fields';
	const META_MESSAGES = '_thimbleform_messages';
	const META_MAIL     = '_thimbleform_mail';
	const META_SETTINGS = '_thimbleform_settings';

	/** Posted / stored value for the "Other" choice option. */
	const OTHER_VALUE = '__other';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_fields() {
		return array(
			array(
				'type'        => 'text',
				'name'        => 'name',
				'label'       => 'Name',
				'placeholder' => 'Your name',
				'description' => '',
				'default'     => '',
				'css_class'   => '',
				'required'    => true,
				'width'       => 'half',
				'options'     => '',
				'step'        => 1,
			),
			array(
				'type'        => 'email',
				'name'        => 'email',
				'label'       => 'Email',
				'placeholder' => 'you@company.com',
				'description' => '',
				'default'     => '',
				'css_class'   => '',
				'required'    => true,
				'width'       => 'half',
				'options'     => '',
				'step'        => 1,
			),
			array(
				'type'        => 'textarea',
				'name'        => 'message',
				'label'       => 'Message',
				'placeholder' => 'A few lines about the project…',
				'description' => '',
				'default'     => '',
				'css_class'   => '',
				'required'    => true,
				'width'       => 'full',
				'options'     => '',
				'step'        => 1,
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function default_messages() {
		return array(
			'success'         => 'Thank you. Your message has been sent.',
			'error_generic'   => 'Something went wrong. Please try again.',
			'required'        => 'This field is required.',
			'invalid_email'   => 'Please enter a valid email address.',
			'invalid_tel'     => 'Please enter a valid phone number.',
			'invalid_url'     => 'Please enter a valid URL.',
			'invalid_number'  => 'Please enter a valid number.',
			'invalid_date'    => 'Please enter a valid date.',
			'invalid_time'    => 'Please enter a valid time.',
			'rate_limited'    => 'Too many submissions. Please wait a minute.',
			'invalid_captcha' => 'Please confirm you are not a robot.',
			'invalid_file'    => 'Please upload a valid file.',
			'file_too_large'  => 'File is too large.',
			'too_many_files'  => 'Too many files selected.',
		);
	}

	/**
	 * @param string $locale en|ru.
	 * @return array<string, string>
	 */
	public static function message_pack( $locale = 'en' ) {
		if ( 'ru' === $locale ) {
			return array(
				'success'         => 'Спасибо. Сообщение отправлено.',
				'error_generic'   => 'Что-то пошло не так. Попробуйте ещё раз.',
				'required'        => 'Это поле обязательно.',
				'invalid_email'   => 'Введите корректный email.',
				'invalid_tel'     => 'Введите корректный телефон.',
				'invalid_url'     => 'Введите корректный URL.',
				'invalid_number'  => 'Введите корректное число.',
				'invalid_date'    => 'Введите корректную дату.',
				'invalid_time'    => 'Введите корректное время.',
				'rate_limited'    => 'Слишком много отправок. Подождите минуту.',
				'invalid_captcha' => 'Подтвердите, что вы не робот.',
				'invalid_file'    => 'Загрузите допустимый файл.',
				'file_too_large'  => 'Файл слишком большой.',
				'too_many_files'  => 'Выбрано слишком много файлов.',
			);
		}
		return self::default_messages();
	}

	/**
	 * @return array<string, string>
	 */
	public static function default_mail() {
		$admin = get_option( 'admin_email' );
		return array(
			'to'                      => is_string( $admin ) ? $admin : '',
			'cc'                      => '',
			'bcc'                     => '',
			'subject'                 => 'New form submission: {form_title}',
			'from_name'               => class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_name() : get_bloginfo( 'name' ),
			'reply_to_field'          => 'email',
			'body_template'           => "New submission from {form_title}\n\n{all_fields}\n",
			'html_enabled'            => '0',
			'logo_id'                 => '0',
			'pdf_attach'              => '0',
			'user_mail_enabled'       => '0',
			'user_mail_subject'       => 'We received your message',
			'user_mail_body'          => "Hi {name},\n\nThanks for contacting us. We will get back to you soon.\n\n— {form_title}\n",
			'extra_enabled'           => '0',
			'extra_to'                => '',
			'extra_subject'           => 'Extra notification: {form_title}',
			'extra_body'              => "Extra notification from {form_title}\n\n{all_fields}\n",
			'extra_condition_field'   => '',
			'extra_condition_op'      => 'equals',
			'extra_condition_value'   => '',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function default_settings() {
		return array(
			'submit_label'       => 'Send message',
			'redirect_url'       => '',
			'success_display'    => 'inline',
			'enable_captcha'     => '0',
			'enable_stripe'      => '0',
			'enable_hubspot'     => '0',
			'hubspot_map'        => self::default_hubspot_map(),
			'enable_steps'       => '0',
			'step_labels'        => "About you\nYour project\nDetails",
			'next_label'         => 'Continue',
			'prev_label'         => 'Back',
			'branch_rules'       => '',
			'webhook_enabled'    => '0',
			'webhook_url'        => '',
			'webhook_secret'     => '',
			'webhook_endpoints'  => array(),
			'time_trap_seconds'  => '3',
			'enable_akismet'     => '0',
			'store_ip'           => '1',
			'form_mode'          => 'form',
			'quiz_show_score'    => '1',
			'quiz_show_answers'  => '0',
			'quiz_timer_seconds' => '0',
			'quiz_max_attempts'  => '0',
			'quiz_attempt_by'    => 'ip',
			'quiz_attempt_field' => 'email',
			'quiz_results'       => '',
			'partial_save'       => '0',
			'partial_ttl_days'   => '7',
			'share_results'      => '0',
			'quiz_cta_label'     => '',
			'automation_enabled'    => '0',
			'automation_match'      => 'all',
			'automation_rules'      => array(),
			'automation_field'      => '',
			'automation_op'         => 'equals',
			'automation_value'      => '',
			'automation_then_status'  => '',
			'automation_then_email'   => '',
			'automation_then_webhook' => '',
			'automation_skip_spam'    => '1',
			'style_skin'         => 'theme',
			'style_accent'       => '',
			'style_accent_text'  => '',
			'style_text'         => '',
			'style_muted'        => '',
			'style_surface'      => '',
			'style_input_bg'     => '',
			'style_border'       => '',
			'style_radius'       => 'md',
			'style_button'       => 'solid',
			'style_font_size'    => 'md',
			'style_gap'          => 'md',
			'style_density'      => 'md',
			'style_custom_css'   => '',
		);
	}

	/**
	 * Front appearance skins.
	 *
	 * @return array<string, string>
	 */
	public static function style_skins() {
		return array(
			'theme'   => __( 'Theme (default)', 'thimbleform' ),
			'classic' => __( 'Classic', 'thimbleform' ),
			'minimal' => __( 'Minimal underline', 'thimbleform' ),
			'soft'    => __( 'Soft filled', 'thimbleform' ),
			'card'    => __( 'Card', 'thimbleform' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function style_radius_options() {
		return array(
			'sm'   => __( 'Small', 'thimbleform' ),
			'md'   => __( 'Medium', 'thimbleform' ),
			'lg'   => __( 'Large', 'thimbleform' ),
			'pill' => __( 'Pill', 'thimbleform' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function style_button_options() {
		return array(
			'solid'   => __( 'Solid', 'thimbleform' ),
			'outline' => __( 'Outline', 'thimbleform' ),
			'soft'    => __( 'Soft', 'thimbleform' ),
		);
	}

	/**
	 * Base typography scale for the form.
	 *
	 * @return array<string, string>
	 */
	public static function style_font_size_options() {
		return array(
			'sm' => __( 'Small', 'thimbleform' ),
			'md' => __( 'Default', 'thimbleform' ),
			'lg' => __( 'Large', 'thimbleform' ),
			'xl' => __( 'Extra large', 'thimbleform' ),
		);
	}

	/**
	 * Vertical spacing between fields / steps.
	 *
	 * @return array<string, string>
	 */
	public static function style_gap_options() {
		return array(
			'sm' => __( 'Tight', 'thimbleform' ),
			'md' => __( 'Default', 'thimbleform' ),
			'lg' => __( 'Relaxed', 'thimbleform' ),
		);
	}

	/**
	 * Input / control padding density.
	 *
	 * @return array<string, string>
	 */
	public static function style_density_options() {
		return array(
			'sm' => __( 'Compact', 'thimbleform' ),
			'md' => __( 'Default', 'thimbleform' ),
			'lg' => __( 'Comfortable', 'thimbleform' ),
		);
	}

	/**
	 * @param string $hex Color.
	 * @return string Empty or #rrggbb.
	 */
	public static function sanitize_hex_color( $hex ) {
		$hex = trim( (string) $hex );
		if ( $hex === '' ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex ) ) {
			if ( 4 === strlen( $hex ) ) {
				return '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
			}
			return strtolower( $hex );
		}
		return '';
	}

	/**
	 * Merge + sanitize appearance keys from raw settings array.
	 *
	 * @param array<string, mixed> $raw      Raw settings.
	 * @param array<string, string> $settings Base settings.
	 * @return array<string, string>
	 */
	public static function apply_style_settings( array $raw, array $settings ) {
		$skins = self::style_skins();
		$skin  = isset( $raw['style_skin'] ) ? sanitize_key( (string) $raw['style_skin'] ) : 'theme';
		$settings['style_skin'] = isset( $skins[ $skin ] ) ? $skin : 'theme';

		$color_keys = array(
			'style_accent',
			'style_accent_text',
			'style_text',
			'style_muted',
			'style_surface',
			'style_input_bg',
			'style_border',
		);
		foreach ( $color_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$settings[ $key ] = self::sanitize_hex_color( (string) $raw[ $key ] );
			}
		}

		$radii = self::style_radius_options();
		$radius = isset( $raw['style_radius'] ) ? sanitize_key( (string) $raw['style_radius'] ) : 'md';
		$settings['style_radius'] = isset( $radii[ $radius ] ) ? $radius : 'md';

		$buttons = self::style_button_options();
		$button  = isset( $raw['style_button'] ) ? sanitize_key( (string) $raw['style_button'] ) : 'solid';
		$settings['style_button'] = isset( $buttons[ $button ] ) ? $button : 'solid';

		$fonts = self::style_font_size_options();
		$font  = isset( $raw['style_font_size'] ) ? sanitize_key( (string) $raw['style_font_size'] ) : 'md';
		$settings['style_font_size'] = isset( $fonts[ $font ] ) ? $font : 'md';

		$gaps = self::style_gap_options();
		$gap  = isset( $raw['style_gap'] ) ? sanitize_key( (string) $raw['style_gap'] ) : 'md';
		$settings['style_gap'] = isset( $gaps[ $gap ] ) ? $gap : 'md';

		$densities = self::style_density_options();
		$density   = isset( $raw['style_density'] ) ? sanitize_key( (string) $raw['style_density'] ) : 'md';
		$settings['style_density'] = isset( $densities[ $density ] ) ? $density : 'md';

		$settings['style_custom_css'] = '';

		return $settings;
	}

	/**
	 * Example custom CSS shown in the Appearance editor.
	 *
	 * @return string
	 */
	public static function style_custom_css_example() {
		return trim(
			'
/* Hooks (auto-scoped to this form) */
.thimbleform__label { letter-spacing: 0.02em; }
.thimbleform__input { font-size: 1rem; }
.thimbleform__submit { text-transform: uppercase; }
.thimbleform__field--email .thimbleform__input { /* email field */ }
.thimbleform__progress-fill { /* steps bar */ }
.thimbleform-select__trigger { /* custom select */ }
'
		);
	}

	/**
	 * Strip dangerous constructs from custom CSS.
	 *
	 * @param string $css Raw CSS.
	 * @return string
	 */
	public static function sanitize_custom_css( $css ) {
		$css = (string) $css;
		$css = str_replace( array( "\0", "\r" ), '', $css );
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '/<\/?style\b[^>]*>/i', '', $css );
		$css = preg_replace( '/@import\b[^;]*;/i', '', $css );
		$css = preg_replace( '/expression\s*\(/i', '', $css );
		$css = preg_replace( '/javascript\s*:/i', '', $css );
		$css = preg_replace( '/-moz-binding\s*:/i', '', $css );
		$css = preg_replace( '/behavior\s*:/i', '', $css );
		if ( strlen( $css ) > 20000 ) {
			$css = substr( $css, 0, 20000 );
		}
		return trim( $css );
	}

	/**
	 * Prefix selectors with a form scope so rules do not leak.
	 *
	 * @param string $css   Custom CSS.
	 * @param string $scope Selector like #thimbleform-12-1.
	 * @return string
	 */
	public static function scope_custom_css( $css, $scope ) {
		$css   = self::sanitize_custom_css( $css );
		$css   = str_replace( 'nest-form', 'thimbleform', $css );
		$scope = trim( (string) $scope );
		if ( $css === '' || $scope === '' ) {
			return '';
		}

		$scoped      = '';
		$length      = strlen( $css );
		$buffer      = '';
		$depth       = 0;
		$in_str      = '';
		$in_comment  = false;
		$at_block    = false;
		$skip_prefix = false;

		for ( $i = 0; $i < $length; $i++ ) {
			$ch   = $css[ $i ];
			$next = ( $i + 1 < $length ) ? $css[ $i + 1 ] : '';

			if ( $in_comment ) {
				$buffer .= $ch;
				if ( '*' === $ch && '/' === $next ) {
					$buffer .= $next;
					$i++;
					$in_comment = false;
				}
				continue;
			}

			if ( '' === $in_str && '/' === $ch && '*' === $next ) {
				$buffer    .= $ch . $next;
				$i++;
				$in_comment = true;
				continue;
			}

			if ( '"' === $ch || "'" === $ch ) {
				if ( '' === $in_str ) {
					$in_str = $ch;
				} elseif ( $in_str === $ch ) {
					$in_str = '';
				}
				$buffer .= $ch;
				continue;
			}

			if ( '' !== $in_str ) {
				$buffer .= $ch;
				continue;
			}

			if ( '{' === $ch ) {
				$chunk = trim( $buffer );
				$buffer = '';
				if ( 0 === $depth ) {
					$at_block    = ( 0 === stripos( $chunk, '@' ) );
					$skip_prefix = (bool) preg_match( '/^@(?:-webkit-)?keyframes\b|^@font-face\b/i', $chunk );
					if ( $at_block ) {
						$scoped .= $chunk . '{';
					} else {
						$scoped .= self::prefix_selector_list( $chunk, $scope ) . '{';
					}
				} elseif ( $at_block && ! $skip_prefix && 1 === $depth ) {
					$scoped .= self::prefix_selector_list( $chunk, $scope ) . '{';
				} else {
					$scoped .= $chunk . '{';
				}
				$depth++;
				continue;
			}

			if ( '}' === $ch ) {
				$scoped .= $buffer . '}';
				$buffer  = '';
				$depth   = max( 0, $depth - 1 );
				if ( 0 === $depth ) {
					$at_block    = false;
					$skip_prefix = false;
				}
				continue;
			}

			$buffer .= $ch;
		}

		$scoped .= $buffer;

		/**
		 * Filter scoped custom CSS for a form instance.
		 *
		 * @param string $scoped Scoped CSS.
		 * @param string $css    Original sanitized CSS.
		 * @param string $scope  Scope selector.
		 */
		return (string) apply_filters( 'thimbleform_scoped_custom_css', $scoped, $css, $scope );
	}

	/**
	 * @param string $selectors Comma-separated selectors.
	 * @param string $scope     Scope.
	 * @return string
	 */
	private static function prefix_selector_list( $selectors, $scope ) {
		$parts  = array_map( 'trim', explode( ',', $selectors ) );
		$fixed  = array();
		foreach ( $parts as $part ) {
			if ( $part === '' ) {
				continue;
			}
			if ( ':root' === $part || 'html' === $part || 'body' === $part ) {
				$fixed[] = $scope;
				continue;
			}
			if ( 0 === strpos( $part, $scope ) ) {
				$fixed[] = $part;
				continue;
			}
			if ( '.thimbleform' === $part ) {
				$fixed[] = $scope;
				continue;
			}
			if ( preg_match( '/^\.thimbleform([\.\[:#\s].*)$/', $part, $m ) ) {
				$fixed[] = $scope . $m[1];
				continue;
			}
			$fixed[] = $scope . ' ' . $part;
		}
		return implode( ', ', $fixed );
	}

	/**
	 * CSS custom properties for a form instance.
	 *
	 * @param array<string, string> $settings Settings.
	 * @return string Inline style attribute value (no wrapping).
	 */
	public static function style_inline_css( array $settings ) {
		$map = array(
			'style_accent'      => '--thimbleform-accent',
			'style_accent_text' => '--thimbleform-accent-text',
			'style_text'        => '--thimbleform-text',
			'style_muted'       => '--thimbleform-muted',
			'style_surface'     => '--thimbleform-surface',
			'style_input_bg'    => '--thimbleform-input-bg',
			'style_border'      => '--thimbleform-border',
		);
		$parts = array();
		foreach ( $map as $key => $var ) {
			$val = isset( $settings[ $key ] ) ? self::sanitize_hex_color( (string) $settings[ $key ] ) : '';
			if ( $val !== '' ) {
				$parts[] = $var . ':' . $val;
			}
		}

		$font = isset( $settings['style_font_size'] ) ? sanitize_key( (string) $settings['style_font_size'] ) : 'md';
		$font_map = array(
			'sm' => array(
				'base'  => '0.875rem',
				'label' => '0.8125rem',
				'help'  => '0.75rem',
			),
			'md' => array(
				'base'  => '1rem',
				'label' => '0.875rem',
				'help'  => '0.8125rem',
			),
			'lg' => array(
				'base'  => '1.0625rem',
				'label' => '0.9375rem',
				'help'  => '0.875rem',
			),
			'xl' => array(
				'base'  => '1.125rem',
				'label' => '1rem',
				'help'  => '0.9375rem',
			),
		);
		if ( ! isset( $font_map[ $font ] ) ) {
			$font = 'md';
		}
		$parts[] = '--thimbleform-font-size:' . $font_map[ $font ]['base'];
		$parts[] = '--thimbleform-label-size:' . $font_map[ $font ]['label'];
		$parts[] = '--thimbleform-help-size:' . $font_map[ $font ]['help'];

		$gap = isset( $settings['style_gap'] ) ? sanitize_key( (string) $settings['style_gap'] ) : 'md';
		$gap_map = array(
			'sm' => '0.65rem',
			'md' => '1rem',
			'lg' => '1.35rem',
		);
		if ( ! isset( $gap_map[ $gap ] ) ) {
			$gap = 'md';
		}
		$parts[] = '--thimbleform-gap:' . $gap_map[ $gap ];

		$radius = isset( $settings['style_radius'] ) ? sanitize_key( (string) $settings['style_radius'] ) : 'md';
		$radius_map = array(
			'sm'   => '0.25rem',
			'md'   => '0.5rem',
			'lg'   => '0.875rem',
			'pill' => '999px',
		);
		$skin = isset( $settings['style_skin'] ) ? sanitize_key( (string) $settings['style_skin'] ) : 'theme';
		// Theme skin keeps theme chrome; only inject skin-specific tokens for plugin skins.
		if ( 'theme' !== $skin ) {
			if ( isset( $radius_map[ $radius ] ) ) {
				$parts[] = '--thimbleform-radius:' . $radius_map[ $radius ];
			}

			$density = isset( $settings['style_density'] ) ? sanitize_key( (string) $settings['style_density'] ) : 'md';
			$density_map = array(
				'sm' => array(
					'pad'   => '0.5rem 0.75rem',
					'min_h' => '2.35rem',
					'btn'   => '0.5rem 1rem',
				),
				'md' => array(
					'pad'   => '0.75rem 1rem',
					'min_h' => '2.75rem',
					'btn'   => '0.65rem 1.25rem',
				),
				'lg' => array(
					'pad'   => '0.9rem 1.15rem',
					'min_h' => '3.1rem',
					'btn'   => '0.8rem 1.4rem',
				),
			);
			if ( ! isset( $density_map[ $density ] ) ) {
				$density = 'md';
			}
			$parts[] = '--thimbleform-control-pad:' . $density_map[ $density ]['pad'];
			$parts[] = '--thimbleform-control-min-h:' . $density_map[ $density ]['min_h'];
			$parts[] = '--thimbleform-btn-pad:' . $density_map[ $density ]['btn'];
		}

		/**
		 * Filter inline CSS variables for a form.
		 *
		 * @param array  $parts    "var:value" chunks.
		 * @param array  $settings Settings.
		 */
		$parts = (array) apply_filters( 'thimbleform_style_inline_parts', $parts, $settings );
		return implode( ';', $parts );
	}

	/**
	 * Extra form classes for appearance.
	 *
	 * @param array<string, string> $settings Settings.
	 * @return array<int, string>
	 */
	public static function style_form_classes( array $settings ) {
		$skins = self::style_skins();
		$skin  = isset( $settings['style_skin'] ) ? sanitize_key( (string) $settings['style_skin'] ) : 'theme';
		if ( ! isset( $skins[ $skin ] ) ) {
			$skin = 'theme';
		}
		$classes = array( 'thimbleform--skin-' . $skin );

		// Button / radius modifiers only for plugin skins (avoids mixing with theme chrome).
		if ( 'theme' !== $skin ) {
			$button = isset( $settings['style_button'] ) ? sanitize_key( (string) $settings['style_button'] ) : 'solid';
			if ( ! isset( self::style_button_options()[ $button ] ) ) {
				$button = 'solid';
			}
			$classes[] = 'thimbleform--btn-' . $button;

			$radius = isset( $settings['style_radius'] ) ? sanitize_key( (string) $settings['style_radius'] ) : 'md';
			if ( isset( self::style_radius_options()[ $radius ] ) ) {
				$classes[] = 'thimbleform--radius-' . $radius;
			}
		}

		/**
		 * Filter appearance classes on the form element.
		 *
		 * @param array $classes  Classes.
		 * @param array $settings Settings.
		 */
		return array_values( array_filter( (array) apply_filters( 'thimbleform_style_form_classes', $classes, $settings ) ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function condition_operators() {
		return array(
			'equals'     => __( 'equals', 'thimbleform' ),
			'not_equals' => __( 'does not equal', 'thimbleform' ),
			'empty'      => __( 'is empty', 'thimbleform' ),
			'not_empty'  => __( 'is not empty', 'thimbleform' ),
			'contains'   => __( 'contains', 'thimbleform' ),
		);
	}

	/**
	 * Merge + sanitize automation keys from raw settings into $out.
	 *
	 * @param array $raw Raw settings.
	 * @param array $out Target settings.
	 * @return array
	 */
	public static function merge_automation_settings( array $raw, array $out ) {
		$out['automation_enabled'] = ! empty( $raw['automation_enabled'] ) && '0' !== (string) $raw['automation_enabled'] ? '1' : '0';

		$match = isset( $raw['automation_match'] ) ? sanitize_key( (string) $raw['automation_match'] ) : 'all';
		$out['automation_match'] = in_array( $match, array( 'all', 'any' ), true ) ? $match : 'all';

		$out['automation_skip_spam'] = ! isset( $raw['automation_skip_spam'] ) || ( ! empty( $raw['automation_skip_spam'] ) && '0' !== (string) $raw['automation_skip_spam'] ) ? '1' : '0';

		if ( isset( $raw['automation_field'] ) && is_string( $raw['automation_field'] ) ) {
			$out['automation_field'] = sanitize_key( str_replace( '-', '_', $raw['automation_field'] ) );
		}
		$auto_op = isset( $raw['automation_op'] ) ? sanitize_key( (string) $raw['automation_op'] ) : 'equals';
		$out['automation_op'] = isset( self::condition_operators()[ $auto_op ] ) ? $auto_op : 'equals';
		if ( isset( $raw['automation_value'] ) && is_string( $raw['automation_value'] ) ) {
			$out['automation_value'] = sanitize_text_field( $raw['automation_value'] );
		}

		$rules = array();
		if ( isset( $raw['automation_rules'] ) && is_array( $raw['automation_rules'] ) ) {
			$rules = self::sanitize_automation_rules( $raw['automation_rules'] );
		}
		if ( array() === $rules && ! empty( $out['automation_field'] ) ) {
			$rules = self::sanitize_automation_rules(
				array(
					array(
						'field' => $out['automation_field'],
						'op'    => $out['automation_op'],
						'value' => $out['automation_value'],
					),
				)
			);
		}
		$out['automation_rules'] = $rules;

		if ( array() !== $rules ) {
			$out['automation_field'] = (string) ( $rules[0]['field'] ?? '' );
			$out['automation_op']    = (string) ( $rules[0]['op'] ?? 'equals' );
			$out['automation_value'] = (string) ( $rules[0]['value'] ?? '' );
		} else {
			$out['automation_field'] = '';
			$out['automation_op']    = 'equals';
			$out['automation_value'] = '';
		}

		$then_status = isset( $raw['automation_then_status'] ) ? sanitize_key( (string) $raw['automation_then_status'] ) : '';
		$out['automation_then_status'] = in_array( $then_status, array( '', 'read', 'new', 'spam' ), true ) ? $then_status : '';
		if ( isset( $raw['automation_then_email'] ) && is_string( $raw['automation_then_email'] ) ) {
			$email = sanitize_email( $raw['automation_then_email'] );
			$out['automation_then_email'] = is_email( $email ) ? $email : '';
		} elseif ( isset( $raw['automation_then_email'] ) ) {
			$out['automation_then_email'] = '';
		}
		if ( isset( $raw['automation_then_webhook'] ) && is_string( $raw['automation_then_webhook'] ) ) {
			$candidate = esc_url_raw( $raw['automation_then_webhook'] );
			$out['automation_then_webhook'] = ( $candidate !== '' && self::is_safe_outbound_url( $candidate ) ) ? $candidate : '';
		} elseif ( isset( $raw['automation_then_webhook'] ) ) {
			$out['automation_then_webhook'] = '';
		}

		return $out;
	}

	/**
	 * @param mixed $raw Endpoint rows from POST/meta.
	 * @return array<int, array{url:string,secret:string}>
	 */
	public static function sanitize_webhook_endpoints( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$max = class_exists( 'Thimbleform_Webhook' ) ? Thimbleform_Webhook::ENDPOINT_MAX : 5;
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$url = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
			if ( $url === '' || ! self::is_safe_outbound_url( $url ) ) {
				continue;
			}
			$out[] = array(
				'url'    => $url,
				'secret' => isset( $row['secret'] ) ? sanitize_text_field( (string) $row['secret'] ) : '',
			);
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Resolved webhook endpoints for a form (supports legacy single URL fields).
	 *
	 * @param array<string, mixed> $settings Form settings.
	 * @return array<int, array{url:string,secret:string}>
	 */
	public static function webhook_endpoints_from_settings( array $settings ) {
		$endpoints = array();
		if ( isset( $settings['webhook_endpoints'] ) && is_array( $settings['webhook_endpoints'] ) ) {
			$endpoints = self::sanitize_webhook_endpoints( $settings['webhook_endpoints'] );
		}
		if ( array() !== $endpoints ) {
			return $endpoints;
		}
		$legacy_url = isset( $settings['webhook_url'] ) ? esc_url_raw( (string) $settings['webhook_url'] ) : '';
		if ( $legacy_url !== '' && self::is_safe_outbound_url( $legacy_url ) ) {
			$endpoints[] = array(
				'url'    => $legacy_url,
				'secret' => isset( $settings['webhook_secret'] ) ? sanitize_text_field( (string) $settings['webhook_secret'] ) : '',
			);
		}
		return $endpoints;
	}

	/**
	 * Merge + sanitize webhook keys from raw settings into $out.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 * @param array<string, mixed> $out Target settings.
	 * @return array<string, mixed>
	 */
	public static function merge_webhook_settings( array $raw, array $out ) {
		$out['webhook_enabled'] = ! empty( $raw['webhook_enabled'] ) && '0' !== (string) $raw['webhook_enabled'] ? '1' : '0';

		$endpoints = array();
		if ( isset( $raw['webhook_endpoints'] ) && is_array( $raw['webhook_endpoints'] ) ) {
			$endpoints = self::sanitize_webhook_endpoints( $raw['webhook_endpoints'] );
		}
		if ( array() === $endpoints && isset( $raw['webhook_url'] ) && is_string( $raw['webhook_url'] ) ) {
			$endpoints = self::sanitize_webhook_endpoints(
				array(
					array(
						'url'    => $raw['webhook_url'],
						'secret' => isset( $raw['webhook_secret'] ) ? $raw['webhook_secret'] : '',
					),
				)
			);
		}

		$out['webhook_endpoints'] = $endpoints;
		$first                    = isset( $endpoints[0] ) ? $endpoints[0] : array(
			'url'    => '',
			'secret' => '',
		);
		$out['webhook_url']       = (string) ( $first['url'] ?? '' );
		$out['webhook_secret']    = (string) ( $first['secret'] ?? '' );

		return $out;
	}

	/**
	 * @param mixed $rules Raw rules.
	 * @return array<int, array{field:string,op:string,value:string}>
	 */
	public static function sanitize_automation_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}
		$ops = self::condition_operators();
		$out = array();
		foreach ( $rules as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$field = isset( $row['field'] ) ? sanitize_key( str_replace( '-', '_', (string) $row['field'] ) ) : '';
			if ( $field === '' ) {
				continue;
			}
			$op = isset( $row['op'] ) ? sanitize_key( (string) $row['op'] ) : 'equals';
			if ( ! isset( $ops[ $op ] ) ) {
				$op = 'equals';
			}
			$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';
			if ( in_array( $op, array( 'empty', 'not_empty' ), true ) ) {
				$value = '';
			}
			$out[] = array(
				'field' => $field,
				'op'    => $op,
				'value' => $value,
			);
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Normalized IF rules from settings (legacy-aware).
	 *
	 * @param array $settings Settings.
	 * @return array<int, array{field:string,op:string,value:string}>
	 */
	public static function get_automation_rules( array $settings ) {
		$rules = isset( $settings['automation_rules'] ) && is_array( $settings['automation_rules'] )
			? self::sanitize_automation_rules( $settings['automation_rules'] )
			: array();
		if ( array() === $rules && ! empty( $settings['automation_field'] ) ) {
			$rules = self::sanitize_automation_rules(
				array(
					array(
						'field' => $settings['automation_field'],
						'op'    => $settings['automation_op'] ?? 'equals',
						'value' => $settings['automation_value'] ?? '',
					),
				)
			);
		}
		return $rules;
	}

	/**
	 * Parse quiz result bands from pipe textarea storage.
	 *
	 * @param string $raw quiz_results string.
	 * @return array<int, array{min:string,max:string,title:string,message:string,redirect:string}>
	 */
	public static function parse_quiz_bands( $raw ) {
		$bands = array();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		if ( ! is_array( $lines ) ) {
			return $bands;
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' || '#' === $line[0] ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 3 ) {
				continue;
			}
			$bands[] = array(
				'min'      => is_numeric( $parts[0] ) ? (string) ( 0 + $parts[0] ) : '0',
				'max'      => is_numeric( $parts[1] ) ? (string) ( 0 + $parts[1] ) : '100',
				'title'    => sanitize_text_field( $parts[2] ),
				'message'  => isset( $parts[3] ) ? sanitize_text_field( $parts[3] ) : '',
				'redirect' => isset( $parts[4] )
					? ( class_exists( 'Thimbleform_Security' )
						? Thimbleform_Security::sanitize_redirect_template( $parts[4] )
						: esc_url_raw( $parts[4] ) )
					: '',
			);
		}
		return $bands;
	}

	/**
	 * Serialize band rows back to pipe textarea storage.
	 *
	 * @param mixed $bands Raw band rows.
	 * @return string
	 */
	public static function serialize_quiz_bands( $bands ) {
		if ( ! is_array( $bands ) ) {
			return '';
		}
		$lines = array();
		foreach ( $bands as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
			if ( $title === '' ) {
				continue;
			}
			$min      = isset( $row['min'] ) && is_numeric( $row['min'] ) ? (string) ( 0 + $row['min'] ) : '0';
			$max      = isset( $row['max'] ) && is_numeric( $row['max'] ) ? (string) ( 0 + $row['max'] ) : '100';
			$message  = isset( $row['message'] ) ? sanitize_text_field( (string) $row['message'] ) : '';
			$redirect = isset( $row['redirect'] )
				? ( class_exists( 'Thimbleform_Security' )
					? Thimbleform_Security::sanitize_redirect_template( (string) $row['redirect'] )
					: esc_url_raw( (string) $row['redirect'] ) )
				: '';
			$line     = $min . '|' . $max . '|' . $title;
			if ( $message !== '' || $redirect !== '' ) {
				$line .= '|' . $message;
			}
			if ( $redirect !== '' ) {
				$line .= '|' . $redirect;
			}
			$lines[] = $line;
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param int $form_id Form post ID.
	 * @return array{fields: array, messages: array, mail: array, settings: array}
	 */
	public static function get( $form_id ) {
		$form_id = (int) $form_id;
		return array(
			'fields'   => self::get_fields( $form_id ),
			'messages' => self::get_messages( $form_id ),
			'mail'     => self::get_mail( $form_id ),
			'settings' => self::get_settings( $form_id ),
		);
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fields( $form_id ) {
		$form_id = (int) $form_id;
		$raw     = get_post_meta( $form_id, self::META_FIELDS, true );
		if ( ! is_array( $raw ) ) {
			// Auto-draft has never been saved: keep the canvas empty so templates can open.
			if ( 'auto-draft' === get_post_status( $form_id ) ) {
				return array();
			}
			return self::default_fields();
		}
		if ( array() === $raw ) {
			return array();
		}
		return array_values( array_filter( array_map( array( __CLASS__, 'sanitize_field_row' ), $raw ) ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function get_messages( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META_MESSAGES, true );
		$out = self::default_messages();
		if ( is_array( $raw ) ) {
			foreach ( $out as $key => $default ) {
				if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && $raw[ $key ] !== '' ) {
					$out[ $key ] = ( 'success' === $key )
						? sanitize_textarea_field( $raw[ $key ] )
						: sanitize_text_field( $raw[ $key ] );
				}
			}
		}
		if ( class_exists( 'Thimbleform_Settings' ) ) {
			$plugin_success = Thimbleform_Settings::default_success_message();
			if ( $plugin_success !== '' && ( ! is_array( $raw ) || empty( $raw['success'] ) ) ) {
				$out['success'] = $plugin_success;
			}
		}
		return $out;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function get_mail( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META_MAIL, true );
		$out = self::default_mail();
		$can_html = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::EMAIL_DESIGNER );
		if ( is_array( $raw ) ) {
			foreach ( $out as $key => $default ) {
				if ( ! isset( $raw[ $key ] ) || ! is_string( $raw[ $key ] ) ) {
					continue;
				}
				if ( in_array( $key, array( 'body_template', 'user_mail_body', 'extra_body' ), true ) ) {
					$out[ $key ] = class_exists( 'Thimbleform_Mail_Html' )
						? Thimbleform_Mail_Html::sanitize_body( $raw[ $key ], $can_html && '1' === (string) ( $raw['html_enabled'] ?? $out['html_enabled'] ) )
						: sanitize_textarea_field( $raw[ $key ] );
				} elseif ( in_array( $key, array( 'user_mail_enabled', 'extra_enabled', 'html_enabled', 'pdf_attach' ), true ) ) {
					$out[ $key ] = ! empty( $raw[ $key ] ) && '0' !== $raw[ $key ] ? '1' : '0';
				} elseif ( 'logo_id' === $key ) {
					$out[ $key ] = (string) max( 0, (int) $raw[ $key ] );
				} else {
					$out[ $key ] = sanitize_text_field( $raw[ $key ] );
				}
			}
			if ( ! isset( self::condition_operators()[ $out['extra_condition_op'] ] ) ) {
				$out['extra_condition_op'] = 'equals';
			}
		}
		if ( ! $can_html ) {
			$out['html_enabled'] = '0';
		}
		if ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::PDF_EXPORT ) ) {
			$out['pdf_attach'] = '0';
		}
		return $out;
	}

	/**
	 * Raw settings meta (unsanitized defaults merge for Pro-preserve on save).
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function get_settings_raw( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META_SETTINGS, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Disable Pro-only settings at runtime when capability is missing.
	 *
	 * @param array<string, string> $settings Settings.
	 * @return array<string, string>
	 */
	public static function apply_feature_gates( array $settings ) {
		if ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::QUIZ_SURVEY ) ) {
			$settings['form_mode']          = 'form';
			$settings['quiz_show_score']    = '0';
			$settings['quiz_show_answers']  = '0';
			$settings['quiz_timer_seconds'] = '0';
			$settings['quiz_max_attempts']  = '0';
			$settings['quiz_results']       = '';
			$settings['partial_save']       = '0';
			$settings['share_results']      = '0';
			$settings['quiz_cta_label']     = '';
		}
		if ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::AUTOMATIONS ) ) {
			$settings['automation_enabled']      = '0';
			$settings['automation_match']        = 'all';
			$settings['automation_rules']        = array();
			$settings['automation_field']        = '';
			$settings['automation_op']           = 'equals';
			$settings['automation_value']        = '';
			$settings['automation_then_status']  = '';
			$settings['automation_then_email']   = '';
			$settings['automation_then_webhook'] = '';
			$settings['automation_skip_spam']    = '1';
		}
		if ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::PAYMENTS ) ) {
			$settings['enable_stripe'] = '0';
		}
		if ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::HUBSPOT ) ) {
			$settings['enable_hubspot'] = '0';
			$settings['hubspot_map']    = self::sanitize_hubspot_map( array() );
		}
		return $settings;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	public static function get_settings( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META_SETTINGS, true );
		$out = self::default_settings();
		if ( is_array( $raw ) ) {
			if ( ! empty( $raw['submit_label'] ) && is_string( $raw['submit_label'] ) ) {
				$out['submit_label'] = sanitize_text_field( $raw['submit_label'] );
			}
			if ( isset( $raw['redirect_url'] ) && is_string( $raw['redirect_url'] ) ) {
				$out['redirect_url'] = class_exists( 'Thimbleform_Security' )
					? Thimbleform_Security::sanitize_redirect_template( $raw['redirect_url'] )
					: esc_url_raw( $raw['redirect_url'] );
			}
			$display = isset( $raw['success_display'] ) ? sanitize_key( (string) $raw['success_display'] ) : 'inline';
			$out['success_display'] = in_array( $display, array( 'inline', 'replace', 'popup' ), true ) ? $display : 'inline';
			$out['enable_captcha'] = ! empty( $raw['enable_captcha'] ) && '0' !== (string) $raw['enable_captcha'] ? '1' : '0';
			$out['enable_stripe']  = ! empty( $raw['enable_stripe'] ) && '0' !== (string) $raw['enable_stripe'] ? '1' : '0';
			$out['enable_hubspot'] = ! empty( $raw['enable_hubspot'] ) && '0' !== (string) $raw['enable_hubspot'] ? '1' : '0';
			$out['hubspot_map']    = self::sanitize_hubspot_map( isset( $raw['hubspot_map'] ) ? $raw['hubspot_map'] : array() );
			$out['enable_steps']   = ! empty( $raw['enable_steps'] ) && '0' !== (string) $raw['enable_steps'] ? '1' : '0';
			if ( isset( $raw['step_labels'] ) && is_string( $raw['step_labels'] ) ) {
				$out['step_labels'] = sanitize_textarea_field( $raw['step_labels'] );
			}
			if ( ! empty( $raw['next_label'] ) && is_string( $raw['next_label'] ) ) {
				$out['next_label'] = sanitize_text_field( $raw['next_label'] );
			}
			if ( ! empty( $raw['prev_label'] ) && is_string( $raw['prev_label'] ) ) {
				$out['prev_label'] = sanitize_text_field( $raw['prev_label'] );
			}
			if ( isset( $raw['branch_rules'] ) && is_string( $raw['branch_rules'] ) ) {
				$out['branch_rules'] = sanitize_textarea_field( $raw['branch_rules'] );
			}
			$out['webhook_enabled'] = ! empty( $raw['webhook_enabled'] ) && '0' !== (string) $raw['webhook_enabled'] ? '1' : '0';
			$out                    = self::merge_webhook_settings( $raw, $out );
			if ( isset( $raw['time_trap_seconds'] ) ) {
				$out['time_trap_seconds'] = (string) max( 0, min( 60, (int) $raw['time_trap_seconds'] ) );
			}
			$out['enable_akismet'] = ! empty( $raw['enable_akismet'] ) && '0' !== (string) $raw['enable_akismet'] ? '1' : '0';
			$out['store_ip']       = ! isset( $raw['store_ip'] ) || ( ! empty( $raw['store_ip'] ) && '0' !== (string) $raw['store_ip'] ) ? '1' : '0';

			$mode = isset( $raw['form_mode'] ) ? sanitize_key( (string) $raw['form_mode'] ) : 'form';
			$out['form_mode'] = in_array( $mode, array( 'form', 'quiz', 'survey' ), true ) ? $mode : 'form';
			$out['quiz_show_score']    = ! empty( $raw['quiz_show_score'] ) && '0' !== (string) $raw['quiz_show_score'] ? '1' : '0';
			$out['quiz_show_answers']  = ! empty( $raw['quiz_show_answers'] ) && '0' !== (string) $raw['quiz_show_answers'] ? '1' : '0';
			$out['quiz_timer_seconds'] = isset( $raw['quiz_timer_seconds'] ) ? (string) max( 0, min( 7200, (int) $raw['quiz_timer_seconds'] ) ) : '0';
			$out['quiz_max_attempts']  = isset( $raw['quiz_max_attempts'] ) ? (string) max( 0, min( 50, (int) $raw['quiz_max_attempts'] ) ) : '0';
			$attempt_by = isset( $raw['quiz_attempt_by'] ) ? sanitize_key( (string) $raw['quiz_attempt_by'] ) : 'ip';
			$out['quiz_attempt_by']    = in_array( $attempt_by, array( 'ip', 'email' ), true ) ? $attempt_by : 'ip';
			if ( isset( $raw['quiz_attempt_field'] ) && is_string( $raw['quiz_attempt_field'] ) ) {
				$out['quiz_attempt_field'] = sanitize_key( str_replace( '-', '_', $raw['quiz_attempt_field'] ) );
			}
			if ( isset( $raw['quiz_bands'] ) && is_array( $raw['quiz_bands'] ) ) {
				$out['quiz_results'] = self::serialize_quiz_bands( $raw['quiz_bands'] );
			} elseif ( isset( $raw['quiz_results'] ) && is_string( $raw['quiz_results'] ) ) {
				$out['quiz_results'] = sanitize_textarea_field( $raw['quiz_results'] );
			}
			$out['partial_save']     = ! empty( $raw['partial_save'] ) && '0' !== (string) $raw['partial_save'] ? '1' : '0';
			$out['partial_ttl_days'] = isset( $raw['partial_ttl_days'] ) ? (string) max( 1, min( 30, (int) $raw['partial_ttl_days'] ) ) : '7';
			$out['share_results']    = ! empty( $raw['share_results'] ) && '0' !== (string) $raw['share_results'] ? '1' : '0';
			if ( isset( $raw['quiz_cta_label'] ) && is_string( $raw['quiz_cta_label'] ) ) {
				$out['quiz_cta_label'] = sanitize_text_field( $raw['quiz_cta_label'] );
			}

			$out = self::merge_automation_settings( $raw, $out );

			$out = self::apply_style_settings( $raw, $out );
		}
		if ( class_exists( 'Thimbleform_Settings' ) ) {
			$plugin_label = Thimbleform_Settings::default_submit_label();
			if ( $plugin_label !== '' && ( ! is_array( $raw ) || empty( $raw['submit_label'] ) ) ) {
				$out['submit_label'] = $plugin_label;
			}
		}
		return self::apply_feature_gates( $out );
	}

	/**
	 * @param int   $form_id Form ID.
	 * @param array $config  Full config.
	 */
	public static function save( $form_id, array $config ) {
		$form_id = (int) $form_id;
		$fields  = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : array();
		$fields  = array_values( array_filter( array_map( array( __CLASS__, 'sanitize_field_row' ), $fields ) ) );
		$fields  = self::ensure_unique_field_names( $fields );
		if ( array() === $fields ) {
			$fields = self::default_fields();
		}
		update_post_meta( $form_id, self::META_FIELDS, $fields );

		$messages = self::default_messages();
		if ( isset( $config['messages'] ) && is_array( $config['messages'] ) ) {
			foreach ( $messages as $key => $default ) {
				if ( isset( $config['messages'][ $key ] ) ) {
					$messages[ $key ] = ( 'success' === $key )
						? sanitize_textarea_field( (string) $config['messages'][ $key ] )
						: sanitize_text_field( (string) $config['messages'][ $key ] );
				}
			}
		}
		update_post_meta( $form_id, self::META_MESSAGES, $messages );

		$mail = self::default_mail();
		if ( isset( $config['mail'] ) && is_array( $config['mail'] ) ) {
			$can_html = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::EMAIL_DESIGNER );
			$can_pdf  = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::PDF_EXPORT );
			$existing_mail = get_post_meta( $form_id, self::META_MAIL, true );
			$existing_mail = is_array( $existing_mail ) ? $existing_mail : array();

			foreach ( $mail as $key => $default ) {
				if ( ! isset( $config['mail'][ $key ] ) ) {
					continue;
				}
				$val = (string) $config['mail'][ $key ];
				if ( in_array( $key, array( 'user_mail_enabled', 'extra_enabled', 'html_enabled', 'pdf_attach' ), true ) ) {
					$mail[ $key ] = ! empty( $config['mail'][ $key ] ) && '0' !== $val ? '1' : '0';
					continue;
				}
				if ( 'logo_id' === $key ) {
					$mail[ $key ] = (string) max( 0, (int) $val );
					continue;
				}
				if ( in_array( $key, array( 'body_template', 'user_mail_body', 'extra_body' ), true ) ) {
					$html_flag = ! empty( $config['mail']['html_enabled'] ) && '0' !== (string) $config['mail']['html_enabled'];
					$mail[ $key ] = class_exists( 'Thimbleform_Mail_Html' )
						? Thimbleform_Mail_Html::sanitize_body( $val, $can_html && $html_flag )
						: sanitize_textarea_field( $val );
					continue;
				}
				$mail[ $key ] = sanitize_text_field( $val );
			}
			if ( ! isset( self::condition_operators()[ $mail['extra_condition_op'] ] ) ) {
				$mail['extra_condition_op'] = 'equals';
			}
			if ( ! $can_html ) {
				$mail['html_enabled'] = isset( $existing_mail['html_enabled'] ) ? (string) $existing_mail['html_enabled'] : '0';
				$mail['logo_id']      = isset( $existing_mail['logo_id'] ) ? (string) $existing_mail['logo_id'] : '0';
				// Preserve designer HTML bodies so Free saves cannot wipe Pro templates.
				if ( '1' === (string) ( $existing_mail['html_enabled'] ?? '0' ) ) {
					if ( isset( $existing_mail['body_template'] ) ) {
						$mail['body_template'] = (string) $existing_mail['body_template'];
					}
					if ( isset( $existing_mail['user_mail_body'] ) ) {
						$mail['user_mail_body'] = (string) $existing_mail['user_mail_body'];
					}
				}
			}
			if ( ! $can_pdf ) {
				$mail['pdf_attach'] = isset( $existing_mail['pdf_attach'] ) ? (string) $existing_mail['pdf_attach'] : '0';
			}
		}
		update_post_meta( $form_id, self::META_MAIL, $mail );

		$settings = self::default_settings();
		if ( isset( $config['settings'] ) && is_array( $config['settings'] ) ) {
			if ( isset( $config['settings']['submit_label'] ) ) {
				$settings['submit_label'] = sanitize_text_field( (string) $config['settings']['submit_label'] );
			}
			if ( isset( $config['settings']['redirect_url'] ) ) {
				$settings['redirect_url'] = class_exists( 'Thimbleform_Security' )
					? Thimbleform_Security::sanitize_redirect_template( (string) $config['settings']['redirect_url'] )
					: esc_url_raw( (string) $config['settings']['redirect_url'] );
			}
			$display = isset( $config['settings']['success_display'] )
				? sanitize_key( (string) $config['settings']['success_display'] )
				: 'inline';
			$settings['success_display'] = in_array( $display, array( 'inline', 'replace', 'popup' ), true ) ? $display : 'inline';
			$settings['enable_captcha'] = ! empty( $config['settings']['enable_captcha'] ) ? '1' : '0';
			$settings['enable_stripe']  = ! empty( $config['settings']['enable_stripe'] ) ? '1' : '0';
			$settings['enable_hubspot'] = ! empty( $config['settings']['enable_hubspot'] ) ? '1' : '0';
			$settings['hubspot_map']    = self::sanitize_hubspot_map(
				isset( $config['settings']['hubspot_map'] ) ? $config['settings']['hubspot_map'] : array()
			);

			$existing = self::get_settings_raw( $form_id );
			$settings['enable_steps'] = isset( $existing['enable_steps'] ) ? (string) $existing['enable_steps'] : '0';
			$settings['step_labels']  = isset( $existing['step_labels'] ) ? (string) $existing['step_labels'] : '';
			$settings['next_label']   = isset( $existing['next_label'] ) ? (string) $existing['next_label'] : $settings['next_label'];
			$settings['prev_label']   = isset( $existing['prev_label'] ) ? (string) $existing['prev_label'] : $settings['prev_label'];
			$settings['branch_rules'] = isset( $existing['branch_rules'] ) ? (string) $existing['branch_rules'] : '';
			$settings = apply_filters( 'thimbleform_settings_from_request', $settings, $config, $form_id );

			$settings = self::merge_webhook_settings(
				isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array(),
				$settings
			);

			if ( isset( $config['settings']['time_trap_seconds'] ) ) {
				$settings['time_trap_seconds'] = (string) max( 0, min( 60, (int) $config['settings']['time_trap_seconds'] ) );
			}
			$settings['enable_akismet'] = ! empty( $config['settings']['enable_akismet'] ) ? '1' : '0';
			$settings['store_ip']       = ! isset( $config['settings']['store_ip'] ) || ! empty( $config['settings']['store_ip'] ) ? '1' : '0';

			$can_quiz = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::QUIZ_SURVEY );
			if ( $can_quiz ) {
				$mode = isset( $config['settings']['form_mode'] ) ? sanitize_key( (string) $config['settings']['form_mode'] ) : 'form';
				$settings['form_mode'] = in_array( $mode, array( 'form', 'quiz', 'survey' ), true ) ? $mode : 'form';
				$settings['quiz_show_score']    = ! empty( $config['settings']['quiz_show_score'] ) ? '1' : '0';
				$settings['quiz_show_answers']  = ! empty( $config['settings']['quiz_show_answers'] ) ? '1' : '0';
				$settings['quiz_timer_seconds'] = isset( $config['settings']['quiz_timer_seconds'] )
					? (string) max( 0, min( 7200, (int) $config['settings']['quiz_timer_seconds'] ) )
					: '0';
				$settings['quiz_max_attempts'] = isset( $config['settings']['quiz_max_attempts'] )
					? (string) max( 0, min( 50, (int) $config['settings']['quiz_max_attempts'] ) )
					: '0';
				$attempt_by = isset( $config['settings']['quiz_attempt_by'] ) ? sanitize_key( (string) $config['settings']['quiz_attempt_by'] ) : 'ip';
				$settings['quiz_attempt_by'] = in_array( $attempt_by, array( 'ip', 'email' ), true ) ? $attempt_by : 'ip';
				if ( isset( $config['settings']['quiz_attempt_field'] ) ) {
					$settings['quiz_attempt_field'] = sanitize_key( str_replace( '-', '_', (string) $config['settings']['quiz_attempt_field'] ) );
				}
				if ( isset( $config['settings']['quiz_results'] ) ) {
					$settings['quiz_results'] = sanitize_textarea_field( (string) $config['settings']['quiz_results'] );
				}
				$settings['partial_save']     = ! empty( $config['settings']['partial_save'] ) ? '1' : '0';
				$settings['partial_ttl_days'] = isset( $config['settings']['partial_ttl_days'] )
					? (string) max( 1, min( 30, (int) $config['settings']['partial_ttl_days'] ) )
					: '7';
				$settings['share_results'] = ! empty( $config['settings']['share_results'] ) ? '1' : '0';
				if ( isset( $config['settings']['quiz_cta_label'] ) ) {
					$settings['quiz_cta_label'] = sanitize_text_field( (string) $config['settings']['quiz_cta_label'] );
				}
			} else {
				foreach ( array( 'form_mode', 'quiz_show_score', 'quiz_show_answers', 'quiz_timer_seconds', 'quiz_max_attempts', 'quiz_attempt_by', 'quiz_attempt_field', 'quiz_results', 'partial_save', 'partial_ttl_days', 'share_results', 'quiz_cta_label' ) as $qk ) {
					if ( isset( $existing[ $qk ] ) ) {
						$settings[ $qk ] = (string) $existing[ $qk ];
					}
				}
			}

			$can_auto = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::AUTOMATIONS );
			if ( $can_auto ) {
				$settings = self::merge_automation_settings( $config['settings'], $settings );
			} else {
				foreach ( array( 'automation_enabled', 'automation_match', 'automation_rules', 'automation_field', 'automation_op', 'automation_value', 'automation_then_status', 'automation_then_email', 'automation_then_webhook', 'automation_skip_spam' ) as $ak ) {
					if ( isset( $existing[ $ak ] ) ) {
						$settings[ $ak ] = $existing[ $ak ];
					}
				}
			}

			$settings = self::apply_style_settings( $config['settings'], $settings );
		}
		update_post_meta( $form_id, self::META_SETTINGS, $settings );
	}

	/**
	 * @param mixed $row Raw row.
	 * @return array<string, mixed>|null
	 */
	public static function sanitize_field_row( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text';

		// Reject Pro field types when capability is not registered (do not cast to text).
		if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::is_pro_field_type( $type ) && ! Thimbleform_Features::can_use_field_type( $type ) ) {
			return null;
		}

		$allowed = array_merge(
			array_keys( self::input_field_type_labels() ),
			array_keys( self::layout_field_type_labels() )
		);
		if ( ! in_array( $type, $allowed, true ) ) {
			// Also reject unknown Pro keys even if labels filter was bypassed.
			if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::is_pro_field_type( $type ) ) {
				return null;
			}
			$type = 'text';
		}
		$name = isset( $row['name'] ) ? sanitize_key( (string) $row['name'] ) : '';
		$name = str_replace( '-', '_', $name );
		if ( $name === '' ) {
			if ( self::is_layout_field( $type ) ) {
				$name = $type;
			} else {
				return null;
			}
		}
		$width_pack   = self::sanitize_field_width( $row['width'] ?? 'full', $row['width_custom'] ?? 50 );
		$width        = $width_pack['width'];
		$width_custom = $width_pack['width_custom'];
		$step = isset( $row['step'] ) ? (int) $row['step'] : 1;
		if ( $step < 1 ) {
			$step = 1;
		}
		if ( $step > 20 ) {
			$step = 20;
		}
		$label_raw = (string) ( $row['label'] ?? '' );
		$label     = ( 'acceptance' === $type )
			? self::sanitize_acceptance_label( $label_raw )
			: sanitize_text_field( $label_raw );

		$options_raw = (string) ( $row['options'] ?? '' );
		if ( 'html' === $type ) {
			$options = wp_kses( $options_raw, self::html_allowed_tags() );
		} elseif ( 'heading' === $type ) {
			$level   = sanitize_key( $options_raw !== '' ? $options_raw : 'h2' );
			$options = in_array( $level, array( 'h2', 'h3', 'h4' ), true ) ? $level : 'h2';
		} elseif ( 'paragraph' === $type ) {
			$options = sanitize_textarea_field( $options_raw );
		} elseif ( 'spacer' === $type ) {
			$size    = sanitize_key( $options_raw !== '' ? $options_raw : 'm' );
			$options = in_array( $size, array( 's', 'm', 'l' ), true ) ? $size : 'm';
		} elseif ( 'divider' === $type ) {
			$options = '';
		} elseif ( 'tel' === $type ) {
			$options = class_exists( 'Thimbleform_Phone' )
				? Thimbleform_Phone::parse_iso( $options_raw )
				: '';
		} elseif ( 'file' === $type ) {
			$options = self::sanitize_file_extensions( $options_raw );
		} elseif ( 'calculated' === $type ) {
			$options = sanitize_text_field( $options_raw );
		} elseif ( 'payment' === $type ) {
			$options = self::sanitize_payment_options( $row, $options_raw );
		} else {
			$options = sanitize_textarea_field( $options_raw );
		}

		$default = (string) ( $row['default'] ?? '' );
		if ( 'image' === $type ) {
			$default = (string) max( 0, (int) $default );
		} elseif ( 'file' === $type ) {
			$default = (string) max( 1, min( 10, (int) ( $default !== '' ? $default : 1 ) ) );
		} elseif ( 'divider' === $type || 'spacer' === $type ) {
			$default = '';
		}

		$placeholder = sanitize_text_field( (string) ( $row['placeholder'] ?? '' ) );
		if ( 'file' === $type && $placeholder === '' ) {
			$placeholder = (string) self::file_default_max_mb();
		}

		$required = ! empty( $row['required'] ) && ! self::is_layout_field( $type );
		// Missing key = enabled (backward compatible). Posted "0" disables the field on the front.
		$enabled = ! array_key_exists( 'enabled', $row ) || ! empty( $row['enabled'] );

		$cond_field = isset( $row['condition_field'] ) ? sanitize_key( (string) $row['condition_field'] ) : '';
		$cond_field = str_replace( '-', '_', $cond_field );
		$cond_op    = isset( $row['condition_op'] ) ? sanitize_key( (string) $row['condition_op'] ) : 'equals';
		if ( ! isset( self::condition_operators()[ $cond_op ] ) ) {
			$cond_op = 'equals';
		}
		$cond_value = sanitize_text_field( (string) ( $row['condition_value'] ?? '' ) );
		if ( self::is_layout_field( $type ) || $cond_field === '' || $cond_field === $name ) {
			$cond_field = '';
			$cond_op    = 'equals';
			$cond_value = '';
		}

		$out = array(
			'type'            => $type,
			'name'            => $name,
			'label'           => $label,
			'placeholder'     => $placeholder,
			'description'     => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
			'default'         => $default,
			'css_class'       => sanitize_html_class( (string) ( $row['css_class'] ?? '' ) ),
			'required'        => $required,
			'enabled'         => $enabled,
			'width'           => $width,
			'width_custom'    => $width_custom,
			'options'         => $options,
			'allow_other'     => ! empty( $row['allow_other'] ) && in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ),
			'other_label'     => in_array( $type, array( 'select', 'radio', 'checkboxes' ), true )
				? sanitize_text_field( (string) ( $row['other_label'] ?? '' ) )
				: '',
			'step'            => $step,
			'condition_field' => $cond_field,
			'condition_op'    => $cond_op,
			'condition_value' => $cond_value,
			'subfields'       => array(),
		);

		if ( 'repeater' === $type && class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::REPEATERS ) ) {
			$raw_subs = isset( $row['subfields'] ) && is_array( $row['subfields'] ) ? $row['subfields'] : array();
			$out['subfields'] = self::sanitize_subfields( $raw_subs );
		}

		/**
		 * Filter a sanitized field row (Pro may refine options for advanced types).
		 *
		 * @param array<string, mixed> $out Sanitized row.
		 * @param array                $row Raw row.
		 */
		$filtered = apply_filters( 'thimbleform_sanitize_field_row', $out, $row );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Sanitize repeater subfields (single level — no nested repeaters).
	 *
	 * @param array $rows  Raw rows.
	 * @param int   $depth Nesting depth (unused, kept for call-site compatibility).
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_subfields( array $rows, $depth = 0 ) {
		unset( $depth );
		$out  = array();
		$seen = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text';
			// No nested repeaters / layout / file / hidden inside a repeater.
			if ( 'repeater' === $type || self::is_layout_field( $type ) || 'file' === $type || 'hidden' === $type ) {
				continue;
			}
			$clean = self::sanitize_field_row( $row );
			if ( null === $clean ) {
				continue;
			}
			$clean['subfields']       = array();
			$clean['condition_field'] = '';
			$clean['condition_op']    = 'equals';
			$clean['condition_value'] = '';
			$clean['step']            = 1;
			if ( isset( $seen[ $clean['name'] ] ) ) {
				continue;
			}
			$seen[ $clean['name'] ] = true;
			$out[] = $clean;
			if ( count( $out ) >= 20 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Whether a field is enabled for the public form (soft-hide in the builder).
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return bool
	 */
	public static function is_field_enabled( array $field ) {
		if ( ! array_key_exists( 'enabled', $field ) ) {
			return true;
		}
		return ! empty( $field['enabled'] );
	}

	/**
	 * Whether a field should be visible given current submitted/posted values.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @param array<string, mixed> $data  Field name => value (string|array|bool).
	 * @return bool
	 */
	public static function is_field_visible( array $field, array $data ) {
		if ( ! self::is_field_enabled( $field ) ) {
			return false;
		}
		$watch = isset( $field['condition_field'] ) ? (string) $field['condition_field'] : '';
		if ( $watch === '' ) {
			return true;
		}
		$op   = isset( $field['condition_op'] ) ? (string) $field['condition_op'] : 'equals';
		$want = isset( $field['condition_value'] ) ? (string) $field['condition_value'] : '';
		$raw  = array_key_exists( $watch, $data ) ? $data[ $watch ] : null;
		return self::match_condition( $op, $want, $raw );
	}

	/**
	 * Evaluate a condition operator against a raw value.
	 *
	 * @param string $op   Operator key.
	 * @param string $want Expected value.
	 * @param mixed  $raw  Submitted value.
	 * @return bool
	 */
	public static function match_condition( $op, $want, $raw ) {
		$value = self::condition_value_as_string( $raw );
		$want  = (string) $want;
		$op    = (string) $op;

		switch ( $op ) {
			case 'empty':
				return $value === '';
			case 'not_empty':
				return $value !== '';
			case 'not_equals':
				return strcasecmp( $value, $want ) !== 0;
			case 'contains':
				if ( $want === '' ) {
					return true;
				}
				return false !== stripos( $value, $want );
			case 'equals':
			default:
				return strcasecmp( $value, $want ) === 0;
		}
	}

	/**
	 * Steps that participate in this submission (branch-aware).
	 * Empty array means "all fields" (steps disabled).
	 *
	 * @param array<int, array<string, mixed>> $fields   Fields.
	 * @param array<string, string>            $settings Settings.
	 * @param array<string, mixed>             $raw_map  Raw submitted values for conditions.
	 * @return array<int, int>
	 */
	public static function resolve_active_steps( array $fields, array $settings, array $raw_map ) {
		$settings = self::apply_feature_gates( $settings );
		if ( empty( $settings['enable_steps'] ) || '1' !== (string) $settings['enable_steps'] ) {
			return array();
		}

		/**
		 * Filter active steps for branch-aware multi-step forms (Thimbleform Pro).
		 *
		 * @param array<int, int>                  $steps    Default empty — Pro supplies path.
		 * @param array<int, array<string, mixed>> $fields   Fields.
		 * @param array<string, string>            $settings Settings.
		 * @param array<string, mixed>             $raw_map  Submitted values.
		 */
		$resolved = apply_filters( 'thimbleform_resolve_active_steps', array(), $fields, $settings, $raw_map );
		return is_array( $resolved ) ? array_values( array_map( 'intval', $resolved ) ) : array();
	}

	/**
	 * Reject localhost / private / reserved webhook targets.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_safe_outbound_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( $url === '' || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( in_array( $host, array( 'localhost', 'metadata', 'metadata.google.internal' ), true ) ) {
			return false;
		}
		if ( preg_match( '/\.(local|localhost|internal|lan|home)$/', $host ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
			if ( ! filter_var( $host, FILTER_VALIDATE_IP, $flags ) ) {
				return false;
			}
		}
		/**
		 * Filter whether a webhook/outbound URL is allowed.
		 *
		 * @param bool   $allowed Allowed.
		 * @param string $url     URL.
		 */
		return (bool) apply_filters( 'thimbleform_is_safe_outbound_url', true, $url );
	}

	/**
	 * @param mixed $raw Raw field value.
	 * @return string
	 */
	public static function condition_value_as_string( $raw ) {
		if ( null === $raw || false === $raw || '' === $raw ) {
			return '';
		}
		if ( true === $raw ) {
			return '1';
		}
		if ( is_array( $raw ) ) {
			$flat = array();
			foreach ( $raw as $item ) {
				if ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
			return implode( ', ', $flat );
		}
		return trim( (string) $raw );
	}

	/**
	 * Parse branch rules: from_step|field|op|value|to_step
	 *
	 * @param string $raw Raw textarea.
	 * @return array<int, array{from:int,field:string,op:string,value:string,to:int}>
	 */
	public static function parse_branch_rules( $raw ) {
		/**
		 * Filter parsed branch rules (Thimbleform Pro).
		 *
		 * @param array<int, array<string, mixed>> $rules Default empty.
		 * @param string                           $raw   Raw textarea.
		 */
		$rules = apply_filters( 'thimbleform_parse_branch_rules', array(), $raw );
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Max files allowed for a file field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return int
	 */
	public static function file_max_count( array $field ) {
		return max( 1, min( 10, (int) ( $field['default'] ?? 1 ) ) );
	}

	/**
	 * Ensure each field name is unique within the form.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ensure_unique_field_names( array $fields ) {
		$used = array();
		foreach ( $fields as $i => $field ) {
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';
			if ( $name === '' ) {
				$name = 'field';
			}
			$base = $name;
			$n    = 2;
			while ( isset( $used[ $name ] ) ) {
				$name = $base . '_' . $n;
				$n++;
			}
			$used[ $name ]           = true;
			$fields[ $i ]['name']    = $name;
		}
		return $fields;
	}

	/**
	 * Allowed markup for Acceptance labels (policy links, etc.).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function acceptance_allowed_html() {
		return array(
			'a' => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
		);
	}

	/**
	 * @param string $label Raw label.
	 * @return string
	 */
	public static function sanitize_acceptance_label( $label ) {
		$html = wp_kses( (string) $label, self::acceptance_allowed_html() );
		// Ensure blank-target links are safer by default.
		$html = preg_replace_callback(
			'/<a\b([^>]*)>/i',
			static function ( $m ) {
				$attrs = $m[1];
				if ( preg_match( '/\btarget\s*=\s*(["\'])_blank\1/i', $attrs ) && ! preg_match( '/\brel\s*=/i', $attrs ) ) {
					$attrs .= ' rel="noopener noreferrer"';
				}
				return '<a' . $attrs . '>';
			},
			$html
		);
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Unique sorted step numbers used by fields.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<int, int>
	 */
	public static function collect_steps( array $fields ) {
		$steps = array();
		foreach ( $fields as $field ) {
			$step = isset( $field['step'] ) ? (int) $field['step'] : 1;
			if ( $step < 1 ) {
				$step = 1;
			}
			$steps[ $step ] = $step;
		}
		if ( array() === $steps ) {
			return array( 1 );
		}
		$steps = array_values( $steps );
		sort( $steps, SORT_NUMERIC );
		return $steps;
	}

	/**
	 * @param string $raw Newline-separated labels.
	 * @return array<int, string> 1-based labels.
	 */
	public static function parse_step_labels( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$out   = array();
		if ( ! is_array( $lines ) ) {
			return $out;
		}
		$i = 1;
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line !== '' ) {
				$out[ $i ] = $line;
			}
			$i++;
		}
		return $out;
	}

	/**
	 * Resolve form ID by id or slug.
	 *
	 * @param array $atts Shortcode atts.
	 * @return int
	 */
	public static function resolve_form_id( array $atts ) {
		if ( ! empty( $atts['id'] ) ) {
			$id = (int) $atts['id'];
			if ( $id > 0 && 'thimbleform' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
				return $id;
			}
		}
		if ( ! empty( $atts['slug'] ) ) {
			$slug = sanitize_title( (string) $atts['slug'] );
			$posts = get_posts(
				array(
					'name'           => $slug,
					'post_type'      => 'thimbleform',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( ! empty( $posts[0] ) ) {
				return (int) $posts[0];
			}
		}
		return 0;
	}

	/**
	 * @return array<string, string>
	 */
	public static function layout_field_type_labels() {
		$labels = array(
			'heading'   => __( 'Heading', 'thimbleform' ),
			'paragraph' => __( 'Paragraph', 'thimbleform' ),
			'image'     => __( 'Image', 'thimbleform' ),
			'divider'   => __( 'Divider', 'thimbleform' ),
			'spacer'    => __( 'Spacer', 'thimbleform' ),
			'html'      => __( 'HTML block', 'thimbleform' ),
		);

		/**
		 * Filter layout (display-only) field type labels.
		 *
		 * @param array<string, string> $labels Type => label.
		 */
		return (array) apply_filters( 'thimbleform_layout_field_types', $labels );
	}

	/**
	 * @return array<string, string>
	 */
	public static function input_field_type_labels() {
		$labels = array(
			'text'       => __( 'Text', 'thimbleform' ),
			'email'      => __( 'Email', 'thimbleform' ),
			'tel'        => __( 'Phone', 'thimbleform' ),
			'url'        => __( 'URL', 'thimbleform' ),
			'password'   => __( 'Password', 'thimbleform' ),
			'number'     => __( 'Number', 'thimbleform' ),
			'range'      => __( 'Range', 'thimbleform' ),
			'date'       => __( 'Date', 'thimbleform' ),
			'time'       => __( 'Time', 'thimbleform' ),
			'textarea'   => __( 'Textarea', 'thimbleform' ),
			'select'     => __( 'Select', 'thimbleform' ),
			'radio'      => __( 'Radio', 'thimbleform' ),
			'checkboxes' => __( 'Checkboxes', 'thimbleform' ),
			'checkbox'   => __( 'Checkbox', 'thimbleform' ),
			'acceptance' => __( 'Acceptance', 'thimbleform' ),
			'file'       => __( 'File upload', 'thimbleform' ),
			'hidden'     => __( 'Hidden', 'thimbleform' ),
		);

		if ( class_exists( 'Thimbleform_Features' ) ) {
			foreach ( Thimbleform_Features::specialty_field_teasers() as $type => $label ) {
				if ( Thimbleform_Features::can_use_field_type( $type ) ) {
					$labels[ $type ] = $label;
				}
			}
		}

		/**
		 * Filter input field type labels (Pro registers advanced types here).
		 *
		 * @param array<string, string> $labels Type => label.
		 */
		$labels = (array) apply_filters( 'thimbleform_input_field_types', $labels );

		// Drop Pro types when capability is missing so they never enter the whitelist.
		if ( class_exists( 'Thimbleform_Features' ) ) {
			foreach ( Thimbleform_Features::pro_field_types() as $pro_type ) {
				if ( ! Thimbleform_Features::can_use_field_type( $pro_type ) ) {
					unset( $labels[ $pro_type ] );
				}
			}
		}

		return $labels;
	}

	/**
	 * Parse choice option lines: Label | Label|score | Label|value|score.
	 *
	 * @param string $raw Options textarea.
	 * @return array<int, array{label:string,value:string,score:float,is_other:bool}>
	 */
	public static function parse_choice_lines( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$out  = array();
		$seen = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$label = $parts[0];
			$value = $label;
			$score = 0.0;
			$is_other = false;
			if ( count( $parts ) === 2 ) {
				if ( '*' === $parts[1] || 'other' === strtolower( $parts[1] ) ) {
					$is_other = true;
					$value    = self::OTHER_VALUE;
				} elseif ( is_numeric( $parts[1] ) ) {
					$score = (float) $parts[1];
				} else {
					$value = $parts[1];
				}
			} elseif ( count( $parts ) >= 3 ) {
				$value = $parts[1] !== '' ? $parts[1] : $label;
				if ( '*' === $value || 'other' === strtolower( $value ) ) {
					$is_other = true;
					$value    = self::OTHER_VALUE;
				}
				if ( is_numeric( $parts[2] ) ) {
					$score = (float) $parts[2];
				}
			}
			if ( isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$out[] = array(
				'label'    => $label,
				'value'    => $value,
				'score'    => $score,
				'is_other' => $is_other,
			);
		}
		return $out;
	}

	/**
	 * Flat list of choice values (for validation allow-lists).
	 *
	 * @param string $raw Options.
	 * @return array<int, string>
	 */
	public static function parse_choice_values( $raw ) {
		$values = array();
		foreach ( self::parse_choice_lines( $raw ) as $row ) {
			$values[] = $row['value'];
		}
		return $values;
	}

	/**
	 * Label for the built-in “Other” choice (select / radio / checkboxes).
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return string
	 */
	public static function other_choice_label( array $field ) {
		$custom = isset( $field['other_label'] ) ? trim( (string) $field['other_label'] ) : '';
		if ( $custom !== '' ) {
			return $custom;
		}
		return __( 'Other', 'thimbleform' );
	}

	/**
	 * Map internal type → HTML input type attribute.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	public static function html_input_type( $type ) {
		$native = array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'date', 'time' );
		return in_array( (string) $type, $native, true ) ? (string) $type : 'text';
	}

	/**
	 * Parse range options: min / max / step (one per line or pipe-separated).
	 *
	 * @param string $raw Options.
	 * @return array{min:float,max:float,step:float}
	 */
	public static function parse_range_options( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) {
			return array(
				'min'  => 0,
				'max'  => 100,
				'step' => 1,
			);
		}
		if ( false !== strpos( $raw, '|' ) ) {
			$parts = array_map( 'trim', explode( '|', $raw ) );
		} else {
			$parts = preg_split( '/\r\n|\r|\n/', $raw );
			$parts = is_array( $parts ) ? array_map( 'trim', $parts ) : array();
		}
		$min  = isset( $parts[0] ) && is_numeric( $parts[0] ) ? (float) $parts[0] : 0;
		$max  = isset( $parts[1] ) && is_numeric( $parts[1] ) ? (float) $parts[1] : 100;
		$step = isset( $parts[2] ) && is_numeric( $parts[2] ) ? (float) $parts[2] : 1;
		if ( $max < $min ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}
		if ( $step <= 0 ) {
			$step = 1;
		}
		return array(
			'min'  => $min,
			'max'  => $max,
			'step' => $step,
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function field_type_labels() {
		return array_merge( self::layout_field_type_labels(), self::input_field_type_labels() );
	}

	/**
	 * Default HubSpot contact property → form field name map.
	 *
	 * @return array<string, string>
	 */
	public static function default_hubspot_map() {
		return array(
			'email'     => '',
			'firstname' => '',
			'lastname'  => '',
			'phone'     => '',
			'company'   => '',
		);
	}

	/**
	 * @param mixed $raw Raw map.
	 * @return array<string, string>
	 */
	public static function sanitize_hubspot_map( $raw ) {
		$out = self::default_hubspot_map();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( array_keys( $out ) as $prop ) {
			if ( ! isset( $raw[ $prop ] ) ) {
				continue;
			}
			$out[ $prop ] = sanitize_key( str_replace( '-', '_', (string) $raw[ $prop ] ) );
		}
		return $out;
	}

	/**
	 * Labels for HubSpot property mapping UI.
	 *
	 * @return array<string, string>
	 */
	public static function hubspot_map_labels() {
		return array(
			'email'     => __( 'Email', 'thimbleform' ),
			'firstname' => __( 'First name', 'thimbleform' ),
			'lastname'  => __( 'Last name', 'thimbleform' ),
			'phone'     => __( 'Phone', 'thimbleform' ),
			'company'   => __( 'Company', 'thimbleform' ),
		);
	}

	/**
	 * Field width presets (stored keys → labels).
	 *
	 * @return array<string, string>
	 */
	public static function field_width_presets() {
		return array(
			'full'       => __( 'Full', 'thimbleform' ),
			'half'       => __( 'Half', 'thimbleform' ),
			'third'      => __( 'One third', 'thimbleform' ),
			'two_thirds' => __( 'Two thirds', 'thimbleform' ),
			'quarter'    => __( 'Quarter', 'thimbleform' ),
			'custom'     => __( 'Custom…', 'thimbleform' ),
		);
	}

	/**
	 * Sanitize width + optional custom percent (1–100).
	 *
	 * @param mixed $width  Preset key.
	 * @param mixed $custom Custom percent.
	 * @return array{width: string, width_custom: int}
	 */
	public static function sanitize_field_width( $width, $custom = 50 ) {
		$width = sanitize_key( (string) $width );
		if ( ! array_key_exists( $width, self::field_width_presets() ) ) {
			$width = 'full';
		}
		$pct = (int) $custom;
		if ( $pct < 1 || $pct > 100 ) {
			$pct = 50;
		}
		return array(
			'width'        => $width,
			'width_custom' => $pct,
		);
	}

	/**
	 * CSS class suffix + optional inline style vars for a field width.
	 *
	 * @param array<string, mixed> $field Field row.
	 * @return array{class: string, style: string}
	 */
	public static function field_width_presentation( array $field ) {
		$pack   = self::sanitize_field_width( $field['width'] ?? 'full', $field['width_custom'] ?? 50 );
		$width  = $pack['width'];
		$class  = str_replace( '_', '-', $width );
		$style  = '';
		$ratios = array(
			'half'       => array( '50%', 0.5 ),
			'third'      => array( '33.333%', 0.33333 ),
			'two_thirds' => array( '66.667%', 0.66667 ),
			'quarter'    => array( '25%', 0.25 ),
		);

		if ( isset( $ratios[ $width ] ) ) {
			$style = sprintf(
				'--thimbleform-field-basis:%1$s;--thimbleform-field-ratio:%2$s;',
				$ratios[ $width ][0],
				(string) $ratios[ $width ][1]
			);
		} elseif ( 'custom' === $width ) {
			$pct   = $pack['width_custom'];
			$ratio = round( $pct / 100, 5 );
			$style = sprintf(
				'--thimbleform-field-basis:%1$d%%;--thimbleform-field-ratio:%2$s;',
				$pct,
				(string) $ratio
			);
		}

		return array(
			'class' => $class,
			'style' => $style,
		);
	}

	/**
	 * @param string $type Field type.
	 * @return bool
	 */
	public static function is_layout_field( $type ) {
		return array_key_exists( (string) $type, self::layout_field_type_labels() );
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return bool
	 */
	public static function has_file_field( array $fields ) {
		foreach ( $fields as $field ) {
			if ( 'file' === ( $field['type'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return int
	 */
	public static function file_default_max_mb() {
		return 5;
	}

	/**
	 * @return string
	 */
	public static function file_default_extensions() {
		return 'jpg,jpeg,png,gif,pdf,doc,docx';
	}

	/**
	 * Extensions that must never be accepted as form uploads.
	 *
	 * @return array<int, string>
	 */
	public static function dangerous_file_extensions() {
		return array(
			'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'phps',
			'cgi', 'pl', 'asp', 'aspx', 'jsp', 'exe', 'com', 'bat', 'cmd',
			'js', 'mjs', 'html', 'htm', 'shtml', 'svg', 'svgz',
			'htaccess', 'user.ini', 'sh', 'bash', 'py', 'rb',
		);
	}

	/**
	 * @param string $ext Extension without dot.
	 * @return bool
	 */
	public static function is_dangerous_file_extension( $ext ) {
		$ext = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) $ext ) );
		return $ext !== '' && in_array( $ext, self::dangerous_file_extensions(), true );
	}

	/**
	 * Common Stripe currency codes for the builder (Pro may filter further).
	 *
	 * @return array<int, string>
	 */
	public static function payment_currency_options() {
		$codes = array( 'USD', 'EUR', 'GBP', 'RUB', 'CAD', 'AUD', 'CHF', 'JPY', 'PLN', 'SEK', 'NOK', 'DKK', 'CZK', 'UAH', 'BRL', 'MXN', 'INR', 'SGD', 'HKD', 'NZD' );
		if ( class_exists( 'Thimbleform_Pro_Payments' ) && is_callable( array( 'Thimbleform_Pro_Payments', 'currencies' ) ) ) {
			$codes = Thimbleform_Pro_Payments::currencies();
		}
		return array_values( array_unique( array_map( 'strtoupper', (array) $codes ) ) );
	}

	/**
	 * Build payment options string from dedicated amount/currency inputs or legacy textarea.
	 *
	 * @param array  $row         Raw field row.
	 * @param string $options_raw Existing options textarea value.
	 * @return string amount\\ncurrency
	 */
	public static function sanitize_payment_options( array $row, $options_raw ) {
		$allowed = self::payment_currency_options();
		$amount  = '';
		$currency = '';

		if ( array_key_exists( 'payment_amount', $row ) || array_key_exists( 'payment_currency', $row ) ) {
			$amount   = trim( (string) ( $row['payment_amount'] ?? '' ) );
			$currency = strtoupper( trim( (string) ( $row['payment_currency'] ?? '' ) ) );
		} else {
			$lines    = preg_split( '/\r\n|\r|\n/', (string) $options_raw );
			$amount   = is_array( $lines ) && isset( $lines[0] ) ? trim( (string) $lines[0] ) : '';
			$currency = is_array( $lines ) && isset( $lines[1] ) ? strtoupper( trim( (string) $lines[1] ) ) : '';
		}

		if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $amount ) ) {
			$amount = '9.99';
		}
		if ( ! in_array( $currency, $allowed, true ) ) {
			$currency = 'USD';
		}

		return $amount . "\n" . $currency;
	}

	/**
	 * @param string $raw Comma or newline separated extensions.
	 * @return string Normalized comma list.
	 */
	public static function sanitize_file_extensions( $raw ) {
		$raw = str_replace( array( "\r\n", "\r", "\n", ' ' ), ',', strtolower( (string) $raw ) );
		$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		$out   = array();
		foreach ( $parts as $part ) {
			$part = preg_replace( '/[^a-z0-9]/', '', $part );
			if ( ! is_string( $part ) || $part === '' || self::is_dangerous_file_extension( $part ) ) {
				continue;
			}
			$out[] = $part;
		}
		if ( array() === $out ) {
			return self::file_default_extensions();
		}
		return implode( ',', array_unique( $out ) );
	}

	/**
	 * @param string $raw Extension list.
	 * @return array<int, string>
	 */
	public static function parse_file_extensions( $raw ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', self::sanitize_file_extensions( $raw ) ) ) ) );
	}

	/**
	 * Allowed markup for HTML layout blocks.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function html_allowed_tags() {
		return array(
			'p'          => array( 'class' => true ),
			'br'         => array(),
			'hr'         => array( 'class' => true ),
			'strong'     => array(),
			'em'         => array(),
			'b'          => array(),
			'i'          => array(),
			'u'          => array(),
			'span'       => array( 'class' => true ),
			'div'        => array( 'class' => true ),
			'ul'         => array( 'class' => true ),
			'ol'         => array( 'class' => true ),
			'li'         => array( 'class' => true ),
			'h2'         => array( 'class' => true ),
			'h3'         => array( 'class' => true ),
			'h4'         => array( 'class' => true ),
			'h5'         => array( 'class' => true ),
			'blockquote' => array( 'class' => true ),
			'a'          => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'class'  => true,
				'width'  => true,
				'height' => true,
				'loading' => true,
			),
		);
	}
}
