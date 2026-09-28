<?php
/**
 * Front renderer + shortcode.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Renderer {

	/** @var bool */
	private static $assets_queued = false;

	public static function init() {
		add_shortcode( 'thimbleform', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'nestform', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'slug' => '',
			),
			$atts,
			'thimbleform'
		);

		$form_id = Nestform_Form_Config::resolve_form_id( $atts );
		if ( $form_id <= 0 ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="nest-form nest-form--missing">' . esc_html__( 'Thimbleform: form not found.', 'nestform' ) . '</p>';
			}
			return '';
		}

		return self::render( $form_id );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $args    Optional render args. `preview` (bool) marks admin preview submits.
	 * @return string
	 */
	public static function render( $form_id, $args = array() ) {
		$form_id = (int) $form_id;
		$args    = wp_parse_args(
			$args,
			array(
				'preview' => false,
			)
		);
		$is_preview = ! empty( $args['preview'] );
		$config  = Nestform_Form_Config::get( $form_id );

		/**
		 * After form config is loaded for render (Pro may detect field types).
		 *
		 * @param int                  $form_id Form ID.
		 * @param array<string, mixed> $config  Config.
		 */
		do_action( 'nestform_render_form', $form_id, $config );

		self::enqueue_front();

		$uid      = 'nest-form-' . $form_id . '-' . wp_unique_id();
		$settings = $config['settings'];
		$settings = Nestform_Form_Config::apply_feature_gates( $settings );
		$messages = $config['messages'];
		$fields   = self::filter_public_fields( Nestform_Form_Config::get_fields( $form_id ) );
		$form_class    = (string) apply_filters( 'nestform_form_class', 'nest-form', $settings, $fields );
		$style_classes = Nestform_Form_Config::style_form_classes( $settings );
		if ( array() !== $style_classes ) {
			$form_class .= ' ' . implode( ' ', $style_classes );
		}
		$style_inline = Nestform_Form_Config::style_inline_css( $settings );
		$has_file     = Nestform_Form_Config::has_file_field( $fields );

		ob_start();
		?>
		<form
			class="<?php echo esc_attr( $form_class ); ?>"
			<?php if ( $style_inline !== '' ) : ?>
				style="<?php echo esc_attr( $style_inline ); ?>"
			<?php endif; ?>
			id="<?php echo esc_attr( $uid ); ?>"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			<?php echo $has_file ? ' enctype="multipart/form-data"' : ''; ?>
			novalidate
			data-nest-form
			data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
			<?php if ( $is_preview ) : ?>
				data-nest-form-preview="1"
			<?php endif; ?>
			data-style-skin="<?php echo esc_attr( (string) ( $settings['style_skin'] ?? 'theme' ) ); ?>"
			data-redirect="<?php echo esc_attr( $settings['redirect_url'] ); ?>"
			data-success-display="<?php echo esc_attr( (string) ( $settings['success_display'] ?? 'inline' ) ); ?>"
			data-msg-required="<?php echo esc_attr( (string) ( $messages['required'] ?? '' ) ); ?>"
			data-msg-invalid-email="<?php echo esc_attr( (string) ( $messages['invalid_email'] ?? '' ) ); ?>"
			data-msg-invalid-tel="<?php echo esc_attr( (string) ( $messages['invalid_tel'] ?? '' ) ); ?>"
			data-msg-invalid-url="<?php echo esc_attr( (string) ( $messages['invalid_url'] ?? '' ) ); ?>"
			data-msg-invalid-number="<?php echo esc_attr( (string) ( $messages['invalid_number'] ?? '' ) ); ?>"
			data-msg-invalid-date="<?php echo esc_attr( (string) ( $messages['invalid_date'] ?? '' ) ); ?>"
			data-msg-invalid-time="<?php echo esc_attr( (string) ( $messages['invalid_time'] ?? '' ) ); ?>"
			data-msg-invalid-file="<?php echo esc_attr( (string) ( $messages['invalid_file'] ?? '' ) ); ?>"
			data-msg-file-too-large="<?php echo esc_attr( (string) ( $messages['file_too_large'] ?? '' ) ); ?>"
			data-msg-too-many-files="<?php echo esc_attr( (string) ( $messages['too_many_files'] ?? '' ) ); ?>"
			data-error-generic="<?php echo esc_attr( (string) ( $messages['error_generic'] ?? '' ) ); ?>"
			<?php
			$form_extra_attrs = (array) apply_filters(
				'nestform_form_html_attrs',
				array(),
				$form_id,
				array(
					'fields'   => $fields,
					'messages' => $messages,
					'settings' => $settings,
				)
			);
			foreach ( $form_extra_attrs as $attr_key => $attr_val ) {
				if ( ! is_string( $attr_key ) || $attr_key === '' ) {
					continue;
				}
				echo ' ' . esc_attr( $attr_key ) . '="' . esc_attr( (string) $attr_val ) . '"';
			}
			?>
		>
			<input type="hidden" name="action" value="nestform_submit" />
			<?php
			echo apply_filters( 'nestform_form_quiz_inputs', '', $form_id, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro returns escaped inputs.
			?>
			<div class="nest-form__result" data-nest-form-result hidden></div>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<input type="hidden" name="nestform_loaded_at" value="<?php echo esc_attr( (string) time() ); ?>" />
			<?php echo apply_filters( 'nestform_form_hidden_inputs', '', $form_id, $settings, $fields ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro returns escaped inputs. ?>
			<?php wp_nonce_field( 'nestform_submit_' . $form_id, 'nestform_nonce' ); ?>
			<?php if ( $is_preview ) : ?>
				<input type="hidden" name="nestform_preview" value="1" />
				<?php wp_nonce_field( 'nestform_preview_submit_' . $form_id, 'nestform_preview_nonce' ); ?>
			<?php endif; ?>
			<div class="nest-form__honeypot" aria-hidden="true">
				<label>
					<span><?php esc_html_e( 'Leave empty', 'nestform' ); ?></span>
					<input type="text" name="nestform_hp" value="" tabindex="-1" autocomplete="off" />
				</label>
			</div>

			<?php echo apply_filters( 'nestform_form_progress_html', '', $form_id, $settings, $fields ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro returns escaped markup. ?>

			<div class="nest-form__fields">
				<?php
				$fields_html = apply_filters( 'nestform_form_fields_html', null, $fields, $uid, $settings );
				if ( is_string( $fields_html ) ) {
					echo $fields_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro builds field markup via render_field().
				} else {
					foreach ( $fields as $field ) {
						echo self::render_field( $field, $uid, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
				?>
			</div>
			<?php
			$captcha_html = (string) apply_filters( 'nestform_captcha_html', '', $form_id, $config );
			if ( $captcha_html !== '' ) :
				$captcha_provider = '';
				if ( class_exists( 'Nestform_Captcha' ) && Nestform_Captcha::enabled_for_form( $form_id, $config ) ) {
					$captcha_provider = Nestform_Captcha::provider();
				}
				$captcha_class = 'nest-form__captcha';
				if ( 'recaptcha_v3' === $captcha_provider ) {
					$captcha_class .= ' nest-form__captcha--invisible';
				}
				$captcha_hidden = (bool) apply_filters( 'nestform_captcha_starts_hidden', false, $settings, $fields );
				?>
				<div class="<?php echo esc_attr( $captcha_class ); ?>"<?php echo $captcha_provider !== '' ? ' data-nest-form-captcha="' . esc_attr( $captcha_provider ) . '"' : ''; ?> data-nest-form-captcha-wrap<?php echo $captcha_hidden ? ' hidden' : ''; ?>>
					<?php echo wp_kses_post( $captcha_html ); ?>
				</div>
			<?php endif; ?>
			<div class="nest-form__actions">
				<?php
				$actions_html = apply_filters( 'nestform_form_actions_html', null, $settings, $fields );
				if ( is_string( $actions_html ) ) {
					echo $actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro returns escaped buttons.
				} else {
					?>
					<button type="submit" class="button button--primary nest-form__submit">
						<span class="nest-form__submit-spinner" aria-hidden="true"></span>
						<span class="nest-form__submit-label"><?php echo esc_html( $settings['submit_label'] ); ?></span>
					</button>
					<?php
				}
				?>
			</div>
			<div class="nest-form__status" data-nest-form-status role="status" aria-live="polite" aria-atomic="true" hidden></div>
		</form>
		<?php
		if ( class_exists( 'Nestform_Settings' ) ) {
			$credit = Nestform_Settings::credit_html();
			if ( $credit !== '' ) {
				echo $credit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in credit_html()
			}
		}
		$html = ob_get_clean();

		/**
		 * Filter rendered form HTML.
		 *
		 * @param string $html    Markup.
		 * @param int    $form_id Form ID.
		 * @param array  $config  Config.
		 */
		return (string) apply_filters( 'nestform_render_html', $html, $form_id, $config );
	}

	/**
	 * @param array  $field       Field config.
	 * @param string $uid         Form unique id.
	 * @param bool   $start_hidden Hide initially (other steps).
	 * @return string
	 */
	public static function render_field( array $field, $uid, $start_hidden = false ) {
		if ( ! Nestform_Form_Config::is_field_enabled( $field ) ) {
			return '';
		}

		$type = $field['type'];

		/**
		 * Short-circuit field render (Pro advanced widgets).
		 * Only applied when Advanced Fields capability is active.
		 *
		 * @param string|null          $html         Custom HTML or null to use core.
		 * @param array<string, mixed> $field        Field config.
		 * @param string               $uid          Form uid.
		 * @param bool                 $start_hidden Hidden initially.
		 */
		$custom = null;
		if ( class_exists( 'Nestform_Features' ) ) {
			$pro_cap = null;
			if ( 'calculated' === $type ) {
				$pro_cap = Nestform_Features::CALCULATED_FIELDS;
			} elseif ( 'repeater' === $type ) {
				$pro_cap = Nestform_Features::REPEATERS;
			} elseif ( 'payment' === $type ) {
				$pro_cap = Nestform_Features::PAYMENTS;
			}
			if ( null !== $pro_cap ) {
				if ( ! Nestform_Features::can( $pro_cap ) ) {
					return '';
				}
				$custom = apply_filters( 'nestform_render_field', null, $field, $uid, $start_hidden );
			} elseif ( Nestform_Features::can( Nestform_Features::ADVANCED_FIELDS ) ) {
				$custom = apply_filters( 'nestform_render_field', null, $field, $uid, $start_hidden );
			}
		}
		if ( is_string( $custom ) ) {
			return $custom;
		}

		$name  = $field['name'];
		$id    = $uid . '-' . $name;
		$width_ui = Nestform_Form_Config::field_width_presentation( $field );
		$width    = $width_ui['class'];
		$req   = ! empty( $field['required'] );
		$label = (string) $field['label'];
		$ph    = (string) $field['placeholder'];
		$desc  = (string) ( $field['description'] ?? '' );
		$def   = (string) ( $field['default'] ?? '' );
		$extra = sanitize_html_class( (string) ( $field['css_class'] ?? '' ) );
		$step  = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;

		$classes = array(
			'field',
			'nest-form__field',
			'nest-form__field--' . $type,
			'nest-form__field--' . $width,
		);
		if ( $req ) {
			$classes[] = 'nest-form__field--required';
		}
		if ( $extra !== '' ) {
			$classes[] = $extra;
		}

		$classes = (array) apply_filters( 'nestform_field_classes', $classes, $field, $uid );

		if ( Nestform_Form_Config::is_layout_field( $type ) ) {
			return self::render_layout_field( $field, $uid, $start_hidden );
		}

		ob_start();

		if ( 'hidden' === $type ) {
			printf(
				'<input type="hidden" class="nest-form__input" name="%1$s" id="%2$s" value="%3$s" data-field-step="%4$s" />',
				esc_attr( $name ),
				esc_attr( $id ),
				esc_attr( $def !== '' ? $def : $ph ),
				esc_attr( (string) $step )
			);
			$html = (string) ob_get_clean();
			return (string) apply_filters( 'nestform_field_html', $html, $field, $uid );
		}

		$condition_attrs = self::condition_data_attrs( $field );
		$width_style     = $width_ui['style'] !== '' ? ' style="' . esc_attr( $width_ui['style'] ) . '"' : '';
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-field-name="' . esc_attr( $name ) . '" data-field-step="' . esc_attr( (string) $step ) . '"' . $width_style . $condition_attrs . ( $start_hidden ? ' hidden' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- width_style escaped; condition_data_attrs() returns esc_attr()'d attributes.

		if ( in_array( $type, array( 'checkbox', 'acceptance' ), true ) ) {
			$check_label_class = 'checkbox-field nest-form__check';
			if ( $req ) {
				$check_label_class .= ' label--required';
			}
			echo '<label class="' . esc_attr( $check_label_class ) . '">';
			printf(
				'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s" id="%2$s" value="1"%3$s%4$s />',
				esc_attr( $name ),
				esc_attr( $id ),
				$req ? ' required' : '',
				( $def === '1' || $def === 'true' || $def === 'yes' ) ? ' checked' : ''
			);
			$label_html = $label !== '' ? $label : $name;
			if ( 'acceptance' === $type ) {
				$label_html = Nestform_Form_Config::sanitize_acceptance_label( $label_html );
			} else {
				$label_html = esc_html( $label_html );
			}
			echo '<span class="label nest-form__label">' . $label_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses/esc_html above.
			echo '</span></label>';
		} else {
			$show_label = ( $label !== '' || $req ) && ! in_array( $type, array( 'radio', 'checkboxes' ), true );
			if ( $show_label ) {
				$label_for    = 'select' === $type ? $id . '-trigger' : $id;
				$label_class  = 'label nest-form__label';
				if ( $req ) {
					$label_class .= ' label--required';
				}
				echo '<label class="' . esc_attr( $label_class ) . '" for="' . esc_attr( $label_for ) . '">';
				echo esc_html( $label !== '' ? $label : $name );
				echo '</label>';
			} elseif ( in_array( $type, array( 'radio', 'checkboxes' ), true ) && ( $label !== '' || $req ) ) {
				$legend_class = 'label nest-form__label';
				if ( $req ) {
					$legend_class .= ' label--required';
				}
				echo '<div class="' . esc_attr( $legend_class ) . '" id="' . esc_attr( $id . '-legend' ) . '">';
				echo esc_html( $label !== '' ? $label : $name );
				echo '</div>';
			}

			if ( 'textarea' === $type ) {
				printf(
					'<textarea class="textarea input nest-form__input nest-form__textarea" name="%1$s" id="%2$s" rows="5" placeholder="%3$s"%4$s>%5$s</textarea>',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $ph ),
					$req ? ' required' : '',
					esc_textarea( $def )
				);
			} elseif ( 'select' === $type ) {
				$choices = Nestform_Form_Config::parse_choice_lines( (string) $field['options'] );
				$allow_other = ! empty( $field['allow_other'] );
				$other_label = Nestform_Form_Config::other_choice_label( $field );
				$ph_text = $ph !== '' ? $ph : __( 'Select...', 'nestform' );
				$list_id = $id . '-list';
				$selected_label = $ph_text;
				$is_placeholder = ( $def === '' );
				foreach ( $choices as $choice ) {
					if ( $def === $choice['value'] ) {
						$selected_label = $choice['label'];
						$is_placeholder = false;
						break;
					}
				}
				echo '<div class="nest-form-select" data-nest-form-select' . ( $allow_other ? ' data-nest-form-allow-other' : '' ) . '>';
				echo '<select class="nest-form-select__native" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" data-nest-form-select-native' . ( $req ? ' required' : '' ) . ' tabindex="-1" aria-hidden="true">';
				echo '<option value="">' . esc_html( $ph_text ) . '</option>';
				foreach ( $choices as $choice ) {
					echo '<option value="' . esc_attr( $choice['value'] ) . '"' . selected( $def, $choice['value'], false ) . '>' . esc_html( $choice['label'] ) . '</option>';
				}
				if ( $allow_other ) {
					echo '<option value="' . esc_attr( Nestform_Form_Config::OTHER_VALUE ) . '">' . esc_html( $other_label ) . '</option>';
				}
				echo '</select>';
				printf(
					'<button type="button" class="select input nest-form-select__trigger nest-form__input" id="%1$s-trigger" role="combobox" aria-autocomplete="none" aria-haspopup="listbox" aria-expanded="false" aria-controls="%2$s" data-nest-form-select-trigger%3$s>',
					esc_attr( $id ),
					esc_attr( $list_id ),
					$req ? ' aria-required="true"' : ''
				);
				echo '<span class="nest-form-select__value' . ( $is_placeholder ? ' is-placeholder' : '' ) . '" data-nest-form-select-value data-placeholder="' . esc_attr( $ph_text ) . '">' . esc_html( $selected_label ) . '</span>';
				echo '<span class="nest-form-select__icon" aria-hidden="true"></span>';
				echo '</button>';
				echo '<ul class="nest-form-select__list" id="' . esc_attr( $list_id ) . '" role="listbox" hidden data-nest-form-select-list>';
				foreach ( $choices as $opt_i => $choice ) {
					$opt_id = $id . '-opt-' . (int) $opt_i;
					$sel    = ( $def === $choice['value'] );
					echo '<li class="nest-form-select__option" role="option" id="' . esc_attr( $opt_id ) . '" tabindex="-1" data-value="' . esc_attr( $choice['value'] ) . '" aria-selected="' . ( $sel ? 'true' : 'false' ) . '">' . esc_html( $choice['label'] ) . '</li>';
				}
				if ( $allow_other ) {
					echo '<li class="nest-form-select__option" role="option" id="' . esc_attr( $id . '-opt-other' ) . '" tabindex="-1" data-value="' . esc_attr( Nestform_Form_Config::OTHER_VALUE ) . '" aria-selected="false">' . esc_html( $other_label ) . '</li>';
				}
				echo '</ul>';
				echo '</div>';
				if ( $allow_other ) {
					printf(
						'<input type="text" class="input nest-form__input nest-form__other" name="%1$s__other" id="%2$s-other" value="" placeholder="%3$s" data-nest-form-other hidden autocomplete="off" />',
						esc_attr( $name ),
						esc_attr( $id ),
						esc_attr__( 'Please specify', 'nestform' )
					);
				}
			} elseif ( 'radio' === $type || 'checkboxes' === $type ) {
				$choices = Nestform_Form_Config::parse_choice_lines( (string) $field['options'] );
				$allow_other = ! empty( $field['allow_other'] );
				$other_label = Nestform_Form_Config::other_choice_label( $field );
				$defaults = array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', $def ) ?: array() ) );
				$group_role = 'radio' === $type ? 'radiogroup' : 'group';
				echo '<div class="nest-form__choices nest-form__choices--' . esc_attr( $type ) . '" role="' . esc_attr( $group_role ) . '" aria-labelledby="' . esc_attr( $id . '-legend' ) . '"' . ( $req ? ' data-required="1"' : '' ) . ' data-nest-form-choices' . ( $allow_other ? ' data-nest-form-allow-other' : '' ) . '>';
				foreach ( $choices as $opt_i => $choice ) {
					$opt_id  = $id . '-' . (int) $opt_i;
					$opt     = $choice['value'];
					$checked = in_array( $opt, $defaults, true ) || ( 'radio' === $type && $def === $opt );
					if ( 'radio' === $type ) {
						echo '<label class="radio-field nest-form__choice" for="' . esc_attr( $opt_id ) . '">';
						printf(
							'<input type="radio" class="radio nest-form__radio" name="%1$s" id="%2$s" value="%3$s"%4$s%5$s />',
							esc_attr( $name ),
							esc_attr( $opt_id ),
							esc_attr( $opt ),
							$checked ? ' checked' : '',
							( $req && 0 === $opt_i ) ? ' required' : ''
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $choice['label'] ) . '</span></label>';
					} else {
						echo '<label class="checkbox-field nest-form__choice" for="' . esc_attr( $opt_id ) . '">';
						printf(
							'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s[]" id="%2$s" value="%3$s"%4$s />',
							esc_attr( $name ),
							esc_attr( $opt_id ),
							esc_attr( $opt ),
							$checked ? ' checked' : ''
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $choice['label'] ) . '</span></label>';
					}
				}
				if ( $allow_other ) {
					$other_id = $id . '-other-choice';
					if ( 'radio' === $type ) {
						echo '<label class="radio-field nest-form__choice nest-form__choice--other" for="' . esc_attr( $other_id ) . '">';
						printf(
							'<input type="radio" class="radio nest-form__radio" name="%1$s" id="%2$s" value="%3$s" data-nest-form-other-trigger />',
							esc_attr( $name ),
							esc_attr( $other_id ),
							esc_attr( Nestform_Form_Config::OTHER_VALUE )
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $other_label ) . '</span></label>';
					} else {
						echo '<label class="checkbox-field nest-form__choice nest-form__choice--other" for="' . esc_attr( $other_id ) . '">';
						printf(
							'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s[]" id="%2$s" value="%3$s" data-nest-form-other-trigger />',
							esc_attr( $name ),
							esc_attr( $other_id ),
							esc_attr( Nestform_Form_Config::OTHER_VALUE )
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $other_label ) . '</span></label>';
					}
					printf(
						'<input type="text" class="input nest-form__input nest-form__other" name="%1$s__other" id="%2$s-other" value="" placeholder="%3$s" data-nest-form-other hidden autocomplete="off" />',
						esc_attr( $name ),
						esc_attr( $id ),
						esc_attr__( 'Please specify', 'nestform' )
					);
				}
				echo '</div>';
			} elseif ( 'file' === $type ) {
				$extensions = Nestform_Form_Config::parse_file_extensions( (string) ( $field['options'] ?? '' ) );
				$accept     = array();
				foreach ( $extensions as $ext ) {
					$accept[] = '.' . $ext;
				}
				$max_mb    = max( 1, min( 50, (int) ( $ph !== '' ? $ph : Nestform_Form_Config::file_default_max_mb() ) ) );
				$max_files = Nestform_Form_Config::file_max_count( $field );
				$multiple  = $max_files > 1;
				printf(
					'<input type="file" class="input nest-form__input nest-form__file" name="%1$s%6$s" id="%2$s"%3$s accept="%4$s" data-max-mb="%5$s" data-max-files="%7$s"%8$s />',
					esc_attr( $name ),
					esc_attr( $id ),
					$req ? ' required' : '',
					esc_attr( implode( ',', $accept ) ),
					esc_attr( (string) $max_mb ),
					$multiple ? '[]' : '',
					esc_attr( (string) $max_files ),
					$multiple ? ' multiple' : ''
				);
			} elseif ( 'tel' === $type && class_exists( 'Nestform_Phone' ) && Nestform_Phone::is_picker_enabled( $field ) ) {
				Nestform_Phone::render_field( $field, $id, $name, $req, $ph, $def );
			} elseif ( 'range' === $type ) {
				$range = Nestform_Form_Config::parse_range_options( (string) ( $field['options'] ?? '' ) );
				$val   = $def !== '' && is_numeric( $def ) ? $def : (string) $range['min'];
				printf(
					'<input type="range" class="input nest-form__input nest-form__range" name="%1$s" id="%2$s" min="%3$s" max="%4$s" step="%5$s" value="%6$s"%7$s />',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( (string) $range['min'] ),
					esc_attr( (string) $range['max'] ),
					esc_attr( (string) $range['step'] ),
					esc_attr( $val ),
					$req ? ' required' : ''
				);
			} else {
				$input_type = Nestform_Form_Config::html_input_type( $type );
				$autocomplete = 'on';
				if ( 'email' === $input_type ) {
					$autocomplete = 'email';
				} elseif ( 'url' === $input_type ) {
					$autocomplete = 'url';
				} elseif ( 'password' === $input_type ) {
					$autocomplete = 'new-password';
				} elseif ( 'tel' === $input_type ) {
					$autocomplete = 'tel';
				} elseif ( in_array( $input_type, array( 'date', 'time', 'number', 'range' ), true ) ) {
					$autocomplete = 'off';
				}
				printf(
					'<input type="%1$s" class="input nest-form__input%8$s" name="%2$s" id="%3$s" placeholder="%4$s" value="%5$s"%6$s autocomplete="%7$s" />',
					esc_attr( $input_type ),
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $ph ),
					esc_attr( $def ),
					$req ? ' required' : '',
					esc_attr( $autocomplete ),
					'password' === $input_type ? ' nest-form__input--password' : ''
				);
			}
		}

		if ( $desc !== '' ) {
			echo '<p class="field__hint nest-form__help">' . esc_html( $desc ) . '</p>';
		}

		echo '<p class="field__error nest-form__error" id="' . esc_attr( $id . '-error' ) . '" data-nest-form-error role="alert" hidden></p>';
		echo '</div>';

		$html = (string) ob_get_clean();

		/**
		 * Filter single field HTML.
		 *
		 * @param string $html  Markup.
		 * @param array  $field Field config.
		 * @param string $uid   Form uid.
		 */
		return (string) apply_filters( 'nestform_field_html', $html, $field, $uid );
	}

	/**
	 * Layout-only blocks (heading, image, HTML) -- not submitted.
	 *
	 * @param array  $field        Field config.
	 * @param string $uid          Form uid.
	 * @param bool   $start_hidden Hidden initially.
	 * @return string
	 */
	private static function render_layout_field( array $field, $uid, $start_hidden = false ) {
		$type  = (string) $field['type'];
		$name  = (string) $field['name'];
		$width_ui = Nestform_Form_Config::field_width_presentation( $field );
		$width    = $width_ui['class'];
		$label = (string) ( $field['label'] ?? '' );
		$desc  = (string) ( $field['description'] ?? '' );
		$extra = sanitize_html_class( (string) ( $field['css_class'] ?? '' ) );
		$step  = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;

		$classes = array(
			'nest-form__layout',
			'nest-form__layout--' . $type,
			'nest-form__field',
			'nest-form__field--' . $type,
			'nest-form__field--' . $width,
		);
		if ( $extra !== '' ) {
			$classes[] = $extra;
		}
		$classes = (array) apply_filters( 'nestform_field_classes', $classes, $field, $uid );

		ob_start();
		$width_style = $width_ui['style'] !== '' ? ' style="' . esc_attr( $width_ui['style'] ) . '"' : '';
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-nest-form-layout data-field-step="' . esc_attr( (string) $step ) . '"' . $width_style . ( $start_hidden ? ' hidden' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- width_style escaped.

		if ( 'heading' === $type ) {
			$level = (string) ( $field['options'] ?? 'h2' );
			if ( ! in_array( $level, array( 'h2', 'h3', 'h4' ), true ) ) {
				$level = 'h2';
			}
			$text = $label !== '' ? $label : $name;
			printf(
				'<%1$s class="nest-form__heading nest-form__heading--%2$s">%3$s</%1$s>',
				tag_escape( $level ),
				esc_attr( str_replace( 'h', '', $level ) ),
				esc_html( $text )
			);
		} elseif ( 'image' === $type ) {
			$attachment_id = max( 0, (int) ( $field['default'] ?? 0 ) );
			if ( $attachment_id > 0 ) {
				$alt = $desc !== '' ? $desc : $label;
				echo '<figure class="nest-form__figure">';
				echo wp_get_attachment_image(
					$attachment_id,
					'large',
					false,
					array(
						'class' => 'nest-form__image',
						'alt'   => $alt,
					)
				);
				if ( $label !== '' ) {
					echo '<figcaption class="nest-form__caption">' . esc_html( $label ) . '</figcaption>';
				}
				echo '</figure>';
			}
		} elseif ( 'paragraph' === $type ) {
			$text = (string) ( $field['options'] ?? '' );
			if ( $text !== '' ) {
				echo '<div class="nest-form__paragraph">' . nl2br( esc_html( $text ) ) . '</div>';
			}
		} elseif ( 'divider' === $type ) {
			echo '<hr class="nest-form__divider" />';
		} elseif ( 'spacer' === $type ) {
			$size = (string) ( $field['options'] ?? 'm' );
			if ( ! in_array( $size, array( 's', 'm', 'l' ), true ) ) {
				$size = 'm';
			}
			echo '<div class="nest-form__spacer nest-form__spacer--' . esc_attr( $size ) . '" aria-hidden="true"></div>';
		} elseif ( 'html' === $type ) {
			$content = (string) ( $field['options'] ?? '' );
			if ( $content !== '' ) {
				echo '<div class="nest-form__html">' . wp_kses( $content, Nestform_Form_Config::html_allowed_tags() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		echo '</div>';
		$html = (string) ob_get_clean();

		return (string) apply_filters( 'nestform_field_html', $html, $field, $uid );
	}

	/**
	 * @param array<string, mixed> $field Field.
	 * @return string HTML attributes (leading space when non-empty).
	 */
	private static function condition_data_attrs( array $field ) {
		$watch = isset( $field['condition_field'] ) ? (string) $field['condition_field'] : '';
		if ( $watch === '' ) {
			return '';
		}
		$op  = isset( $field['condition_op'] ) ? (string) $field['condition_op'] : 'equals';
		$val = isset( $field['condition_value'] ) ? (string) $field['condition_value'] : '';
		return sprintf(
			' data-condition-field="%1$s" data-condition-op="%2$s" data-condition-value="%3$s"',
			esc_attr( $watch ),
			esc_attr( $op ),
			esc_attr( $val )
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<int, array<string, mixed>>
	 */
	private static function filter_public_fields( array $fields ) {
		$out = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			if ( class_exists( 'Nestform_Features' ) && ! Nestform_Features::can_use_field_type( $type ) ) {
				continue;
			}
			$out[] = $field;
		}
		return $out;
	}

	public static function enqueue_front() {
		if ( self::$assets_queued ) {
			return;
		}
		self::$assets_queued = true;

		$css = nestform_front_css_path();
		$js  = nestform_front_js_path();
		wp_enqueue_style(
			'nestform-front',
			nestform_front_css_url(),
			array(),
			(string) filemtime( $css ) ?: NESTFORM_VERSION
		);
		wp_enqueue_script(
			'nestform-front',
			nestform_front_js_url(),
			array(),
			(string) filemtime( $js ) ?: NESTFORM_VERSION,
			true
		);
		wp_localize_script(
			'nestform-front',
			'nestform',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'close'         => __( 'Close', 'nestform' ),
					'successTitle'  => __( 'Thank you', 'nestform' ),
					'submitting'    => __( 'Sending…', 'nestform' ),
				),
			)
		);

		/**
		 * After core front assets are queued (Pro may enqueue widgets / trackers).
		 */
		do_action( 'nestform_enqueue_front' );
	}
}
