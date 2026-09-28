<?php
/**
 * Gutenberg block: thimbleform/form.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Block {

	const BLOCK_NAME   = 'thimbleform/form';
	const SCRIPT_HANDLE = 'thimbleform-form-editor';
	const STYLE_HANDLE  = 'thimbleform-form-editor';

	public static function init() {
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'localize_editor' ) );
	}

	/**
	 * @param array[] $categories Categories.
	 * @param mixed   $context    Editor context.
	 * @return array[]
	 */
	public static function category( $categories, $context = null ) {
		unset( $context );
		$slug = 'thimbleform';
		foreach ( $categories as $cat ) {
			if ( isset( $cat['slug'] ) && $slug === $cat['slug'] ) {
				return $categories;
			}
		}
		array_unshift(
			$categories,
			array(
				'slug'  => $slug,
				'title' => __( 'Thimbleform', 'thimbleform' ),
				'icon'  => 'feedback',
			)
		);
		return $categories;
	}

	public static function register() {
		$block_dir  = wp_normalize_path( THIMBLEFORM_PATH . 'blocks/form' );
		$editor_js  = thimbleform_js_path( 'blocks/form/editor.js' );
		$editor_css = $block_dir . '/editor.css';
		$render     = $block_dir . '/render.php';

		if ( ! is_readable( $editor_js ) || ! is_readable( $render ) ) {
			return;
		}

		$js_ver  = (string) filemtime( $editor_js );
		$css_ver = is_readable( $editor_css ) ? (string) filemtime( $editor_css ) : THIMBLEFORM_VERSION;

		wp_register_script(
			self::SCRIPT_HANDLE,
			thimbleform_js_url( 'blocks/form/editor.js' ),
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
			),
			$js_ver ? $js_ver : THIMBLEFORM_VERSION,
			true
		);

		if ( is_readable( $editor_css ) ) {
			wp_register_style(
				self::STYLE_HANDLE,
				THIMBLEFORM_URL . 'blocks/form/editor.css',
				array( 'wp-edit-blocks' ),
				$css_ver ? $css_ver : THIMBLEFORM_VERSION
			);
		}

		$result = register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 2,
				'title'           => __( 'Thimbleform', 'thimbleform' ),
				'description'     => __( 'Insert a Thimbleform form by selecting it from the list.', 'thimbleform' ),
				'category'        => 'thimbleform',
				'icon'            => 'feedback',
				'keywords'        => array( 'form', 'contact', 'lead', 'thimbleform' ),
				'attributes'      => array(
					'formId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'supports'        => array(
					'html'      => false,
					'align'     => array( 'wide', 'full' ),
					'className' => true,
					'anchor'    => true,
				),
				'editor_script'   => self::SCRIPT_HANDLE,
				'editor_style'    => self::STYLE_HANDLE,
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);

		if ( ! $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Thimbleform: failed to register block thimbleform/form' );
		}

		register_block_type(
			'nestform/form',
			array(
				'api_version'     => 2,
				'title'           => __( 'Thimbleform', 'thimbleform' ),
				'description'     => __( 'Legacy block name. Use the Thimbleform block.', 'thimbleform' ),
				'category'        => 'thimbleform',
				'icon'            => 'feedback',
				'attributes'      => array(
					'formId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'supports'        => array(
					'html'      => false,
					'align'     => array( 'wide', 'full' ),
					'className' => true,
					'anchor'    => true,
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);

		register_block_type(
			'liteforms/form',
			array(
				'api_version'     => 2,
				'title'           => __( 'LiteForm (legacy)', 'thimbleform' ),
				'description'     => __( 'Legacy block name — use Thimbleform instead.', 'thimbleform' ),
				'category'        => 'thimbleform',
				'icon'            => 'feedback',
				'attributes'      => array(
					'formId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'supports'        => array(
					'html'      => false,
					'align'     => array( 'wide', 'full' ),
					'className' => true,
					'anchor'    => true,
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);

		register_block_type(
			'vite-forms/form',
			array(
				'api_version'     => 2,
				'title'           => __( 'Vite Form (legacy)', 'thimbleform' ),
				'description'     => __( 'Legacy block name — use Thimbleform instead.', 'thimbleform' ),
				'category'        => 'thimbleform',
				'icon'            => 'feedback',
				'attributes'      => array(
					'formId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'supports'        => array(
					'html'      => false,
					'align'     => array( 'wide', 'full' ),
					'className' => true,
					'anchor'    => true,
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function render( $attributes, $content = '', $block = null ) {
		unset( $content, $block );
		$form_id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
		if ( $form_id <= 0 || ! class_exists( 'Thimbleform_Renderer' ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="thimbleform-block thimbleform-block--empty">' . esc_html__( 'Select a Thimbleform in the block settings.', 'thimbleform' ) . '</p>';
			}
			return '';
		}

		$html = Thimbleform_Renderer::render( $form_id );
		if ( $html === '' ) {
			return '';
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'thimbleform-block',
			)
		);

		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	/**
	 * Pass published forms into the block editor script.
	 */
	public static function localize_editor() {
		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) && ! wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		$forms = get_posts(
			array(
				'post_type'              => Thimbleform_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$list = array();
		foreach ( $forms as $form ) {
			$title = $form->post_title !== '' ? $form->post_title : sprintf(
				/* translators: %d: form id */
				__( 'Form #%d', 'thimbleform' ),
				(int) $form->ID
			);
			if ( 'publish' !== $form->post_status ) {
				$title .= ' (' . $form->post_status . ')';
			}
			$list[] = array(
				'id'    => (int) $form->ID,
				'title' => $title,
			);
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'thimbleformBlock',
			array(
				'forms' => $list,
				'i18n'  => array(
					'selectForm'       => __( 'Select a form...', 'thimbleform' ),
					'formLabel'        => __( 'Form', 'thimbleform' ),
					'panelTitle'       => __( 'Thimbleform', 'thimbleform' ),
					'noForms'          => __( 'No forms yet. Create one under Forms in the admin menu.', 'thimbleform' ),
					'previewHint'      => __( 'The live form renders on the front end.', 'thimbleform' ),
					'placeholderLabel' => __( 'Thimbleform', 'thimbleform' ),
					'placeholderHelp'  => __( 'Choose which form to insert.', 'thimbleform' ),
					/* translators: %d: Form ID. */
					'formFallback'     => __( 'Form #%d', 'thimbleform' ),
				),
			)
		);
	}
}
