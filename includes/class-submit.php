<?php
/**
 * AJAX submit: validate, store, wp_mail.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Submit {

	const RATE_LIMIT_SECONDS = 60;

	public static function init() {
		add_action( 'wp_ajax_thimbleform_submit', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_thimbleform_submit', array( __CLASS__, 'handle' ) );
	}

	public static function handle() {
		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
		$pt = get_post_type( $form_id );
		if ( $form_id <= 0 || ! in_array( $pt, array( 'thimbleform', 'liteform', 'vite_form' ), true ) || 'publish' !== get_post_status( $form_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid form.', 'thimbleform' ),
				),
				400
			);
		}

		$nonce = isset( $_POST['thimbleform_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['thimbleform_nonce'] ) ) : '';
		if ( '' === $nonce && isset( $_POST['liteforms_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['liteforms_nonce'] ) );
		}
		if ( '' === $nonce && isset( $_POST['vite_forms_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['vite_forms_nonce'] ) );
		}
		if (
			! wp_verify_nonce( $nonce, 'thimbleform_submit_' . $form_id )
			&& ! wp_verify_nonce( $nonce, 'liteforms_submit_' . $form_id )
			&& ! wp_verify_nonce( $nonce, 'vite_forms_submit_' . $form_id )
		) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh and try again.', 'thimbleform' ),
				),
				403
			);
		}

		$config   = Thimbleform_Form_Config::get( $form_id );
		$messages = $config['messages'];
		$is_preview = self::is_preview_submit( $form_id );

		if ( class_exists( 'Thimbleform_Security' ) ) {
			Thimbleform_Security::set_upload_form_id( $form_id );
		}

		// Honeypot.
		$hp = isset( $_POST['thimbleform_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['thimbleform_hp'] ) ) : '';
		if ( '' === $hp && isset( $_POST['liteforms_hp'] ) ) {
			$hp = sanitize_text_field( wp_unslash( $_POST['liteforms_hp'] ) );
		}
		if ( '' === $hp && isset( $_POST['vite_forms_hp'] ) ) {
			$hp = sanitize_text_field( wp_unslash( $_POST['vite_forms_hp'] ) );
		}
		$ip = self::client_ip();

		if ( $hp !== '' ) {
			self::log_spam( $form_id, 'honeypot', '', $ip );
			wp_send_json_success(
				array(
					'message'  => $messages['success'],
					'redirect' => '',
				)
			);
		}

		// Time trap (too-fast bots).
		$trap_seconds = isset( $config['settings']['time_trap_seconds'] ) ? (int) $config['settings']['time_trap_seconds'] : 3;
		if ( $trap_seconds > 0 ) {
			$loaded_at = isset( $_POST['thimbleform_loaded_at'] ) ? (int) $_POST['thimbleform_loaded_at'] : 0;
			if ( $loaded_at <= 0 && isset( $_POST['liteforms_loaded_at'] ) ) {
				$loaded_at = (int) $_POST['liteforms_loaded_at'];
			}
			if ( $loaded_at <= 0 && isset( $_POST['vite_forms_loaded_at'] ) ) {
				$loaded_at = (int) $_POST['vite_forms_loaded_at'];
			}
			$elapsed   = time() - $loaded_at;
			if ( $loaded_at <= 0 || $elapsed < $trap_seconds ) {
				self::log_spam( $form_id, 'too_fast', '', $ip );
				wp_send_json_success(
					array(
						'message'  => $messages['success'],
						'redirect' => '',
					)
				);
			}
		}

		if ( ! $is_preview && class_exists( 'Thimbleform_Spam_Filter' ) && Thimbleform_Spam_Filter::is_blocked_ip( $ip ) ) {
			self::log_spam( $form_id, 'ip_blocked', '', $ip );
			wp_send_json_error(
				array(
					'message' => $messages['error_generic'],
				),
				403
			);
		}
		if ( ! $is_preview && self::is_rate_limited( $ip ) ) {
			self::log_spam( $form_id, 'rate_limited', '', $ip );
			wp_send_json_error(
				array(
					'message' => $messages['rate_limited'],
				),
				429
			);
		}

		if ( ! empty( $config['settings']['enable_akismet'] ) && '1' === (string) $config['settings']['enable_akismet'] ) {
			$akismet = self::check_akismet( $form_id, $config, $ip );
			if ( is_wp_error( $akismet ) ) {
				self::log_spam( $form_id, 'akismet', '', $ip );
				wp_send_json_error(
					array(
						'message' => $messages['error_generic'],
					),
					403
				);
			}
		}

		/**
		 * Captcha / bot checks before field validation.
		 * When Thimbleform captcha is enabled for the form, verification is always
		 * enforced here — a late filter returning true cannot bypass it.
		 * Custom captcha providers use thimbleform_verify_captcha when Thimbleform captcha is off.
		 *
		 * @param true|WP_Error $result Verification result.
		 * @param int           $form_id Form ID.
		 * @param array         $messages Form messages.
		 */
		if ( class_exists( 'Thimbleform_Captcha' ) && Thimbleform_Captcha::enabled_for_form( $form_id ) ) {
			$captcha = Thimbleform_Captcha::verify();
			if ( ! is_wp_error( $captcha ) ) {
				// Allow additional restrictions only (cannot skip Thimbleform verify).
				$extra = apply_filters( 'thimbleform_verify_captcha', true, $form_id, $messages );
				if ( is_wp_error( $extra ) ) {
					$captcha = $extra;
				} elseif ( false === $extra ) {
					$captcha = new WP_Error( 'thimbleform_captcha', $messages['invalid_captcha'] );
				}
			}
		} else {
			$captcha = apply_filters( 'thimbleform_verify_captcha', true, $form_id, $messages );
		}
		if ( is_wp_error( $captcha ) ) {
			self::log_spam( $form_id, 'captcha', '', $ip );
			wp_send_json_error(
				array(
					'message' => $captcha->get_error_message() ? $captcha->get_error_message() : $messages['invalid_captcha'],
				),
				403
			);
		}
		if ( true !== $captcha && false === $captcha ) {
			self::log_spam( $form_id, 'captcha', '', $ip );
			wp_send_json_error(
				array(
					'message' => $messages['invalid_captcha'],
				),
				403
			);
		}

		/**
		 * Fires before field validation (after captcha).
		 *
		 * @param int   $form_id Form ID.
		 * @param array $config  Config.
		 */
		do_action( 'thimbleform_before_validate', $form_id, $config );

		$validated = self::validate( $config['fields'], $messages, $config['settings'] );
		if ( is_wp_error( $validated ) ) {
			$errors = $validated->get_error_data();
			wp_send_json_error(
				array(
					'message' => $validated->get_error_message(),
					'errors'  => is_array( $errors ) ? $errors : array(),
				),
				422
			);
		}

		/** @var array<string, mixed> $data */
		$data = $validated;

		if ( ! $is_preview && class_exists( 'Thimbleform_Spam_Filter' ) ) {
			if ( Thimbleform_Spam_Filter::is_duplicate( $form_id, $data, $ip ) ) {
				self::log_spam( $form_id, 'duplicate', '', $ip );
				wp_send_json_error(
					array(
						'message' => $messages['rate_limited'],
					),
					429
				);
			}
			$content_hit = Thimbleform_Spam_Filter::check_content( $data );
			if ( is_array( $content_hit ) && ! empty( $content_hit['reason'] ) ) {
				self::log_spam(
					$form_id,
					(string) $content_hit['reason'],
					(string) ( $content_hit['detail'] ?? '' ),
					$ip
				);
				wp_send_json_error(
					array(
						'message' => $messages['error_generic'],
					),
					403
				);
			}
		}

		// Recalculate calculated fields server-side (Pro add-on).
		$data = apply_filters( 'thimbleform_apply_calculated_fields', $data, $config['fields'] );

		/**
		 * Filter sanitized entry payload before store/mail.
		 *
		 * @param array $data    Data.
		 * @param int   $form_id Form ID.
		 * @param array $config  Config.
		 */
		$data = (array) apply_filters( 'thimbleform_entry_data', $data, $form_id, $config );

		if ( $is_preview ) {
			self::send_preview_success( $form_id, $data, $config );
		}

		$store_ip = ! isset( $config['settings']['store_ip'] ) || '0' !== (string) $config['settings']['store_ip'];
		$entry_id = Thimbleform_Submissions::create( $form_id, $data, $store_ip ? $ip : '' );

		/**
		 * Fires after a successful form submission is stored.
		 *
		 * @param int                  $form_id  Form ID.
		 * @param array<string, mixed> $data     Sanitized data.
		 * @param int                  $entry_id Entry ID (0 if store failed).
		 */
		do_action( 'thimbleform_submitted', $form_id, $data, $entry_id );

		$mail_result = self::send_mail( $form_id, $data, $config['mail'] );

		/**
		 * Fires after wp_mail attempt (admin notification).
		 *
		 * @param int                  $form_id     Form ID.
		 * @param array<string, mixed> $data        Data.
		 * @param bool                 $mail_result Mail result.
		 */
		do_action( 'thimbleform_mail_sent', $form_id, $data, $mail_result );

		$extra_result = self::send_extra_mail( $form_id, $data, $config['mail'] );
		if ( null !== $extra_result ) {
			do_action( 'thimbleform_extra_mail_sent', $form_id, $data, $extra_result );
		}

		$user_mail_result = self::send_user_mail( $form_id, $data, $config['mail'] );
		if ( null !== $user_mail_result ) {
			/**
			 * Fires after visitor autoreply attempt.
			 *
			 * @param int   $form_id Form ID.
			 * @param array $data    Data.
			 * @param bool  $result  Mail result.
			 */
			do_action( 'thimbleform_user_mail_sent', $form_id, $data, $user_mail_result );
		}

		self::bump_rate_limit( $ip );
		if ( class_exists( 'Thimbleform_Spam_Filter' ) ) {
			Thimbleform_Spam_Filter::remember_submission( $form_id, $data, $ip );
		}

		$success_message = self::apply_success_merge_tags( (string) $messages['success'], $data, $form_id );
		/**
		 * Filter success message text before JSON response.
		 *
		 * @param string               $message  Message.
		 * @param array<string, mixed> $data     Entry data.
		 * @param int                  $form_id  Form ID.
		 * @param int                  $entry_id Entry ID.
		 */
		$success_message = (string) apply_filters( 'thimbleform_success_message', $success_message, $data, $form_id, $entry_id );

		$redirect = isset( $config['settings']['redirect_url'] ) ? (string) $config['settings']['redirect_url'] : '';
		$redirect = self::resolve_redirect_url( $redirect, $form_id, $data, $entry_id );

		$payload = array(
			'message'  => $success_message,
			'redirect' => $redirect,
			'entry_id' => $entry_id,
		);

		/**
		 * Filter AJAX success payload (Pro may add quiz result, share URL, …).
		 *
		 * @param array                $payload  Payload.
		 * @param array<string, mixed> $data     Entry data.
		 * @param int                  $form_id  Form ID.
		 * @param int                  $entry_id Entry ID.
		 * @param array                $config   Form config.
		 */
		$payload = (array) apply_filters( 'thimbleform_submit_success_data', $payload, $data, $form_id, $entry_id, $config );
		if ( isset( $payload['redirect'] ) && class_exists( 'Thimbleform_Security' ) ) {
			$payload['redirect'] = Thimbleform_Security::sanitize_redirect_url( (string) $payload['redirect'] );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Whether this POST is an authenticated admin form preview submit.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	private static function is_preview_submit( $form_id ) {
		if ( empty( $_POST['thimbleform_preview'] ) || '1' !== sanitize_text_field( wp_unslash( (string) $_POST['thimbleform_preview'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		$nonce = isset( $_POST['thimbleform_preview_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['thimbleform_preview_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'thimbleform_preview_submit_' . $form_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $form_id );
	}

	/**
	 * Return success JSON for admin preview submits (no entry, mail, or webhooks).
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Validated data.
	 * @param array<string, mixed> $config  Form config.
	 */
	private static function send_preview_success( $form_id, array $data, array $config ) {
		$messages        = $config['messages'];
		$success_message = self::apply_success_merge_tags( (string) $messages['success'], $data, $form_id );
		/**
		 * Filter preview success message (same hook as live submits).
		 *
		 * @param string               $message  Message.
		 * @param array<string, mixed> $data     Entry data.
		 * @param int                  $form_id  Form ID.
		 * @param int                  $entry_id Entry ID (always 0 in preview).
		 */
		$success_message = (string) apply_filters( 'thimbleform_success_message', $success_message, $data, $form_id, 0 );

		$payload = array(
			'message'  => $success_message,
			'redirect' => self::resolve_redirect_url( (string) ( $config['settings']['redirect_url'] ?? '' ), $form_id, $data, 0 ),
			'entry_id' => 0,
			'preview'  => true,
		);

		/**
		 * Filter preview AJAX success payload.
		 *
		 * @param array                $payload  Payload.
		 * @param array<string, mixed> $data     Entry data.
		 * @param int                  $form_id  Form ID.
		 * @param int                  $entry_id Entry ID (0).
		 * @param array                $config   Form config.
		 */
		$payload = (array) apply_filters( 'thimbleform_submit_success_data', $payload, $data, $form_id, 0, $config );
		if ( isset( $payload['redirect'] ) && class_exists( 'Thimbleform_Security' ) ) {
			$payload['redirect'] = Thimbleform_Security::sanitize_redirect_url( (string) $payload['redirect'] );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * @param array<int, array<string, mixed>> $fields   Fields.
	 * @param array<string, string>            $messages Messages.
	 * @param array<string, string>            $settings Settings.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate( array $fields, array $messages, array $settings = array() ) {
		$data         = array();
		$errors       = array();
		$raw_map      = self::collect_condition_values( $fields );
		$active_steps = Thimbleform_Form_Config::resolve_active_steps( $fields, $settings, $raw_map );

		foreach ( $fields as $field ) {
			$name = $field['name'];
			$type = $field['type'];

			if ( Thimbleform_Form_Config::is_layout_field( $type ) ) {
				continue;
			}

			if ( ! Thimbleform_Form_Config::is_field_enabled( $field ) ) {
				continue;
			}

			// Hidden values come from form config only (never from POST).
			if ( 'hidden' === $type ) {
				$data[ $name ] = sanitize_text_field( (string) ( $field['default'] ?? '' ) );
				continue;
			}

			// Ignore client-supplied visited steps; resolve path server-side (branch rules + values).
			if ( array() !== $active_steps ) {
				$field_step = isset( $field['step'] ) ? (int) $field['step'] : 1;
				if ( ! in_array( $field_step, $active_steps, true ) ) {
					continue;
				}
			}

			if ( ! Thimbleform_Form_Config::is_field_visible( $field, $raw_map ) ) {
				continue;
			}

			if ( 'file' === $type ) {
				$uploaded = self::handle_file_upload( $field, $messages );
				if ( is_wp_error( $uploaded ) ) {
					$errors[ $name ] = $uploaded->get_error_message();
					continue;
				}
				$data[ $name ] = $uploaded;
				continue;
			}

			$req = ! empty( $field['required'] );
			$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_array( $raw ) ) {
				$raw = map_deep( $raw, 'sanitize_text_field' );
			} else {
				$raw = sanitize_text_field( (string) ( $raw ?? '' ) );
			}

			/**
			 * Early validation for custom field types (Pro).
			 * Return array{ handled: true, value?: mixed, error?: string } to take over.
			 * Only applied when Advanced Fields capability is active.
			 *
			 * @param array|null           $result   Result or null.
			 * @param array<string, mixed> $field    Field config.
			 * @param mixed                $raw      Raw POST value.
			 * @param array<string, string> $messages Messages.
			 * @param bool                 $required Required.
			 */
			$early = null;
			if ( class_exists( 'Thimbleform_Features' ) ) {
				if (
					Thimbleform_Features::can( Thimbleform_Features::ADVANCED_FIELDS )
					|| Thimbleform_Features::can( Thimbleform_Features::REPEATERS )
					|| Thimbleform_Features::can( Thimbleform_Features::PAYMENTS )
				) {
					$early = apply_filters( 'thimbleform_pre_validate_field', null, $field, $raw, $messages, $req );
				}
			}
			if ( is_array( $early ) && ! empty( $early['handled'] ) ) {
				if ( ! empty( $early['error'] ) ) {
					$errors[ $name ] = (string) $early['error'];
				} else {
					$data[ $name ] = array_key_exists( 'value', $early ) ? $early['value'] : '';
				}
				continue;
			}

			if ( 'calculated' === $type ) {
				$data[ $name ] = '';
				continue;
			}

			if ( 'repeater' === $type ) {
				continue;
			}

			if ( in_array( $type, array( 'checkbox', 'acceptance' ), true ) ) {
				$checked = ! empty( $raw );
				if ( $req && ! $checked ) {
					$errors[ $name ] = $messages['required'];
					continue;
				}
				$data[ $name ] = $checked;
				continue;
			}

			if ( 'checkboxes' === $type ) {
				$options = Thimbleform_Form_Config::parse_choice_values( (string) ( $field['options'] ?? '' ) );
				if ( ! empty( $field['allow_other'] ) ) {
					$options[] = Thimbleform_Form_Config::OTHER_VALUE;
				}
				$picked  = array();
				if ( is_array( $raw ) ) {
					foreach ( $raw as $item ) {
						if ( ! is_string( $item ) ) {
							continue;
						}
						$item = trim( $item );
						if ( $item !== '' && in_array( $item, $options, true ) ) {
							$picked[] = sanitize_text_field( $item );
						}
					}
				}
				$picked = array_values( array_unique( $picked ) );
				if ( ! empty( $field['allow_other'] ) && in_array( Thimbleform_Form_Config::OTHER_VALUE, $picked, true ) ) {
					$other_text = self::posted_other_text( $name );
					if ( $other_text === '' ) {
						$errors[ $name ] = $messages['required'];
						continue;
					}
					$picked = array_map(
						static function ( $v ) use ( $other_text ) {
							return Thimbleform_Form_Config::OTHER_VALUE === $v ? $other_text : $v;
						},
						$picked
					);
				}
				if ( $req && array() === $picked ) {
					$errors[ $name ] = $messages['required'];
					continue;
				}
				$data[ $name ] = $picked;
				continue;
			}

			if ( 'tel' === $type && class_exists( 'Thimbleform_Phone' ) && Thimbleform_Phone::is_picker_enabled( $field ) ) {
				$iso_key      = $name . '__iso';
				$national_key = $name . '__national';
				$iso          = isset( $_POST[ $iso_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $iso_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$iso          = Thimbleform_Phone::sanitize_iso( $iso );
				$national     = isset( $_POST[ $national_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $national_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$posted       = is_string( $raw ) ? trim( $raw ) : '';
				$source       = $national !== '' ? $national : $posted;
				$e164         = Thimbleform_Phone::to_e164( $iso, $source );
				if ( '' === $e164 ) {
					if ( $req ) {
						$errors[ $name ] = $messages['required'];
					} else {
						$data[ $name ] = '';
					}
					continue;
				}
				if ( ! Thimbleform_Phone::is_valid_e164( $e164 ) ) {
					$errors[ $name ] = $messages['invalid_tel'];
					continue;
				}
				$data[ $name ] = sanitize_text_field( $e164 );
				continue;
			}

			$value = is_string( $raw ) ? trim( $raw ) : '';

			if ( $req && $value === '' ) {
				$errors[ $name ] = $messages['required'];
				continue;
			}

			if ( $value === '' ) {
				$data[ $name ] = '';
				continue;
			}

			if ( 'email' === $type ) {
				$email = sanitize_email( $value );
				if ( ! is_email( $email ) || false !== strpos( $email, ' ' ) ) {
					$errors[ $name ] = $messages['invalid_email'];
					continue;
				}
				$data[ $name ] = $email;
				continue;
			}

			if ( 'tel' === $type ) {
				$digits = preg_replace( '/\D+/', '', $value );
				$digits = is_string( $digits ) ? $digits : '';
				$len    = strlen( $digits );
				if ( $len < 7 || $len > 15 ) {
					$errors[ $name ] = $messages['invalid_tel'];
					continue;
				}
				$data[ $name ] = sanitize_text_field( $value );
				continue;
			}

			if ( 'url' === $type ) {
				$url = esc_url_raw( $value );
				if ( $url === '' || ! wp_http_validate_url( $url ) ) {
					$errors[ $name ] = $messages['invalid_url'] ?? $messages['error_generic'];
					continue;
				}
				$data[ $name ] = $url;
				continue;
			}

			if ( 'number' === $type || 'range' === $type ) {
				if ( ! is_numeric( $value ) ) {
					$errors[ $name ] = $messages['invalid_number'] ?? $messages['error_generic'];
					continue;
				}
				if ( 'range' === $type ) {
					$range = Thimbleform_Form_Config::parse_range_options( (string) ( $field['options'] ?? '' ) );
					$num   = (float) $value;
					if ( $num < $range['min'] || $num > $range['max'] ) {
						$errors[ $name ] = $messages['invalid_number'] ?? $messages['error_generic'];
						continue;
					}
				}
				$data[ $name ] = sanitize_text_field( $value );
				continue;
			}

			if ( 'date' === $type ) {
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					$errors[ $name ] = $messages['invalid_date'] ?? $messages['error_generic'];
					continue;
				}
				$parts = array_map( 'intval', explode( '-', $value ) );
				if ( count( $parts ) !== 3 || ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
					$errors[ $name ] = $messages['invalid_date'] ?? $messages['error_generic'];
					continue;
				}
				$data[ $name ] = $value;
				continue;
			}

			if ( 'time' === $type ) {
				if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
					$errors[ $name ] = $messages['invalid_time'] ?? $messages['error_generic'];
					continue;
				}
				$t = explode( ':', $value );
				$h = (int) $t[0];
				$m = (int) $t[1];
				$s = isset( $t[2] ) ? (int) $t[2] : 0;
				if ( $h > 23 || $m > 59 || $s > 59 ) {
					$errors[ $name ] = $messages['invalid_time'] ?? $messages['error_generic'];
					continue;
				}
				$data[ $name ] = sanitize_text_field( $value );
				continue;
			}

			if ( 'textarea' === $type ) {
				if ( strlen( $value ) > 10000 ) {
					$errors[ $name ] = $messages['error_generic'];
					continue;
				}
				$data[ $name ] = sanitize_textarea_field( $value );
				continue;
			}

			if ( 'password' === $type ) {
				if ( strlen( $value ) > 500 ) {
					$errors[ $name ] = $messages['error_generic'];
					continue;
				}
				$plain = sanitize_text_field( $value );
				/**
				 * Filter stored password field value (never keep plaintext by default).
				 *
				 * @param string               $stored Stored value.
				 * @param string               $plain  Plaintext password.
				 * @param array<string, mixed> $field  Field config.
				 */
				$data[ $name ] = (string) apply_filters( 'thimbleform_store_password_value', '[redacted]', $plain, $field );
				continue;
			}

			if ( 'text' === $type ) {
				if ( strlen( $value ) > 500 ) {
					$errors[ $name ] = $messages['error_generic'];
					continue;
				}
				$data[ $name ] = sanitize_text_field( $value );
				continue;
			}

			if ( 'select' === $type || 'radio' === $type ) {
				$options = Thimbleform_Form_Config::parse_choice_values( (string) ( $field['options'] ?? '' ) );
				if ( ! empty( $field['allow_other'] ) ) {
					$options[] = Thimbleform_Form_Config::OTHER_VALUE;
				}
				if ( ! in_array( $value, $options, true ) ) {
					$errors[ $name ] = $messages['error_generic'];
					continue;
				}
				if ( ! empty( $field['allow_other'] ) && Thimbleform_Form_Config::OTHER_VALUE === $value ) {
					$other_text = self::posted_other_text( $name );
					if ( $other_text === '' ) {
						$errors[ $name ] = $messages['required'];
						continue;
					}
					$data[ $name ] = $other_text;
					continue;
				}
				$data[ $name ] = sanitize_text_field( $value );
				continue;
			}

			$data[ $name ] = sanitize_text_field( $value );
		}

		foreach ( $fields as $field ) {
			$name = $field['name'];
			if ( ! array_key_exists( $name, $data ) || isset( $errors[ $name ] ) ) {
				continue;
			}
			/**
			 * Filter a single validated field value. Return WP_Error to fail that field.
			 *
			 * @param mixed $value    Value.
			 * @param array $field    Field config.
			 * @param array $messages Messages.
			 */
			$filtered = apply_filters( 'thimbleform_validate_field', $data[ $name ], $field, $messages );
			if ( is_wp_error( $filtered ) ) {
				$errors[ $name ] = $filtered->get_error_message();
				continue;
			}
			$data[ $name ] = $filtered;
		}

		if ( array() !== $errors ) {
			$error = new WP_Error( 'thimbleform_validation', $messages['error_generic'], $errors );
			return $error;
		}

		return $data;
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Data.
	 * @param array<string, string> $mail   Mail config.
	 * @return bool
	 */
	private static function send_mail( $form_id, array $data, array $mail ) {
		$to = array_filter( array_map( 'trim', explode( ',', $mail['to'] ) ) );
		$to = array_values( array_filter( $to, 'is_email' ) );
		if ( array() === $to ) {
			$admin = get_option( 'admin_email' );
			if ( is_string( $admin ) && is_email( $admin ) ) {
				$to = array( $admin );
			} else {
				return false;
			}
		}

		$is_html = class_exists( 'Thimbleform_Mail_Html' ) && Thimbleform_Mail_Html::is_html_mail( (string) ( $mail['body_template'] ?? '' ), $mail );
		$subject = self::replace_placeholders( $mail['subject'], $form_id, $data, false );
		$body    = self::replace_placeholders( $mail['body_template'], $form_id, $data, $is_html );

		$headers = array(
			$is_html
				? 'Content-Type: text/html; charset=UTF-8'
				: 'Content-Type: text/plain; charset=UTF-8',
		);
		$from_name  = $mail['from_name'] !== '' ? $mail['from_name'] : ( class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_name() : get_bloginfo( 'name' ) );
		$from_email = class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_email() : get_option( 'admin_email' );
		if ( is_string( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . self::format_from( $from_name, $from_email );
		}

		$reply_field = sanitize_key( $mail['reply_to_field'] );
		if ( $reply_field !== '' && ! empty( $data[ $reply_field ] ) && is_email( (string) $data[ $reply_field ] ) ) {
			$headers[] = 'Reply-To: ' . (string) $data[ $reply_field ];
		}

		foreach ( self::parse_email_list( (string) ( $mail['cc'] ?? '' ) ) as $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}
		foreach ( self::parse_email_list( (string) ( $mail['bcc'] ?? '' ) ) as $bcc ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		$attachments = array();
		/**
		 * Filter mail attachments (Pro PDF may add a file path).
		 * Only applied when PDF Export capability is active.
		 *
		 * @param array                $attachments Paths.
		 * @param int                  $form_id     Form ID.
		 * @param array<string, mixed> $data        Data.
		 * @param array                $mail        Mail config.
		 */
		if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::PDF_EXPORT ) ) {
			$attachments = (array) apply_filters( 'thimbleform_mail_attachments', $attachments, $form_id, $data, $mail );
		}

		/**
		 * Filter mail args before wp_mail.
		 *
		 * @param array $args Mail args.
		 * @param int   $form_id Form ID.
		 * @param array $data Data.
		 */
		$args = apply_filters(
			'thimbleform_mail_args',
			array(
				'to'          => $to,
				'subject'     => $subject,
				'body'        => $body,
				'headers'     => $headers,
				'attachments' => $attachments,
			),
			$form_id,
			$data
		);

		$attach = isset( $args['attachments'] ) && is_array( $args['attachments'] ) ? $args['attachments'] : array();
		if ( class_exists( 'Thimbleform_Email_Log' ) ) {
			return Thimbleform_Email_Log::send( 'admin', $args['to'], $args['subject'], $args['body'], $args['headers'], $attach, $form_id, 0 );
		}
		return (bool) wp_mail( $args['to'], $args['subject'], $args['body'], $args['headers'], $attach );
	}

	/**
	 * Conditional second admin notification.
	 *
	 * @param int                   $form_id Form ID.
	 * @param array<string, mixed>  $data    Data.
	 * @param array<string, string> $mail    Mail config.
	 * @return bool|null
	 */
	private static function send_extra_mail( $form_id, array $data, array $mail ) {
		if ( empty( $mail['extra_enabled'] ) || '1' !== (string) $mail['extra_enabled'] ) {
			return null;
		}

		$probe = array(
			'condition_field' => (string) ( $mail['extra_condition_field'] ?? '' ),
			'condition_op'    => (string) ( $mail['extra_condition_op'] ?? 'equals' ),
			'condition_value' => (string) ( $mail['extra_condition_value'] ?? '' ),
		);
		if ( $probe['condition_field'] !== '' && ! Thimbleform_Form_Config::is_field_visible( $probe, $data ) ) {
			return null;
		}

		$to = self::parse_email_list( (string) ( $mail['extra_to'] ?? '' ) );
		if ( array() === $to ) {
			return null;
		}

		$subject = self::replace_placeholders( (string) ( $mail['extra_subject'] ?? '' ), $form_id, $data, false );
		$body    = self::replace_placeholders( (string) ( $mail['extra_body'] ?? '' ), $form_id, $data, class_exists( 'Thimbleform_Mail_Html' ) && Thimbleform_Mail_Html::is_html_mail( (string) ( $mail['extra_body'] ?? '' ), $mail ) );
		if ( $subject === '' || $body === '' ) {
			return null;
		}

		$is_html = class_exists( 'Thimbleform_Mail_Html' ) && Thimbleform_Mail_Html::is_html_mail( $body, $mail );
		$headers   = array(
			$is_html
				? 'Content-Type: text/html; charset=UTF-8'
				: 'Content-Type: text/plain; charset=UTF-8',
		);
		$from_name  = $mail['from_name'] !== '' ? $mail['from_name'] : ( class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_name() : get_bloginfo( 'name' ) );
		$from_email = class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_email() : get_option( 'admin_email' );
		if ( is_string( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . self::format_from( $from_name, $from_email );
		}

		$args = apply_filters(
			'thimbleform_extra_mail_args',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			),
			$form_id,
			$data
		);

		return class_exists( 'Thimbleform_Email_Log' )
			? Thimbleform_Email_Log::send( 'extra', $args['to'], $args['subject'], $args['body'], $args['headers'], array(), $form_id, 0 )
			: (bool) wp_mail( $args['to'], $args['subject'], $args['body'], $args['headers'] );
	}

	/**
	 * Optional confirmation mail to the visitor (Reply-To field).
	 *
	 * @param int                   $form_id Form ID.
	 * @param array<string, mixed>  $data    Data.
	 * @param array<string, string> $mail    Mail config.
	 * @return bool|null Null when disabled / no recipient.
	 */
	private static function send_user_mail( $form_id, array $data, array $mail ) {
		if ( empty( $mail['user_mail_enabled'] ) || '1' !== (string) $mail['user_mail_enabled'] ) {
			return null;
		}

		$reply_field = sanitize_key( (string) ( $mail['reply_to_field'] ?? 'email' ) );
		$to          = ( $reply_field !== '' && ! empty( $data[ $reply_field ] ) ) ? (string) $data[ $reply_field ] : '';
		if ( ! is_email( $to ) ) {
			return null;
		}

		$subject = self::replace_placeholders( (string) ( $mail['user_mail_subject'] ?? '' ), $form_id, $data, false );
		$body    = self::replace_placeholders( (string) ( $mail['user_mail_body'] ?? '' ), $form_id, $data, class_exists( 'Thimbleform_Mail_Html' ) && Thimbleform_Mail_Html::is_html_mail( (string) ( $mail['user_mail_body'] ?? '' ), $mail ) );
		if ( $subject === '' || $body === '' ) {
			return null;
		}

		$is_html = class_exists( 'Thimbleform_Mail_Html' ) && Thimbleform_Mail_Html::is_html_mail( $body, $mail );
		$headers   = array(
			$is_html
				? 'Content-Type: text/html; charset=UTF-8'
				: 'Content-Type: text/plain; charset=UTF-8',
		);
		$from_name  = $mail['from_name'] !== '' ? $mail['from_name'] : ( class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_name() : get_bloginfo( 'name' ) );
		$from_email = class_exists( 'Thimbleform_Settings' ) ? Thimbleform_Settings::mail_from_email() : get_option( 'admin_email' );
		if ( is_string( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . self::format_from( $from_name, $from_email );
		}

		/**
		 * Filter user autoreply mail args.
		 *
		 * @param array $args    Args.
		 * @param int   $form_id Form ID.
		 * @param array $data    Data.
		 */
		$args = apply_filters(
			'thimbleform_user_mail_args',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			),
			$form_id,
			$data
		);

		return class_exists( 'Thimbleform_Email_Log' )
			? Thimbleform_Email_Log::send( 'user', $args['to'], $args['subject'], $args['body'], $args['headers'], array(), $form_id, 0 )
			: (bool) wp_mail( $args['to'], $args['subject'], $args['body'], $args['headers'] );
	}

	/**
	 * @param string               $template Template.
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $data     Data.
	 * @param bool                 $html     HTML mode.
	 * @return string
	 */
	private static function replace_placeholders( $template, $form_id, array $data, $html = false ) {
		$form_title = get_the_title( $form_id );
		$template   = (string) $template;

		if ( class_exists( 'Thimbleform_Mail_Html' ) ) {
			$template = Thimbleform_Mail_Html::expand_loops( $template, $data, $html );
			$all      = Thimbleform_Mail_Html::format_all_fields( $data, $html );
			$template = Thimbleform_Mail_Html::replace_simple_tokens( $template, $data, $html );
		} else {
			$all_lines = array();
			foreach ( $data as $key => $value ) {
				$display     = self::format_data_value( $value );
				$all_lines[] = $key . ': ' . $display;
				$template    = str_replace( '{' . $key . '}', $display, $template );
			}
			$all = implode( "\n", $all_lines );
		}

		$replacements = array(
			'{all_fields}' => $all,
			'{form_title}' => $html ? esc_html( $form_title ) : $form_title,
			'{form_id}'    => (string) (int) $form_id,
		);
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Recalculate calculated fields from formulas.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @param array<string, mixed>             $data   Data.
	 * @return array<string, mixed>
	 */
	

	/**
	 * Validate a repeater field (supports nested repeaters).
	 *
	 * @param array<string, mixed>  $field    Field.
	 * @param mixed                 $raw      Raw POST.
	 * @param array<string, string> $messages Messages.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	

	/**
	 * @param string $name Field name.
	 * @return string
	 */
	private static function posted_other_text( $name ) {
		$key = $name . '__other';
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_string( $raw ) ? sanitize_text_field( trim( $raw ) ) : '';
	}

	/**
	 * Replace {field_name} tokens in the success message.
	 *
	 * @param string               $template Message.
	 * @param array<string, mixed> $data     Entry data.
	 * @param int                  $form_id  Form ID.
	 * @return string
	 */
	private static function apply_success_merge_tags( $template, array $data, $form_id ) {
		$form = get_post( (int) $form_id );
		$replacements = array(
			'{form_title}' => $form ? $form->post_title : '',
			'{form_id}'    => (string) (int) $form_id,
		);
		foreach ( $data as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = self::format_data_value( $value );
		}
		return str_replace( array_keys( $replacements ), array_values( $replacements ), (string) $template );
	}

	/**
	 * Resolve redirect URL template with entry field merge tags (URL-encoded values).
	 *
	 * Example: https://game.example/play?email={email}
	 *
	 * @param string               $template Redirect template.
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $data     Entry data.
	 * @param int                  $entry_id Entry ID (0 in preview).
	 * @return string
	 */
	public static function resolve_redirect_url( $template, $form_id, array $data, $entry_id = 0 ) {
		$template = trim( (string) $template );
		if ( $template === '' ) {
			return '';
		}

		$form = get_post( (int) $form_id );
		$replacements = array(
			'{form_title}' => rawurlencode( $form ? (string) $form->post_title : '' ),
			'{form_id}'    => (string) (int) $form_id,
			'{entry_id}'   => (string) (int) $entry_id,
		);

		$field_keys = array_keys( $data );
		usort(
			$field_keys,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		foreach ( $field_keys as $key ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}
			$replacements[ '{' . $key . '}' ] = rawurlencode( self::format_data_value_for_redirect( $data[ $key ] ?? '' ) );
		}

		$url = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
		$url = (string) preg_replace( '/\{[a-zA-Z0-9_]+\}/', '', $url );

		/**
		 * Filter resolved redirect URL after merge tags are applied.
		 *
		 * @param string               $url      Resolved URL (not yet re-sanitized).
		 * @param string               $template Original template.
		 * @param int                  $form_id  Form ID.
		 * @param array<string, mixed> $data     Entry data.
		 * @param int                  $entry_id Entry ID.
		 */
		$url = (string) apply_filters( 'thimbleform_redirect_url', $url, $template, $form_id, $data, $entry_id );

		if ( class_exists( 'Thimbleform_Security' ) ) {
			return Thimbleform_Security::sanitize_redirect_url( $url );
		}
		return esc_url_raw( $url );
	}

	/**
	 * @param mixed $value Field value.
	 * @return string Plain text for redirect query/path segments.
	 */
	private static function format_data_value_for_redirect( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			return (string) $value['url'];
		}
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
			return implode( ',', $flat );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param mixed $value Field value.
	 * @return string
	 */
	private static function format_data_value( $value ) {
		if ( class_exists( 'Thimbleform_Mail_Html' ) ) {
			return Thimbleform_Mail_Html::format_value( $value, false );
		}
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			$name = ! empty( $value['name'] ) ? (string) $value['name'] : 'file';
			return $name . ' (' . (string) $value['url'] . ')';
		}
		if ( is_array( $value ) && isset( $value['intent_id'], $value['amount'], $value['currency'] ) ) {
			$line = sprintf(
				/* translators: 1: amount, 2: currency */
				__( 'Paid %1$s %2$s', 'thimbleform' ),
				(string) $value['amount'],
				(string) $value['currency']
			);
			if ( ! empty( $value['mode'] ) && 'test' === (string) $value['mode'] ) {
				$line .= ' · ' . __( 'test', 'thimbleform' );
			}
			if ( ! empty( $value['intent_id'] ) ) {
				$line .= ' (' . (string) $value['intent_id'] . ')';
			}
			return $line;
		}
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) && ! empty( $item['url'] ) ) {
					$n = ! empty( $item['name'] ) ? (string) $item['name'] : 'file';
					$flat[] = $n . ' (' . (string) $item['url'] . ')';
				} elseif ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
			return implode( ', ', $flat );
		}
		return (string) $value;
	}

	/**
	 * @param string $raw Options textarea.
	 * @return array<int, string>
	 */
	private static function parse_choice_options( $raw ) {
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
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<string, mixed>  $field    Field config.
	 * @param array<string, string> $messages Messages.
	 * @return array<string, mixed>|array<int, array<string, mixed>>|string|WP_Error
	 */
	private static function handle_file_upload( array $field, array $messages ) {
		$name      = (string) $field['name'];
		$req       = ! empty( $field['required'] );
		$max_count = Thimbleform_Form_Config::file_max_count( $field );
		$file      = isset( $_FILES[ $name ] ) ? $_FILES[ $name ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $file ) || empty( $file['name'] ) ) {
			if ( $req ) {
				return new WP_Error( 'required', $messages['required'] );
			}
			return '';
		}

		$bag = array();
		if ( is_array( $file['name'] ) ) {
			$count = count( $file['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( empty( $file['name'][ $i ] ) ) {
					continue;
				}
				$bag[] = array(
					'name'     => $file['name'][ $i ],
					'type'     => $file['type'][ $i ] ?? '',
					'tmp_name' => $file['tmp_name'][ $i ] ?? '',
					'error'    => $file['error'][ $i ] ?? UPLOAD_ERR_NO_FILE,
					'size'     => $file['size'][ $i ] ?? 0,
				);
			}
		} else {
			$bag[] = $file;
		}

		if ( array() === $bag ) {
			if ( $req ) {
				return new WP_Error( 'required', $messages['required'] );
			}
			return '';
		}

		if ( count( $bag ) > $max_count ) {
			return new WP_Error( 'too_many_files', $messages['too_many_files'] ?? $messages['error_generic'] );
		}

		$uploaded = array();
		foreach ( $bag as $one ) {
			$one_result = self::store_single_upload( $one, $field, $messages );
			if ( is_wp_error( $one_result ) ) {
				return $one_result;
			}
			$uploaded[] = $one_result;
		}

		return 1 === count( $uploaded ) ? $uploaded[0] : $uploaded;
	}

	/**
	 * @param array<string, mixed>  $file     Single $_FILES row.
	 * @param array<string, mixed>  $field    Field.
	 * @param array<string, string> $messages Messages.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function store_single_upload( array $file, array $field, array $messages ) {
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $file['error'] ) {
			if ( UPLOAD_ERR_INI_SIZE === (int) $file['error'] || UPLOAD_ERR_FORM_SIZE === (int) $file['error'] ) {
				return new WP_Error( 'file_too_large', $messages['file_too_large'] ?? $messages['error_generic'] );
			}
			return new WP_Error( 'invalid_file', $messages['invalid_file'] ?? $messages['error_generic'] );
		}

		$max_mb    = max( 1, min( 50, (int) ( $field['placeholder'] ?? Thimbleform_Form_Config::file_default_max_mb() ) ) );
		$max_bytes = $max_mb * 1024 * 1024;
		if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
			return new WP_Error( 'file_too_large', $messages['file_too_large'] ?? $messages['error_generic'] );
		}

		$allowed_ext = Thimbleform_Form_Config::parse_file_extensions( (string) ( $field['options'] ?? '' ) );
		$check       = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$file_ext    = isset( $check['ext'] ) ? strtolower( (string) $check['ext'] ) : '';
		if ( $file_ext === '' || ! in_array( $file_ext, $allowed_ext, true ) ) {
			return new WP_Error( 'invalid_file', $messages['invalid_file'] ?? $messages['error_generic'] );
		}
		if ( Thimbleform_Form_Config::is_dangerous_file_extension( $file_ext ) ) {
			return new WP_Error( 'invalid_file', $messages['invalid_file'] ?? $messages['error_generic'] );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$mimes = array();
		foreach ( $allowed_ext as $ext ) {
			$type = wp_check_filetype( 'file.' . $ext );
			if ( ! empty( $type['type'] ) ) {
				$mimes[ $ext ] = $type['type'];
			}
		}

		$do_upload = static function () use ( $file, $mimes ) {
			return wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => $mimes ? $mimes : null,
				)
			);
		};

		$upload = class_exists( 'Thimbleform_Security' )
			? Thimbleform_Security::with_private_uploads( $do_upload )
			: $do_upload();

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'invalid_file', $messages['invalid_file'] ?? $messages['error_generic'] );
		}

		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$filetype   = wp_check_filetype( basename( $upload['file'] ), null );
		$attachment = array(
			'post_mime_type' => $filetype['type'] ?? $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( ! is_wp_error( $attach_id ) && $attach_id ) {
			$metadata = wp_generate_attachment_metadata( (int) $attach_id, $upload['file'] );
			wp_update_attachment_metadata( (int) $attach_id, $metadata );
		} else {
			$attach_id = 0;
		}

		if ( class_exists( 'Thimbleform_Security' ) && $attach_id > 0 ) {
			return Thimbleform_Security::finalize_private_attachment(
				(int) $attach_id,
				(string) $upload['file'],
				(string) ( $upload['type'] ?? '' ),
				(int) ( $file['size'] ?? 0 )
			);
		}

		return array(
			'id'   => (int) $attach_id,
			'url'  => (string) ( $upload['url'] ?? '' ),
			'name' => basename( $upload['file'] ),
			'size' => (int) ( $file['size'] ?? 0 ),
			'type' => (string) ( $upload['type'] ?? '' ),
		);
	}

	/**
	 * @param string $raw Comma-separated emails.
	 * @return array<int, string>
	 */
	private static function parse_email_list( $raw ) {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
		return array_values( array_filter( $parts, 'is_email' ) );
	}

	/**
	 * Optional Akismet check when plugin is active.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $config  Config.
	 * @param string               $ip      IP.
	 * @return true|WP_Error
	 */
	private static function check_akismet( $form_id, array $config, $ip ) {
		if ( ! class_exists( 'Akismet' ) || ! method_exists( 'Akismet', 'http_post' ) ) {
			return true;
		}
		$api_key = apply_filters( 'thimbleform_akismet_key', defined( 'WPCOM_API_KEY' ) ? WPCOM_API_KEY : get_option( 'wordpress_api_key' ) );
		if ( ! is_string( $api_key ) || $api_key === '' ) {
			return true;
		}

		$content_parts = array();
		$author        = '';
		$email         = '';
		foreach ( (array) ( $config['fields'] ?? array() ) as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			$type = (string) ( $field['type'] ?? '' );
			if ( $name === '' || Thimbleform_Form_Config::is_layout_field( $type ) ) {
				continue;
			}
			$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_array( $raw ) ) {
				$raw = implode( ' ', array_map( 'strval', $raw ) );
			}
			$raw = sanitize_text_field( (string) $raw );
			if ( $raw === '' ) {
				continue;
			}
			$content_parts[] = $raw;
			if ( 'email' === $type && is_email( $raw ) ) {
				$email = $raw;
			}
			if ( in_array( $name, array( 'name', 'full_name', 'fio' ), true ) && $author === '' ) {
				$author = $raw;
			}
		}

		$query = array(
			'blog'                 => home_url( '/' ),
			'user_ip'              => $ip,
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'comment_type'         => 'contact-form',
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_content'      => implode( "\n", $content_parts ),
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_option( 'blog_charset' ),
		);

		$response = Akismet::http_post( build_query( $query ), 'comment-check' );
		if ( ! empty( $response[1] ) && 'true' === trim( (string) $response[1] ) ) {
			return new WP_Error( 'akismet_spam', 'spam' );
		}
		return true;
	}

	/**
	 * Collect raw POST values used only for conditional visibility checks.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<string, mixed>
	 */
	private static function collect_condition_values( array $fields ) {
		$map = array();
		foreach ( $fields as $field ) {
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( $name === '' || Thimbleform_Form_Config::is_layout_field( $type ) || 'file' === $type ) {
				continue;
			}
			$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( in_array( $type, array( 'checkbox', 'acceptance' ), true ) ) {
				$map[ $name ] = ! empty( $raw );
				continue;
			}
			if ( 'checkboxes' === $type ) {
				if ( is_array( $raw ) ) {
					$map[ $name ] = array_map( 'sanitize_text_field', array_map( 'strval', $raw ) );
				} else {
					$map[ $name ] = array();
				}
				continue;
			}
			if ( 'tel' === $type && class_exists( 'Thimbleform_Phone' ) && Thimbleform_Phone::is_picker_enabled( $field ) ) {
				$iso_key      = $name . '__iso';
				$national_key = $name . '__national';
				$iso          = isset( $_POST[ $iso_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $iso_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$national     = isset( $_POST[ $national_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $national_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$posted       = is_string( $raw ) ? trim( $raw ) : '';
				$source       = $national !== '' ? $national : $posted;
				$map[ $name ] = Thimbleform_Phone::to_e164( Thimbleform_Phone::sanitize_iso( $iso ), $source );
				continue;
			}
			if ( is_array( $raw ) ) {
				$map[ $name ] = array_map( 'sanitize_text_field', array_map( 'strval', $raw ) );
			} else {
				$map[ $name ] = sanitize_text_field( (string) ( $raw ?? '' ) );
			}
		}
		return $map;
	}

	/**
	 * @param string $name  Name.
	 * @param string $email Email.
	 * @return string
	 */
	private static function format_from( $name, $email ) {
		$name = str_replace( array( "\r", "\n", ':' ), '', $name );
		return sprintf( '%s <%s>', $name, $email );
	}

	/**
	 * Record a blocked attempt when the spam log is available.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $reason  Reason key.
	 * @param string $detail  Optional detail.
	 * @param string $ip      Client IP.
	 */
	private static function log_spam( $form_id, $reason, $detail = '', $ip = '' ) {
		if ( class_exists( 'Thimbleform_Spam_Log' ) ) {
			Thimbleform_Spam_Log::record( (int) $form_id, (string) $reason, (string) $detail, (string) $ip );
		}
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip;
	}

	/**
	 * @param string $ip IP.
	 * @return bool
	 */
	private static function is_rate_limited( $ip ) {
		if ( class_exists( 'Thimbleform_Spam_Filter' ) ) {
			return Thimbleform_Spam_Filter::is_rate_limited( $ip );
		}
		$bucket = $ip !== '' ? $ip : 'unknown';
		$key    = 'thimbleform_rl_' . md5( $bucket );
		return (bool) get_transient( $key );
	}

	/**
	 * @param string $ip IP.
	 */
	private static function bump_rate_limit( $ip ) {
		if ( class_exists( 'Thimbleform_Spam_Filter' ) ) {
			Thimbleform_Spam_Filter::bump_rate_limit( $ip );
			return;
		}
		$bucket = $ip !== '' ? $ip : 'unknown';
		$key    = 'thimbleform_rl_' . md5( $bucket );
		set_transient( $key, 1, self::RATE_LIMIT_SECONDS );
	}
}
