<?php
/**
 * CSV / XLSX export for form entries.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Export {

	const ACTION      = 'thimbleform_export_csv';
	const ACTION_XLSX = 'thimbleform_export_xlsx';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION_XLSX, array( __CLASS__, 'handle_xlsx' ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function url( $form_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'           => self::ACTION,
					'thimbleform_form_id' => (int) $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . (int) $form_id
		);
	}

	/**
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function xlsx_url( $form_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'           => self::ACTION_XLSX,
					'thimbleform_form_id' => (int) $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_XLSX . '_' . (int) $form_id
		);
	}

	/**
	 * Export button with CSV / Excel dropdown.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $args    Optional: variant (primary|outline).
	 * @return string
	 */
	public static function dropdown_html( $form_id, array $args = array() ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'variant' => 'primary',
			)
		);
		$variant = sanitize_key( (string) $args['variant'] );
		if ( ! in_array( $variant, array( 'primary', 'outline' ), true ) ) {
			$variant = 'primary';
		}

		$btn_class = 'primary' === $variant ? 'thimbleform-btn--primary' : 'thimbleform-btn--outline';
		$icon      = function_exists( 'thimbleform_admin_icon_html' ) ? thimbleform_admin_icon_html( 'download' ) : '';

		ob_start();
		?>
		<div class="thimbleform-export-menu" data-thimbleform-export-menu>
			<button
				type="button"
				class="thimbleform-btn <?php echo esc_attr( $btn_class ); ?> thimbleform-export-menu__toggle"
				aria-expanded="false"
				aria-haspopup="true"
			>
				<?php echo wp_kses( $icon, thimbleform_svg_allowed_html() ); ?>
				<?php esc_html_e( 'Export', 'thimbleform' ); ?>
			</button>
			<div class="thimbleform-export-menu__panel" hidden>
				<a class="thimbleform-export-menu__item" href="<?php echo esc_url( self::url( $form_id ) ); ?>">
					<?php esc_html_e( 'CSV (.csv)', 'thimbleform' ); ?>
				</a>
				<a class="thimbleform-export-menu__item" href="<?php echo esc_url( self::xlsx_url( $form_id ) ); ?>">
					<?php esc_html_e( 'Excel (.xlsx)', 'thimbleform' ); ?>
				</a>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle() {
		$form_id = self::authorize_export();
		$labels  = self::collect_field_labels( $form_id );
		$entry_ids = self::query_entry_ids( $form_id );

		if ( class_exists( 'Thimbleform_Promotion' ) ) {
			Thimbleform_Promotion::record_export();
		}

		$filename = 'thimbleform-' . $form_id . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not open export stream.', 'thimbleform' ), 500 );
		}

		// UTF-8 BOM for Excel.
		echo "\xEF\xBB\xBF";

		$header = array_merge(
			array( 'entry_id', 'submitted_at', 'status', 'ip' ),
			array_keys( $labels )
		);
		fputcsv( $out, $header );

		foreach ( $entry_ids as $entry_id ) {
			fputcsv( $out, self::build_row( (int) $entry_id, $labels ) );
		}

		exit;
	}

	public static function handle_xlsx() {
		$form_id = self::authorize_export( self::ACTION_XLSX );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die(
				esc_html__( 'Excel export requires the PHP ZipArchive extension, which is not available on this server. Please export as CSV instead, or ask your host to enable ZipArchive.', 'thimbleform' ),
				esc_html__( 'Excel export unavailable', 'thimbleform' ),
				array( 'response' => 500 )
			);
		}

		$labels    = self::collect_field_labels( $form_id );
		$entry_ids = self::query_entry_ids( $form_id );

		if ( class_exists( 'Thimbleform_Promotion' ) ) {
			Thimbleform_Promotion::record_export();
		}

		$headers = array_merge(
			array( 'entry_id', 'submitted_at', 'status', 'ip' ),
			array_keys( $labels )
		);

		$rows = array();
		foreach ( $entry_ids as $entry_id ) {
			$rows[] = self::build_row( (int) $entry_id, $labels );
		}

		$filename = 'thimbleform-' . $form_id . '-' . gmdate( 'Ymd-His' );

		nocache_headers();
		Thimbleform_Xlsx_Export::download( $filename, $headers, $rows );
	}

	/**
	 * Validate form ID, capability, and nonce for an export action.
	 *
	 * @param string $action Export action slug.
	 * @return int Form ID.
	 */
	private static function authorize_export( $action = self::ACTION ) {
		$form_id = isset( $_GET['thimbleform_form_id'] ) ? (int) $_GET['thimbleform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $form_id <= 0 || Thimbleform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'thimbleform' ), 400 );
		}

		$can = current_user_can( 'edit_post', $form_id );
		if ( ! $can && class_exists( 'Thimbleform_Capabilities' ) ) {
			$can = ( Thimbleform_Capabilities::can_manage() || Thimbleform_Capabilities::can_view_entries() )
				&& Thimbleform_Submissions::user_can_manage_form_entries( $form_id );
		}
		if ( ! $can ) {
			wp_die( esc_html__( 'You do not have permission to export entries.', 'thimbleform' ), 403 );
		}

		check_admin_referer( $action . '_' . $form_id );

		return $form_id;
	}

	/**
	 * Exportable field keys => labels for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, string>
	 */
	private static function collect_field_labels( $form_id ) {
		$fields = Thimbleform_Form_Config::get_fields( $form_id );
		$keys   = array();
		$labels = array();
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || Thimbleform_Form_Config::is_layout_field( $field['type'] ) ) {
				continue;
			}
			if ( in_array( (string) $field['type'], array( 'hidden', 'password' ), true ) ) {
				continue;
			}
			$key = (string) $field['name'];
			if ( isset( $keys[ $key ] ) ) {
				continue;
			}
			$keys[ $key ]   = true;
			$labels[ $key ] = (string) ( $field['label'] !== '' ? $field['label'] : $key );
		}
		return $labels;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<int, int> Entry post IDs.
	 */
	private static function query_entry_ids( $form_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => Thimbleform_Submissions::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => Thimbleform_Submissions::META_FORM,
						'value' => $form_id,
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}

	/**
	 * One export row aligned with collect_field_labels() + meta columns.
	 *
	 * @param int                  $entry_id Entry ID.
	 * @param array<string, string> $labels  Field key => label map.
	 * @return array<int, string|int>
	 */
	private static function build_row( $entry_id, array $labels ) {
		$data = get_post_meta( $entry_id, Thimbleform_Submissions::META_DATA, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$status = Thimbleform_Submissions::get_status( $entry_id );
		$ip     = (string) get_post_meta( $entry_id, Thimbleform_Submissions::META_IP, true );
		$row    = array(
			$entry_id,
			get_post_time( 'c', true, $entry_id ),
			$status,
			$ip,
		);
		foreach ( array_keys( $labels ) as $key ) {
			$row[] = self::cell_value( isset( $data[ $key ] ) ? $data[ $key ] : '' );
		}
		return $row;
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function cell_value( $value ) {
		/**
		 * Filter CSV cell value (Pro advanced field types).
		 *
		 * @param string|null $custom Custom string or null for default.
		 * @param mixed       $value  Stored value.
		 */
		$custom = apply_filters( 'thimbleform_export_cell_value', null, $value );
		if ( is_string( $custom ) ) {
			return $custom;
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_array( $value ) && isset( $value['intent_id'], $value['amount'], $value['currency'] ) ) {
			$line = sprintf(
				'Paid %s %s',
				(string) $value['amount'],
				(string) $value['currency']
			);
			if ( ! empty( $value['intent_id'] ) ) {
				$line .= ' (' . (string) $value['intent_id'] . ')';
			}
			return $line;
		}
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			return (string) $value['url'];
		}
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) && ! empty( $item['url'] ) ) {
					$flat[] = (string) $item['url'];
				} elseif ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
			return implode( ', ', $flat );
		}
		return trim( (string) $value );
	}
}
