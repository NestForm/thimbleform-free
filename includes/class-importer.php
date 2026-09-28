<?php
/**
 * Import forms from Contact Form 7 and WPForms into Thimbleform drafts.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Importer {

	const PAGE_SLUG     = 'thimbleform-import';
	const ACTION_IMPORT = 'thimbleform_import_external';
	const REPORT_TTL    = 300;

	/** CF7 tag pattern: type, optional *, name, params. */
	const CF7_TAG_PATTERN = '/\[([a-z][a-z0-9_]*)(\*?)\s+([a-zA-Z][a-zA-Z0-9_:.\-]*)((?:\s[^\]]*)?)\]/';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 35 );
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				'post_type' => Thimbleform_Post_Type::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Whether the current user may import external forms.
	 *
	 * @return bool
	 */
	public static function user_can_import() {
		return current_user_can( 'edit_posts' )
			|| ( class_exists( 'Thimbleform_Capabilities' ) && Thimbleform_Capabilities::can_manage() );
	}

	public static function menu() {
		if ( ! self::user_can_import() ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE,
			__( 'Import forms', 'thimbleform' ),
			__( 'Import forms', 'thimbleform' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			$classes .= ' thimbleform-admin-screen thimbleform-import-screen thimbleform-settings-screen';
		}
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver = (string) filemtime( thimbleform_admin_css_path() );
		wp_enqueue_style(
			'thimbleform-admin',
			thimbleform_admin_css_url(),
			thimbleform_admin_style_deps(),
			$ver ? $ver : THIMBLEFORM_VERSION
		);
	}

	/**
	 * Compact hub link for CF7 / WPForms import.
	 *
	 * @param bool $menu_item When true, styles as a dropdown item.
	 * @return string
	 */
	public static function hub_link_html( $menu_item = false ) {
		if ( ! self::user_can_import() ) {
			return '';
		}
		$class = $menu_item
			? 'thimbleform-btn thimbleform-btn--ghost thimbleform-hub__import-item'
			: 'thimbleform-btn thimbleform-btn--outline';
		$label = $menu_item
			? __( 'From CF7 / WPForms', 'thimbleform' )
			: __( 'Import forms', 'thimbleform' );
		return sprintf(
			'<a class="%1$s" href="%2$s">%3$s%4$s</a>',
			esc_attr( $class ),
			esc_url( self::url() ),
			thimbleform_admin_icon_html( 'download' ),
			esc_html( $label )
		);
	}

	public static function render() {
		if ( ! self::user_can_import() ) {
			wp_die( esc_html__( 'You do not have permission to import forms.', 'thimbleform' ) );
		}

		$sources = self::sources();
		?>
		<div class="wrap thimbleform-admin thimbleform-settings thimbleform-import">
			<?php
			thimbleform_render_page_head(
				array(
					'title'       => __( 'Import forms', 'thimbleform' ),
					'description' => __( 'Bring questions across from Contact Form 7 or WPForms. Imports land as drafts — review before publishing. Responses are not copied.', 'thimbleform' ),
					'icon'        => 'forms',
				)
			);
			self::render_report();
			?>

			<?php foreach ( $sources as $slug => $source ) : ?>
				<?php self::render_source_card( $slug, $source ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @return array<string, array{label:string,description:string,forms:array<int,array{id:int,title:string}>}>
	 */
	public static function sources() {
		return array(
			'cf7'     => array(
				'label'       => __( 'Contact Form 7', 'thimbleform' ),
				'description' => __( 'Reads CF7 forms stored on this site. The plugin does not need to be active.', 'thimbleform' ),
				'forms'       => self::posts_of_type( 'wpcf7_contact_form' ),
			),
			'wpforms' => array(
				'label'       => __( 'WPForms', 'thimbleform' ),
				'description' => __( 'Reads WPForms / WPForms Lite forms from this site. The plugin does not need to be active.', 'thimbleform' ),
				'forms'       => self::posts_of_type( 'wpforms' ),
			),
		);
	}

	/**
	 * @param string $post_type CPT.
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function posts_of_type( $post_type ) {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'id'    => (int) $post->ID,
				'title' => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'thimbleform' ),
			);
		}
		return $out;
	}

	/**
	 * @param string               $slug   Source slug.
	 * @param array<string, mixed> $source Source meta.
	 */
	private static function render_source_card( $slug, array $source ) {
		$forms = isset( $source['forms'] ) && is_array( $source['forms'] ) ? $source['forms'] : array();
		?>
		<div class="thimbleform-admin__surface thimbleform-settings__card thimbleform-import__card">
			<div class="thimbleform-admin__panel-head">
				<div>
					<h2 class="thimbleform-admin__panel-title"><?php echo esc_html( (string) $source['label'] ); ?></h2>
					<p class="thimbleform-admin__panel-desc"><?php echo esc_html( (string) $source['description'] ); ?></p>
				</div>
			</div>

			<?php if ( array() === $forms ) : ?>
				<p class="thimbleform-import__empty"><?php esc_html_e( 'No forms of this type were found in the database.', 'thimbleform' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="thimbleform-import__form">
					<?php wp_nonce_field( self::ACTION_IMPORT ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>" />
					<input type="hidden" name="source" value="<?php echo esc_attr( $slug ); ?>" />
					<label class="thimbleform-admin__field-control">
						<span class="thimbleform-admin__label"><?php esc_html_e( 'Form to import', 'thimbleform' ); ?></span>
						<select class="thimbleform-admin__input" name="reference" required>
							<option value=""><?php esc_html_e( 'Select a form…', 'thimbleform' ); ?></option>
							<?php foreach ( $forms as $form ) : ?>
								<option value="<?php echo esc_attr( (string) $form['id'] ); ?>">
									<?php echo esc_html( $form['title'] . ' (#' . $form['id'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="submit" class="thimbleform-btn thimbleform-btn--primary">
						<?php esc_html_e( 'Import as draft', 'thimbleform' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle() {
		if ( ! self::user_can_import() ) {
			wp_die( esc_html__( 'You do not have permission to import forms.', 'thimbleform' ), 403 );
		}
		check_admin_referer( self::ACTION_IMPORT );

		$source    = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reference = isset( $_POST['reference'] ) ? absint( wp_unslash( $_POST['reference'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$result = self::import( $source, (string) $reference );
			self::store_report(
				array(
					'ok'        => true,
					'form_id'   => $result['form_id'],
					'title'     => $result['title'],
					'questions' => $result['questions'],
					'notes'     => $result['notes'],
				)
			);
		} catch ( Exception $e ) {
			self::store_report(
				array(
					'ok'      => false,
					'message' => $e->getMessage(),
				)
			);
		}

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * @param string $source    cf7|wpforms.
	 * @param string $reference Source form ID.
	 * @return array{form_id:int,title:string,questions:int,notes:array<int,string>}
	 * @throws Exception On failure.
	 */
	public static function import( $source, $reference ) {
		if ( 'cf7' === $source ) {
			$definition = self::cf7_to_definition( $reference );
		} elseif ( 'wpforms' === $source ) {
			$definition = self::wpforms_to_definition( $reference );
		} else {
			throw new Exception( esc_html__(  'Unknown import source.', 'thimbleform' ) );
		}

		$fields = self::normalize_fields( isset( $definition['fields'] ) ? $definition['fields'] : array() );
		$count  = 0;
		foreach ( $fields as $field ) {
			if ( ! empty( $field['type'] ) && ! Thimbleform_Form_Config::is_layout_field( (string) $field['type'] ) ) {
				++$count;
			}
		}
		if ( $count < 1 ) {
			throw new Exception( esc_html__(  'No Thimbleform-compatible questions were found in that form.', 'thimbleform' ) );
		}

		$title = isset( $definition['title'] ) ? sanitize_text_field( (string) $definition['title'] ) : '';
		if ( '' === $title ) {
			$title = __( 'Imported form', 'thimbleform' );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => Thimbleform_Post_Type::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			throw new Exception( esc_html__(  'Could not create the imported form.', 'thimbleform' ) );
		}

		$config = array(
			'fields'   => $fields,
			'messages' => Thimbleform_Form_Config::default_messages(),
			'mail'     => Thimbleform_Form_Config::default_mail(),
			'settings' => Thimbleform_Form_Config::default_settings(),
		);
		if ( ! empty( $definition['submit_label'] ) ) {
			$config['settings']['submit_label'] = sanitize_text_field( (string) $definition['submit_label'] );
		}

		Thimbleform_Form_Config::save( (int) $new_id, $config );

		return array(
			'form_id'   => (int) $new_id,
			'title'     => $title,
			'questions' => $count,
			'notes'     => isset( $definition['notes'] ) && is_array( $definition['notes'] ) ? array_values( $definition['notes'] ) : array(),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Raw fields.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_fields( array $fields ) {
		$used = array();
		$out  = array();
		foreach ( $fields as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_key( str_replace( '-', '_', (string) $row['name'] ) ) : '';
			if ( '' === $name ) {
				$base = sanitize_key( str_replace( '-', '_', sanitize_title( (string) ( $row['label'] ?? 'field' ) ) ) );
				$name = $base ? $base : 'field';
			}
			$original = $name;
			$n        = 2;
			while ( isset( $used[ $name ] ) ) {
				$name = $original . '_' . $n;
				++$n;
			}
			$used[ $name ] = true;
			$row['name']   = $name;

			if ( isset( $row['options'] ) && is_array( $row['options'] ) ) {
				$row['options'] = implode( "\n", array_map( 'strval', $row['options'] ) );
			}

			$clean = Thimbleform_Form_Config::sanitize_field_row( $row );
			if ( $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	/**
	 * @param string $reference CF7 post ID.
	 * @return array{title:string,fields:array<int,array<string,mixed>>,notes:array<int,string>,submit_label?:string}
	 * @throws Exception On failure.
	 */
	private static function cf7_to_definition( $reference ) {
		$post_id = absint( $reference );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) {
			throw new Exception( esc_html__(  'That Contact Form 7 form could not be found.', 'thimbleform' ) );
		}

		$template = (string) get_post_meta( $post_id, '_form', true );
		if ( '' === trim( $template ) ) {
			throw new Exception( esc_html__(  'That Contact Form 7 form has no questions in it.', 'thimbleform' ) );
		}

		$type_map = array(
			'text'       => 'text',
			'email'      => 'email',
			'tel'        => 'tel',
			'url'        => 'url',
			'number'     => 'number',
			'range'      => 'range',
			'textarea'   => 'textarea',
			'select'     => 'select',
			'radio'      => 'radio',
			'checkbox'   => 'checkboxes',
			'acceptance' => 'acceptance',
			'date'       => 'date',
			'file'       => 'file',
		);
		$ignored = array( 'submit', 'hidden', 'captchac', 'captchar', 'recaptcha', 'response', 'count', 'honeypot', 'akismet', 'quiz' );

		$fields = array();
		$notes  = array();
		$submit = '';

		if ( ! preg_match_all( self::CF7_TAG_PATTERN, $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			throw new Exception( esc_html__(  'No Contact Form 7 tags were found in that form.', 'thimbleform' ) );
		}

		$cursor = 0;
		foreach ( $matches as $match ) {
			$whole    = $match[0][0];
			$offset   = (int) $match[0][1];
			$tag      = $match[1][0];
			$required = '*' === $match[2][0];
			$name     = $match[3][0];
			$params   = $match[4][0];
			$before   = substr( $template, $cursor, $offset - $cursor );
			$cursor   = $offset + strlen( $whole );

			if ( 'submit' === $tag ) {
				$quoted = self::cf7_quoted_values( $params );
				if ( $quoted ) {
					$submit = $quoted[0];
				}
				continue;
			}

			if ( in_array( $tag, $ignored, true ) ) {
				continue;
			}

			$label = '';
			if ( 'acceptance' === $tag ) {
				list( $label, $cursor ) = self::cf7_consume_after( $template, $cursor );
			}
			if ( '' === $label ) {
				$label = self::cf7_text_before( $before );
			}
			if ( '' === $label ) {
				$label = ucfirst( trim( str_replace( array( '-', '_' ), ' ', $name ) ) );
			}

			if ( ! isset( $type_map[ $tag ] ) ) {
				$notes[] = sprintf(
					/* translators: 1: field label, 2: CF7 tag type */
					__( 'Skipped “%1$s” (%2$s) — no Thimbleform equivalent.', 'thimbleform' ),
					$label,
					$tag
				);
				continue;
			}

			$type   = $type_map[ $tag ];
			$values = self::cf7_quoted_values( $params );
			$field  = array(
				'label'    => $label,
				'name'     => $name,
				'type'     => $type,
				'required' => $required,
				'width'    => 'full',
				'options'  => '',
			);

			if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
				$field['options'] = $values;
				if ( 'checkboxes' === $type && count( $values ) <= 1 && false === strpos( $params, 'exclusive' ) ) {
					// Single CF7 checkbox often means one consent-like tick.
					if ( count( $values ) <= 1 ) {
						$field['type']    = 'checkbox';
						$field['options'] = '';
					}
				}
			} elseif ( 'acceptance' === $type ) {
				$field['label'] = $label;
			} elseif ( $values && false !== strpos( $params, 'placeholder' ) ) {
				$field['placeholder'] = $values[0];
			}

			$fields[] = $field;
		}

		$out = array(
			'title'  => (string) $post->post_title,
			'fields' => $fields,
			'notes'  => $notes,
		);
		if ( '' !== $submit ) {
			$out['submit_label'] = $submit;
		}
		return $out;
	}

	/**
	 * @param string $params Tag params.
	 * @return array<int, string>
	 */
	private static function cf7_quoted_values( $params ) {
		if ( ! preg_match_all( '/"([^"]*)"|\'([^\']*)\'/', $params, $matches, PREG_SET_ORDER ) ) {
			return array();
		}
		$values = array();
		foreach ( $matches as $match ) {
			$value = trim( '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' ) );
			if ( '' !== $value ) {
				$values[] = $value;
			}
		}
		return $values;
	}

	/**
	 * @param string $markup Markup before tag.
	 * @return string
	 */
	private static function cf7_text_before( $markup ) {
		$lines = self::cf7_text_lines( $markup );
		return $lines ? (string) end( $lines ) : '';
	}

	/**
	 * @param string $template Template.
	 * @param int    $offset   Cursor.
	 * @return array{0:string,1:int}
	 */
	private static function cf7_consume_after( $template, $offset ) {
		$rest = substr( $template, $offset );
		$next = strpos( $rest, '[' );
		$span = false !== $next ? substr( $rest, 0, $next ) : $rest;
		$lines = self::cf7_text_lines( $span );
		if ( ! $lines ) {
			return array( '', $offset );
		}
		$break = strpos( $span, "\n" );
		return array( (string) reset( $lines ), $offset + ( false === $break ? strlen( $span ) : $break ) );
	}

	/**
	 * @param string $markup Markup chunk.
	 * @return array<int, string>
	 */
	private static function cf7_text_lines( $markup ) {
		$text = preg_replace( '#<(br|/p|/div|/label|/li|/h[1-6])\s*/?>#i', "\n", $markup );
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
		$lines = array();
		foreach ( preg_split( '/\R/', $text ) ?: array() as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) ?: '' );
			if ( '' !== $line ) {
				$lines[] = substr( $line, 0, 200 );
			}
		}
		return $lines;
	}

	/**
	 * @param string $reference WPForms post ID.
	 * @return array{title:string,fields:array<int,array<string,mixed>>,notes:array<int,string>}
	 * @throws Exception On failure.
	 */
	private static function wpforms_to_definition( $reference ) {
		$post_id = absint( $reference );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || 'wpforms' !== $post->post_type ) {
			throw new Exception( esc_html__(  'That WPForms form could not be found.', 'thimbleform' ) );
		}

		$data = json_decode( (string) $post->post_content, true );
		if ( ! is_array( $data ) ) {
			$data = json_decode( (string) wp_unslash( $post->post_content ), true );
		}
		if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			throw new Exception( esc_html__(  'That WPForms form has no questions in it.', 'thimbleform' ) );
		}

		$notes    = array();
		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$fields   = self::wpforms_convert_fields( $data['fields'], $notes );

		$title = trim( (string) $post->post_title );
		if ( '' === $title ) {
			$title = (string) ( $settings['form_title'] ?? '' );
		}

		$out = array(
			'title'  => $title,
			'fields' => $fields,
			'notes'  => $notes,
		);
		if ( ! empty( $settings['submit_text'] ) ) {
			$out['submit_label'] = (string) $settings['submit_text'];
		}
		return $out;
	}

	/**
	 * @param array<mixed>     $fields WPForms fields.
	 * @param array<int,string> $notes Notes (by ref).
	 * @return array<int, array<string, mixed>>
	 */
	private static function wpforms_convert_fields( array $fields, array &$notes ) {
		$type_map = array(
			'text'          => 'text',
			'textarea'      => 'textarea',
			'richtext'      => 'textarea',
			'select'        => 'select',
			'radio'         => 'radio',
			'checkbox'      => 'checkboxes',
			'email'         => 'email',
			'url'           => 'url',
			'number'        => 'number',
			'number-slider' => 'range',
			'phone'         => 'tel',
			'date-time'     => 'date',
			'date'          => 'date',
			'time'          => 'time',
			'gdpr-checkbox' => 'acceptance',
			'file-upload'   => 'file',
			'password'      => 'password',
			'hidden'        => 'hidden',
			'html'          => 'html',
			'content'       => 'paragraph',
			'divider'       => 'divider',
			'name'          => 'text',
			'address'       => 'textarea',
		);
		if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::PAYMENTS ) ) {
			$map['credit-card']        = 'payment';
			$map['stripe-credit-card'] = 'payment';
		}
		$ignored = array( 'captcha', 'entry-preview', 'internal-information', 'pagebreak' );
		$unsupported = array(
			'signature'          => __( 'signature', 'thimbleform' ),
			'payment-single'     => __( 'payment', 'thimbleform' ),
			'payment-multiple'   => __( 'payment', 'thimbleform' ),
			'payment-checkbox'   => __( 'payment', 'thimbleform' ),
			'payment-select'     => __( 'payment', 'thimbleform' ),
			'payment-total'      => __( 'payment total', 'thimbleform' ),
			'rating'             => __( 'rating (Pro)', 'thimbleform' ),
			'likert_scale'       => __( 'likert (Pro)', 'thimbleform' ),
			'net_promoter_score' => __( 'NPS (Pro)', 'thimbleform' ),
		);
		if ( empty( $map['credit-card'] ) ) {
			$unsupported['credit-card']        = __( 'card payment', 'thimbleform' );
			$unsupported['stripe-credit-card'] = __( 'card payment', 'thimbleform' );
		}

		$converted = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type  = (string) ( $field['type'] ?? '' );
			$label = trim( (string) ( $field['label'] ?? '' ) );

			if ( in_array( $type, array( 'layout', 'repeater' ), true ) ) {
				foreach ( (array) ( $field['columns'] ?? array() ) as $column ) {
					$children = array_filter( (array) ( $column['fields'] ?? array() ), 'is_array' );
					if ( $children ) {
						$converted = array_merge( $converted, self::wpforms_convert_fields( $children, $notes ) );
					}
				}
				continue;
			}

			if ( in_array( $type, $ignored, true ) ) {
				continue;
			}

			if ( isset( $unsupported[ $type ] ) ) {
				$notes[] = sprintf(
					/* translators: 1: field label, 2: skipped kind */
					__( 'Skipped “%1$s” (%2$s).', 'thimbleform' ),
					$label ? $label : __( 'Untitled', 'thimbleform' ),
					$unsupported[ $type ]
				);
				continue;
			}

			if ( ! isset( $type_map[ $type ] ) ) {
				$notes[] = sprintf(
					/* translators: 1: field label, 2: WPForms type */
					__( 'Skipped “%1$s” (%2$s) — no Thimbleform equivalent.', 'thimbleform' ),
					$label ? $label : __( 'Untitled', 'thimbleform' ),
					$type
				);
				continue;
			}

			$nf_type = $type_map[ $type ];
			$id      = isset( $field['id'] ) ? (string) $field['id'] : '';
			$name    = 'field_' . ( $id !== '' ? $id : wp_generate_password( 6, false, false ) );
			if ( ! empty( $field['name'] ) ) {
				$name = (string) $field['name'];
			} elseif ( $label ) {
				$name = sanitize_title( $label );
			}

			if ( 'name' === $type ) {
				$notes[] = sprintf(
					/* translators: %s: field label */
					__( '“%s” (Name) was imported as a single text field.', 'thimbleform' ),
					$label ? $label : __( 'Name', 'thimbleform' )
				);
			}
			if ( 'address' === $type ) {
				$notes[] = sprintf(
					/* translators: %s: field label */
					__( '“%s” (Address) was imported as a textarea.', 'thimbleform' ),
					$label ? $label : __( 'Address', 'thimbleform' )
				);
			}

			$row = array(
				'type'        => $nf_type,
				'name'        => $name,
				'label'       => $label ? $label : ucfirst( $nf_type ),
				'required'    => ! empty( $field['required'] ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'description' => (string) ( $field['description'] ?? '' ),
				'width'       => 'full',
				'options'     => '',
			);

			if ( in_array( $nf_type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
				$choices = array();
				foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) {
					if ( is_array( $choice ) ) {
						$choices[] = (string) ( $choice['label'] ?? $choice['value'] ?? '' );
					} else {
						$choices[] = (string) $choice;
					}
				}
				$row['options'] = array_values( array_filter( array_map( 'trim', $choices ) ) );
			}

			if ( 'html' === $nf_type ) {
				$row['options'] = (string) ( $field['code'] ?? $field['content'] ?? '' );
			}
			if ( 'paragraph' === $nf_type ) {
				$row['options'] = wp_strip_all_tags( (string) ( $field['content'] ?? $field['description'] ?? $label ) );
				$row['label']   = '';
			}
			if ( 'acceptance' === $nf_type ) {
				$row['label'] = $label ? $label : __( 'I agree to the terms', 'thimbleform' );
			}

			$converted[] = $row;
		}
		return $converted;
	}

	/**
	 * @param array<string, mixed> $report Report.
	 */
	private static function store_report( array $report ) {
		set_transient( self::report_key(), $report, self::REPORT_TTL );
	}

	/**
	 * @return string
	 */
	private static function report_key() {
		return 'thimbleform_import_report_' . get_current_user_id();
	}

	private static function render_report() {
		$key    = self::report_key();
		$report = get_transient( $key );
		if ( ! is_array( $report ) ) {
			return;
		}
		delete_transient( $key );

		if ( empty( $report['ok'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( (string) ( $report['message'] ?? __( 'Import failed.', 'thimbleform' ) ) ) . '</p></div>';
			return;
		}

		$form_id = (int) ( $report['form_id'] ?? 0 );
		$edit    = $form_id ? get_edit_post_link( $form_id, 'raw' ) : '';
		?>
		<div class="notice notice-success is-dismissible thimbleform-import__report">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: form title, 2: question count */
						__( 'Imported “%1$s” with %2$d questions as a draft.', 'thimbleform' ),
						(string) ( $report['title'] ?? '' ),
						(int) ( $report['questions'] ?? 0 )
					)
				);
				?>
				<?php if ( $edit ) : ?>
					<a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Open in builder', 'thimbleform' ); ?></a>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $report['notes'] ) && is_array( $report['notes'] ) ) : ?>
				<ul class="thimbleform-import__notes">
					<?php foreach ( $report['notes'] as $note ) : ?>
						<li><?php echo esc_html( (string) $note ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
