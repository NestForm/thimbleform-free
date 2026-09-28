<?php
/**
 * Admin meta boxes for form builder.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Admin_UI {

	const NONCE = 'thimbleform_save_config';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_default_boxes' ), 40 );
		add_action( 'save_post_' . Thimbleform_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'editor_title_actions' ) );
		add_action( 'wp_ajax_thimbleform_preview', array( __CLASS__, 'ajax_preview' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'enter_title_here' ), 10, 2 );
	}

	/**
	 * @param string  $text Placeholder.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function enter_title_here( $text, $post ) {
		if ( $post && Thimbleform_Post_Type::POST_TYPE === $post->post_type ) {
			return __( 'Form name', 'thimbleform' );
		}
		return $text;
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Thimbleform_Post_Type::POST_TYPE === $screen->post_type ) {
			$classes .= ' thimbleform-admin-screen';
		}
		return $classes;
	}

	/**
	 * Save / publish next to the form title (Make editor chrome).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function editor_title_actions( $post ) {
		if ( ! $post || Thimbleform_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}
		$status = get_post_status( $post );
		?>
		<div class="thimbleform-editor__title-actions" data-thimbleform-title-actions>
			<span class="thimbleform-editor__dirty" data-thimbleform-dirty hidden><?php esc_html_e( 'Unsaved', 'thimbleform' ); ?></span>
			<button type="button" class="thimbleform-btn thimbleform-btn--outline thimbleform-editor__title-action" data-thimbleform-preview>
				<?php thimbleform_admin_icon( 'preview' ); ?>
				<?php esc_html_e( 'Preview', 'thimbleform' ); ?>
			</button>
			<?php if ( 'publish' === $status ) : ?>
				<button type="submit" class="thimbleform-btn thimbleform-btn--primary thimbleform-editor__title-action" name="save" value="Save" data-thimbleform-save>
					<?php thimbleform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Save', 'thimbleform' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" class="thimbleform-btn thimbleform-btn--warn thimbleform-editor__title-action" name="saveasdraft" value="1" data-thimbleform-save>
					<?php thimbleform_admin_icon( 'draft' ); ?>
					<?php esc_html_e( 'Save draft', 'thimbleform' ); ?>
				</button>
				<button type="submit" class="thimbleform-btn thimbleform-btn--primary thimbleform-editor__title-action" name="publish" value="Publish" data-thimbleform-save>
					<?php thimbleform_admin_icon( 'publish' ); ?>
					<?php esc_html_e( 'Publish', 'thimbleform' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function remove_default_boxes() {
		$pt = Thimbleform_Post_Type::POST_TYPE;
		$boxes = array(
			'slugdiv',
			'submitdiv',
			'authordiv',
			'revisionsdiv',
			'commentstatusdiv',
			'commentsdiv',
			'trackbacksdiv',
			'postcustom',
			'postexcerpt',
			'pageparentdiv',
		);
		foreach ( $boxes as $box ) {
			remove_meta_box( $box, $pt, 'normal' );
			remove_meta_box( $box, $pt, 'side' );
			remove_meta_box( $box, $pt, 'advanced' );
		}
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Thimbleform_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$ver_css = (string) filemtime( thimbleform_admin_css_path() );
		$ver_js  = (string) filemtime( thimbleform_admin_js_path( 'admin.js' ) );

		wp_enqueue_media();
		if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::EMAIL_DESIGNER ) ) {
			wp_enqueue_editor();
		}
		wp_enqueue_style(
			'thimbleform-admin',
			thimbleform_admin_css_url(),
			thimbleform_admin_style_deps(),
			$ver_css ? $ver_css : THIMBLEFORM_VERSION
		);
		wp_enqueue_script(
			'thimbleform-admin',
			thimbleform_admin_js_url( 'admin.js' ),
			array( 'jquery', 'media-editor' ),
			$ver_js ? $ver_js : THIMBLEFORM_VERSION,
			true
		);
		wp_localize_script(
			'thimbleform-admin',
			'thimbleformAdmin',
			array(
				'i18n' => array(
					'untitled'       => __( 'Untitled field', 'thimbleform' ),
					'required'       => __( 'Required', 'thimbleform' ),
					'optional'       => __( 'Optional', 'thimbleform' ),
					'toggleRequired' => __( 'Toggle required', 'thimbleform' ),
					'hideField'      => __( 'Hide from form', 'thimbleform' ),
					'showField'      => __( 'Show on form', 'thimbleform' ),
					'fieldHiddenChip'=> __( 'Hidden', 'thimbleform' ),
					'confirmDel'           => __( 'Remove this field?', 'thimbleform' ),
					'confirmDelStep'       => __( 'Remove this step?', 'thimbleform' ),
					/* translators: %d: Number of fields in the step. */
					'confirmDelStepFields' => __( 'Remove this step and its %d field(s)?', 'thimbleform' ),
					'copied'         => __( 'Copied', 'thimbleform' ),
					'duplicate'      => __( 'copy', 'thimbleform' ),
					'step'           => __( 'Step', 'thimbleform' ),
					/* translators: %d: Step number. */
					'stepTitle'      => __( 'Step %d title', 'thimbleform' ),
					'layout'         => __( 'Layout', 'thimbleform' ),
					'pickImage'      => __( 'Select image', 'thimbleform' ),
					'changeImage'    => __( 'Change image', 'thimbleform' ),
					'removeImage'    => __( 'Remove', 'thimbleform' ),
					'noImage'        => __( 'No image selected', 'thimbleform' ),
					'mediaUnavailable' => __( 'WordPress media library is not available.', 'thimbleform' ),
					'selectPlaceholder' => __( 'Select...', 'thimbleform' ),
					'fieldPlaceholder'  => __( 'Optional hint', 'thimbleform' ),
					'previewNeedSave'   => __( 'Save the form to refresh preview.', 'thimbleform' ),
					'templateSaveFirst' => __( 'Save the form as a draft first, then you can apply a template.', 'thimbleform' ),
					'alwaysShow'        => __( '— Always show —', 'thimbleform' ),
					'ifPrefix'          => __( 'if', 'thimbleform' ),
					'removeRule'        => __( 'Remove', 'thimbleform' ),
					'fromStep'          => __( 'From', 'thimbleform' ),
					'toStep'            => __( 'To', 'thimbleform' ),
					'pickField'         => __( '— Field —', 'thimbleform' ),
					'pickFieldHint'     => __( 'Choose a field for this branch rule.', 'thimbleform' ),
					'noRules'           => __( 'No branch rules yet.', 'thimbleform' ),
					'fieldPreviewEmpty' => __( 'Configure this field to see a preview.', 'thimbleform' ),
					'fieldPreviewHtml'  => __( 'HTML block', 'thimbleform' ),
					'fieldPreviewHidden'=> __( 'Hidden field — not shown on the form', 'thimbleform' ),
					'fieldPreviewSpacer'=> __( 'Spacer', 'thimbleform' ),
					'fieldPreviewDivider'=> __( 'Divider', 'thimbleform' ),
					'fieldPreviewImage' => __( 'Image', 'thimbleform' ),
					'fieldPreviewFile'  => __( 'Choose files…', 'thimbleform' ),
					'fieldPreviewCalc'  => __( 'Calculated value', 'thimbleform' ),
					'fieldPreviewRepeater'=> __( 'Repeater row', 'thimbleform' ),
					'fieldPreviewRequired'=> __( 'required', 'thimbleform' ),
					'fieldPreviewSignature'=> __( 'Sign here', 'thimbleform' ),
					'subOptChoices'     => __( 'Choices (one per line)', 'thimbleform' ),
					'subOptRange'       => __( 'Min / max / step', 'thimbleform' ),
					'subOptFormula'     => __( 'Formula', 'thimbleform' ),
					'subHintChoices'    => __( 'One choice per line — the text visitors see. Example: Yes', 'thimbleform' ),
					'subHintRange'      => __( 'Three lines: lowest value, highest value, step. Example: 0, then 100, then 1.', 'thimbleform' ),
					'subHintFormula'    => __( 'Use other subfield names in braces, e.g. {qty} * {price}.', 'thimbleform' ),
					'subPhChoices'      => __( "Yes\nNo", 'thimbleform' ),
					'subPhRange'        => "0\n100\n1",
					'subPhFormula'      => '{price} * {qty}',
					'optionsLabelChoices' => __( 'Choices', 'thimbleform' ),
					'optionsLabelRange'   => __( 'Min / max / step', 'thimbleform' ),
					'optionsLabelRating'  => __( 'Number of stars', 'thimbleform' ),
					'optionsLabelScale'   => __( 'Scale setup', 'thimbleform' ),
					'optionsLabelMatrix'  => __( 'Rows and columns', 'thimbleform' ),
					'optionsLabelPayment' => __( 'Amount & currency', 'thimbleform' ),
					'optionsHintChoices'  => __( 'One choice per line — the text visitors see. For quizzes, add points after | : Correct answer|10', 'thimbleform' ),
					'optionsHintRange'    => __( 'Three lines: lowest value, highest value, and step size.', 'thimbleform' ),
					'optionsHintRating'   => __( 'Enter one number for how many stars to show (1–10), e.g. 5.', 'thimbleform' ),
					'optionsHintScale'    => __( 'Four lines: lowest number, highest number, left label, right label.', 'thimbleform' ),
					'optionsHintMatrix'   => __( 'List row labels, then a line with only ---, then column labels.', 'thimbleform' ),
					'optionsHintPayment'  => __( 'Line 1 = amount (e.g. 9.99). Line 2 = currency code (USD, EUR, GBP, RUB…).', 'thimbleform' ),
					'optionsPhChoices'    => __( "Yes\nNo\nMaybe", 'thimbleform' ),
					'optionsPhRange'      => "0\n100\n1",
					'optionsPhRating'     => '5',
					'optionsPhScale'      => __( "1\n5\nVery dissatisfied\nVery satisfied", 'thimbleform' ),
					'optionsPhMatrix'     => __( "Support\nProduct\n---\nPoor\nFair\nGood", 'thimbleform' ),
					'optionsPhPayment'    => "9.99\nUSD",
					'optionsTipChoices'   => __( 'One choice per line. Quizzes: Correct answer|10. Optional advanced: Label|saved_value|points', 'thimbleform' ),
					'optionsTipRange'     => __( 'Line 1 = min, line 2 = max, line 3 = step. Example: 0 / 100 / 1', 'thimbleform' ),
					'optionsTipRating'    => __( 'A single number sets max stars (1–10). Or list one label per star.', 'thimbleform' ),
					'optionsTipScale'     => __( 'Line 1–2 = number range, line 3–4 = labels under the ends of the scale.', 'thimbleform' ),
					'optionsTipMatrix'    => __( 'Rows above ---, columns below. Each line is one label.', 'thimbleform' ),
					'optionsTipPayment'   => __( 'Fixed charge for this field. Currency must be a 3-letter ISO code supported by Stripe.', 'thimbleform' ),
					'fieldPreviewPayment' => __( 'Card payment', 'thimbleform' ),
					'subUntitled'       => __( 'Untitled', 'thimbleform' ),
					/* translators: %d: Column number. */
					'subColFallback'    => __( 'Column %d', 'thimbleform' ),
					'helpText'          => __( 'Help text', 'thimbleform' ),
					'altText'           => __( 'Alt text', 'thimbleform' ),
					'describeImage'     => __( 'Describe the image', 'thimbleform' ),
					'shownUnderField'   => __( 'Shown under the field', 'thimbleform' ),
					'typeSectionTitles' => array(
						'heading'    => __( 'Heading', 'thimbleform' ),
						'image'      => __( 'Image', 'thimbleform' ),
						'html'       => __( 'HTML', 'thimbleform' ),
						'paragraph'  => __( 'Paragraph', 'thimbleform' ),
						'spacer'     => __( 'Spacer', 'thimbleform' ),
						'tel'        => __( 'Phone', 'thimbleform' ),
						'file'       => __( 'Upload limits', 'thimbleform' ),
						'select'     => __( 'Choices', 'thimbleform' ),
						'radio'      => __( 'Choices', 'thimbleform' ),
						'checkboxes' => __( 'Choices', 'thimbleform' ),
						'range'      => __( 'Range', 'thimbleform' ),
						'rating'     => __( 'Choices', 'thimbleform' ),
						'scale'      => __( 'Choices', 'thimbleform' ),
						'ranking'    => __( 'Choices', 'thimbleform' ),
						'matrix'     => __( 'Matrix', 'thimbleform' ),
						'calculated' => __( 'Formula', 'thimbleform' ),
						'repeater'   => __( 'Row fields', 'thimbleform' ),
						'payment'    => __( 'Payment', 'thimbleform' ),
					),
				),
				'typeLabels'  => Thimbleform_Form_Config::field_type_labels(),
				'layoutTypes' => array_keys( Thimbleform_Form_Config::layout_field_type_labels() ),
				'operators'   => Thimbleform_Form_Config::condition_operators(),
				'previewUrl'  => admin_url( 'admin-ajax.php?action=thimbleform_preview' ),
				'previewNonce'=> wp_create_nonce( 'thimbleform_preview' ),
				'formId'      => isset( $_GET['post'] ) ? (int) $_GET['post'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	public static function ajax_preview() {
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_ajax_referer( 'thimbleform_preview', 'nonce' );
		if ( $form_id <= 0 || Thimbleform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'thimbleform' ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'Forbidden', 'thimbleform' ), 403 );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		wp_head();
		wp_enqueue_style(
			'thimbleform-preview-frame',
			THIMBLEFORM_URL . 'assets/css/preview-frame.css',
			array(),
			THIMBLEFORM_VERSION
		);
		wp_print_styles( 'thimbleform-preview-frame' );
		echo '</head><body class="thimbleform-preview-body"><div class="thimbleform-preview-shell">';
		echo Thimbleform_Renderer::render( $form_id, array( 'preview' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		wp_footer();
		echo '</body></html>';
		exit;
	}

	public static function meta_boxes() {
		add_meta_box(
			'thimbleform_builder',
			__( 'Form builder', 'thimbleform' ),
			array( __CLASS__, 'render_builder_box' ),
			Thimbleform_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'thimbleform_shortcode',
			__( 'Publish & embed', 'thimbleform' ),
			array( __CLASS__, 'render_shortcode_box' ),
			Thimbleform_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_builder_box( $post ) {
		wp_nonce_field( self::NONCE, 'thimbleform_nonce' );
		$form_id  = (int) $post->ID;
		$fields   = Thimbleform_Form_Config::get_fields( $form_id );
		$messages = Thimbleform_Form_Config::get_messages( $form_id );
		$mail     = Thimbleform_Form_Config::get_mail( $form_id );
		$settings = Thimbleform_Form_Config::get_settings( $form_id );
		$types        = Thimbleform_Form_Config::field_type_labels();
		$layout_types = Thimbleform_Form_Config::layout_field_type_labels();
		$input_types  = Thimbleform_Form_Config::input_field_type_labels();
		$steps_enabled = ( '1' === (string) ( $settings['enable_steps'] ?? '0' ) );
		$can_quiz      = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::QUIZ_SURVEY );
		$can_advanced  = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::ADVANCED_FIELDS );
		$can_auto      = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::AUTOMATIONS );
		$step_labels   = Thimbleform_Form_Config::parse_step_labels( (string) ( $settings['step_labels'] ?? '' ) );
		$used_steps    = Thimbleform_Form_Config::collect_steps( $fields );
		if ( $steps_enabled ) {
			$max_step = ! empty( $used_steps ) ? max( $used_steps ) : 1;
			if ( ! empty( $step_labels ) ) {
				$max_step = max( $max_step, max( array_keys( $step_labels ) ) );
			}
			$max_step = max( $max_step, 2 );
		} else {
			$max_step = 1;
		}
		$fields_by_step = array();
		foreach ( $fields as $i => $field ) {
			$s = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;
			if ( ! isset( $fields_by_step[ $s ] ) ) {
				$fields_by_step[ $s ] = array();
			}
			$fields_by_step[ $s ][] = array( $i, $field );
		}

		$editor_tabs = array( 'fields', 'messages', 'mail', 'settings', 'appearance' );
		$active_tab  = 'fields';
		$tab_cookie  = 'thimbleform_editor_tab_' . $form_id;
		if ( isset( $_COOKIE[ $tab_cookie ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_tab = sanitize_key( wp_unslash( $_COOKIE[ $tab_cookie ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( in_array( $cookie_tab, $editor_tabs, true ) ) {
				$active_tab = $cookie_tab;
			}
		}
		$tab_labels = array(
			'fields'     => __( 'Fields', 'thimbleform' ),
			'messages'   => __( 'Messages', 'thimbleform' ),
			'mail'       => __( 'Mail', 'thimbleform' ),
			'settings'   => __( 'Settings', 'thimbleform' ),
			'appearance' => __( 'Appearance', 'thimbleform' ),
		);
		?>
		<div class="thimbleform-admin" data-thimbleform-admin data-form-id="<?php echo esc_attr( (string) (int) $form_id ); ?>" data-steps-enabled="<?php echo $steps_enabled ? '1' : '0'; ?>" data-thimbleform-empty="<?php echo array() === $fields ? '1' : '0'; ?>">
			<nav class="thimbleform-admin__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Form sections', 'thimbleform' ); ?>">
				<?php foreach ( $editor_tabs as $tab_id ) : ?>
					<?php $is_tab = ( $active_tab === $tab_id ); ?>
					<button
						type="button"
						class="thimbleform-admin__tab<?php echo $is_tab ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $is_tab ? 'true' : 'false'; ?>"
						tabindex="<?php echo $is_tab ? '0' : '-1'; ?>"
						data-thimbleform-tab="<?php echo esc_attr( $tab_id ); ?>"
						id="thimbleform-tab-<?php echo esc_attr( $tab_id ); ?>"
						aria-controls="thimbleform-panel-<?php echo esc_attr( $tab_id ); ?>"
					><?php echo esc_html( $tab_labels[ $tab_id ] ); ?></button>
				<?php endforeach; ?>
			</nav>

			<div class="thimbleform-admin__panel<?php echo 'fields' === $active_tab ? ' is-active' : ''; ?>" data-thimbleform-panel="fields" id="thimbleform-panel-fields" role="tabpanel" aria-labelledby="thimbleform-tab-fields"<?php echo 'fields' === $active_tab ? '' : ' hidden'; ?>>
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Fields', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc"><?php
						echo wp_kses(
							sprintf(
								/* translators: %s: {name} token */
								__( 'Drag to reorder. Field name becomes %s in mail templates.', 'thimbleform' ),
								'<code class="thimbleform-admin__token">{name}</code>'
							),
							array(
								'code' => array(
									'class' => true,
								),
							)
						);
						?></p>
					</div>
					<div class="thimbleform-admin__panel-tools">
						<div class="thimbleform-templates-home">
							<span class="thimbleform-templates-home__nudge" data-thimbleform-templates-nudge hidden>
								<span class="thimbleform-templates-home__arrow" aria-hidden="true"></span>
							</span>
							<button type="button" class="thimbleform-btn thimbleform-btn--ghost" data-thimbleform-templates-open data-thimbleform-templates-home>
								<?php thimbleform_admin_icon( 'forms' ); ?>
								<?php esc_html_e( 'Templates', 'thimbleform' ); ?>
							</button>
						</div>
					</div>
				</div>
				<?php
				$show_tpl_empty = array() === $fields;
				if ( $show_tpl_empty ) :
					?>
				<div class="thimbleform-templates-empty" data-thimbleform-templates-empty>
					<div class="thimbleform-templates-empty__bar">
						<div class="thimbleform-templates-empty__copy">
							<strong><?php esc_html_e( 'Start from a template', 'thimbleform' ); ?></strong>
							<p><?php esc_html_e( 'Pick a ready-made form, then tweak fields to match your brand.', 'thimbleform' ); ?></p>
						</div>
						<button type="button" class="thimbleform-btn thimbleform-btn--primary" data-thimbleform-templates-open>
							<?php esc_html_e( 'Browse all', 'thimbleform' ); ?>
						</button>
					</div>
					<div class="thimbleform-templates-empty__grid">
						<?php self::render_template_cards( $form_id, 6 ); ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( has_action( 'thimbleform_render_steps_editor' ) ) : ?>
					<?php do_action( 'thimbleform_render_steps_editor', $form_id, $settings ); ?>
				<?php elseif ( class_exists( 'Thimbleform_Promotion' ) ) : ?>
					<?php
					Thimbleform_Promotion::render_feature_teaser(
						array(
							'title'   => __( 'Multi-step forms', 'thimbleform' ),
							'copy'    => __( 'Split long forms into wizard steps with optional branch rules. Available in the Thimbleform Pro add-on.', 'thimbleform' ),
							'cta'     => __( 'See Thimbleform Pro', 'thimbleform' ),
							'compact' => true,
						)
					);
					?>
				<?php endif; ?>

				<div class="thimbleform-admin__surface thimbleform-admin__quick-add" data-thimbleform-quick-add>
					<?php
					$favorite_keys = array( 'text', 'email', 'tel', 'textarea', 'select' );
					$more_groups   = array(
						__( 'Text & data', 'thimbleform' ) => array( 'url', 'password', 'number', 'range', 'date', 'time' ),
						__( 'Choices', 'thimbleform' )     => array( 'radio', 'checkboxes', 'checkbox', 'acceptance' ),
						__( 'Other', 'thimbleform' )       => array( 'file', 'hidden' ),
					);
					$pro_teasers = class_exists( 'Thimbleform_Features' )
						? Thimbleform_Features::advanced_field_teasers()
						: array(
							'rating'    => __( 'Rating', 'thimbleform' ),
							'signature' => __( 'Signature', 'thimbleform' ),
						);
					$specialty_teasers = class_exists( 'Thimbleform_Features' )
						? Thimbleform_Features::specialty_field_teasers()
						: array(
							'calculated' => __( 'Calculated', 'thimbleform' ),
							'repeater'   => __( 'Repeater', 'thimbleform' ),
						);
					$pro_menu_types = array();
					foreach ( array_merge( $pro_teasers, $specialty_teasers ) as $type_key => $type_label ) {
						if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can_use_field_type( (string) $type_key ) ) {
							$pro_menu_types[ $type_key ] = $type_label;
						}
					}
					?>
					<div class="thimbleform-add">
						<div class="thimbleform-add__main">
							<button
								type="button"
								class="thimbleform-add__label"
								data-thimbleform-add-browse
								aria-haspopup="true"
								aria-expanded="false"
								aria-controls="thimbleform-add-more-panel"
								title="<?php esc_attr_e( 'Browse all field types', 'thimbleform' ); ?>"
							>
								<span class="thimbleform-add__icon" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
										<rect width="22" height="22" rx="6" fill="currentColor"/>
										<path d="M11 6.5v9M6.5 11h9" stroke="#fff" stroke-width="1.75" stroke-linecap="round"/>
									</svg>
								</span>
								<?php esc_html_e( 'Add field', 'thimbleform' ); ?>
							</button>
							<div class="thimbleform-add__favorites" role="group" aria-label="<?php esc_attr_e( 'Common fields', 'thimbleform' ); ?>">
								<?php foreach ( $favorite_keys as $type_key ) : ?>
									<?php if ( isset( $input_types[ $type_key ] ) ) : ?>
										<button type="button" class="thimbleform-admin__chip thimbleform-add__chip" data-thimbleform-add-type="<?php echo esc_attr( $type_key ); ?>">
											<?php echo esc_html( $input_types[ $type_key ] ); ?>
										</button>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
							<div class="thimbleform-add__menus">
								<div class="thimbleform-add-menu" data-thimbleform-add-menu="more">
									<button
										type="button"
										class="thimbleform-add-menu__toggle"
										aria-expanded="false"
										aria-haspopup="true"
										data-thimbleform-add-menu-toggle
									>
										<?php esc_html_e( 'More fields', 'thimbleform' ); ?>
									</button>
									<div
										id="thimbleform-add-more-panel"
										class="thimbleform-add-menu__panel"
										data-thimbleform-add-menu-panel
										hidden
									>
										<label class="thimbleform-add-menu__search">
											<span class="screen-reader-text"><?php esc_html_e( 'Search fields', 'thimbleform' ); ?></span>
											<input type="search" class="thimbleform-admin__input" placeholder="<?php esc_attr_e( 'Search fields…', 'thimbleform' ); ?>" data-thimbleform-add-search autocomplete="off" />
										</label>
										<?php foreach ( $more_groups as $group_label => $type_keys ) : ?>
											<div class="thimbleform-add-menu__group">
												<span class="thimbleform-add-menu__group-label"><?php echo esc_html( $group_label ); ?></span>
												<div class="thimbleform-add-menu__list">
													<?php foreach ( $type_keys as $type_key ) : ?>
														<?php if ( isset( $input_types[ $type_key ] ) ) : ?>
															<button type="button" class="thimbleform-add-menu__item" data-thimbleform-add-type="<?php echo esc_attr( $type_key ); ?>">
																<?php echo esc_html( $input_types[ $type_key ] ); ?>
															</button>
														<?php endif; ?>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endforeach; ?>
										<?php if ( array() !== $pro_menu_types ) : ?>
											<div class="thimbleform-add-menu__group">
												<span class="thimbleform-add-menu__group-label"><?php esc_html_e( 'Pro fields', 'thimbleform' ); ?></span>
												<div class="thimbleform-add-menu__list">
													<?php foreach ( $pro_menu_types as $pro_key => $pro_label ) : ?>
														<button type="button" class="thimbleform-add-menu__item" data-thimbleform-add-type="<?php echo esc_attr( $pro_key ); ?>">
															<?php echo esc_html( $pro_label ); ?>
														</button>
													<?php endforeach; ?>
												</div>
											</div>
										<?php elseif ( class_exists( 'Thimbleform_Promotion' ) && Thimbleform_Promotion::should_promote() ) : ?>
											<?php
											$pro_teaser_labels = array_values(
												array_slice(
													array_merge( $pro_teasers, $specialty_teasers ),
													0,
													6
												)
											);
											?>
											<div class="thimbleform-add-menu__group thimbleform-add-menu__group--teaser">
												<span class="thimbleform-add-menu__group-label"><?php esc_html_e( 'Also in Thimbleform Pro', 'thimbleform' ); ?></span>
												<div class="thimbleform-add-menu__list">
													<?php foreach ( $pro_teaser_labels as $pro_label ) : ?>
														<span class="thimbleform-add-menu__item thimbleform-add-menu__item--teaser"><?php echo esc_html( (string) $pro_label ); ?></span>
													<?php endforeach; ?>
												</div>
												<a class="thimbleform-add-menu__pro-link" href="<?php echo esc_url( Thimbleform_Promotion::url() ); ?>">
													<?php esc_html_e( 'See Thimbleform Pro', 'thimbleform' ); ?>
												</a>
											</div>
										<?php endif; ?>
									</div>
								</div>
								<div class="thimbleform-add-menu" data-thimbleform-add-menu="layout">
									<button
										type="button"
										class="thimbleform-add-menu__toggle"
										aria-expanded="false"
										aria-haspopup="true"
										data-thimbleform-add-menu-toggle
									>
										<?php esc_html_e( 'Layout', 'thimbleform' ); ?>
									</button>
									<div class="thimbleform-add-menu__panel" data-thimbleform-add-menu-panel hidden>
										<div class="thimbleform-add-menu__group">
											<span class="thimbleform-add-menu__group-label"><?php esc_html_e( 'Display only', 'thimbleform' ); ?></span>
											<div class="thimbleform-add-menu__list">
												<?php foreach ( $layout_types as $type_key => $type_label ) : ?>
													<button type="button" class="thimbleform-add-menu__item" data-thimbleform-add-type="<?php echo esc_attr( $type_key ); ?>">
														<?php echo esc_html( $type_label ); ?>
													</button>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<span class="thimbleform-add__hint" data-thimbleform-add-hint <?php echo $steps_enabled ? '' : 'hidden'; ?>>
							<?php esc_html_e( 'Adds to active step', 'thimbleform' ); ?>
						</span>
					</div>
				</div>

				<div class="thimbleform-workspace" data-thimbleform-workspace data-mode="<?php echo $steps_enabled ? 'steps' : 'flat'; ?>">
					<nav class="thimbleform-step-nav" data-thimbleform-step-nav <?php echo $steps_enabled ? '' : 'hidden'; ?> role="tablist" aria-label="<?php esc_attr_e( 'Steps', 'thimbleform' ); ?>">
						<?php for ( $s = 1; $s <= $max_step; $s++ ) : ?>
							<?php
							$nav_title = isset( $step_labels[ $s ] ) ? $step_labels[ $s ] : sprintf(
								/* translators: %d: step number */
								__( 'Step %d', 'thimbleform' ),
								$s
							);
							$count_in = isset( $fields_by_step[ $s ] ) ? count( $fields_by_step[ $s ] ) : 0;
							?>
							<button type="button" class="thimbleform-step-nav__btn<?php echo 1 === $s ? ' is-active' : ''; ?>" data-thimbleform-step-tab="<?php echo esc_attr( (string) $s ); ?>" role="tab" aria-selected="<?php echo 1 === $s ? 'true' : 'false'; ?>">
								<span class="thimbleform-step-nav__index"><?php echo esc_html( (string) $s ); ?></span>
								<span class="thimbleform-step-nav__title" data-thimbleform-step-nav-title><?php echo esc_html( $nav_title ); ?></span>
								<span class="thimbleform-step-nav__count" data-thimbleform-step-nav-count><?php echo esc_html( (string) $count_in ); ?></span>
							</button>
						<?php endfor; ?>
					</nav>

					<div class="thimbleform-admin__surface thimbleform-step-groups" data-thimbleform-step-groups>
						<?php for ( $s = 1; $s <= $max_step; $s++ ) : ?>
							<?php
							$group_title = isset( $step_labels[ $s ] ) ? $step_labels[ $s ] : '';
							$group_fields = $fields_by_step[ $s ] ?? array();
							$show_group = ! $steps_enabled ? ( 1 === $s ) : ( 1 === $s );
							?>
							<section class="thimbleform-step-group<?php echo $show_group ? ' is-active' : ''; ?>" data-thimbleform-step-group data-step="<?php echo esc_attr( (string) $s ); ?>" <?php echo ( $steps_enabled && 1 !== $s ) ? 'hidden' : ''; ?>>
								<header class="thimbleform-step-group__head" data-thimbleform-step-head <?php echo $steps_enabled ? '' : 'hidden'; ?>>
									<span class="thimbleform-step-group__badge"><?php echo esc_html( sprintf( /* translators: %d step */ __( 'Step %d', 'thimbleform' ), $s ) ); ?></span>
									<label class="thimbleform-step-group__title-wrap">
										<span class="screen-reader-text"><?php esc_html_e( 'Step title', 'thimbleform' ); ?></span>
										<input type="text" class="thimbleform-admin__input thimbleform-step-group__title" value="<?php echo esc_attr( $group_title ); ?>" placeholder="<?php echo esc_attr( sprintf( /* translators: %d */ __( 'Step %d title', 'thimbleform' ), $s ) ); ?>" data-thimbleform-step-title />
									</label>
									<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-step-group__remove" data-thimbleform-remove-step title="<?php esc_attr_e( 'Remove step', 'thimbleform' ); ?>" <?php echo $max_step <= 1 ? 'hidden' : ''; ?>>
										<?php esc_html_e( 'Remove step', 'thimbleform' ); ?>
									</button>
								</header>
								<div class="thimbleform-admin__fields" data-thimbleform-fields data-step="<?php echo esc_attr( (string) $s ); ?>">
									<?php if ( $steps_enabled ) : ?>
										<?php foreach ( $group_fields as $pair ) : ?>
											<?php self::render_field_row( (int) $pair[0], $pair[1], $types, true ); ?>
										<?php endforeach; ?>
									<?php elseif ( 1 === $s ) : ?>
										<?php foreach ( $fields as $i => $field ) : ?>
											<?php self::render_field_row( (int) $i, $field, $types, true ); ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<p class="thimbleform-admin__empty" data-thimbleform-empty <?php echo ( $steps_enabled ? empty( $group_fields ) : ( 1 === $s && array() === $fields ) ) ? '' : 'hidden'; ?>>
									<span class="thimbleform-admin__empty-copy"><?php echo $steps_enabled ? esc_html__( 'No fields on this step yet.', 'thimbleform' ) : esc_html__( 'No fields yet.', 'thimbleform' ); ?></span>
									<button type="button" class="thimbleform-btn thimbleform-btn--primary thimbleform-admin__empty-cta" data-thimbleform-add-browse>
										<?php esc_html_e( 'Add first field', 'thimbleform' ); ?>
									</button>
								</p>
							</section>
						<?php endfor; ?>
					</div>
				</div>

				<template data-thimbleform-field-template>
					<?php
					self::render_field_row(
						'__INDEX__',
						array(
							'type'            => 'text',
							'name'            => '',
							'label'           => '',
							'placeholder'     => '',
							'description'     => '',
							'default'         => '',
							'css_class'       => '',
							'required'        => false,
							'width'           => 'full',
							'options'         => '',
							'step'            => 1,
							'condition_field' => '',
							'condition_op'    => 'equals',
							'condition_value' => '',
						),
						$types,
						false
					);
					?>
				</template>
				<template data-thimbleform-step-group-template>
					<section class="thimbleform-step-group" data-thimbleform-step-group data-step="__STEP__" hidden>
						<header class="thimbleform-step-group__head" data-thimbleform-step-head>
							<span class="thimbleform-step-group__badge">Step __STEP__</span>
							<label class="thimbleform-step-group__title-wrap">
								<span class="screen-reader-text"><?php esc_html_e( 'Step title', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input thimbleform-step-group__title" value="" placeholder="<?php esc_attr_e( 'Step title', 'thimbleform' ); ?>" data-thimbleform-step-title />
							</label>
							<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-step-group__remove" data-thimbleform-remove-step><?php esc_html_e( 'Remove step', 'thimbleform' ); ?></button>
						</header>
						<div class="thimbleform-admin__fields" data-thimbleform-fields data-step="__STEP__"></div>
						<p class="thimbleform-admin__empty" data-thimbleform-empty>
							<span class="thimbleform-admin__empty-copy"><?php esc_html_e( 'No fields on this step yet.', 'thimbleform' ); ?></span>
							<button type="button" class="thimbleform-btn thimbleform-btn--primary thimbleform-admin__empty-cta" data-thimbleform-add-browse>
								<?php esc_html_e( 'Add first field', 'thimbleform' ); ?>
							</button>
						</p>
					</section>
				</template>
			</div>

			<div class="thimbleform-admin__panel<?php echo 'messages' === $active_tab ? ' is-active' : ''; ?>" data-thimbleform-panel="messages" id="thimbleform-panel-messages" role="tabpanel" aria-labelledby="thimbleform-tab-messages"<?php echo 'messages' === $active_tab ? '' : ' hidden'; ?>>
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Messages', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Shown after submit and on validation errors (front + AJAX).', 'thimbleform' ); ?></p>
					</div>
				</div>
				<div class="thimbleform-admin__surface">
					<?php
					$message_primary = array(
						'success'       => array( __( 'Success', 'thimbleform' ), __( 'After a valid submission. Supports {field_name} merge tags. Display mode is under Settings.', 'thimbleform' ) ),
						'required'      => array( __( 'Required field', 'thimbleform' ), __( 'Empty required field.', 'thimbleform' ) ),
						'invalid_email' => array( __( 'Invalid email', 'thimbleform' ), __( 'Email format check failed.', 'thimbleform' ) ),
						'error_generic' => array( __( 'Generic error', 'thimbleform' ), __( 'Fallback when something fails.', 'thimbleform' ) ),
					);
					$message_more    = array(
						'invalid_tel'     => array( __( 'Invalid phone', 'thimbleform' ), __( 'Phone format check failed.', 'thimbleform' ) ),
						'invalid_url'     => array( __( 'Invalid URL', 'thimbleform' ), __( 'URL format check failed.', 'thimbleform' ) ),
						'invalid_number'  => array( __( 'Invalid number', 'thimbleform' ), __( 'Number format check failed.', 'thimbleform' ) ),
						'invalid_date'    => array( __( 'Invalid date', 'thimbleform' ), __( 'Date format check failed (YYYY-MM-DD).', 'thimbleform' ) ),
						'invalid_time'    => array( __( 'Invalid time', 'thimbleform' ), __( 'Time format check failed (HH:MM).', 'thimbleform' ) ),
						'rate_limited'    => array( __( 'Rate limited', 'thimbleform' ), __( 'Too many submits from one IP.', 'thimbleform' ) ),
						'invalid_captcha' => array( __( 'Captcha failed', 'thimbleform' ), __( 'When a captcha hook rejects the submit.', 'thimbleform' ) ),
						'invalid_file'    => array( __( 'Invalid file', 'thimbleform' ), __( 'Wrong type or upload failed.', 'thimbleform' ) ),
						'file_too_large'  => array( __( 'File too large', 'thimbleform' ), __( 'Exceeds the max size for this field.', 'thimbleform' ) ),
						'too_many_files'  => array( __( 'Too many files', 'thimbleform' ), __( 'Exceeds max files for this field.', 'thimbleform' ) ),
					);
					?>
					<div class="thimbleform-admin__grid">
						<?php foreach ( $message_primary as $key => $meta ) : ?>
							<label class="thimbleform-admin__field-control<?php echo 'success' === $key ? ' thimbleform-admin__field-control--full' : ''; ?>">
								<span class="thimbleform-admin__label">
									<?php echo esc_html( $meta[0] ); ?>
									<?php self::render_field_tip( $meta[1] ); ?>
								</span>
								<?php if ( 'success' === $key ) : ?>
									<textarea class="thimbleform-admin__input thimbleform-admin__textarea" name="thimbleform[messages][<?php echo esc_attr( $key ); ?>]" rows="3" placeholder="<?php esc_attr_e( 'Thank you. Your message has been sent.', 'thimbleform' ); ?>"><?php echo esc_textarea( $messages[ $key ] ?? '' ); ?></textarea>
								<?php else : ?>
									<input type="text" class="thimbleform-admin__input" name="thimbleform[messages][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $messages[ $key ] ?? '' ); ?>" />
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<details class="thimbleform-admin__more">
						<summary><?php esc_html_e( 'More validation messages', 'thimbleform' ); ?></summary>
						<div class="thimbleform-admin__more-body thimbleform-admin__grid">
							<?php foreach ( $message_more as $key => $meta ) : ?>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label">
										<?php echo esc_html( $meta[0] ); ?>
										<?php self::render_field_tip( $meta[1] ); ?>
									</span>
									<input type="text" class="thimbleform-admin__input" name="thimbleform[messages][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $messages[ $key ] ?? '' ); ?>" />
								</label>
							<?php endforeach; ?>
						</div>
					</details>
				</div>
			</div>

			<?php
			$can_email          = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::EMAIL_DESIGNER );
			$html_on            = $can_email && '1' === (string) ( $mail['html_enabled'] ?? '0' );
			$can_pdf            = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::PDF_EXPORT );
			$user_mail_open     = '1' === (string) ( $mail['user_mail_enabled'] ?? '0' );
			$mail_advanced_open = ( '1' === (string) ( $mail['extra_enabled'] ?? '0' )
				|| (string) ( $mail['cc'] ?? '' ) !== ''
				|| (string) ( $mail['bcc'] ?? '' ) !== '' );
			?>
			<div class="thimbleform-admin__panel<?php echo 'mail' === $active_tab ? ' is-active' : ''; ?>" data-thimbleform-panel="mail" id="thimbleform-panel-mail" role="tabpanel" aria-labelledby="thimbleform-tab-mail"<?php echo 'mail' === $active_tab ? '' : ' hidden'; ?>>
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Mail', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Sent via wp_mail. Use WP Mail SMTP (or similar) for delivery — no SMTP settings here.', 'thimbleform' ); ?></p>
					</div>
				</div>

				<?php
				$mail_subtab = 'notification';
				$mail_subtabs = array(
					'notification' => __( 'Notification', 'thimbleform' ),
					'autoreply'    => __( 'Autoreply', 'thimbleform' ),
					'advanced'     => __( 'Advanced', 'thimbleform' ),
				);
				?>
				<div class="thimbleform-admin__subtabs" data-thimbleform-subtabs data-thimbleform-subtabs-key="mail" data-thimbleform-subtabs-default="<?php echo esc_attr( $mail_subtab ); ?>">
					<nav class="thimbleform-settings__subnav thimbleform-admin__subtabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Mail sections', 'thimbleform' ); ?>">
						<?php foreach ( $mail_subtabs as $sub_id => $sub_label ) : ?>
							<?php $sub_on = $mail_subtab === $sub_id; ?>
							<button
								type="button"
								class="thimbleform-settings__subnav-item<?php echo $sub_on ? ' thimbleform-settings__subnav-item--active' : ''; ?>"
								role="tab"
								id="thimbleform-mail-subtab-<?php echo esc_attr( $sub_id ); ?>"
								aria-selected="<?php echo $sub_on ? 'true' : 'false'; ?>"
								aria-controls="thimbleform-mail-subpanel-<?php echo esc_attr( $sub_id ); ?>"
								tabindex="<?php echo $sub_on ? '0' : '-1'; ?>"
								data-thimbleform-subtab="<?php echo esc_attr( $sub_id ); ?>"
							>
								<?php echo esc_html( $sub_label ); ?>
							</button>
						<?php endforeach; ?>
					</nav>

				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'notification' === $mail_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="notification" id="thimbleform-mail-subpanel-notification" role="tabpanel" aria-labelledby="thimbleform-mail-subtab-notification"<?php echo 'notification' === $mail_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Notification', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'Email you receive when someone submits the form.', 'thimbleform' ); ?></p>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'To', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Comma-separated addresses.', 'thimbleform' ) ); ?>
								</span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][to]" value="<?php echo esc_attr( $mail['to'] ); ?>" placeholder="you@example.com" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Subject', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][subject]" value="<?php echo esc_attr( $mail['subject'] ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'From name', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][from_name]" value="<?php echo esc_attr( $mail['from_name'] ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Reply-To field', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Field name that holds the visitor email.', 'thimbleform' ) ); ?>
								</span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][reply_to_field]" value="<?php echo esc_attr( $mail['reply_to_field'] ); ?>" placeholder="email" />
							</label>
						</div>
					</section>

					<section class="thimbleform-admin__block">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Message body', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc">
							<?php
							if ( $can_email || ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::REPEATERS ) ) ) {
								esc_html_e( 'Tokens like {email} or {all_fields}. Repeater: {#items}…{field}…{/items}.', 'thimbleform' );
							} else {
								esc_html_e( 'Tokens like {email}, {all_fields}, {form_title}, or {form_id}.', 'thimbleform' );
							}
							?>
						</p>
						<div class="thimbleform-admin__field-control thimbleform-admin__field-control--full">
							<span class="thimbleform-admin__label">
								<?php esc_html_e( 'Body template', 'thimbleform' ); ?>
								<?php
								self::render_field_tip(
									( $can_email || ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::REPEATERS ) ) )
										? __( 'Placeholders: {field_name}, {all_fields}, {form_title}, {form_id}. Repeater loops: {#items}…{price}…{/items}.', 'thimbleform' )
										: __( 'Placeholders: {field_name}, {all_fields}, {form_title}, {form_id}.', 'thimbleform' )
								);
								?>
							</span>
							<?php if ( $can_email ) : ?>
								<div class="thimbleform-mail-designer" data-thimbleform-mail-designer>
									<?php
									$logo_id  = (int) ( $mail['logo_id'] ?? 0 );
									$logo_url = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
									?>
									<div class="thimbleform-mail-designer__toolbar">
										<div class="thimbleform-mail-designer__group thimbleform-mail-designer__group--mode">
											<label class="thimbleform-admin__check">
												<input type="hidden" name="thimbleform[mail][html_enabled]" value="0" />
												<input type="checkbox" name="thimbleform[mail][html_enabled]" value="1" <?php checked( $html_on ); ?> data-thimbleform-mail-html />
												<span><?php esc_html_e( 'HTML email', 'thimbleform' ); ?></span>
											</label>
										</div>
										<div class="thimbleform-mail-designer__group thimbleform-mail-designer__group--brand">
											<input type="hidden" name="thimbleform[mail][logo_id]" value="<?php echo esc_attr( (string) $logo_id ); ?>" data-thimbleform-mail-logo-id />
											<button type="button" class="thimbleform-btn thimbleform-btn--outline" data-thimbleform-mail-logo><?php esc_html_e( 'Set logo', 'thimbleform' ); ?></button>
											<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text" data-thimbleform-mail-logo-clear<?php echo $logo_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'thimbleform' ); ?></button>
											<span class="thimbleform-mail-designer__logo-preview" data-thimbleform-mail-logo-preview>
												<?php if ( $logo_url ) : ?>
													<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
												<?php endif; ?>
											</span>
										</div>
										<div class="thimbleform-mail-designer__group thimbleform-mail-designer__group--layout">
											<label class="thimbleform-mail-designer__preset">
												<span class="screen-reader-text"><?php esc_html_e( 'Layout preset', 'thimbleform' ); ?></span>
												<select class="thimbleform-admin__input" data-thimbleform-mail-preset>
													<option value=""><?php esc_html_e( 'Layout presets…', 'thimbleform' ); ?></option>
													<?php foreach ( Thimbleform_Mail_Html::presets() as $pkey => $preset ) : ?>
														<option value="<?php echo esc_attr( $pkey ); ?>"><?php echo esc_html( $preset['label'] ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
										</div>
										<div class="thimbleform-mail-designer__group thimbleform-mail-designer__group--actions">
											<button type="button" class="thimbleform-btn thimbleform-btn--outline" data-thimbleform-mail-token="{all_fields}"><?php esc_html_e( 'Insert {all_fields}', 'thimbleform' ); ?></button>
											<button type="button" class="thimbleform-btn thimbleform-btn--primary" data-thimbleform-mail-preview><?php esc_html_e( 'Preview', 'thimbleform' ); ?></button>
										</div>
									</div>
									<?php
									wp_editor(
										(string) $mail['body_template'],
										'thimbleform_mail_body_template',
										array(
											'textarea_name' => 'thimbleform[mail][body_template]',
											'textarea_rows' => 12,
											'media_buttons' => true,
											'teeny'         => false,
											'quicktags'     => true,
											'tinymce'       => array(
												'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat',
												'toolbar2' => '',
											),
										)
									);
									?>
									<div class="thimbleform-mail-preview" data-thimbleform-mail-preview-panel hidden>
										<button type="button" class="thimbleform-mail-preview__backdrop" data-thimbleform-mail-preview-close aria-label="<?php esc_attr_e( 'Close preview', 'thimbleform' ); ?>"></button>
										<div class="thimbleform-mail-preview__dialog" role="dialog" aria-modal="true" aria-labelledby="thimbleform-mail-preview-title">
											<header class="thimbleform-mail-preview__head">
												<div class="thimbleform-mail-preview__copy">
													<strong id="thimbleform-mail-preview-title"><?php esc_html_e( 'Email preview', 'thimbleform' ); ?></strong>
													<span class="description"><?php esc_html_e( 'Sample merge tags filled in — not a real send.', 'thimbleform' ); ?></span>
												</div>
												<button type="button" class="button" data-thimbleform-mail-preview-close><?php esc_html_e( 'Close', 'thimbleform' ); ?></button>
											</header>
											<iframe class="thimbleform-mail-preview__frame" title="<?php esc_attr_e( 'Email preview', 'thimbleform' ); ?>" data-thimbleform-mail-preview-frame></iframe>
										</div>
									</div>
								</div>
								<script type="application/json" id="thimbleform-mail-presets"><?php echo wp_json_encode( Thimbleform_Mail_Html::presets() ); ?></script>
							<?php else : ?>
								<input type="hidden" name="thimbleform[mail][html_enabled]" value="0" />
								<input type="hidden" name="thimbleform[mail][logo_id]" value="0" />
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" rows="8" name="thimbleform[mail][body_template]"><?php echo esc_textarea( $mail['body_template'] ); ?></textarea>
							<?php endif; ?>
						</div>
						<?php if ( $can_pdf ) : ?>
						<label class="thimbleform-admin__check thimbleform-admin__check--full thimbleform-admin__mt-4">
							<input type="hidden" name="thimbleform[mail][pdf_attach]" value="0" />
							<input type="checkbox" name="thimbleform[mail][pdf_attach]" value="1" <?php checked( '1' === (string) ( $mail['pdf_attach'] ?? '0' ) ); ?> />
							<span>
								<?php esc_html_e( 'Attach PDF of the submission to this notification', 'thimbleform' ); ?>
								<?php self::render_field_tip( __( 'With HTML email on, the PDF uses your message body template. Otherwise it is a field report.', 'thimbleform' ) ); ?>
							</span>
						</label>
						<?php else : ?>
							<input type="hidden" name="thimbleform[mail][pdf_attach]" value="0" />
							<?php if ( ! $can_email && class_exists( 'Thimbleform_Promotion' ) ) : ?>
								<div class="thimbleform-admin__mt-4">
									<?php
									Thimbleform_Promotion::render_feature_teaser(
										array(
											'title'   => __( 'HTML email & PDF', 'thimbleform' ),
											'copy'    => __( 'Design HTML notifications with a logo and attach a PDF of each submission. Available in the Thimbleform Pro add-on.', 'thimbleform' ),
											'cta'     => __( 'See Thimbleform Pro', 'thimbleform' ),
											'compact' => true,
										)
									);
									?>
								</div>
							<?php elseif ( class_exists( 'Thimbleform_Promotion' ) ) : ?>
								<div class="thimbleform-admin__mt-4">
									<?php
									Thimbleform_Promotion::render_feature_teaser(
										array(
											'title'   => __( 'PDF attachments', 'thimbleform' ),
											'copy'    => __( 'Attach a PDF of each submission to the notification email. Available in Thimbleform Pro.', 'thimbleform' ),
											'cta'     => __( 'See Thimbleform Pro', 'thimbleform' ),
											'compact' => true,
										)
									);
									?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</section>
				</div>

				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'autoreply' === $mail_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="autoreply" id="thimbleform-mail-subpanel-autoreply" role="tabpanel" aria-labelledby="thimbleform-mail-subtab-autoreply"<?php echo 'autoreply' === $mail_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Visitor confirmation', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'Optional autoreply to the visitor\'s Reply-To email.', 'thimbleform' ); ?></p>
						<label class="thimbleform-admin__check">
							<input type="hidden" name="thimbleform[mail][user_mail_enabled]" value="0" />
							<input type="checkbox" name="thimbleform[mail][user_mail_enabled]" value="1" <?php checked( $user_mail_open ); ?> />
							<span><?php esc_html_e( 'Send a confirmation email to the Reply-To field', 'thimbleform' ); ?></span>
						</label>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2 thimbleform-admin__mt-4">
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Autoreply subject', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][user_mail_subject]" value="<?php echo esc_attr( (string) ( $mail['user_mail_subject'] ?? '' ) ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Autoreply body', 'thimbleform' ); ?></span>
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" rows="5" name="thimbleform[mail][user_mail_body]"><?php echo esc_textarea( (string) ( $mail['user_mail_body'] ?? '' ) ); ?></textarea>
							</label>
						</div>
					</section>
				</div>

				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'advanced' === $mail_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="advanced" id="thimbleform-mail-subpanel-advanced" role="tabpanel" aria-labelledby="thimbleform-mail-subtab-advanced"<?php echo 'advanced' === $mail_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Advanced', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'CC/BCC and a second notification when a field matches.', 'thimbleform' ); ?></p>
						<h5 class="thimbleform-admin__subsection-title"><?php esc_html_e( 'Copies', 'thimbleform' ); ?></h5>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'CC', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][cc]" value="<?php echo esc_attr( (string) ( $mail['cc'] ?? '' ) ); ?>" placeholder="cc@example.com" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'BCC', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][bcc]" value="<?php echo esc_attr( (string) ( $mail['bcc'] ?? '' ) ); ?>" placeholder="bcc@example.com" />
							</label>
						</div>
						<h5 class="thimbleform-admin__subsection-title"><?php esc_html_e( 'Extra notification', 'thimbleform' ); ?></h5>
						<label class="thimbleform-admin__check">
							<input type="hidden" name="thimbleform[mail][extra_enabled]" value="0" />
							<input type="checkbox" name="thimbleform[mail][extra_enabled]" value="1" <?php checked( (string) ( $mail['extra_enabled'] ?? '0' ), '1' ); ?> />
							<span><?php esc_html_e( 'Enable extra notification', 'thimbleform' ); ?></span>
						</label>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2 thimbleform-admin__mt-4">
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Extra To', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][extra_to]" value="<?php echo esc_attr( (string) ( $mail['extra_to'] ?? '' ) ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Extra subject', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][extra_subject]" value="<?php echo esc_attr( (string) ( $mail['extra_subject'] ?? '' ) ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'If field', 'thimbleform' ); ?></span>
								<select class="thimbleform-admin__input" name="thimbleform[mail][extra_condition_field]" data-thimbleform-extra-condition-field>
									<option value=""><?php esc_html_e( '— Always (when enabled) —', 'thimbleform' ); ?></option>
									<?php
									$extra_field = (string) ( $mail['extra_condition_field'] ?? '' );
									if ( $extra_field !== '' ) :
										?>
										<option value="<?php echo esc_attr( $extra_field ); ?>" selected><?php echo esc_html( $extra_field ); ?></option>
									<?php endif; ?>
								</select>
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Operator', 'thimbleform' ); ?></span>
								<select class="thimbleform-admin__input" name="thimbleform[mail][extra_condition_op]">
									<?php
									$extra_op = (string) ( $mail['extra_condition_op'] ?? 'equals' );
									foreach ( Thimbleform_Form_Config::condition_operators() as $op_key => $op_label ) :
										?>
										<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $extra_op, $op_key ); ?>><?php echo esc_html( $op_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Value', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[mail][extra_condition_value]" value="<?php echo esc_attr( (string) ( $mail['extra_condition_value'] ?? '' ) ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Extra body', 'thimbleform' ); ?></span>
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" rows="5" name="thimbleform[mail][extra_body]"><?php echo esc_textarea( (string) ( $mail['extra_body'] ?? '' ) ); ?></textarea>
							</label>
						</div>
					</section>
				</div>
				</div>
			</div>

			<?php
			$form_mode_val      = (string) ( $settings['form_mode'] ?? 'form' );
			$settings_spam_open = ( '1' === (string) ( $settings['enable_captcha'] ?? '0' )
				|| '1' === (string) ( $settings['enable_akismet'] ?? '0' )
				|| '0' === (string) ( $settings['store_ip'] ?? '1' ) );
			$can_payments = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::PAYMENTS );
			$can_hubspot  = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::HUBSPOT );
			$settings_payments_open = $can_payments && '1' === (string) ( $settings['enable_stripe'] ?? '0' );
			$settings_hubspot_open  = $can_hubspot && '1' === (string) ( $settings['enable_hubspot'] ?? '0' );
			$settings_quiz_open = in_array( $form_mode_val, array( 'quiz', 'survey' ), true );
			$settings_hook_open = '1' === (string) ( $settings['webhook_enabled'] ?? '0' );
			$settings_auto_open = '1' === (string) ( $settings['automation_enabled'] ?? '0' );
			$captcha_status     = class_exists( 'Thimbleform_Captcha' ) ? Thimbleform_Captcha::admin_status() : array(
				'global_on' => false,
				'message'   => '',
				'url'       => '',
			);
			$stripe_ready       = $can_payments && class_exists( 'Thimbleform_Settings' ) && Thimbleform_Settings::stripe_ready();
			$stripe_mode_label  = class_exists( 'Thimbleform_Settings' ) && 'live' === Thimbleform_Settings::stripe_mode()
				? __( 'Live', 'thimbleform' )
				: __( 'Test', 'thimbleform' );
			$hubspot_ready      = $can_hubspot && class_exists( 'Thimbleform_Settings' ) && Thimbleform_Settings::hubspot_ready();
			$hubspot_map        = Thimbleform_Form_Config::sanitize_hubspot_map( isset( $settings['hubspot_map'] ) ? $settings['hubspot_map'] : array() );
			$hubspot_labels     = Thimbleform_Form_Config::hubspot_map_labels();
			$integrations_stripe_url = class_exists( 'Thimbleform_Integrations' )
				? Thimbleform_Integrations::url( array( 'section' => 'stripe' ) )
				: '';
			$integrations_hubspot_url = class_exists( 'Thimbleform_Integrations' )
				? Thimbleform_Integrations::url( array( 'section' => 'hubspot' ) )
				: '';
			$map_field_options = array();
			foreach ( $fields as $map_field ) {
				if ( ! is_array( $map_field ) ) {
					continue;
				}
				$map_type = (string) ( $map_field['type'] ?? '' );
				if ( Thimbleform_Form_Config::is_layout_field( $map_type ) || 'payment' === $map_type ) {
					continue;
				}
				$map_name = sanitize_key( (string) ( $map_field['name'] ?? '' ) );
				if ( '' === $map_name ) {
					continue;
				}
				$map_label = trim( (string) ( $map_field['label'] ?? '' ) );
				$map_field_options[ $map_name ] = '' !== $map_label
					? sprintf( '%s (%s)', $map_label, $map_name )
					: $map_name;
			}
			?>
			<div class="thimbleform-admin__panel<?php echo 'settings' === $active_tab ? ' is-active' : ''; ?>" data-thimbleform-panel="settings" id="thimbleform-panel-settings" role="tabpanel" aria-labelledby="thimbleform-tab-settings"<?php echo 'settings' === $active_tab ? '' : ' hidden'; ?>>
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Settings', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Submit behavior, spam protection, webhooks, and optional Pro features when licensed.', 'thimbleform' ); ?></p>
					</div>
				</div>

				<?php
				$settings_subtabs = array(
					'submit' => __( 'Submit', 'thimbleform' ),
					'spam'   => __( 'Spam', 'thimbleform' ),
				);
				if ( $can_payments ) {
					$settings_subtabs['payments'] = __( 'Payments', 'thimbleform' );
				}
				if ( $can_hubspot ) {
					$settings_subtabs['hubspot'] = __( 'HubSpot', 'thimbleform' );
				}
				if ( ! empty( $can_quiz ) ) {
					$settings_subtabs['quiz'] = __( 'Quiz', 'thimbleform' );
				}
				$settings_subtabs['webhooks'] = __( 'Webhooks', 'thimbleform' );
				if ( ! empty( $can_auto ) ) {
					$settings_subtabs['automations'] = __( 'Automations', 'thimbleform' );
				}
				$settings_subtab = 'submit';
				if ( ! isset( $settings_subtabs[ $settings_subtab ] ) ) {
					$settings_subtab = 'submit';
				}
				?>
				<div class="thimbleform-admin__subtabs" data-thimbleform-subtabs data-thimbleform-subtabs-key="settings" data-thimbleform-subtabs-default="<?php echo esc_attr( $settings_subtab ); ?>">
					<nav class="thimbleform-settings__subnav thimbleform-admin__subtabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'thimbleform' ); ?>">
						<?php foreach ( $settings_subtabs as $sub_id => $sub_label ) : ?>
							<?php $sub_on = $settings_subtab === $sub_id; ?>
							<button
								type="button"
								class="thimbleform-settings__subnav-item<?php echo $sub_on ? ' thimbleform-settings__subnav-item--active' : ''; ?>"
								role="tab"
								id="thimbleform-settings-subtab-<?php echo esc_attr( $sub_id ); ?>"
								aria-selected="<?php echo $sub_on ? 'true' : 'false'; ?>"
								aria-controls="thimbleform-settings-subpanel-<?php echo esc_attr( $sub_id ); ?>"
								tabindex="<?php echo $sub_on ? '0' : '-1'; ?>"
								data-thimbleform-subtab="<?php echo esc_attr( $sub_id ); ?>"
							>
								<?php echo esc_html( $sub_label ); ?>
							</button>
						<?php endforeach; ?>
					</nav>

				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'submit' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="submit" id="thimbleform-settings-subpanel-submit" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-submit"<?php echo 'submit' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Submit & thank-you', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'Button label, where the success message appears, and optional redirect.', 'thimbleform' ); ?></p>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Submit button label', 'thimbleform' ); ?></span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][submit_label]" value="<?php echo esc_attr( $settings['submit_label'] ); ?>" />
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Thank-you display', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Where to show the success message from the Messages tab. Redirect (if set) still runs after.', 'thimbleform' ) ); ?>
								</span>
								<?php $success_display = (string) ( $settings['success_display'] ?? 'inline' ); ?>
								<select class="thimbleform-admin__input" name="thimbleform[settings][success_display]">
									<option value="inline" <?php selected( $success_display, 'inline' ); ?>><?php esc_html_e( 'Below the form', 'thimbleform' ); ?></option>
									<option value="replace" <?php selected( $success_display, 'replace' ); ?>><?php esc_html_e( 'Replace the form', 'thimbleform' ); ?></option>
									<option value="popup" <?php selected( $success_display, 'popup' ); ?>><?php esc_html_e( 'Popup', 'thimbleform' ); ?></option>
								</select>
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Redirect URL', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Optional. Leave empty to stay on the page and show the success message.', 'thimbleform' ) ); ?>
								</span>
								<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][redirect_url]" value="<?php echo esc_attr( $settings['redirect_url'] ); ?>" placeholder="https://game.example/play?email={email}" spellcheck="false" />
								<p class="thimbleform-admin__hint">
									<?php
									esc_html_e(
										'Use {field_name} merge tags — the Name from each field (not the label). Values are URL-encoded automatically. Also: {form_id}, {form_title}, {entry_id}.',
										'thimbleform'
									);
									?>
									<code class="thimbleform-admin__hint-code">https://game.example/play?email={email}</code>
								</p>
							</label>
						</div>
					</section>
				</div>

				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'spam' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="spam" id="thimbleform-settings-subpanel-spam" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-spam"<?php echo 'spam' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Spam & privacy', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc">
							<?php
							if ( ! empty( $captcha_status['global_on'] ) && ! empty( $captcha_status['provider_label'] ) ) {
								echo esc_html(
									sprintf(
										/* translators: %s: captcha provider short name */
										__( 'Captcha ready: %s · time trap, Akismet, IP storage.', 'thimbleform' ),
										(string) $captcha_status['provider_label']
									)
								);
							} else {
								esc_html_e( 'Captcha, time trap, Akismet, and IP storage.', 'thimbleform' );
							}
							?>
						</p>
							<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
								<label class="thimbleform-admin__check thimbleform-admin__field-control--full thimbleform-admin__captcha-toggle thimbleform-admin__mt-0">
									<input type="hidden" name="thimbleform[settings][enable_captcha]" value="0" />
									<input type="checkbox" name="thimbleform[settings][enable_captcha]" value="1" <?php checked( (string) ( $settings['enable_captcha'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Enable captcha on this form', 'thimbleform' ); ?></span>
									<?php if ( ! empty( $captcha_status['global_on'] ) && ! empty( $captcha_status['provider_label'] ) ) : ?>
										<span class="thimbleform-badge thimbleform-badge--ok thimbleform-admin__captcha-provider"><?php echo esc_html( (string) $captcha_status['provider_label'] ); ?></span>
									<?php elseif ( ! empty( $captcha_status['provider_label'] ) ) : ?>
										<span class="thimbleform-badge thimbleform-badge--draft thimbleform-admin__captcha-provider"><?php echo esc_html( (string) $captcha_status['provider_label'] ); ?></span>
									<?php endif; ?>
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Time trap (seconds)', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'Reject (silently) submits faster than this. 0 = off.', 'thimbleform' ) ); ?>
									</span>
									<input type="number" min="0" max="60" class="thimbleform-admin__input" name="thimbleform[settings][time_trap_seconds]" value="<?php echo esc_attr( (string) ( $settings['time_trap_seconds'] ?? '3' ) ); ?>" />
								</label>
								<label class="thimbleform-admin__check thimbleform-admin__field-control--full">
									<input type="hidden" name="thimbleform[settings][enable_akismet]" value="0" />
									<input type="checkbox" name="thimbleform[settings][enable_akismet]" value="1" <?php checked( (string) ( $settings['enable_akismet'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Check with Akismet (if plugin active)', 'thimbleform' ); ?></span>
								</label>
								<label class="thimbleform-admin__check thimbleform-admin__field-control--full">
									<input type="hidden" name="thimbleform[settings][store_ip]" value="0" />
									<input type="checkbox" name="thimbleform[settings][store_ip]" value="1" <?php checked( (string) ( $settings['store_ip'] ?? '1' ), '1' ); ?> />
									<span><?php esc_html_e( 'Store visitor IP with entries', 'thimbleform' ); ?></span>
								</label>
							</div>
							<div class="thimbleform-admin__note<?php echo empty( $captcha_status['global_on'] ) ? ' thimbleform-admin__note--warn' : ''; ?>">
								<strong><?php esc_html_e( 'Site captcha', 'thimbleform' ); ?></strong>
								<p><?php echo esc_html( (string) ( $captcha_status['message'] ?? '' ) ); ?></p>
								<?php if ( ! empty( $captcha_status['url'] ) ) : ?>
									<p><a href="<?php echo esc_url( (string) $captcha_status['url'] ); ?>"><?php esc_html_e( 'Open Integrations', 'thimbleform' ); ?></a></p>
								<?php endif; ?>
								<p><?php esc_html_e( 'Also built-in: nonce, honeypot, IP rate limit.', 'thimbleform' ); ?></p>
							</div>
					</section>
				</div>

					<?php if ( $can_payments ) : ?>
				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'payments' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="payments" id="thimbleform-settings-subpanel-payments" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-payments"<?php echo 'payments' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Payments', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc">
							<?php
							if ( $stripe_ready && '1' === (string) ( $settings['enable_stripe'] ?? '0' ) ) {
								echo esc_html(
									sprintf(
										/* translators: %s: Stripe mode (Test or Live) */
										__( 'Stripe on · %s mode. Add a Payment field to charge visitors.', 'thimbleform' ),
										$stripe_mode_label
									)
								);
							} elseif ( $stripe_ready ) {
								esc_html_e( 'Stripe keys ready — enable payments on this form.', 'thimbleform' );
							} else {
								esc_html_e( 'Stripe card payments via Thimbleform Pro Payment fields.', 'thimbleform' );
							}
							?>
						</p>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
							<label class="thimbleform-admin__check thimbleform-admin__field-control--full thimbleform-admin__mt-0">
								<input type="hidden" name="thimbleform[settings][enable_stripe]" value="0" />
								<input type="checkbox" name="thimbleform[settings][enable_stripe]" value="1" <?php checked( (string) ( $settings['enable_stripe'] ?? '0' ), '1' ); ?> />
								<span><?php esc_html_e( 'Enable Stripe payments on this form', 'thimbleform' ); ?></span>
								<?php if ( $stripe_ready ) : ?>
									<span class="thimbleform-badge thimbleform-badge--ok"><?php echo esc_html( $stripe_mode_label ); ?></span>
								<?php else : ?>
									<span class="thimbleform-badge thimbleform-badge--draft"><?php esc_html_e( 'Keys needed', 'thimbleform' ); ?></span>
								<?php endif; ?>
							</label>
						</div>
						<div class="thimbleform-admin__note<?php echo $stripe_ready ? '' : ' thimbleform-admin__note--warn'; ?>">
							<strong><?php esc_html_e( 'How it works', 'thimbleform' ); ?></strong>
							<p><?php esc_html_e( '1) Save Stripe keys under Forms → Integrations. 2) Enable payments here. 3) Add a Payment field in the builder with amount and currency.', 'thimbleform' ); ?></p>
							<?php if ( $integrations_stripe_url ) : ?>
								<p><a href="<?php echo esc_url( $integrations_stripe_url ); ?>"><?php esc_html_e( 'Open Integrations → Stripe', 'thimbleform' ); ?></a></p>
							<?php endif; ?>
						</div>
					</section>
				</div>
					<?php endif; ?>

					<?php if ( $can_hubspot ) : ?>
				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'hubspot' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="hubspot" id="thimbleform-settings-subpanel-hubspot" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-hubspot"<?php echo 'hubspot' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'HubSpot', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc">
							<?php
							if ( $hubspot_ready && '1' === (string) ( $settings['enable_hubspot'] ?? '0' ) ) {
								esc_html_e( 'HubSpot on — contacts sync on successful submit.', 'thimbleform' );
							} elseif ( $hubspot_ready ) {
								esc_html_e( 'HubSpot token ready — enable sync and map fields.', 'thimbleform' );
							} else {
								esc_html_e( 'Create or update HubSpot contacts from submissions.', 'thimbleform' );
							}
							?>
						</p>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
							<label class="thimbleform-admin__check thimbleform-admin__field-control--full thimbleform-admin__mt-0">
								<input type="hidden" name="thimbleform[settings][enable_hubspot]" value="0" />
								<input type="checkbox" name="thimbleform[settings][enable_hubspot]" value="1" <?php checked( (string) ( $settings['enable_hubspot'] ?? '0' ), '1' ); ?> />
								<span><?php esc_html_e( 'Enable HubSpot sync on this form', 'thimbleform' ); ?></span>
								<?php if ( $hubspot_ready ) : ?>
									<span class="thimbleform-badge thimbleform-badge--ok"><?php esc_html_e( 'Ready', 'thimbleform' ); ?></span>
								<?php else : ?>
									<span class="thimbleform-badge thimbleform-badge--draft"><?php esc_html_e( 'Token needed', 'thimbleform' ); ?></span>
								<?php endif; ?>
							</label>
						</div>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2 thimbleform-admin__mt-4">
							<?php foreach ( $hubspot_labels as $hs_prop => $hs_label ) : ?>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label">
										<?php echo esc_html( $hs_label ); ?>
										<?php if ( 'email' === $hs_prop ) : ?>
											<?php self::render_field_tip( __( 'Required for sync. Leave empty to auto-pick the first Email field.', 'thimbleform' ) ); ?>
										<?php endif; ?>
									</span>
									<select class="thimbleform-admin__input" name="thimbleform[settings][hubspot_map][<?php echo esc_attr( $hs_prop ); ?>]">
										<option value=""><?php esc_html_e( '— Not mapped —', 'thimbleform' ); ?></option>
										<?php foreach ( $map_field_options as $opt_name => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_name ); ?>" <?php selected( (string) ( $hubspot_map[ $hs_prop ] ?? '' ), $opt_name ); ?>>
												<?php echo esc_html( $opt_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="thimbleform-admin__note<?php echo $hubspot_ready ? '' : ' thimbleform-admin__note--warn'; ?>">
							<strong><?php esc_html_e( 'How it works', 'thimbleform' ); ?></strong>
							<p><?php esc_html_e( '1) Save a HubSpot Private App token under Forms → Integrations. 2) Enable sync here and map Email (and optional name/phone/company). 3) Thimbleform Pro creates or updates the contact after a successful submit.', 'thimbleform' ); ?></p>
							<?php if ( $integrations_hubspot_url ) : ?>
								<p><a href="<?php echo esc_url( $integrations_hubspot_url ); ?>"><?php esc_html_e( 'Open Integrations → HubSpot', 'thimbleform' ); ?></a></p>
							<?php endif; ?>
						</div>
					</section>
				</div>
					<?php endif; ?>

					<?php if ( $can_quiz ) : ?>
				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'quiz' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="quiz" id="thimbleform-settings-subpanel-quiz" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-quiz"<?php echo 'quiz' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Quiz & survey', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'Scoring, result messages, timer, attempts, resume, and shareable results.', 'thimbleform' ); ?></p>
						<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Form mode', 'thimbleform' ); ?></span>
									<select class="thimbleform-admin__input" name="thimbleform[settings][form_mode]">
										<option value="form" <?php selected( $form_mode_val, 'form' ); ?>><?php esc_html_e( 'Standard form', 'thimbleform' ); ?></option>
										<option value="quiz" <?php selected( $form_mode_val, 'quiz' ); ?>><?php esc_html_e( 'Quiz (scored)', 'thimbleform' ); ?></option>
										<option value="survey" <?php selected( $form_mode_val, 'survey' ); ?>><?php esc_html_e( 'Survey', 'thimbleform' ); ?></option>
									</select>
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Timer (seconds)', 'thimbleform' ); ?></span>
									<input type="number" min="0" max="7200" class="thimbleform-admin__input" name="thimbleform[settings][quiz_timer_seconds]" value="<?php echo esc_attr( (string) ( $settings['quiz_timer_seconds'] ?? '0' ) ); ?>" />
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Max attempts', 'thimbleform' ); ?></span>
									<input type="number" min="0" max="50" class="thimbleform-admin__input" name="thimbleform[settings][quiz_max_attempts]" value="<?php echo esc_attr( (string) ( $settings['quiz_max_attempts'] ?? '0' ) ); ?>" />
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Limit attempts by', 'thimbleform' ); ?></span>
									<select class="thimbleform-admin__input" name="thimbleform[settings][quiz_attempt_by]">
										<option value="ip" <?php selected( (string) ( $settings['quiz_attempt_by'] ?? 'ip' ), 'ip' ); ?>><?php esc_html_e( 'IP address', 'thimbleform' ); ?></option>
										<option value="email" <?php selected( (string) ( $settings['quiz_attempt_by'] ?? '' ), 'email' ); ?>><?php esc_html_e( 'Email field', 'thimbleform' ); ?></option>
									</select>
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Attempt email field', 'thimbleform' ); ?></span>
									<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_attempt_field]" value="<?php echo esc_attr( (string) ( $settings['quiz_attempt_field'] ?? 'email' ) ); ?>" placeholder="email" />
								</label>
								<div class="thimbleform-admin__field-control thimbleform-admin__field-control--full">
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Result messages', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'After the quiz, show a message based on the score percent (0–100). First matching range wins.', 'thimbleform' ) ); ?>
									</span>
									<?php
									$quiz_bands = Thimbleform_Form_Config::parse_quiz_bands( (string) ( $settings['quiz_results'] ?? '' ) );
									if ( array() === $quiz_bands ) {
										$quiz_bands = array(
											array(
												'min'      => '0',
												'max'      => '49',
												'title'    => '',
												'message'  => '',
												'redirect' => '',
											),
											array(
												'min'      => '50',
												'max'      => '79',
												'title'    => '',
												'message'  => '',
												'redirect' => '',
											),
											array(
												'min'      => '80',
												'max'      => '100',
												'title'    => '',
												'message'  => '',
												'redirect' => '',
											),
										);
									}
									?>
									<div class="thimbleform-bands" data-thimbleform-bands>
										<p class="thimbleform-bands__lead">
											<?php esc_html_e( 'Example: 0–49 = Keep practicing, 50–79 = Good job, 80–100 = Excellent. Ranges use score %.', 'thimbleform' ); ?>
										</p>
										<div class="thimbleform-bands__list" data-thimbleform-bands-list>
											<?php foreach ( $quiz_bands as $bi => $band ) : ?>
												<div class="thimbleform-bands__row" data-thimbleform-bands-row>
													<label class="thimbleform-admin__field-control thimbleform-bands__from">
														<span class="thimbleform-admin__label"><?php esc_html_e( 'From %', 'thimbleform' ); ?></span>
														<input type="number" min="0" max="100" step="1" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][<?php echo (int) $bi; ?>][min]" value="<?php echo esc_attr( (string) ( $band['min'] ?? '0' ) ); ?>" placeholder="0" data-thimbleform-bands-min />
													</label>
													<label class="thimbleform-admin__field-control thimbleform-bands__to">
														<span class="thimbleform-admin__label"><?php esc_html_e( 'To %', 'thimbleform' ); ?></span>
														<input type="number" min="0" max="100" step="1" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][<?php echo (int) $bi; ?>][max]" value="<?php echo esc_attr( (string) ( $band['max'] ?? '100' ) ); ?>" placeholder="100" data-thimbleform-bands-max />
													</label>
													<label class="thimbleform-admin__field-control thimbleform-bands__title">
														<span class="thimbleform-admin__label"><?php esc_html_e( 'Title', 'thimbleform' ); ?></span>
														<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][<?php echo (int) $bi; ?>][title]" value="<?php echo esc_attr( (string) ( $band['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Good job', 'thimbleform' ); ?>" data-thimbleform-bands-title />
													</label>
													<label class="thimbleform-admin__field-control thimbleform-bands__message">
														<span class="thimbleform-admin__label"><?php esc_html_e( 'Message', 'thimbleform' ); ?></span>
														<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][<?php echo (int) $bi; ?>][message]" value="<?php echo esc_attr( (string) ( $band['message'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Solid score — keep going.', 'thimbleform' ); ?>" data-thimbleform-bands-message />
													</label>
													<label class="thimbleform-admin__field-control thimbleform-bands__redirect">
														<span class="thimbleform-admin__label"><?php esc_html_e( 'Redirect URL', 'thimbleform' ); ?></span>
														<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][<?php echo (int) $bi; ?>][redirect]" value="<?php echo esc_attr( (string) ( $band['redirect'] ?? '' ) ); ?>" placeholder="https://game.example/?email={email}" data-thimbleform-bands-redirect spellcheck="false" />
													</label>
													<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-bands__remove" data-thimbleform-bands-remove aria-label="<?php esc_attr_e( 'Remove result', 'thimbleform' ); ?>">
														<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
													</button>
												</div>
											<?php endforeach; ?>
										</div>
										<p class="thimbleform-bands__actions">
											<button type="button" class="button" data-thimbleform-bands-add <?php echo count( $quiz_bands ) >= 8 ? 'hidden' : ''; ?>>
												<?php esc_html_e( 'Add result', 'thimbleform' ); ?>
											</button>
											<span class="thimbleform-bands__limit"><?php esc_html_e( 'Up to 8 results', 'thimbleform' ); ?></span>
										</p>
										<template data-thimbleform-bands-tpl>
											<div class="thimbleform-bands__row" data-thimbleform-bands-row>
												<label class="thimbleform-admin__field-control thimbleform-bands__from">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'From %', 'thimbleform' ); ?></span>
													<input type="number" min="0" max="100" step="1" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][__i__][min]" value="0" placeholder="0" data-thimbleform-bands-min />
												</label>
												<label class="thimbleform-admin__field-control thimbleform-bands__to">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'To %', 'thimbleform' ); ?></span>
													<input type="number" min="0" max="100" step="1" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][__i__][max]" value="100" placeholder="100" data-thimbleform-bands-max />
												</label>
												<label class="thimbleform-admin__field-control thimbleform-bands__title">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'Title', 'thimbleform' ); ?></span>
													<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][__i__][title]" value="" placeholder="<?php esc_attr_e( 'Good job', 'thimbleform' ); ?>" data-thimbleform-bands-title />
												</label>
												<label class="thimbleform-admin__field-control thimbleform-bands__message">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'Message', 'thimbleform' ); ?></span>
													<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][__i__][message]" value="" placeholder="<?php esc_attr_e( 'Solid score — keep going.', 'thimbleform' ); ?>" data-thimbleform-bands-message />
												</label>
												<label class="thimbleform-admin__field-control thimbleform-bands__redirect">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'Redirect URL', 'thimbleform' ); ?></span>
													<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_bands][__i__][redirect]" value="" placeholder="https://game.example/?email={email}" data-thimbleform-bands-redirect spellcheck="false" />
												</label>
												<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-bands__remove" data-thimbleform-bands-remove aria-label="<?php esc_attr_e( 'Remove result', 'thimbleform' ); ?>">
													<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
												</button>
											</div>
										</template>
									</div>
								</div>
								<label class="thimbleform-admin__check">
									<input type="hidden" name="thimbleform[settings][quiz_show_score]" value="0" />
									<input type="checkbox" name="thimbleform[settings][quiz_show_score]" value="1" <?php checked( (string) ( $settings['quiz_show_score'] ?? '1' ), '1' ); ?> />
									<span><?php esc_html_e( 'Show score on result', 'thimbleform' ); ?></span>
								</label>
								<label class="thimbleform-admin__check">
									<input type="hidden" name="thimbleform[settings][quiz_show_answers]" value="0" />
									<input type="checkbox" name="thimbleform[settings][quiz_show_answers]" value="1" <?php checked( (string) ( $settings['quiz_show_answers'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Show submitted answers', 'thimbleform' ); ?></span>
								</label>
								<label class="thimbleform-admin__check">
									<input type="hidden" name="thimbleform[settings][partial_save]" value="0" />
									<input type="checkbox" name="thimbleform[settings][partial_save]" value="1" <?php checked( (string) ( $settings['partial_save'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Allow resume (partial save)', 'thimbleform' ); ?></span>
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Resume TTL (days)', 'thimbleform' ); ?></span>
									<input type="number" min="1" max="30" class="thimbleform-admin__input" name="thimbleform[settings][partial_ttl_days]" value="<?php echo esc_attr( (string) ( $settings['partial_ttl_days'] ?? '7' ) ); ?>" />
								</label>
								<label class="thimbleform-admin__check thimbleform-admin__field-control--full">
									<input type="hidden" name="thimbleform[settings][share_results]" value="0" />
									<input type="checkbox" name="thimbleform[settings][share_results]" value="1" <?php checked( (string) ( $settings['share_results'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Generate shareable result link', 'thimbleform' ); ?></span>
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Result CTA label', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'Button on the result screen. Uses the band redirect URL, or the form Redirect URL if the band has none.', 'thimbleform' ) ); ?>
									</span>
									<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][quiz_cta_label]" value="<?php echo esc_attr( (string) ( $settings['quiz_cta_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Continue', 'thimbleform' ); ?>" />
								</label>
							</div>
					</section>
				</div>
					<?php endif; ?>

				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'webhooks' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="webhooks" id="thimbleform-settings-subpanel-webhooks" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-webhooks"<?php echo 'webhooks' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Webhooks', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'POST JSON to one or more HTTPS endpoints after each successful submission (non-blocking).', 'thimbleform' ); ?></p>
							<div class="thimbleform-webhooks" data-thimbleform-webhooks>
								<label class="thimbleform-admin__check thimbleform-admin__field-control--full">
									<input type="hidden" name="thimbleform[settings][webhook_enabled]" value="0" />
									<input type="checkbox" name="thimbleform[settings][webhook_enabled]" value="1" <?php checked( $settings_hook_open ); ?> data-thimbleform-webhooks-enabled />
									<span><?php esc_html_e( 'Enable webhooks', 'thimbleform' ); ?></span>
								</label>
								<?php
								$webhook_endpoints = Thimbleform_Form_Config::webhook_endpoints_from_settings( $settings );
								if ( array() === $webhook_endpoints ) {
									$webhook_endpoints = array(
										array(
											'url'    => '',
											'secret' => '',
										),
									);
								}
								$webhook_max = class_exists( 'Thimbleform_Webhook' ) ? Thimbleform_Webhook::ENDPOINT_MAX : 5;
								?>
								<div class="thimbleform-webhooks__list" data-thimbleform-webhooks-list>
									<?php foreach ( $webhook_endpoints as $wi => $endpoint ) : ?>
										<div class="thimbleform-webhooks__row" data-thimbleform-webhooks-row>
											<label class="thimbleform-admin__field-control thimbleform-webhooks__url">
												<span class="thimbleform-admin__label">
													<?php esc_html_e( 'Endpoint URL', 'thimbleform' ); ?>
													<?php self::render_field_tip( __( 'HTTPS endpoint that accepts application/json POST.', 'thimbleform' ) ); ?>
												</span>
												<input type="url" class="thimbleform-admin__input" name="thimbleform[settings][webhook_endpoints][<?php echo (int) $wi; ?>][url]" value="<?php echo esc_attr( (string) ( $endpoint['url'] ?? '' ) ); ?>" placeholder="https://" data-thimbleform-webhooks-url />
											</label>
											<label class="thimbleform-admin__field-control thimbleform-webhooks__secret">
												<span class="thimbleform-admin__label">
													<?php esc_html_e( 'Secret (optional)', 'thimbleform' ); ?>
													<?php self::render_field_tip( __( 'Sent as X-Thimbleform-Secret header for this endpoint.', 'thimbleform' ) ); ?>
												</span>
												<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][webhook_endpoints][<?php echo (int) $wi; ?>][secret]" value="<?php echo esc_attr( (string) ( $endpoint['secret'] ?? '' ) ); ?>" autocomplete="off" data-thimbleform-webhooks-secret />
											</label>
											<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-webhooks__remove" data-thimbleform-webhooks-remove aria-label="<?php esc_attr_e( 'Remove endpoint', 'thimbleform' ); ?>">
												<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
											</button>
										</div>
									<?php endforeach; ?>
								</div>
								<p class="thimbleform-webhooks__actions">
									<button type="button" class="thimbleform-btn thimbleform-btn--outline" data-thimbleform-webhooks-add <?php echo count( $webhook_endpoints ) >= $webhook_max ? 'hidden' : ''; ?>>
										<?php esc_html_e( 'Add endpoint', 'thimbleform' ); ?>
									</button>
									<span class="thimbleform-webhooks__limit">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: max endpoints */
												__( 'Up to %d endpoints', 'thimbleform' ),
												$webhook_max
											)
										);
										?>
									</span>
								</p>
								<template data-thimbleform-webhooks-tpl>
									<div class="thimbleform-webhooks__row" data-thimbleform-webhooks-row>
										<label class="thimbleform-admin__field-control thimbleform-webhooks__url">
											<span class="thimbleform-admin__label"><?php esc_html_e( 'Endpoint URL', 'thimbleform' ); ?></span>
											<input type="url" class="thimbleform-admin__input" name="thimbleform[settings][webhook_endpoints][__i__][url]" value="" placeholder="https://" data-thimbleform-webhooks-url />
										</label>
										<label class="thimbleform-admin__field-control thimbleform-webhooks__secret">
											<span class="thimbleform-admin__label"><?php esc_html_e( 'Secret (optional)', 'thimbleform' ); ?></span>
											<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][webhook_endpoints][__i__][secret]" value="" autocomplete="off" data-thimbleform-webhooks-secret />
										</label>
										<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-webhooks__remove" data-thimbleform-webhooks-remove aria-label="<?php esc_attr_e( 'Remove endpoint', 'thimbleform' ); ?>">
											<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
										</button>
									</div>
								</template>
							</div>
					</section>
				</div>

					<?php if ( $can_auto ) : ?>
				<div class="thimbleform-admin__stack thimbleform-admin__subpanel<?php echo 'automations' === $settings_subtab ? ' is-active' : ''; ?>" data-thimbleform-subpanel="automations" id="thimbleform-settings-subpanel-automations" role="tabpanel" aria-labelledby="thimbleform-settings-subtab-automations"<?php echo 'automations' === $settings_subtab ? '' : ' hidden'; ?>>
					<section class="thimbleform-admin__block thimbleform-admin__block--primary">
						<h4 class="thimbleform-admin__section-title"><?php esc_html_e( 'Automations', 'thimbleform' ); ?></h4>
						<p class="thimbleform-admin__section-desc"><?php esc_html_e( 'When submitted → if conditions → then actions.', 'thimbleform' ); ?></p>
								<?php
								$auto_rules = Thimbleform_Form_Config::get_automation_rules( $settings );
								if ( array() === $auto_rules ) {
									$auto_rules = array(
										array(
											'field' => '',
											'op'    => 'equals',
											'value' => '',
										),
									);
								}
								$auto_match      = (string) ( $settings['automation_match'] ?? 'all' );
								$auto_skip_spam  = '0' !== (string) ( $settings['automation_skip_spam'] ?? '1' );
								$auto_status     = (string) ( $settings['automation_then_status'] ?? '' );
								$auto_email      = (string) ( $settings['automation_then_email'] ?? '' );
								$auto_webhook    = (string) ( $settings['automation_then_webhook'] ?? '' );
								$auto_has_then   = ( $auto_status !== '' || $auto_email !== '' || $auto_webhook !== '' );
								$field_options   = array();
								foreach ( $fields as $f ) {
									$fn = (string) ( $f['name'] ?? '' );
									if ( $fn === '' ) {
										continue;
									}
									$field_options[ $fn ] = (string) ( $f['label'] ?? $fn ) . ' (' . $fn . ')';
								}
								$ops = Thimbleform_Form_Config::condition_operators();
								?>
							<div class="thimbleform-auto" data-thimbleform-auto>
								<section class="thimbleform-auto__step thimbleform-auto__step--when">
									<span class="thimbleform-auto__badge"><?php esc_html_e( 'When', 'thimbleform' ); ?></span>
									<p class="thimbleform-auto__lead"><?php esc_html_e( 'On form submit', 'thimbleform' ); ?></p>
									<label class="thimbleform-admin__check">
										<input type="hidden" name="thimbleform[settings][automation_enabled]" value="0" />
										<input type="checkbox" name="thimbleform[settings][automation_enabled]" value="1" <?php checked( $settings_auto_open ); ?> data-thimbleform-auto-enabled />
										<span><?php esc_html_e( 'Enable automation', 'thimbleform' ); ?></span>
									</label>
									<label class="thimbleform-admin__check">
										<input type="hidden" name="thimbleform[settings][automation_skip_spam]" value="0" />
										<input type="checkbox" name="thimbleform[settings][automation_skip_spam]" value="1" <?php checked( $auto_skip_spam ); ?> />
										<span><?php esc_html_e( 'Skip spam entries', 'thimbleform' ); ?></span>
									</label>
								</section>

								<section class="thimbleform-auto__step thimbleform-auto__step--if">
									<span class="thimbleform-auto__badge"><?php esc_html_e( 'If', 'thimbleform' ); ?></span>
									<p class="thimbleform-auto__lead"><?php esc_html_e( 'Optional conditions. Leave field empty (or remove all) to always run.', 'thimbleform' ); ?></p>
									<label class="thimbleform-admin__field-control thimbleform-auto__match">
										<span class="thimbleform-admin__label"><?php esc_html_e( 'Match', 'thimbleform' ); ?></span>
										<select class="thimbleform-admin__input" name="thimbleform[settings][automation_match]" data-thimbleform-auto-match>
											<option value="all" <?php selected( $auto_match, 'all' ); ?>><?php esc_html_e( 'All conditions (AND)', 'thimbleform' ); ?></option>
											<option value="any" <?php selected( $auto_match, 'any' ); ?>><?php esc_html_e( 'Any condition (OR)', 'thimbleform' ); ?></option>
										</select>
									</label>
									<div class="thimbleform-auto__rules" data-thimbleform-auto-rules>
										<?php foreach ( $auto_rules as $ri => $rule ) : ?>
											<?php
											$r_field = (string) ( $rule['field'] ?? '' );
											$r_op    = (string) ( $rule['op'] ?? 'equals' );
											$r_val   = (string) ( $rule['value'] ?? '' );
											$hide_val = in_array( $r_op, array( 'empty', 'not_empty' ), true );
											?>
											<div class="thimbleform-auto__rule" data-thimbleform-auto-rule>
												<label class="thimbleform-admin__field-control">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'Field', 'thimbleform' ); ?></span>
													<select class="thimbleform-admin__input" name="thimbleform[settings][automation_rules][<?php echo (int) $ri; ?>][field]" data-thimbleform-auto-field>
														<option value=""><?php esc_html_e( '— Select —', 'thimbleform' ); ?></option>
														<?php foreach ( $field_options as $fn => $fl ) : ?>
															<option value="<?php echo esc_attr( $fn ); ?>" <?php selected( $r_field, $fn ); ?>><?php echo esc_html( $fl ); ?></option>
														<?php endforeach; ?>
													</select>
												</label>
												<label class="thimbleform-admin__field-control">
													<span class="thimbleform-admin__label"><?php esc_html_e( 'Operator', 'thimbleform' ); ?></span>
													<select class="thimbleform-admin__input" name="thimbleform[settings][automation_rules][<?php echo (int) $ri; ?>][op]" data-thimbleform-auto-op>
														<?php foreach ( $ops as $op_key => $op_label ) : ?>
															<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $r_op, $op_key ); ?>><?php echo esc_html( $op_label ); ?></option>
														<?php endforeach; ?>
													</select>
												</label>
												<label class="thimbleform-admin__field-control thimbleform-auto__value" data-thimbleform-auto-value-wrap <?php echo $hide_val ? 'hidden' : ''; ?>>
													<span class="thimbleform-admin__label"><?php esc_html_e( 'Value', 'thimbleform' ); ?></span>
													<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][automation_rules][<?php echo (int) $ri; ?>][value]" value="<?php echo esc_attr( $r_val ); ?>" data-thimbleform-auto-value />
												</label>
												<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-auto__remove" data-thimbleform-auto-remove aria-label="<?php esc_attr_e( 'Remove condition', 'thimbleform' ); ?>">
													<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
												</button>
											</div>
										<?php endforeach; ?>
									</div>
									<p class="thimbleform-auto__actions">
										<button type="button" class="button" data-thimbleform-auto-add <?php echo count( $auto_rules ) >= 3 ? 'hidden' : ''; ?>>
											<?php esc_html_e( 'Add condition', 'thimbleform' ); ?>
										</button>
										<span class="thimbleform-auto__limit"><?php esc_html_e( 'Up to 3 conditions', 'thimbleform' ); ?></span>
									</p>
									<template data-thimbleform-auto-rule-tpl>
										<div class="thimbleform-auto__rule" data-thimbleform-auto-rule>
											<label class="thimbleform-admin__field-control">
												<span class="thimbleform-admin__label"><?php esc_html_e( 'Field', 'thimbleform' ); ?></span>
												<select class="thimbleform-admin__input" name="thimbleform[settings][automation_rules][__i__][field]" data-thimbleform-auto-field>
													<option value=""><?php esc_html_e( '— Select —', 'thimbleform' ); ?></option>
													<?php foreach ( $field_options as $fn => $fl ) : ?>
														<option value="<?php echo esc_attr( $fn ); ?>"><?php echo esc_html( $fl ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="thimbleform-admin__field-control">
												<span class="thimbleform-admin__label"><?php esc_html_e( 'Operator', 'thimbleform' ); ?></span>
												<select class="thimbleform-admin__input" name="thimbleform[settings][automation_rules][__i__][op]" data-thimbleform-auto-op>
													<?php foreach ( $ops as $op_key => $op_label ) : ?>
														<option value="<?php echo esc_attr( $op_key ); ?>"><?php echo esc_html( $op_label ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="thimbleform-admin__field-control thimbleform-auto__value" data-thimbleform-auto-value-wrap>
												<span class="thimbleform-admin__label"><?php esc_html_e( 'Value', 'thimbleform' ); ?></span>
												<input type="text" class="thimbleform-admin__input" name="thimbleform[settings][automation_rules][__i__][value]" value="" data-thimbleform-auto-value />
											</label>
											<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-auto__remove" data-thimbleform-auto-remove aria-label="<?php esc_attr_e( 'Remove condition', 'thimbleform' ); ?>">
												<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
											</button>
										</div>
									</template>
								</section>

								<section class="thimbleform-auto__step thimbleform-auto__step--then">
									<span class="thimbleform-auto__badge"><?php esc_html_e( 'Then', 'thimbleform' ); ?></span>
									<p class="thimbleform-auto__lead"><?php esc_html_e( 'Run one or more actions when conditions match.', 'thimbleform' ); ?></p>
									<p class="thimbleform-admin__note thimbleform-auto__warn" data-thimbleform-auto-warn <?php echo ( $settings_auto_open && ! $auto_has_then ) ? '' : 'hidden'; ?>>
										<?php esc_html_e( 'Automation is enabled but no THEN action is set — nothing will run.', 'thimbleform' ); ?>
									</p>
									<label class="thimbleform-admin__field-control">
										<span class="thimbleform-admin__label"><?php esc_html_e( 'Set entry status', 'thimbleform' ); ?></span>
										<select class="thimbleform-admin__input" name="thimbleform[settings][automation_then_status]" data-thimbleform-auto-then>
											<option value="" <?php selected( $auto_status, '' ); ?>><?php esc_html_e( 'No change', 'thimbleform' ); ?></option>
											<option value="read" <?php selected( $auto_status, 'read' ); ?>><?php esc_html_e( 'Mark as read', 'thimbleform' ); ?></option>
											<option value="new" <?php selected( $auto_status, 'new' ); ?>><?php esc_html_e( 'Mark as new', 'thimbleform' ); ?></option>
											<option value="spam" <?php selected( $auto_status, 'spam' ); ?>><?php esc_html_e( 'Mark as spam', 'thimbleform' ); ?></option>
										</select>
									</label>
									<label class="thimbleform-admin__field-control">
										<span class="thimbleform-admin__label">
											<?php esc_html_e( 'Notify email', 'thimbleform' ); ?>
											<?php self::render_field_tip( __( 'Optional. Short alert when the IF conditions match.', 'thimbleform' ) ); ?>
										</span>
										<input type="email" class="thimbleform-admin__input" name="thimbleform[settings][automation_then_email]" value="<?php echo esc_attr( $auto_email ); ?>" placeholder="ops@example.com" data-thimbleform-auto-then />
									</label>
									<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full">
										<span class="thimbleform-admin__label">
											<?php esc_html_e( 'Conditional webhook URL', 'thimbleform' ); ?>
											<?php self::render_field_tip( __( 'Optional. Separate from the always-on Webhook above — fires only when IF matches.', 'thimbleform' ) ); ?>
										</span>
										<input type="url" class="thimbleform-admin__input" name="thimbleform[settings][automation_then_webhook]" value="<?php echo esc_attr( $auto_webhook ); ?>" placeholder="https://" data-thimbleform-auto-then />
									</label>
								</section>
							</div>
					</section>
				</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="thimbleform-admin__panel thimbleform-appearance<?php echo 'appearance' === $active_tab ? ' is-active' : ''; ?>" data-thimbleform-panel="appearance" id="thimbleform-panel-appearance" role="tabpanel" aria-labelledby="thimbleform-tab-appearance"<?php echo 'appearance' === $active_tab ? '' : ' hidden'; ?>>
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Appearance', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Pick a skin and colors per form. Live preview updates as you edit — no save needed.', 'thimbleform' ); ?></p>
					</div>
				</div>
				<div class="thimbleform-appearance__layout">
					<div class="thimbleform-appearance__controls">
				<div class="thimbleform-admin__surface">
					<span class="thimbleform-admin__label thimbleform-appearance__section"><?php esc_html_e( 'Skin', 'thimbleform' ); ?></span>
					<div class="thimbleform-style-skins" role="radiogroup" aria-label="<?php esc_attr_e( 'Form skin', 'thimbleform' ); ?>">
						<?php
						$skin_hints = array(
							'theme'   => __( 'Use theme field & button styles', 'thimbleform' ),
							'classic' => __( 'Outlined inputs, clear borders', 'thimbleform' ),
							'minimal' => __( 'Underline fields, light chrome', 'thimbleform' ),
							'soft'    => __( 'Filled soft backgrounds', 'thimbleform' ),
							'card'    => __( 'Form in a bordered card', 'thimbleform' ),
						);
						$current_skin = (string) ( $settings['style_skin'] ?? 'theme' );
						foreach ( Thimbleform_Form_Config::style_skins() as $skin_key => $skin_label ) :
							?>
							<label class="thimbleform-style-skins__item<?php echo $current_skin === $skin_key ? ' is-active' : ''; ?>">
								<input
									type="radio"
									name="thimbleform[settings][style_skin]"
									value="<?php echo esc_attr( $skin_key ); ?>"
									<?php checked( $current_skin, $skin_key ); ?>
								/>
								<span class="thimbleform-style-skins__preview thimbleform-style-skins__preview--<?php echo esc_attr( $skin_key ); ?>" aria-hidden="true">
									<span></span><span></span>
								</span>
								<span class="thimbleform-style-skins__copy">
									<span class="thimbleform-style-skins__title"><?php echo esc_html( $skin_label ); ?></span>
									<span class="thimbleform-style-skins__hint"><?php echo esc_html( $skin_hints[ $skin_key ] ?? '' ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="thimbleform-admin__surface">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Colors', 'thimbleform' ); ?></h3>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Empty = skin default. Accent also tints progress and selects.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<?php
					$color_groups = array(
						'brand'    => array(
							'label'  => __( 'Brand', 'thimbleform' ),
							'fields' => array(
								'style_accent'      => array( __( 'Accent', 'thimbleform' ), '#2563eb' ),
								'style_accent_text' => array( __( 'On accent', 'thimbleform' ), '#ffffff' ),
							),
						),
						'text'     => array(
							'label'  => __( 'Text', 'thimbleform' ),
							'fields' => array(
								'style_text'  => array( __( 'Text', 'thimbleform' ), '#01123e' ),
								'style_muted' => array( __( 'Muted', 'thimbleform' ), '#6b6b80' ),
							),
						),
						'surfaces' => array(
							'label'  => __( 'Surfaces', 'thimbleform' ),
							'fields' => array(
								'style_surface'  => array( __( 'Surface', 'thimbleform' ), '#ffffff' ),
								'style_input_bg' => array( __( 'Input fill', 'thimbleform' ), '#ffffff' ),
								'style_border'   => array( __( 'Border', 'thimbleform' ), '#e8e8ec' ),
							),
						),
					);
					?>
					<div class="thimbleform-style-colors" data-thimbleform-style-colors>
						<div class="thimbleform-style-colors__preview" aria-hidden="true">
							<span class="thimbleform-style-colors__chip thimbleform-style-colors__chip--accent" data-thimbleform-color-preview="style_accent"></span>
							<span class="thimbleform-style-colors__chip thimbleform-style-colors__chip--surface" data-thimbleform-color-preview="style_surface"></span>
							<span class="thimbleform-style-colors__chip thimbleform-style-colors__chip--border" data-thimbleform-color-preview="style_border"></span>
							<span class="thimbleform-style-colors__chip thimbleform-style-colors__chip--text" data-thimbleform-color-preview="style_text"></span>
						</div>
						<?php foreach ( $color_groups as $group ) : ?>
							<div class="thimbleform-style-colors__group">
								<span class="thimbleform-style-colors__group-label"><?php echo esc_html( $group['label'] ); ?></span>
								<div class="thimbleform-style-colors__grid">
									<?php foreach ( $group['fields'] as $color_key => $meta ) : ?>
										<?php
										$color_label = $meta[0];
										$color_fallback = $meta[1];
										$color_val = (string) ( $settings[ $color_key ] ?? '' );
										$swatch_val = $color_val !== '' ? $color_val : $color_fallback;
										?>
										<label class="thimbleform-style-color<?php echo $color_val !== '' ? ' is-set' : ''; ?>" data-thimbleform-style-color-wrap>
											<span class="thimbleform-style-color__swatch-wrap">
												<input
													type="color"
													class="thimbleform-style-color__swatch"
													value="<?php echo esc_attr( $swatch_val ); ?>"
													data-thimbleform-style-color
													data-fallback="<?php echo esc_attr( $color_fallback ); ?>"
													aria-label="<?php echo esc_attr( $color_label ); ?>"
												/>
											</span>
											<span class="thimbleform-style-color__meta">
												<span class="thimbleform-style-color__name"><?php echo esc_html( $color_label ); ?></span>
												<span class="thimbleform-style-color__row">
													<input
														type="text"
														class="thimbleform-admin__input thimbleform-style-color__hex"
														name="thimbleform[settings][<?php echo esc_attr( $color_key ); ?>]"
														value="<?php echo esc_attr( $color_val ); ?>"
														placeholder="<?php echo esc_attr( $color_fallback ); ?>"
														pattern="^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$"
														data-thimbleform-style-hex
														data-color-key="<?php echo esc_attr( $color_key ); ?>"
													/>
													<button type="button" class="button-link thimbleform-style-color__clear" data-thimbleform-style-clear <?php echo $color_val === '' ? ' hidden' : ''; ?>>
														<?php esc_html_e( 'Reset', 'thimbleform' ); ?>
													</button>
												</span>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="thimbleform-admin__surface">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Typography & spacing', 'thimbleform' ); ?></h3>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Font size and field spacing apply to every skin, including Theme.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
						<label class="thimbleform-admin__field-control">
							<span class="thimbleform-admin__label"><?php esc_html_e( 'Font size', 'thimbleform' ); ?></span>
							<select class="thimbleform-admin__input" name="thimbleform[settings][style_font_size]">
								<?php foreach ( Thimbleform_Form_Config::style_font_size_options() as $f_key => $f_label ) : ?>
									<option value="<?php echo esc_attr( $f_key ); ?>" <?php selected( (string) ( $settings['style_font_size'] ?? 'md' ), $f_key ); ?>><?php echo esc_html( $f_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="thimbleform-admin__field-control">
							<span class="thimbleform-admin__label"><?php esc_html_e( 'Field spacing', 'thimbleform' ); ?></span>
							<select class="thimbleform-admin__input" name="thimbleform[settings][style_gap]">
								<?php foreach ( Thimbleform_Form_Config::style_gap_options() as $g_key => $g_label ) : ?>
									<option value="<?php echo esc_attr( $g_key ); ?>" <?php selected( (string) ( $settings['style_gap'] ?? 'md' ), $g_key ); ?>><?php echo esc_html( $g_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</div>
				<div class="thimbleform-admin__surface" data-thimbleform-style-chrome>
					<div class="thimbleform-admin__panel-head">
						<div>
							<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Shape & controls', 'thimbleform' ); ?></h3>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Applies to Classic / Minimal / Soft / Card. Theme skin keeps site chrome.', 'thimbleform' ); ?></p>
							<p class="thimbleform-style-chrome-note" data-thimbleform-style-chrome-note hidden><?php esc_html_e( 'Theme skin is active — these options are stored but not applied on the front.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
						<label class="thimbleform-admin__field-control">
							<span class="thimbleform-admin__label"><?php esc_html_e( 'Input size', 'thimbleform' ); ?></span>
							<select class="thimbleform-admin__input" name="thimbleform[settings][style_density]" data-thimbleform-style-chrome-field>
								<?php foreach ( Thimbleform_Form_Config::style_density_options() as $d_key => $d_label ) : ?>
									<option value="<?php echo esc_attr( $d_key ); ?>" <?php selected( (string) ( $settings['style_density'] ?? 'md' ), $d_key ); ?>><?php echo esc_html( $d_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="thimbleform-admin__field-control">
							<span class="thimbleform-admin__label"><?php esc_html_e( 'Corner radius', 'thimbleform' ); ?></span>
							<select class="thimbleform-admin__input" name="thimbleform[settings][style_radius]" data-thimbleform-style-chrome-field>
								<?php foreach ( Thimbleform_Form_Config::style_radius_options() as $r_key => $r_label ) : ?>
									<option value="<?php echo esc_attr( $r_key ); ?>" <?php selected( (string) ( $settings['style_radius'] ?? 'md' ), $r_key ); ?>><?php echo esc_html( $r_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="thimbleform-admin__field-control">
							<span class="thimbleform-admin__label"><?php esc_html_e( 'Primary button', 'thimbleform' ); ?></span>
							<select class="thimbleform-admin__input" name="thimbleform[settings][style_button]" data-thimbleform-style-chrome-field>
								<?php foreach ( Thimbleform_Form_Config::style_button_options() as $b_key => $b_label ) : ?>
									<option value="<?php echo esc_attr( $b_key ); ?>" <?php selected( (string) ( $settings['style_button'] ?? 'solid' ), $b_key ); ?>><?php echo esc_html( $b_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</div>
					</div>
					<?php
					$live_style   = Thimbleform_Form_Config::style_inline_css( $settings );
					$submit_label = (string) ( $settings['submit_label'] ?? '' );
					if ( $submit_label === '' ) {
						$submit_label = __( 'Send', 'thimbleform' );
					}
					$live_preview_mods = array( 'thimbleform-live-preview', 'thimbleform-live-preview--skin-' . $current_skin );
					if ( 'theme' !== $current_skin ) {
						$live_btn = isset( $settings['style_button'] ) ? sanitize_key( (string) $settings['style_button'] ) : 'solid';
						if ( ! isset( Thimbleform_Form_Config::style_button_options()[ $live_btn ] ) ) {
							$live_btn = 'solid';
						}
						$live_preview_mods[] = 'thimbleform-live-preview--btn-' . $live_btn;
					}
					?>
					<aside class="thimbleform-appearance__live" data-thimbleform-live-preview>
						<div class="thimbleform-appearance__live-head">
							<strong><?php esc_html_e( 'Live preview', 'thimbleform' ); ?></strong>
							<span><?php esc_html_e( 'Updates instantly', 'thimbleform' ); ?></span>
						</div>
						<div class="thimbleform-appearance__live-stage">
							<div
								class="<?php echo esc_attr( implode( ' ', $live_preview_mods ) ); ?>"
								data-thimbleform-live-form
								style="<?php echo esc_attr( $live_style ); ?>"
							>
								<div class="thimbleform-live-preview__progress" aria-hidden="true">
									<span class="thimbleform-live-preview__bar">
										<span class="thimbleform-live-preview__bar-fill"></span>
									</span>
								</div>
								<div class="thimbleform-live-preview__fields">
									<div class="thimbleform-live-preview__field thimbleform-live-preview__field--half">
										<span class="thimbleform-live-preview__label"><?php esc_html_e( 'Name', 'thimbleform' ); ?></span>
										<span class="thimbleform-live-preview__control"><?php esc_html_e( 'Jane Doe', 'thimbleform' ); ?></span>
									</div>
									<div class="thimbleform-live-preview__field thimbleform-live-preview__field--half">
										<span class="thimbleform-live-preview__label"><?php esc_html_e( 'Email', 'thimbleform' ); ?></span>
										<span class="thimbleform-live-preview__control">jane@example.com</span>
									</div>
									<div class="thimbleform-live-preview__field">
										<span class="thimbleform-live-preview__label"><?php esc_html_e( 'Message', 'thimbleform' ); ?></span>
										<span class="thimbleform-live-preview__control thimbleform-live-preview__control--area"><?php esc_html_e( 'How can we help?', 'thimbleform' ); ?></span>
										<span class="thimbleform-live-preview__hint"><?php esc_html_e( 'Helper text sample', 'thimbleform' ); ?></span>
									</div>
								</div>
								<div class="thimbleform-live-preview__actions">
									<span class="thimbleform-live-preview__btn" data-thimbleform-live-submit><?php echo esc_html( $submit_label ); ?></span>
								</div>
							</div>
							<p class="thimbleform-appearance__live-note" data-thimbleform-live-theme-note <?php echo 'theme' === $current_skin ? '' : 'hidden'; ?>>
								<?php esc_html_e( 'Theme skin keeps your site chrome on the front. Preview still shows Thimbleform colors, size, and spacing.', 'thimbleform' ); ?>
							</p>
						</div>
					</aside>
				</div>
			</div>
			<?php self::render_templates_panel( $form_id ); ?>
		</div>
		<?php
	}

	/**
	 * Help icon with native title tooltip next to a field label.
	 *
	 * @param string               $text Tip text.
	 * @param array<string, string> $atts Extra HTML attributes.
	 */
	private static function render_field_tip( $text, array $atts = array() ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return;
		}
		$attr = '';
		foreach ( $atts as $key => $value ) {
			if ( $value === '' || $value === null ) {
				continue;
			}
			$attr .= ' ' . esc_attr( (string) $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
		printf(
			'<span class="thimbleform-admin__tip"%1$s title="%2$s" aria-label="%2$s"><span class="thimbleform-admin__tip-dot" aria-hidden="true">?</span></span>',
			$attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr above.
			esc_attr( $text )
		);
	}

	/**
	 * @param int|string            $index     Index.
	 * @param array<string, mixed>  $field     Field.
	 * @param array<string, string> $types     Types.
	 * @param bool                  $collapsed Start collapsed.
	 */
	private static function render_field_row( $index, array $field, array $types, $collapsed = true ) {
		$prefix = 'thimbleform[fields][' . $index . ']';
		$type   = (string) ( $field['type'] ?? 'text' );
		$label  = (string) ( $field['label'] ?? '' );
		$name   = (string) ( $field['name'] ?? '' );
		$is_layout = Thimbleform_Form_Config::is_layout_field( $type );
		$title  = $label !== '' ? wp_strip_all_tags( $label ) : ( $name !== '' ? $name : __( 'Untitled field', 'thimbleform' ) );
		$req    = ! empty( $field['required'] );
		$enabled = ! array_key_exists( 'enabled', $field ) || ! empty( $field['enabled'] );
		$step   = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;
		$image_id = (int) ( $field['default'] ?? 0 );
		$heading_level = (string) ( $field['options'] ?? 'h2' );
		if ( ! in_array( $heading_level, array( 'h2', 'h3', 'h4' ), true ) ) {
			$heading_level = 'h2';
		}
		$phone_picker = class_exists( 'Thimbleform_Phone' ) && Thimbleform_Phone::is_picker_enabled( $field );
		$phone_iso    = class_exists( 'Thimbleform_Phone' )
			? Thimbleform_Phone::sanitize_iso( (string) ( $field['options'] ?? '' ) )
			: 'US';
		$cond_field = (string) ( $field['condition_field'] ?? '' );
		$cond_op    = (string) ( $field['condition_op'] ?? 'equals' );
		$cond_value = (string) ( $field['condition_value'] ?? '' );
		$css_class  = (string) ( $field['css_class'] ?? '' );
		$field_width        = (string) ( $field['width'] ?? 'full' );
		$field_width_custom = (int) ( $field['width_custom'] ?? 50 );
		if ( $field_width_custom < 1 || $field_width_custom > 100 ) {
			$field_width_custom = 50;
		}
		if ( ! array_key_exists( $field_width, Thimbleform_Form_Config::field_width_presets() ) ) {
			$field_width = 'full';
		}
		$desc_val    = (string) ( $field['description'] ?? '' );
		$ph_val      = (string) ( $field['placeholder'] ?? '' );
		$def_val     = (string) ( $field['default'] ?? '' );
		$more_open   = ( 'full' !== $field_width )
			|| ( $desc_val !== '' )
			|| ( 'file' !== $type && $ph_val !== '' )
			|| ( 'file' !== $type && 'image' !== $type && $def_val !== '' && '0' !== $def_val );
		$cond_open   = $cond_field !== '';
		$adv_open    = $css_class !== '' || $step > 1;
		// Keep at most one secondary panel open by default to avoid an overloaded card.
		if ( $cond_open ) {
			$more_open = false;
			$adv_open  = false;
		} elseif ( $adv_open ) {
			$more_open = false;
		}
		$card_class = 'thimbleform-card' . ( $collapsed ? ' is-collapsed' : '' ) . ( $enabled ? '' : ' is-disabled' );
		$layout_types = Thimbleform_Form_Config::layout_field_type_labels();
		$input_types  = Thimbleform_Form_Config::input_field_type_labels();
		?>
		<article class="<?php echo esc_attr( $card_class ); ?>" data-thimbleform-field data-field-type="<?php echo esc_attr( $type ); ?>" data-field-step="<?php echo esc_attr( (string) $step ); ?>" draggable="false">
			<header class="thimbleform-card__header" data-thimbleform-card-head>
				<span class="thimbleform-card__handle" data-thimbleform-drag-handle title="<?php esc_attr_e( 'Drag to reorder', 'thimbleform' ); ?>" aria-hidden="true">
					<svg width="12" height="16" viewBox="0 0 8 16" fill="currentColor"><circle cx="2" cy="3" r="1.5"/><circle cx="6" cy="3" r="1.5"/><circle cx="2" cy="8" r="1.5"/><circle cx="6" cy="8" r="1.5"/><circle cx="2" cy="13" r="1.5"/><circle cx="6" cy="13" r="1.5"/></svg>
				</span>
				<button type="button" class="thimbleform-card__toggle" data-thimbleform-toggle aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>" title="<?php esc_attr_e( 'Expand / collapse', 'thimbleform' ); ?>">
					<?php thimbleform_admin_icon( 'chevron' ); ?>
				</button>
				<span class="thimbleform-card__badge" data-thimbleform-type-badge><?php echo esc_html( $types[ $type ] ?? $type ); ?></span>
				<span class="thimbleform-card__step" data-thimbleform-step-badge><?php echo esc_html( sprintf( /* translators: %d step */ __( 'Step %d', 'thimbleform' ), $step ) ); ?></span>
				<div class="thimbleform-card__identity">
					<button type="button" class="thimbleform-card__title-btn" data-thimbleform-toggle>
						<span class="thimbleform-card__title" data-thimbleform-card-title><?php echo esc_html( $title ); ?></span>
						<span class="thimbleform-card__meta" data-thimbleform-card-meta><?php echo esc_html( $name !== '' ? '{' . $name . '}' : '' ); ?></span>
						<span class="thimbleform-card__summary" data-thimbleform-card-summary></span>
					</button>
				</div>
				<label
					class="thimbleform-card__required<?php echo $req ? ' is-on' : ''; ?>"
					data-thimbleform-required-wrap
					title="<?php esc_attr_e( 'Toggle required', 'thimbleform' ); ?>"
					<?php echo $is_layout ? ' hidden' : ''; ?>
				>
					<input type="hidden" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="0" />
					<input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( $req ); ?> data-thimbleform-required />
					<span class="thimbleform-card__required-text"><?php esc_html_e( 'Required', 'thimbleform' ); ?></span>
					<span class="thimbleform-switch" aria-hidden="true"></span>
				</label>
				<span class="thimbleform-card__divider" aria-hidden="true"></span>
				<div class="thimbleform-card__actions">
					<?php
					$visibility_tip = $enabled ? __( 'Hide from form', 'thimbleform' ) : __( 'Show on form', 'thimbleform' );
					?>
					<label
						class="thimbleform-card__btn thimbleform-card__visibility<?php echo $enabled ? '' : ' is-off'; ?>"
						data-thimbleform-enabled-wrap
						data-thimbleform-tooltip="<?php echo esc_attr( $visibility_tip ); ?>"
						aria-label="<?php echo esc_attr( $visibility_tip ); ?>"
					>
						<input type="hidden" name="<?php echo esc_attr( $prefix . '[enabled]' ); ?>" value="0" />
						<input type="checkbox" class="screen-reader-text" name="<?php echo esc_attr( $prefix . '[enabled]' ); ?>" value="1" <?php checked( $enabled ); ?> data-thimbleform-enabled />
						<span class="thimbleform-card__visibility-icon thimbleform-card__visibility-icon--on" aria-hidden="true"><?php thimbleform_admin_icon( 'preview' ); ?></span>
						<span class="thimbleform-card__visibility-icon thimbleform-card__visibility-icon--off" aria-hidden="true"><?php thimbleform_admin_icon( 'eye-off' ); ?></span>
						<span class="screen-reader-text"><?php echo esc_html( $visibility_tip ); ?></span>
					</label>
					<button
						type="button"
						class="thimbleform-card__btn"
						data-thimbleform-duplicate
						data-thimbleform-tooltip="<?php esc_attr_e( 'Duplicate', 'thimbleform' ); ?>"
						aria-label="<?php esc_attr_e( 'Duplicate', 'thimbleform' ); ?>"
					>
						<?php thimbleform_admin_icon( 'copy' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Duplicate', 'thimbleform' ); ?></span>
					</button>
					<button
						type="button"
						class="thimbleform-card__btn thimbleform-card__btn--danger"
						data-thimbleform-remove-field
						data-thimbleform-tooltip="<?php esc_attr_e( 'Delete field', 'thimbleform' ); ?>"
						aria-label="<?php esc_attr_e( 'Delete field', 'thimbleform' ); ?>"
					>
						<?php thimbleform_admin_icon( 'trash' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Delete field', 'thimbleform' ); ?></span>
					</button>
				</div>
			</header>
			<div class="thimbleform-card__body" data-thimbleform-card-body <?php echo $collapsed ? 'hidden' : ''; ?>>
				<?php
				$type_section_title = self::type_section_title( $type );
				?>
				<div class="thimbleform-card__sections">
					<section class="thimbleform-card__section thimbleform-card__section--primary" data-thimbleform-section="field">
						<h4 class="thimbleform-card__section-title"><?php esc_html_e( 'Field', 'thimbleform' ); ?></h4>
						<div class="thimbleform-card__section-grid">
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Type', 'thimbleform' ); ?></span>
								<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" data-thimbleform-type>
									<optgroup label="<?php esc_attr_e( 'Layout', 'thimbleform' ); ?>">
										<?php foreach ( $layout_types as $value => $type_label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
										<?php endforeach; ?>
									</optgroup>
									<optgroup label="<?php esc_attr_e( 'Fields', 'thimbleform' ); ?>">
										<?php foreach ( $input_types as $value => $type_label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								</select>
							</label>
							<label class="thimbleform-admin__field-control">
								<span class="thimbleform-admin__label">
									<?php echo 'html' === $type ? esc_html__( 'Block title (admin)', 'thimbleform' ) : esc_html__( 'Label', 'thimbleform' ); ?>
									<?php
									self::render_field_tip(
										__( 'Links allowed, e.g. I agree to the <a href="/privacy-policy" target="_blank">Privacy Policy</a>', 'thimbleform' ),
										array( 'data-thimbleform-show' => 'acceptance-html' )
									);
									?>
								</span>
								<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[label]' ); ?>" value="<?php echo esc_attr( $label ); ?>" data-thimbleform-label placeholder="<?php echo esc_attr( 'heading' === $type ? __( 'Section title', 'thimbleform' ) : __( 'Visible label', 'thimbleform' ) ); ?>" />
							</label>
						</div>

						<div class="thimbleform-card__primary-type" data-thimbleform-section="type" <?php echo '' === $type_section_title ? 'hidden' : ''; ?>>
							<h5 class="thimbleform-card__primary-type-title" data-thimbleform-type-section-title><?php echo esc_html( $type_section_title ); ?></h5>
							<div class="thimbleform-card__section-grid">
								<p class="thimbleform-admin__hint thimbleform-card__type-intro" data-thimbleform-show="phone-country">
									<?php esc_html_e( 'Optional country picker with dial code. Leave off for a plain phone input.', 'thimbleform' ); ?>
								</p>
							<p class="thimbleform-admin__hint thimbleform-card__type-intro" data-thimbleform-show="file-limits">
								<?php esc_html_e( 'Limit which files visitors can upload and how large each file (or set) may be.', 'thimbleform' ); ?>
							</p>
							<p class="thimbleform-admin__hint thimbleform-card__type-intro" data-thimbleform-show="options" data-thimbleform-options-intro>
								<?php esc_html_e( 'What visitors can pick — keep each line simple and readable.', 'thimbleform' ); ?>
							</p>
							<label class="thimbleform-admin__field-control" data-thimbleform-show="heading-level">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Heading level', 'thimbleform' ); ?></span>
								<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>"<?php echo self::disabled_for_show( $type, 'heading-level' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<option value="h2" <?php selected( $heading_level, 'h2' ); ?>>H2</option>
									<option value="h3" <?php selected( $heading_level, 'h3' ); ?>>H3</option>
									<option value="h4" <?php selected( $heading_level, 'h4' ); ?>>H4</option>
								</select>
							</label>
							<div class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="image-picker">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Image', 'thimbleform' ); ?></span>
								<input type="hidden" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-thimbleform-image-id<?php echo self::disabled_for_show( $type, 'image-picker' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								<div class="thimbleform-image-picker" data-thimbleform-image-picker>
									<div class="thimbleform-image-picker__preview<?php echo $image_id > 0 ? ' has-image' : ''; ?>" data-thimbleform-image-preview>
										<?php if ( $image_id > 0 ) : ?>
											<?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'thimbleform-image-picker__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php else : ?>
											<span class="thimbleform-image-picker__empty"><?php esc_html_e( 'No image selected', 'thimbleform' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="thimbleform-image-picker__actions">
										<button type="button" class="thimbleform-btn" data-thimbleform-image-pick><?php esc_html_e( 'Select image', 'thimbleform' ); ?></button>
										<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-image-picker__clear" data-thimbleform-image-clear <?php echo $image_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove image', 'thimbleform' ); ?></button>
									</div>
								</div>
							</div>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="html-content">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'HTML content', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Static content — not saved with entries. Basic HTML allowed.', 'thimbleform' ) ); ?>
								</span>
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="5" placeholder="<?php esc_attr_e( '<p>Intro text or <img src=\"…\" alt=\"\">', 'thimbleform' ); ?>"<?php echo self::disabled_for_show( $type, 'html-content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( (string) ( $field['options'] ?? '' ) ); ?></textarea>
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="paragraph-text">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Paragraph', 'thimbleform' ); ?></span>
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="4" placeholder="<?php esc_attr_e( 'Intro or helper text shown on the form.', 'thimbleform' ); ?>"<?php echo self::disabled_for_show( $type, 'paragraph-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( 'paragraph' === $type ? (string) ( $field['options'] ?? '' ) : '' ); ?></textarea>
							</label>
							<label class="thimbleform-admin__field-control" data-thimbleform-show="spacer-size">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Spacer size', 'thimbleform' ); ?></span>
								<?php $spacer_size = in_array( (string) ( $field['options'] ?? '' ), array( 's', 'm', 'l' ), true ) ? (string) $field['options'] : 'm'; ?>
								<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>"<?php echo self::disabled_for_show( $type, 'spacer-size' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<option value="s" <?php selected( $spacer_size, 's' ); ?>><?php esc_html_e( 'Small', 'thimbleform' ); ?></option>
									<option value="m" <?php selected( $spacer_size, 'm' ); ?>><?php esc_html_e( 'Medium', 'thimbleform' ); ?></option>
									<option value="l" <?php selected( $spacer_size, 'l' ); ?>><?php esc_html_e( 'Large', 'thimbleform' ); ?></option>
								</select>
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="phone-country">
								<span class="thimbleform-admin__label">
									<input type="checkbox" value="1" data-thimbleform-phone-picker <?php checked( $phone_picker ); ?> <?php echo self::disabled_for_show( $type, 'phone-country' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
									<?php esc_html_e( 'Country picker', 'thimbleform' ); ?>
								</span>
							</label>
							<label class="thimbleform-admin__field-control" data-thimbleform-show="phone-country" data-thimbleform-phone-iso-wrap<?php echo $phone_picker ? '' : ' hidden'; ?>>
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Default country', 'thimbleform' ); ?></span>
								<input type="hidden" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" value="" data-thimbleform-phone-off<?php echo ( 'tel' === $type && ! $phone_picker ) ? '' : ' disabled'; ?> />
								<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" data-thimbleform-phone-iso<?php echo ( 'tel' === $type && $phone_picker ) ? '' : ' disabled'; ?>>
									<?php if ( class_exists( 'Thimbleform_Phone' ) ) : ?>
										<?php foreach ( Thimbleform_Phone::countries() as $country ) : ?>
											<option value="<?php echo esc_attr( $country['iso'] ); ?>" <?php selected( $phone_iso, $country['iso'] ); ?>>
												<?php echo esc_html( $country['iso'] . ' +' . $country['dial'] . ' ' . $country['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="file-limits">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Allowed extensions', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Comma-separated, e.g. jpg,png,pdf', 'thimbleform' ) ); ?>
								</span>
								<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" value="<?php echo esc_attr( (string) ( $field['options'] ?? Thimbleform_Form_Config::file_default_extensions() ) ); ?>" placeholder="<?php echo esc_attr( Thimbleform_Form_Config::file_default_extensions() ); ?>"<?php echo self::disabled_for_show( $type, 'file-limits' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</label>
							<label class="thimbleform-admin__field-control" data-thimbleform-show="file-max">
								<span class="thimbleform-admin__label"><?php esc_html_e( 'Max size (MB)', 'thimbleform' ); ?></span>
								<input type="number" min="1" max="50" step="1" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[placeholder]' ); ?>" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? Thimbleform_Form_Config::file_default_max_mb() ) ); ?>" data-thimbleform-file-max<?php echo self::disabled_for_show( $type, 'file-max' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</label>
							<label class="thimbleform-admin__field-control" data-thimbleform-show="file-max">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Max files', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( '1–10. More than 1 enables multiple upload.', 'thimbleform' ) ); ?>
								</span>
								<input type="number" min="1" max="10" step="1" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( (string) max( 1, (int) ( $field['default'] ?? 1 ) ) ); ?>"<?php echo self::disabled_for_show( $type, 'file-max' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</label>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="options">
								<?php
								$options_label = __( 'Choices', 'thimbleform' );
								$options_tip   = __( 'One choice per line. Quizzes: Correct answer|10. Optional advanced: Label|saved_value|points', 'thimbleform' );
								$options_ph    = __( "Yes\nNo\nMaybe", 'thimbleform' );
								$options_hint  = __( 'One choice per line — the text visitors see. For quizzes, add points after | : Correct answer|10', 'thimbleform' );
								if ( 'range' === $type ) {
									$options_label = __( 'Min / max / step', 'thimbleform' );
									$options_tip   = __( 'Line 1 = min, line 2 = max, line 3 = step. Example: 0 / 100 / 1', 'thimbleform' );
									$options_ph    = "0\n100\n1";
									$options_hint  = __( 'Three lines: lowest value, highest value, and step size.', 'thimbleform' );
								} elseif ( 'rating' === $type ) {
									$options_label = __( 'Number of stars', 'thimbleform' );
									$options_tip   = __( 'A single number sets max stars (1–10). Or list one label per star.', 'thimbleform' );
									$options_ph    = '5';
									$options_hint  = __( 'Enter one number for how many stars to show (1–10), e.g. 5.', 'thimbleform' );
								} elseif ( 'scale' === $type ) {
									$options_label = __( 'Scale setup', 'thimbleform' );
									$options_tip   = __( 'Line 1–2 = number range, line 3–4 = labels under the ends of the scale.', 'thimbleform' );
									$options_ph    = __( "1\n5\nVery dissatisfied\nVery satisfied", 'thimbleform' );
									$options_hint  = __( 'Four lines: lowest number, highest number, left label, right label.', 'thimbleform' );
								} elseif ( 'matrix' === $type ) {
									$options_label = __( 'Rows and columns', 'thimbleform' );
									$options_tip   = __( 'Rows above ---, columns below. Each line is one label.', 'thimbleform' );
									$options_ph    = __( "Support\nProduct\n---\nPoor\nFair\nGood", 'thimbleform' );
									$options_hint  = __( 'List row labels, then a line with only ---, then column labels.', 'thimbleform' );
								}
								?>
								<span class="thimbleform-admin__label">
									<span data-thimbleform-options-label><?php echo esc_html( $options_label ); ?></span>
									<?php self::render_field_tip( $options_tip, array( 'data-thimbleform-options-tip' => '1' ) ); ?>
								</span>
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="3" data-thimbleform-options-input placeholder="<?php echo esc_attr( $options_ph ); ?>"<?php echo self::disabled_for_show( $type, 'options' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( in_array( $type, array( 'calculated', 'payment' ), true ) ? '' : (string) ( $field['options'] ?? '' ) ); ?></textarea>
								<p class="thimbleform-admin__hint" data-thimbleform-options-hint><?php echo esc_html( $options_hint ); ?></p>
							</label>
							<?php
							$pay_lines    = preg_split( '/\r\n|\r|\n/', (string) ( $field['options'] ?? "9.99\nUSD" ) );
							$pay_amount   = is_array( $pay_lines ) && isset( $pay_lines[0] ) && preg_match( '/^\d+(\.\d{1,2})?$/', trim( (string) $pay_lines[0] ) ) ? trim( (string) $pay_lines[0] ) : '9.99';
							$pay_currency = is_array( $pay_lines ) && isset( $pay_lines[1] ) ? strtoupper( trim( (string) $pay_lines[1] ) ) : 'USD';
							$pay_codes    = Thimbleform_Form_Config::payment_currency_options();
							if ( ! in_array( $pay_currency, $pay_codes, true ) ) {
								$pay_currency = 'USD';
							}
							?>
							<div class="thimbleform-admin__field-control thimbleform-admin__field-control--full thimbleform-card__payment-setup" data-thimbleform-show="payment-setup">
								<p class="thimbleform-admin__hint thimbleform-card__type-intro">
									<?php esc_html_e( 'Fixed amount charged through Stripe when the form is submitted.', 'thimbleform' ); ?>
								</p>
								<div class="thimbleform-admin__grid thimbleform-admin__grid--2">
									<label class="thimbleform-admin__field-control">
										<span class="thimbleform-admin__label">
											<?php esc_html_e( 'Amount', 'thimbleform' ); ?>
											<?php self::render_field_tip( __( 'Use a decimal amount, e.g. 9.99 or 1500.', 'thimbleform' ) ); ?>
										</span>
										<input
											type="text"
											inputmode="decimal"
											class="thimbleform-admin__input"
											name="<?php echo esc_attr( $prefix . '[payment_amount]' ); ?>"
											value="<?php echo esc_attr( $pay_amount ); ?>"
											placeholder="9.99"
											autocomplete="off"
											data-thimbleform-payment-amount
											<?php echo self::disabled_for_show( $type, 'payment-setup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										/>
									</label>
									<label class="thimbleform-admin__field-control">
										<span class="thimbleform-admin__label">
											<?php esc_html_e( 'Currency', 'thimbleform' ); ?>
											<?php self::render_field_tip( __( 'ISO currency code supported by your Stripe account.', 'thimbleform' ) ); ?>
										</span>
										<select
											class="thimbleform-admin__input"
											name="<?php echo esc_attr( $prefix . '[payment_currency]' ); ?>"
											data-thimbleform-payment-currency
											<?php echo self::disabled_for_show( $type, 'payment-setup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										>
											<?php foreach ( $pay_codes as $code ) : ?>
												<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $pay_currency, $code ); ?>><?php echo esc_html( $code ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
							</div>
							<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="formula">
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Formula', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Use field names in braces. Operators: + - * / ( ). Functions: min(), max(), round(). Example: {price} * {qty}', 'thimbleform' ) ); ?>
								</span>
								<textarea class="thimbleform-admin__input thimbleform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="2" placeholder="{price} * {qty}"<?php echo self::disabled_for_show( $type, 'formula' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( 'calculated' === $type ? (string) ( $field['options'] ?? '' ) : '' ); ?></textarea>
								<p class="thimbleform-admin__hint" data-thimbleform-show="formula">
									<?php esc_html_e( 'Names must match other fields’ Name (slug), e.g. price and qty → {price} * {qty}. The value is recalculated on the server on submit.', 'thimbleform' ); ?>
								</p>
							</label>
							<div class="thimbleform-admin__field-control thimbleform-admin__field-control--full" data-thimbleform-show="subfields" data-thimbleform-subfields>
								<span class="thimbleform-admin__label">
									<?php esc_html_e( 'Subfields', 'thimbleform' ); ?>
									<?php self::render_field_tip( __( 'Each row on the form repeats this set of fields. Visitors can add more rows.', 'thimbleform' ) ); ?>
								</span>
								<p class="thimbleform-admin__hint thimbleform-card__type-intro">
									<?php esc_html_e( 'Define the columns of one row. Choice fields need one option per line. Calculated fields need a formula with other subfield names.', 'thimbleform' ); ?>
								</p>
								<div class="thimbleform-subfields-preview" data-thimbleform-subfields-preview hidden>
									<span class="thimbleform-subfields-preview__label"><?php esc_html_e( 'Row columns', 'thimbleform' ); ?></span>
									<div class="thimbleform-subfields-preview__cols" data-thimbleform-subfields-preview-cols></div>
								</div>
								<?php
								$sub_type_labels = Thimbleform_Form_Config::input_field_type_labels();
								$subs            = isset( $field['subfields'] ) && is_array( $field['subfields'] ) ? $field['subfields'] : array();
								$subs_empty      = array() === $subs;
								?>
								<div class="thimbleform-subfields-empty" data-thimbleform-subfields-empty<?php echo $subs_empty ? '' : ' hidden'; ?>>
									<p class="thimbleform-subfields-empty__text"><?php esc_html_e( 'No columns yet. Add the fields that make up one repeater row.', 'thimbleform' ); ?></p>
									<button type="button" class="thimbleform-btn thimbleform-btn--outline" data-thimbleform-subfield-add>
										<?php esc_html_e( 'Add first subfield', 'thimbleform' ); ?>
									</button>
								</div>
								<div class="thimbleform-subfields" data-thimbleform-subfields-list<?php echo $subs_empty ? ' hidden' : ''; ?>>
									<?php
									foreach ( $subs as $si => $sub ) {
										self::render_subfield_row( $prefix . '[subfields][' . $si . ']', $sub, $sub_type_labels );
									}
									?>
								</div>
								<button type="button" class="thimbleform-btn thimbleform-btn--outline" data-thimbleform-subfield-add data-thimbleform-subfield-add-more<?php echo $subs_empty ? ' hidden' : ''; ?>>
									<?php esc_html_e( 'Add subfield', 'thimbleform' ); ?>
								</button>
								<template data-thimbleform-subfield-template>
									<?php
									self::render_subfield_row(
										$prefix . '[subfields][__SI__]',
										array(
											'type'  => 'text',
											'name'  => '',
											'label' => '',
										),
										$sub_type_labels
									);
									?>
								</template>
							</div>
							<div class="thimbleform-admin__other-row" data-thimbleform-other-row>
								<label class="thimbleform-admin__check thimbleform-admin__field-control thimbleform-admin__other-row__allow" data-thimbleform-show="choice-other">
									<input type="checkbox" name="<?php echo esc_attr( $prefix . '[allow_other]' ); ?>" value="1" <?php checked( ! empty( $field['allow_other'] ) ); ?> data-thimbleform-allow-other<?php echo self::disabled_for_show( $type, 'choice-other' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
									<span class="thimbleform-admin__other-row__allow-text"><?php esc_html_e( 'Allow “Other” with a text field', 'thimbleform' ); ?></span>
								</label>
								<label class="thimbleform-admin__field-control thimbleform-admin__other-row__label" data-thimbleform-show="choice-other" data-thimbleform-other-label<?php echo empty( $field['allow_other'] ) ? ' hidden' : ''; ?>>
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Other label', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'Text shown for the extra choice. Leave empty for “Other”.', 'thimbleform' ) ); ?>
									</span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[other_label]' ); ?>" value="<?php echo esc_attr( (string) ( $field['other_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Other', 'thimbleform' ); ?>"<?php echo self::disabled_for_show( $type, 'choice-other' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
							</div>
							</div>
						</div>
					</section>

					<details class="thimbleform-card__details" data-thimbleform-section="more" <?php echo $more_open ? 'open' : ''; ?>>
						<summary>
							<span class="thimbleform-card__details-chevron" aria-hidden="true"></span>
							<span class="thimbleform-card__details-copy">
								<span class="thimbleform-card__details-title"><?php esc_html_e( 'More', 'thimbleform' ); ?></span>
								<span class="thimbleform-card__details-hint"><?php esc_html_e( 'Placeholder, default value, help text, and width.', 'thimbleform' ); ?></span>
							</span>
						</summary>
						<div class="thimbleform-card__details-body">
							<div class="thimbleform-card__section-grid">
								<label class="thimbleform-admin__field-control" data-thimbleform-show="placeholder">
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Placeholder', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'Text inputs: hint inside the field. Select: label on the custom trigger when nothing is chosen.', 'thimbleform' ) ); ?>
									</span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[placeholder]' ); ?>" value="<?php echo esc_attr( $ph_val ); ?>" data-thimbleform-placeholder placeholder="<?php echo esc_attr( 'select' === $type ? __( 'Select...', 'thimbleform' ) : __( 'Optional hint', 'thimbleform' ) ); ?>"<?php echo self::disabled_for_show( $type, 'placeholder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
								<label class="thimbleform-admin__field-control" data-thimbleform-show="default">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Default value', 'thimbleform' ); ?></span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( $def_val ); ?>"<?php echo self::disabled_for_show( $type, 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
								<label class="thimbleform-admin__field-control" data-thimbleform-show="description">
									<span class="thimbleform-admin__label" data-thimbleform-description-label><?php echo 'image' === $type ? esc_html__( 'Alt text', 'thimbleform' ) : esc_html__( 'Help text', 'thimbleform' ); ?></span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[description]' ); ?>" value="<?php echo esc_attr( $desc_val ); ?>" data-thimbleform-description placeholder="<?php echo esc_attr( 'image' === $type ? __( 'Describe the image', 'thimbleform' ) : __( 'Shown under the field', 'thimbleform' ) ); ?>"<?php echo self::disabled_for_show( $type, 'description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Width', 'thimbleform' ); ?></span>
									<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[width]' ); ?>" data-thimbleform-width>
										<?php foreach ( Thimbleform_Form_Config::field_width_presets() as $width_key => $width_label ) : ?>
											<option value="<?php echo esc_attr( $width_key ); ?>" <?php selected( $field_width, $width_key ); ?>><?php echo esc_html( $width_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="thimbleform-admin__field-control" data-thimbleform-width-custom-wrap <?php echo 'custom' === $field_width ? '' : 'hidden'; ?>>
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Custom width (%)', 'thimbleform' ); ?></span>
									<input
										type="number"
										class="thimbleform-admin__input"
										name="<?php echo esc_attr( $prefix . '[width_custom]' ); ?>"
										value="<?php echo esc_attr( (string) $field_width_custom ); ?>"
										min="1"
										max="100"
										step="1"
										inputmode="numeric"
										data-thimbleform-width-custom
									/>
								</label>
								<input type="hidden" name="<?php echo esc_attr( $prefix . '[step]' ); ?>" value="<?php echo esc_attr( (string) $step ); ?>" data-thimbleform-step />
							</div>
						</div>
					</details>

					<details class="thimbleform-card__details" data-thimbleform-section="condition" <?php echo $is_layout ? 'hidden' : ''; ?> <?php echo ( ! $is_layout && $cond_open ) ? 'open' : ''; ?>>
						<summary>
							<span class="thimbleform-card__details-chevron" aria-hidden="true"></span>
							<span class="thimbleform-card__details-copy">
								<span class="thimbleform-card__details-title"><?php esc_html_e( 'Logic', 'thimbleform' ); ?></span>
								<span class="thimbleform-card__details-hint"><?php esc_html_e( 'Optional — show this field only when another field matches a rule.', 'thimbleform' ); ?></span>
							</span>
						</summary>
						<div class="thimbleform-card__details-body">
							<div class="thimbleform-card__section-grid" data-thimbleform-show="condition" <?php echo $is_layout ? 'hidden' : ''; ?>>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Watch field', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'Leave empty to always show.', 'thimbleform' ) ); ?>
									</span>
									<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[condition_field]' ); ?>" data-thimbleform-condition-field<?php echo $is_layout ? ' disabled' : ''; ?>>
										<option value=""><?php esc_html_e( '— Always show —', 'thimbleform' ); ?></option>
										<?php if ( ! $is_layout && $cond_field !== '' ) : ?>
											<option value="<?php echo esc_attr( $cond_field ); ?>" selected><?php echo esc_html( $cond_field ); ?></option>
										<?php endif; ?>
									</select>
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Operator', 'thimbleform' ); ?></span>
									<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[condition_op]' ); ?>" data-thimbleform-condition-op<?php echo $is_layout ? ' disabled' : ''; ?>>
										<?php foreach ( Thimbleform_Form_Config::condition_operators() as $op_key => $op_label ) : ?>
											<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $cond_op, $op_key ); ?>><?php echo esc_html( $op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="thimbleform-admin__field-control" data-thimbleform-condition-value-wrap>
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Value', 'thimbleform' ); ?></span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[condition_value]' ); ?>" value="<?php echo esc_attr( $is_layout ? '' : $cond_value ); ?>" placeholder="<?php esc_attr_e( 'e.g. Yes', 'thimbleform' ); ?>" data-thimbleform-condition-value<?php echo $is_layout ? ' disabled' : ''; ?> />
								</label>
							</div>
						</div>
					</details>

					<details class="thimbleform-card__details" data-thimbleform-section="advanced" <?php echo $adv_open ? 'open' : ''; ?>>
						<summary>
							<span class="thimbleform-card__details-chevron" aria-hidden="true"></span>
							<span class="thimbleform-card__details-copy">
								<span class="thimbleform-card__details-title"><?php esc_html_e( 'Advanced', 'thimbleform' ); ?></span>
								<span class="thimbleform-card__details-hint"><?php esc_html_e( 'Slug for mail tokens, CSS class, and step placement.', 'thimbleform' ); ?></span>
							</span>
						</summary>
						<div class="thimbleform-card__details-body">
							<div class="thimbleform-card__section-grid">
								<label class="thimbleform-admin__field-control" data-thimbleform-show="name">
									<span class="thimbleform-admin__label">
										<?php esc_html_e( 'Name (slug)', 'thimbleform' ); ?>
										<?php self::render_field_tip( __( 'Used in mail/PDF tokens as {name}. Lowercase letters, numbers, and underscores only.', 'thimbleform' ) ); ?>
									</span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[name]' ); ?>" value="<?php echo esc_attr( $name ); ?>" pattern="[a-z0-9_]+" data-thimbleform-name placeholder="email" />
								</label>
								<label class="thimbleform-admin__field-control">
									<span class="thimbleform-admin__label"><?php esc_html_e( 'CSS class', 'thimbleform' ); ?></span>
									<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[css_class]' ); ?>" value="<?php echo esc_attr( $css_class ); ?>" placeholder="my-field" />
								</label>
								<label class="thimbleform-admin__field-control" data-thimbleform-move-step-wrap>
									<span class="thimbleform-admin__label"><?php esc_html_e( 'Move to step', 'thimbleform' ); ?></span>
									<select class="thimbleform-admin__input" data-thimbleform-move-step>
										<?php for ( $s = 1; $s <= 10; $s++ ) : ?>
											<option value="<?php echo esc_attr( (string) $s ); ?>" <?php selected( $step, $s ); ?>><?php echo esc_html( sprintf( /* translators: %d */ __( 'Step %d', 'thimbleform' ), $s ) ); ?></option>
										<?php endfor; ?>
									</select>
								</label>
							</div>
						</div>
					</details>
				</div>
				<aside class="thimbleform-card__preview" data-thimbleform-field-preview>
					<div class="thimbleform-card__preview-head">
						<span class="thimbleform-card__preview-title"><?php esc_html_e( 'Preview', 'thimbleform' ); ?></span>
						<span class="thimbleform-card__preview-note"><?php esc_html_e( 'Approximate front-end look', 'thimbleform' ); ?></span>
					</div>
					<div class="thimbleform-card__preview-stage thimbleform-live-preview" data-thimbleform-field-preview-stage aria-hidden="true"></div>
				</aside>
			</div>
		</article>
		<?php
	}

	/**
	 * Contextual title for the primary type-settings block.
	 *
	 * @param string $type Field type.
	 * @return string Empty when the type has no extra settings.
	 */
	private static function type_section_title( $type ) {
		$map = array(
			'heading'    => __( 'Heading', 'thimbleform' ),
			'image'      => __( 'Image', 'thimbleform' ),
			'html'       => __( 'HTML', 'thimbleform' ),
			'paragraph'  => __( 'Paragraph', 'thimbleform' ),
			'spacer'     => __( 'Spacer', 'thimbleform' ),
			'tel'        => __( 'Phone', 'thimbleform' ),
			'file'       => __( 'Upload limits', 'thimbleform' ),
			'select'     => __( 'Choices', 'thimbleform' ),
			'radio'      => __( 'Choices', 'thimbleform' ),
			'checkboxes' => __( 'Choices', 'thimbleform' ),
			'range'      => __( 'Range', 'thimbleform' ),
			'rating'     => __( 'Choices', 'thimbleform' ),
			'scale'      => __( 'Choices', 'thimbleform' ),
			'ranking'    => __( 'Choices', 'thimbleform' ),
			'matrix'     => __( 'Matrix', 'thimbleform' ),
			'calculated' => __( 'Formula', 'thimbleform' ),
			'repeater'   => __( 'Row fields', 'thimbleform' ),
			'payment'    => __( 'Payment', 'thimbleform' ),
		);
		$type = (string) $type;
		return isset( $map[ $type ] ) ? (string) $map[ $type ] : '';
	}

	/**
	 * Render template gallery cards.
	 *
	 * @param int $form_id Form post ID.
	 * @param int $limit   Max cards (0 = all).
	 * @return int Number of cards printed.
	 */
	private static function render_template_cards( $form_id, $limit = 0 ) {
		$form_id   = (int) $form_id;
		$limit     = max( 0, (int) $limit );
		$can_apply = $form_id > 0 && Thimbleform_Post_Type::POST_TYPE === get_post_type( $form_id );
		$templates = class_exists( 'Thimbleform_Templates' ) ? Thimbleform_Templates::all() : array();
		$shown     = 0;
		foreach ( $templates as $tpl_key => $tpl ) {
			if ( $limit > 0 && $shown >= $limit ) {
				break;
			}
			if ( ! Thimbleform_Templates::template_allowed( (string) $tpl_key ) ) {
				continue;
			}
			self::render_template_card( $form_id, (string) $tpl_key, is_array( $tpl ) ? $tpl : array(), $can_apply );
			++$shown;
		}
		return $shown;
	}

	/**
	 * @param int                  $form_id   Form post ID.
	 * @param string               $tpl_key   Template key.
	 * @param array<string, mixed> $tpl       Template row.
	 * @param bool                 $can_apply Whether apply URLs are valid.
	 */
	private static function render_template_card( $form_id, $tpl_key, array $tpl, $can_apply ) {
		$allowed  = Thimbleform_Templates::template_allowed( $tpl_key );
		$category = isset( $tpl['category'] ) ? sanitize_key( (string) $tpl['category'] ) : 'other';
		$label    = isset( $tpl['label'] ) ? (string) $tpl['label'] : $tpl_key;
		$desc     = isset( $tpl['description'] ) ? (string) $tpl['description'] : '';
		$category_labels = array(
			'contact' => __( 'Contact', 'thimbleform' ),
			'lead'    => __( 'Lead', 'thimbleform' ),
			'survey'  => __( 'Survey', 'thimbleform' ),
			'quiz'    => __( 'Quiz', 'thimbleform' ),
			'other'   => __( 'Other', 'thimbleform' ),
		);
		$category_label = isset( $category_labels[ $category ] ) ? $category_labels[ $category ] : ucfirst( $category );
		$icon_map       = array(
			'contact' => 'forms',
			'lead'    => 'entries',
			'survey'  => 'analytics',
			'quiz'    => 'sparkle',
			'other'   => 'docs',
		);
		$icon_name = isset( $icon_map[ $category ] ) ? $icon_map[ $category ] : 'forms';
		$search    = strtolower(
			trim(
				implode(
					' ',
					array_filter(
						array(
							(string) $tpl_key,
							$label,
							$desc,
							$category,
							$category_label,
						)
					)
				)
			)
		);

		// Pro-only templates: omit locked cards (honest upsell lives on Forms → Pro).
		if ( ! $allowed ) {
			return;
		}

		$card_class = 'thimbleform-templates__card thimbleform-templates__card--' . $category;
		if ( $can_apply ) {
			?>
			<a
				class="<?php echo esc_attr( $card_class ); ?>"
				data-thimbleform-templates-card
				data-category="<?php echo esc_attr( $category ); ?>"
				data-thimbleform-templates-search="<?php echo esc_attr( $search ); ?>"
				href="<?php echo esc_url( Thimbleform_Templates::url( $form_id, $tpl_key ) ); ?>"
				onclick="return confirm('<?php echo esc_js( __( 'Replace current fields with this template?', 'thimbleform' ) ); ?>');"
			>
				<span class="thimbleform-templates__card-icon" aria-hidden="true"><?php thimbleform_admin_icon( $icon_name ); ?></span>
				<span class="thimbleform-templates__card-label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $desc !== '' ) : ?>
					<span class="thimbleform-templates__card-desc"><?php echo esc_html( $desc ); ?></span>
				<?php endif; ?>
				<span class="thimbleform-templates__card-meta"><?php echo esc_html( $category_label ); ?></span>
			</a>
			<?php
			return;
		}
		?>
		<button
			type="button"
			class="<?php echo esc_attr( $card_class . ' thimbleform-templates__card--disabled' ); ?>"
			data-thimbleform-templates-card
			data-thimbleform-templates-save-first
			data-category="<?php echo esc_attr( $category ); ?>"
			data-thimbleform-templates-search="<?php echo esc_attr( $search ); ?>"
		>
			<span class="thimbleform-templates__card-icon" aria-hidden="true"><?php thimbleform_admin_icon( $icon_name ); ?></span>
			<span class="thimbleform-templates__card-label"><?php echo esc_html( $label ); ?></span>
			<?php if ( $desc !== '' ) : ?>
				<span class="thimbleform-templates__card-desc"><?php echo esc_html( $desc ); ?></span>
			<?php endif; ?>
			<span class="thimbleform-templates__card-meta"><?php esc_html_e( 'Save draft first', 'thimbleform' ); ?></span>
		</button>
		<?php
	}

	/**
	 * Starter templates gallery (modal + empty state).
	 *
	 * @param int  $form_id Form post ID.
	 * @param bool $empty   Show empty-state hero.
	 */
	private static function render_templates_panel( $form_id, $empty = false ) {
		$form_id = (int) $form_id;
		unset( $empty );
		?>
		<div class="thimbleform-templates" data-thimbleform-templates-drawer hidden>
			<div class="thimbleform-templates__backdrop" data-thimbleform-templates-close></div>
			<div class="thimbleform-templates__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Form templates', 'thimbleform' ); ?>">
				<header class="thimbleform-templates__head">
					<div>
						<strong><?php esc_html_e( 'Templates', 'thimbleform' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Replaces fields, mail, and step settings. Validation messages stay as-is.', 'thimbleform' ); ?></p>
					</div>
					<button type="button" class="thimbleform-btn" data-thimbleform-templates-close><?php esc_html_e( 'Close', 'thimbleform' ); ?></button>
				</header>
				<div class="thimbleform-templates__toolbar">
					<label class="thimbleform-templates__search">
						<span class="thimbleform-templates__search-icon" aria-hidden="true"><?php thimbleform_admin_icon( 'search' ); ?></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Search templates', 'thimbleform' ); ?></span>
						<input
							type="search"
							class="thimbleform-admin__input thimbleform-templates__search-input"
							data-thimbleform-templates-search
							placeholder="<?php esc_attr_e( 'Search templates…', 'thimbleform' ); ?>"
							autocomplete="off"
						/>
					</label>
					<div class="thimbleform-templates__filters" role="tablist">
						<button type="button" class="thimbleform-templates__chip is-active" data-thimbleform-templates-filter="all"><?php esc_html_e( 'All', 'thimbleform' ); ?></button>
						<button type="button" class="thimbleform-templates__chip" data-thimbleform-templates-filter="contact"><?php esc_html_e( 'Contact', 'thimbleform' ); ?></button>
						<button type="button" class="thimbleform-templates__chip" data-thimbleform-templates-filter="lead"><?php esc_html_e( 'Lead', 'thimbleform' ); ?></button>
						<button type="button" class="thimbleform-templates__chip" data-thimbleform-templates-filter="survey"><?php esc_html_e( 'Survey', 'thimbleform' ); ?></button>
						<button type="button" class="thimbleform-templates__chip" data-thimbleform-templates-filter="quiz"><?php esc_html_e( 'Quiz', 'thimbleform' ); ?></button>
						<button type="button" class="thimbleform-templates__chip" data-thimbleform-templates-filter="other"><?php esc_html_e( 'Other', 'thimbleform' ); ?></button>
					</div>
				</div>
				<div class="thimbleform-templates__grid">
					<?php
					$shown = self::render_template_cards( $form_id, 0 );
					?>
					<p class="thimbleform-templates__empty" data-thimbleform-templates-no-results <?php echo $shown > 0 ? 'hidden' : ''; ?>>
						<?php esc_html_e( 'No templates match your search.', 'thimbleform' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_shortcode_box( $post ) {
		$id      = (int) $post->ID;
		$slug    = $post->post_name ? $post->post_name : 'your-slug';
		$id_sc   = '[thimbleform id="' . $id . '"]';
		$slug_sc = '[thimbleform slug="' . $slug . '"]';
		$status  = get_post_status( $post );
		$status_labels = array(
			'publish'    => __( 'Published', 'thimbleform' ),
			'draft'      => __( 'Draft', 'thimbleform' ),
			'pending'    => __( 'Pending', 'thimbleform' ),
			'private'    => __( 'Private', 'thimbleform' ),
			'auto-draft' => __( 'Draft', 'thimbleform' ),
		);
		$status_label = $status_labels[ $status ] ?? ucfirst( (string) $status );
		$forms_url    = admin_url( 'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE );

		$public_url = '';
		if ( $id > 0 && 'auto-draft' !== $status ) {
			$found = self::find_embed_page( $id );
			if ( $found ) {
				$public_url = (string) $found['url'];
			}
		}

		$qr_svg = '';
		if ( $public_url !== '' && class_exists( 'Thimbleform_Qr_Code' ) ) {
			$qr_svg = Thimbleform_Qr_Code::svg( $public_url, __( 'QR code for this form', 'thimbleform' ) );
		}

		$embed_entries_url = '';
		$embed_entries_new = 0;
		if ( $id > 0 && 'auto-draft' !== $status && class_exists( 'Thimbleform_Submissions' ) ) {
			$embed_entries_new = (int) Thimbleform_Submissions::count_new_for_form( $id );
			$embed_entries_url = $embed_entries_new > 0
				? Thimbleform_Submissions::list_url( $id, Thimbleform_Submissions::STATUS_NEW )
				: Thimbleform_Submissions::list_url( $id );
		}
		$can_export     = class_exists( 'Thimbleform_Form_IO' ) && $id > 0 && 'auto-draft' !== $status;
		$can_duplicate  = $id > 0 && 'auto-draft' !== $status;
		$can_unpublish  = ( 'publish' === $status );
		$has_more_actions = $can_export || $can_duplicate || $can_unpublish;
		$trash_url      = '';
		if ( $id > 0 && 'auto-draft' !== $status && current_user_can( 'delete_post', $id ) ) {
			$trash_url = (string) get_delete_post_link( $id, '', false );
		}
		?>
		<div class="thimbleform-embed">
			<a class="thimbleform-btn thimbleform-btn--outline thimbleform-embed__back" href="<?php echo esc_url( $forms_url ); ?>">
				<?php thimbleform_admin_icon( 'back' ); ?>
				<?php esc_html_e( 'Forms', 'thimbleform' ); ?>
			</a>
			<div class="thimbleform-embed__status">
				<span class="thimbleform-embed__status-dot thimbleform-embed__status-dot--<?php echo esc_attr( 'publish' === $status ? 'live' : 'draft' ); ?>" aria-hidden="true"></span>
				<span class="thimbleform-embed__status-text"><?php echo esc_html( $status_label ); ?></span>
			</div>

			<label class="thimbleform-admin__label"><?php esc_html_e( 'Shortcode', 'thimbleform' ); ?></label>
			<div class="thimbleform-embed__row">
				<code class="thimbleform-embed__code" data-thimbleform-copy-text><?php echo esc_html( $id_sc ); ?></code>
				<button type="button" class="thimbleform-btn thimbleform-btn--outline thimbleform-embed__copy" data-thimbleform-copy aria-label="<?php esc_attr_e( 'Copy shortcode', 'thimbleform' ); ?>">
					<?php thimbleform_admin_icon( 'copy' ); ?>
				</button>
			</div>

			<?php if ( $public_url !== '' ) : ?>
				<div class="thimbleform-embed__share">
					<div class="thimbleform-embed__row thimbleform-embed__row--flush">
						<code class="thimbleform-embed__code" data-thimbleform-copy-text title="<?php echo esc_attr( $public_url ); ?>"><?php echo esc_html( $public_url ); ?></code>
						<button type="button" class="thimbleform-btn thimbleform-btn--outline thimbleform-embed__copy" data-thimbleform-copy aria-label="<?php esc_attr_e( 'Copy link', 'thimbleform' ); ?>">
							<?php thimbleform_admin_icon( 'copy' ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<details class="thimbleform-embed__more">
				<summary><?php esc_html_e( 'More embed options', 'thimbleform' ); ?></summary>
				<div class="thimbleform-embed__more-body">
					<label class="thimbleform-admin__label"><?php esc_html_e( 'By slug', 'thimbleform' ); ?></label>
					<div class="thimbleform-embed__row thimbleform-embed__row--flush">
						<code class="thimbleform-embed__code" data-thimbleform-copy-text><?php echo esc_html( $slug_sc ); ?></code>
						<button type="button" class="thimbleform-btn thimbleform-btn--outline thimbleform-embed__copy" data-thimbleform-copy aria-label="<?php esc_attr_e( 'Copy shortcode', 'thimbleform' ); ?>">
							<?php thimbleform_admin_icon( 'copy' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Or pick this form in the Gutenberg Thimbleform block or an ACF Form field.', 'thimbleform' ); ?></p>
					<?php if ( $qr_svg !== '' ) : ?>
						<div class="thimbleform-embed__qr"><?php echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated SVG ?></div>
					<?php endif; ?>
				</div>
			</details>

			<?php if ( $embed_entries_url !== '' ) : ?>
				<div class="thimbleform-embed__primary">
					<a class="thimbleform-btn thimbleform-btn--outline thimbleform-btn--accent thimbleform-embed__entries" href="<?php echo esc_url( $embed_entries_url ); ?>">
						<?php thimbleform_admin_icon( 'entries' ); ?>
						<?php esc_html_e( 'Entries', 'thimbleform' ); ?>
						<?php if ( $embed_entries_new > 0 ) : ?>
							<span class="thimbleform-embed__entries-count"><?php echo esc_html( number_format_i18n( $embed_entries_new ) ); ?></span>
						<?php endif; ?>
					</a>
				</div>
			<?php endif; ?>

			<?php if ( $has_more_actions ) : ?>
				<details class="thimbleform-embed__actions-more" open>
					<summary><?php esc_html_e( 'More actions', 'thimbleform' ); ?></summary>
					<div class="thimbleform-embed__actions-more-body">
						<?php if ( $can_export ) : ?>
							<a class="thimbleform-embed__action" href="<?php echo esc_url( Thimbleform_Form_IO::export_url( $id ) ); ?>">
								<?php thimbleform_admin_icon( 'download' ); ?>
								<span><?php esc_html_e( 'Export', 'thimbleform' ); ?></span>
							</a>
						<?php endif; ?>
						<?php if ( $can_duplicate ) : ?>
							<a class="thimbleform-embed__action" href="<?php echo esc_url( Thimbleform_Post_Type::duplicate_url( $id ) ); ?>">
								<?php thimbleform_admin_icon( 'copy' ); ?>
								<span><?php esc_html_e( 'Duplicate', 'thimbleform' ); ?></span>
							</a>
						<?php endif; ?>
						<?php if ( $can_unpublish ) : ?>
							<button
								type="submit"
								class="thimbleform-embed__action"
								name="saveasdraft"
								value="1"
								title="<?php esc_attr_e( 'Unpublish and keep editing as a draft. Use Save in the header to keep this form live.', 'thimbleform' ); ?>"
							>
								<?php thimbleform_admin_icon( 'unpublish' ); ?>
								<span><?php esc_html_e( 'Switch to draft', 'thimbleform' ); ?></span>
							</button>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>

			<?php if ( $trash_url !== '' ) : ?>
				<a
					class="thimbleform-btn thimbleform-btn--danger thimbleform-embed__delete"
					href="<?php echo esc_url( $trash_url ); ?>"
					onclick="return confirm('<?php echo esc_js( __( 'Move this form to Trash?', 'thimbleform' ) ); ?>');"
				>
					<?php thimbleform_admin_icon( 'trash' ); ?>
					<?php esc_html_e( 'Delete', 'thimbleform' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<div class="thimbleform-preview" data-thimbleform-preview-drawer hidden>
			<div class="thimbleform-preview__backdrop" data-thimbleform-preview-close></div>
			<div class="thimbleform-preview__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Form preview', 'thimbleform' ); ?>">
				<header class="thimbleform-preview__head">
					<strong><?php esc_html_e( 'Preview', 'thimbleform' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Saved form only — save changes first.', 'thimbleform' ); ?></span>
					<button type="button" class="thimbleform-btn" data-thimbleform-preview-close><?php esc_html_e( 'Close', 'thimbleform' ); ?></button>
				</header>
				<iframe class="thimbleform-preview__frame" title="<?php esc_attr_e( 'Form preview', 'thimbleform' ); ?>" data-thimbleform-preview-frame></iframe>
			</div>
		</div>
		<?php
	}

	/**
	 * First published page/post that embeds this form via shortcode, block, or ACF.
	 *
	 * @param int $form_id Form ID.
	 * @return array{url:string,title:string}|null
	 */
	public static function find_embed_page( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return null;
		}

		$filtered = apply_filters( 'thimbleform_find_embed_page', null, $form_id );
		if ( is_array( $filtered ) && ! empty( $filtered['url'] ) ) {
			return array(
				'url'   => (string) $filtered['url'],
				'title' => isset( $filtered['title'] ) ? (string) $filtered['title'] : '',
			);
		}

		$searches = array( 'thimbleform', (string) $form_id );
		$seen     = array();

		foreach ( $searches as $search ) {
			$query = new WP_Query(
				array(
					'post_type'              => array( 'page', 'post' ),
					'post_status'            => 'publish',
					'posts_per_page'         => 50,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					's'                      => $search,
				)
			);

			foreach ( $query->posts as $post ) {
				if ( isset( $seen[ $post->ID ] ) ) {
					continue;
				}
				$seen[ $post->ID ] = true;

				if ( ! self::content_embeds_form( (string) $post->post_content, $form_id ) ) {
					continue;
				}

				$url = get_permalink( $post );
				if ( $url ) {
					return array(
						'url'   => $url,
						'title' => get_the_title( $post ),
					);
				}
			}
		}

		return null;
	}

	/**
	 * Whether post content embeds the given form.
	 *
	 * @param string $content Raw post content.
	 * @param int    $form_id Form ID.
	 * @return bool
	 */
	public static function content_embeds_form( $content, $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 || $content === '' ) {
			return false;
		}

		$needles = array();
		foreach ( array( 'thimbleform', 'thimbleform' ) as $tag ) {
			$needles[] = '[' . $tag . ' id="' . $form_id . '"]';
			$needles[] = '[' . $tag . " id='" . $form_id . "']";
			$needles[] = '[' . $tag . ' id=' . $form_id . ']';
		}

		$form = get_post( $form_id );
		if ( $form && $form->post_name !== '' ) {
			$slug = $form->post_name;
			foreach ( array( 'thimbleform', 'thimbleform' ) as $tag ) {
				$needles[] = '[' . $tag . ' slug="' . $slug . '"]';
				$needles[] = '[' . $tag . " slug='" . $slug . "']";
				$needles[] = '[' . $tag . ' slug=' . $slug . ']';
			}
		}

		foreach ( $needles as $needle ) {
			if ( false !== strpos( $content, $needle ) ) {
				return true;
			}
		}

		if ( false !== strpos( $content, '"formId":' . $form_id )
			|| false !== strpos( $content, '"formId":"' . $form_id . '"' )
			|| false !== strpos( $content, '"id":' . $form_id )
		) {
			if ( false !== strpos( $content, 'wp:thimbleform' ) || false !== strpos( $content, 'thimbleform/form' ) ) {
				return true;
			}
		}

		$form_pattern = '/"form"\s*:\s*"?'. preg_quote( (string) $form_id, '/' ) .'"?/';
		if ( preg_match( $form_pattern, $content ) && false !== strpos( $content, 'acf/' ) ) {
			return true;
		}

		if ( function_exists( 'parse_blocks' ) ) {
			$blocks = parse_blocks( $content );
			if ( self::blocks_contain_form( $blocks, $form_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param int                               $form_id Form ID.
	 * @return bool
	 */
	private static function blocks_contain_form( array $blocks, $form_id ) {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			if ( $name === 'thimbleform/form' || ( $name !== '' && false !== strpos( $name, 'thimbleform/' ) ) ) {
				$block_form_id = 0;
				if ( isset( $attrs['formId'] ) ) {
					$block_form_id = (int) $attrs['formId'];
				} elseif ( isset( $attrs['id'] ) ) {
					$block_form_id = (int) $attrs['id'];
				}
				if ( $block_form_id === $form_id ) {
					return true;
				}
			}

			if ( $name !== '' && false !== strpos( $name, 'acf/' ) ) {
				$data = isset( $attrs['data'] ) && is_array( $attrs['data'] ) ? $attrs['data'] : array();
				foreach ( $data as $value ) {
					if ( is_numeric( $value ) && (int) $value === $form_id ) {
						return true;
					}
					if ( is_array( $value ) && isset( $value['ID'] ) && (int) $value['ID'] === $form_id ) {
						return true;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				if ( self::blocks_contain_form( $block['innerBlocks'], $form_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['thimbleform_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['thimbleform_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['thimbleform'] ) || ! is_array( $_POST['thimbleform'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Form_Config::save
		$raw    = wp_unslash( $_POST['thimbleform'] );
		$config = array(
			'fields'   => isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array(),
			'messages' => isset( $raw['messages'] ) && is_array( $raw['messages'] ) ? $raw['messages'] : array(),
			'mail'     => isset( $raw['mail'] ) && is_array( $raw['mail'] ) ? $raw['mail'] : array(),
			'settings' => isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array(),
		);

		foreach ( $config['fields'] as $i => $field ) {
			if ( ! is_array( $field ) ) {
				unset( $config['fields'][ $i ] );
				continue;
			}
			$config['fields'][ $i ]['required'] = ! empty( $field['required'] );
			if ( empty( $field['name'] ) && ! empty( $field['label'] ) ) {
				$config['fields'][ $i ]['name'] = sanitize_key( str_replace( '-', '_', sanitize_title( (string) $field['label'] ) ) );
			}
		}

		Thimbleform_Form_Config::save( $post_id, $config );
	}

	/**
	 * Map data-thimbleform-show → types (keep in sync with admin.js showForTypes).
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function show_for_types() {
		return array(
			'heading-level'  => array( 'heading' ),
			'image-picker'   => array( 'image' ),
			'html-content'   => array( 'html' ),
			'paragraph-text' => array( 'paragraph' ),
			'spacer-size'    => array( 'spacer' ),
			'phone-country'  => array( 'tel' ),
			'file-limits'    => array( 'file' ),
			'file-max'       => array( 'file' ),
			'options'        => array( 'select', 'radio', 'checkboxes', 'range', 'rating', 'scale', 'ranking', 'matrix' ),
			'payment-setup'  => array( 'payment' ),
			'formula'        => array( 'calculated' ),
			'subfields'      => array( 'repeater' ),
			'choice-other'   => array( 'select', 'radio', 'checkboxes' ),
			'placeholder'    => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'textarea', 'select' ),
			'default'        => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'textarea', 'hidden', 'select', 'radio', 'checkboxes', 'date', 'time', 'checkbox', 'rating', 'nps', 'scale' ),
			'description'    => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'textarea', 'select', 'radio', 'checkboxes', 'checkbox', 'acceptance', 'file', 'image', 'date', 'time', 'hidden', 'rating', 'signature', 'nps', 'scale', 'ranking', 'paragraph', 'calculated', 'repeater', 'payment' ),
			'acceptance-html'=> array( 'acceptance' ),
			'condition'      => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'textarea', 'select', 'radio', 'checkboxes', 'checkbox', 'acceptance', 'file', 'date', 'time', 'hidden', 'rating', 'signature', 'nps', 'scale', 'ranking', 'calculated', 'repeater', 'payment' ),
		);
	}

	/**
	 * Render one repeater subfield row in the builder.
	 *
	 * @param string                $prefix      Name prefix.
	 * @param array<string, mixed>  $sub         Subfield.
	 * @param array<string, string> $input_types Input type labels (from Form_Config).
	 */
	private static function render_subfield_row( $prefix, array $sub, array $input_types ) {
		$sub_type = (string) ( $sub['type'] ?? 'text' );
		$allowed  = array(
			'text'       => __( 'Text', 'thimbleform' ),
			'email'      => __( 'Email', 'thimbleform' ),
			'tel'        => __( 'Phone', 'thimbleform' ),
			'url'        => __( 'URL', 'thimbleform' ),
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
		);
		if ( ! empty( $input_types['calculated'] ) ) {
			$allowed['calculated'] = (string) $input_types['calculated'];
		} elseif ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::CALCULATED_FIELDS ) ) {
			$allowed['calculated'] = __( 'Calculated', 'thimbleform' );
		}
		// Prefer labels from the live input map when present.
		foreach ( $allowed as $key => $label ) {
			if ( isset( $input_types[ $key ] ) && is_string( $input_types[ $key ] ) && $input_types[ $key ] !== '' ) {
				$allowed[ $key ] = $input_types[ $key ];
			}
		}
		if ( ! isset( $allowed[ $sub_type ] ) ) {
			$sub_type = 'text';
		}
		$needs_options = in_array( $sub_type, array( 'select', 'radio', 'checkboxes', 'range', 'calculated' ), true );
		$options_label = 'calculated' === $sub_type
			? __( 'Formula', 'thimbleform' )
			: ( 'range' === $sub_type ? __( 'Min / max / step', 'thimbleform' ) : __( 'Choices (one per line)', 'thimbleform' ) );
		$options_ph    = 'calculated' === $sub_type
			? '{price} * {qty}'
			: ( 'range' === $sub_type ? "0\n100\n1" : __( "Yes\nNo", 'thimbleform' ) );
		?>
		<div class="thimbleform-subfield" data-thimbleform-subfield>
			<div class="thimbleform-subfield__grid">
				<label class="thimbleform-admin__field-control thimbleform-subfield__type">
					<span class="thimbleform-admin__label"><?php esc_html_e( 'Type', 'thimbleform' ); ?></span>
					<select class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" data-thimbleform-subfield-type>
						<?php foreach ( $allowed as $t => $type_label ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $sub_type, $t ); ?>><?php echo esc_html( $type_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="thimbleform-admin__field-control thimbleform-subfield__label">
					<span class="thimbleform-admin__label"><?php esc_html_e( 'Label', 'thimbleform' ); ?></span>
					<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[label]' ); ?>" value="<?php echo esc_attr( (string) ( $sub['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Visible label', 'thimbleform' ); ?>" data-thimbleform-subfield-label />
				</label>
				<label class="thimbleform-admin__field-control thimbleform-subfield__name">
					<span class="thimbleform-admin__label"><?php esc_html_e( 'Name', 'thimbleform' ); ?></span>
					<input type="text" class="thimbleform-admin__input" name="<?php echo esc_attr( $prefix . '[name]' ); ?>" value="<?php echo esc_attr( (string) ( $sub['name'] ?? '' ) ); ?>" placeholder="item" pattern="[a-z0-9_]+" data-thimbleform-subfield-name />
				</label>
				<label class="thimbleform-admin__check thimbleform-subfield__required">
					<input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( ! empty( $sub['required'] ) ); ?> />
					<span><?php esc_html_e( 'Required', 'thimbleform' ); ?></span>
				</label>
				<button type="button" class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text thimbleform-subfield__remove" data-thimbleform-subfield-remove>
					<?php esc_html_e( 'Remove', 'thimbleform' ); ?>
				</button>
			</div>
			<label class="thimbleform-admin__field-control thimbleform-admin__field-control--full thimbleform-subfield__options" data-thimbleform-subfield-options<?php echo $needs_options ? '' : ' hidden'; ?>>
				<span class="thimbleform-admin__label" data-thimbleform-subfield-options-label><?php echo esc_html( $options_label ); ?></span>
				<textarea
					class="thimbleform-admin__input thimbleform-admin__textarea"
					name="<?php echo esc_attr( $prefix . '[options]' ); ?>"
					rows="3"
					data-thimbleform-subfield-options-input
					placeholder="<?php echo esc_attr( $options_ph ); ?>"
					<?php echo $needs_options ? '' : ' disabled'; ?>
				><?php echo esc_textarea( (string) ( $sub['options'] ?? '' ) ); ?></textarea>
				<p class="thimbleform-admin__hint" data-thimbleform-subfield-options-hint>
					<?php
					if ( 'calculated' === $sub_type ) {
						esc_html_e( 'Use other subfield names in braces, e.g. {qty} * {price}.', 'thimbleform' );
					} elseif ( 'range' === $sub_type ) {
						esc_html_e( 'Three lines: minimum, maximum, step.', 'thimbleform' );
					} else {
						esc_html_e( 'One choice per line — the text visitors see. Example: Yes', 'thimbleform' );
					}
					?>
				</p>
			</label>
		</div>
		<?php
	}

	/**
	 * Disable inactive named controls so shared keys (options/default) do not collide on save.
	 *
	 * @param string $type Current field type.
	 * @param string $key  data-thimbleform-show key.
	 * @return string
	 */
	private static function disabled_for_show( $type, $key ) {
		$map = self::show_for_types();
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return in_array( $type, $map[ $key ], true ) ? '' : ' disabled';
	}
}
