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

class Thimbleform_Entry_Print {

	const ACTION = 'thimbleform_print_entry';

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

		if ( $entry_id <= 0 || Thimbleform_Submissions::POST_TYPE !== get_post_type( $entry_id ) ) {
			wp_die( esc_html__( 'Entry not found.', 'thimbleform' ), 404 );
		}

		$form_id = (int) get_post_meta( $entry_id, Thimbleform_Submissions::META_FORM, true );
		if ( $form_id <= 0 || Thimbleform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Form not found.', 'thimbleform' ), 404 );
		}

		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to print this entry.', 'thimbleform' ), 403 );
		}

		$form  = get_post( $form_id );
		$entry = get_post( $entry_id );
		if ( ! $form || ! $entry ) {
			wp_die( esc_html__( 'Entry not found.', 'thimbleform' ), 404 );
		}

		$data = get_post_meta( $entry_id, Thimbleform_Submissions::META_DATA, true );
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
		$form_title = $form->post_title !== '' ? $form->post_title : __( '(no title)', 'thimbleform' );
		$title      = sprintf(
			/* translators: 1: form title, 2: entry ID. */
			__( '%1$s — entry #%2$d', 'thimbleform' ),
			$form_title,
			(int) $entry->ID
		);

		$status     = Thimbleform_Submissions::get_status( $entry->ID );
		$labels     = Thimbleform_Submissions::status_labels();
		$status_lbl = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$submitted  = class_exists( 'Thimbleform_Settings' )
			? Thimbleform_Settings::format_entry_datetime( $entry )
			: get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry );
		$ip         = (string) get_post_meta( $entry->ID, Thimbleform_Submissions::META_IP, true );
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
		'thimbleform-entry-print',
		THIMBLEFORM_URL . 'assets/css/entry-print.css',
		array(),
		THIMBLEFORM_VERSION
	);
	wp_enqueue_script(
		'thimbleform-entry-print',
		THIMBLEFORM_URL . 'assets/js/admin/entry-print.js',
		array(),
		THIMBLEFORM_VERSION,
		false
	);
	wp_print_styles( 'thimbleform-entry-print' );
	wp_print_scripts( 'thimbleform-entry-print' );
	?>
</head>
<body class="thimbleform-print-body">
	<div class="thimbleform-print-actions">
		<button type="button" class="thimbleform-print-actions__button" data-thimbleform-print><?php esc_html_e( 'Print', 'thimbleform' ); ?></button>
	</div>

	<article class="thimbleform-print">
		<header class="thimbleform-print__header">
			<p class="thimbleform-print__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<h1 class="thimbleform-print__title"><?php echo esc_html( $form_title ); ?></h1>
			<dl class="thimbleform-print__meta">
				<div class="thimbleform-print__meta-item">
					<dt class="thimbleform-print__meta-label"><?php esc_html_e( 'Entry', 'thimbleform' ); ?></dt>
					<dd class="thimbleform-print__meta-value">#<?php echo esc_html( (string) (int) $entry->ID ); ?></dd>
				</div>
				<div class="thimbleform-print__meta-item">
					<dt class="thimbleform-print__meta-label"><?php esc_html_e( 'Submitted', 'thimbleform' ); ?></dt>
					<dd class="thimbleform-print__meta-value"><?php echo esc_html( $submitted ); ?></dd>
				</div>
				<div class="thimbleform-print__meta-item">
					<dt class="thimbleform-print__meta-label"><?php esc_html_e( 'Status', 'thimbleform' ); ?></dt>
					<dd class="thimbleform-print__meta-value"><?php echo esc_html( $status_lbl ); ?></dd>
				</div>
				<?php if ( $ip !== '' ) : ?>
					<div class="thimbleform-print__meta-item">
						<dt class="thimbleform-print__meta-label"><?php esc_html_e( 'IP', 'thimbleform' ); ?></dt>
						<dd class="thimbleform-print__meta-value"><?php echo esc_html( $ip ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</header>

		<div class="thimbleform-print__body">
			<?php self::render_fields( $form->ID, $data ); ?>
		</div>

		<footer class="thimbleform-print__footer">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: site name, 2: date the page was printed. */
					__( '%1$s — printed %2$s', 'thimbleform' ),
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
		$fields = Thimbleform_Form_Config::get_fields( $form_id );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		foreach ( $fields as $field ) {
			$type = (string) ( isset( $field['type'] ) ? $field['type'] : '' );

			if ( Thimbleform_Form_Config::is_layout_field( $type ) ) {
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
			$display  = trim( Thimbleform_Export::cell_value( $value ) );
			$answered = '' !== $display;
			?>
			<div class="thimbleform-print__field">
				<p class="thimbleform-print__label"><?php echo esc_html( $label ); ?></p>
				<div class="thimbleform-print__value<?php echo $answered ? '' : ' thimbleform-print__value--empty'; ?>">
					<?php
					if ( $answered ) {
						echo esc_html( $display );
					} else {
						esc_html_e( 'Not answered', 'thimbleform' );
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
			<div class="thimbleform-print__section">
				<h2 class="thimbleform-print__section-title"><?php echo esc_html( $label !== '' ? $label : $content ); ?></h2>
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
			<div class="thimbleform-print__note"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
			<?php
		}
	}
}
