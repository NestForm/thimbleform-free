<?php
/**
 * Printable single entry view.
 *
 * Renders one entry as a standalone page (no WP admin chrome) for Print / Save as PDF.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Entry_Print {

	const ACTION = 'nestform_print_entry';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function url( $entry_id ) {
		$entry_id = (int) $entry_id;
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION . '&entry_id=' . $entry_id ),
			self::ACTION . '_' . $entry_id
		);
	}

	/**
	 * Streams the printable page and exits.
	 */
	public static function render() {
		$entry_id = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		check_admin_referer( self::ACTION . '_' . $entry_id );

		if ( $entry_id <= 0 || Nestform_Submissions::POST_TYPE !== get_post_type( $entry_id ) ) {
			wp_die( esc_html__( 'Entry not found.', 'nestform' ), 404 );
		}

		$form_id = (int) get_post_meta( $entry_id, Nestform_Submissions::META_FORM, true );
		if ( $form_id <= 0 || Nestform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Form not found.', 'nestform' ), 404 );
		}

		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to print this entry.', 'nestform' ), 403 );
		}

		$form  = get_post( $form_id );
		$entry = get_post( $entry_id );
		if ( ! $form || ! $entry ) {
			wp_die( esc_html__( 'Entry not found.', 'nestform' ), 404 );
		}

		$data = get_post_meta( $entry_id, Nestform_Submissions::META_DATA, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		nocache_headers();

		self::render_page( $form, $entry, $data );
		exit;
	}

	/**
	 * @param WP_Post              $form  Form post.
	 * @param WP_Post              $entry Entry post.
	 * @param array<string, mixed> $data  Payload.
	 */
	private static function render_page( $form, $entry, array $data ) {
		$form_title = $form->post_title !== '' ? $form->post_title : __( '(no title)', 'nestform' );
		$title      = sprintf(
			/* translators: 1: form title, 2: entry ID. */
			__( '%1$s — entry #%2$d', 'nestform' ),
			$form_title,
			(int) $entry->ID
		);

		$status     = Nestform_Submissions::get_status( $entry->ID );
		$labels     = Nestform_Submissions::status_labels();
		$status_lbl = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$submitted  = class_exists( 'Nestform_Settings' )
			? Nestform_Settings::format_entry_datetime( $entry )
			: get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry );
		$ip         = (string) get_post_meta( $entry->ID, Nestform_Submissions::META_IP, true );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<?php
	wp_enqueue_style(
		'nestform-entry-print',
		NESTFORM_URL . 'assets/css/entry-print.css',
		array(),
		NESTFORM_VERSION
	);
	wp_enqueue_script(
		'nestform-entry-print',
		NESTFORM_URL . 'assets/js/admin/entry-print.js',
		array(),
		NESTFORM_VERSION,
		false
	);
	wp_print_styles( 'nestform-entry-print' );
	wp_print_scripts( 'nestform-entry-print' );
	?>
</head>
<body class="nestform-print-body">
	<div class="nestform-print-actions">
		<button type="button" class="nestform-print-actions__button" data-nestform-print><?php esc_html_e( 'Print', 'nestform' ); ?></button>
	</div>

	<article class="nestform-print">
		<header class="nestform-print__header">
			<p class="nestform-print__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<h1 class="nestform-print__title"><?php echo esc_html( $form_title ); ?></h1>
			<dl class="nestform-print__meta">
				<div class="nestform-print__meta-item">
					<dt class="nestform-print__meta-label"><?php esc_html_e( 'Entry', 'nestform' ); ?></dt>
					<dd class="nestform-print__meta-value">#<?php echo esc_html( (string) (int) $entry->ID ); ?></dd>
				</div>
				<div class="nestform-print__meta-item">
					<dt class="nestform-print__meta-label"><?php esc_html_e( 'Submitted', 'nestform' ); ?></dt>
					<dd class="nestform-print__meta-value"><?php echo esc_html( $submitted ); ?></dd>
				</div>
				<div class="nestform-print__meta-item">
					<dt class="nestform-print__meta-label"><?php esc_html_e( 'Status', 'nestform' ); ?></dt>
					<dd class="nestform-print__meta-value"><?php echo esc_html( $status_lbl ); ?></dd>
				</div>
				<?php if ( $ip !== '' ) : ?>
					<div class="nestform-print__meta-item">
						<dt class="nestform-print__meta-label"><?php esc_html_e( 'IP', 'nestform' ); ?></dt>
						<dd class="nestform-print__meta-value"><?php echo esc_html( $ip ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</header>

		<div class="nestform-print__body">
			<?php self::render_fields( $form->ID, $data ); ?>
		</div>

		<footer class="nestform-print__footer">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: site name, 2: date the page was printed. */
					__( '%1$s — printed %2$s', 'nestform' ),
					get_bloginfo( 'name' ),
					date_i18n( get_option( 'date_format' ) )
				)
			);
			?>
		</footer>
	</article>
</body>
</html>
		<?php
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Payload.
	 */
	private static function render_fields( $form_id, array $data ) {
		$fields = Nestform_Form_Config::get_fields( $form_id );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		foreach ( $fields as $field ) {
			$type = (string) ( isset( $field['type'] ) ? $field['type'] : '' );

			if ( Nestform_Form_Config::is_layout_field( $type ) ) {
				self::render_layout_block( $field, $type );
				continue;
			}

			if ( in_array( $type, array( 'hidden', 'password' ), true ) ) {
				continue;
			}

			$name  = (string) ( isset( $field['name'] ) ? $field['name'] : '' );
			if ( $name === '' ) {
				continue;
			}

			$label    = (string) ( isset( $field['label'] ) && $field['label'] !== '' ? $field['label'] : $name );
			$value    = array_key_exists( $name, $data ) ? $data[ $name ] : '';
			$display  = trim( Nestform_Export::cell_value( $value ) );
			$answered = '' !== $display;
			?>
			<div class="nestform-print__field">
				<p class="nestform-print__label"><?php echo esc_html( $label ); ?></p>
				<div class="nestform-print__value<?php echo $answered ? '' : ' nestform-print__value--empty'; ?>">
					<?php
					if ( $answered ) {
						echo esc_html( $display );
					} else {
						esc_html_e( 'Not answered', 'nestform' );
					}
					?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * @param array  $field Field definition.
	 * @param string $type  Field type.
	 */
	private static function render_layout_block( array $field, $type ) {
		if ( in_array( $type, array( 'image', 'divider', 'spacer' ), true ) ) {
			return;
		}

		$label   = (string) ( isset( $field['label'] ) ? $field['label'] : '' );
		$content = (string) ( isset( $field['content'] ) ? $field['content'] : ( isset( $field['default'] ) ? $field['default'] : '' ) );

		if ( 'heading' === $type ) {
			if ( '' === $label && '' === $content ) {
				return;
			}
			?>
			<div class="nestform-print__section">
				<h2 class="nestform-print__section-title"><?php echo esc_html( $label !== '' ? $label : $content ); ?></h2>
			</div>
			<?php
			return;
		}

		if ( 'paragraph' === $type || 'html' === $type ) {
			$text = $content !== '' ? $content : $label;
			if ( '' === $text ) {
				return;
			}
			?>
			<div class="nestform-print__note"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
			<?php
		}
	}
}
