<?php
/**
 * WordPress privacy tools integration (export / erase personal data).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Privacy {

	const PER_PAGE = 20;

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['nestform'] = array(
			'exporter_friendly_name' => __( 'Thimbleform entries', 'nestform' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['nestform'] = array(
			'eraser_friendly_name' => __( 'Thimbleform entries', 'nestform' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * @param string $email Email address from the privacy request.
	 * @param int    $page  1-based page.
	 * @return array{data:array<int, array<string, mixed>>, done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		$page       = max( 1, (int) $page );
		$offset     = ( $page - 1 ) * self::PER_PAGE;
		$candidates = self::search_entries( $email, self::PER_PAGE, $offset );
		$data       = array();
		$forms      = array();

		foreach ( $candidates as $entry ) {
			$form_id = (int) get_post_meta( $entry->ID, Nestform_Submissions::META_FORM, true );
			if ( $form_id <= 0 ) {
				continue;
			}

			if ( ! isset( $forms[ $form_id ] ) ) {
				$forms[ $form_id ] = get_post( $form_id );
			}
			$form = $forms[ $form_id ];
			if ( ! $form || ! self::entry_belongs_to( $entry, $form, $email ) ) {
				continue;
			}

			$payload = get_post_meta( $entry->ID, Nestform_Submissions::META_DATA, true );
			if ( ! is_array( $payload ) ) {
				$payload = array();
			}

			$items = array(
				array(
					'name'  => __( 'Form', 'nestform' ),
					'value' => $form->post_title,
				),
				array(
					'name'  => __( 'Submitted', 'nestform' ),
					'value' => get_post_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), true, $entry ),
				),
			);

			foreach ( self::data_fields( $form_id ) as $field ) {
				$name  = (string) ( $field['name'] ?? '' );
				$value = $name !== '' && isset( $payload[ $name ] ) ? $payload[ $name ] : '';
				if ( '' === self::format_value( $value ) ) {
					continue;
				}
				$items[] = array(
					'name'  => (string) ( $field['label'] ?? $name ),
					'value' => self::format_value( $value ),
				);
			}

			$ip = (string) get_post_meta( $entry->ID, Nestform_Submissions::META_IP, true );
			if ( $ip !== '' ) {
				$items[] = array(
					'name'  => __( 'IP address', 'nestform' ),
					'value' => $ip,
				);
			}

			$data[] = array(
				'group_id'    => 'nestform-entries',
				'group_label' => __( 'Form entries', 'nestform' ),
				'item_id'     => 'nestform-entry-' . (int) $entry->ID,
				'data'        => $items,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $candidates ) < self::PER_PAGE,
		);
	}

	/**
	 * @param string $email Email address.
	 * @param int    $page  Unused page index (WordPress API).
	 * @return array{items_removed:bool, items_retained:bool, messages:array<int, string>, done:bool}
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );

		$candidates = self::search_entries( $email, self::PER_PAGE, 0 );
		$removed    = false;
		$forms      = array();

		foreach ( $candidates as $entry ) {
			$form_id = (int) get_post_meta( $entry->ID, Nestform_Submissions::META_FORM, true );
			if ( $form_id <= 0 ) {
				continue;
			}

			if ( ! isset( $forms[ $form_id ] ) ) {
				$forms[ $form_id ] = get_post( $form_id );
			}
			$form = $forms[ $form_id ];
			if ( ! $form || ! self::entry_belongs_to( $entry, $form, $email ) ) {
				continue;
			}

			if ( wp_delete_post( (int) $entry->ID, true ) ) {
				$removed = true;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $candidates ) < self::PER_PAGE,
		);
	}

	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content =
			'<p class="privacy-policy-tutorial">' .
			esc_html__( 'Suggested text for sites that collect form entries with Thimbleform. Edit it to match the forms you run.', 'nestform' ) .
			'</p><p>' .
			esc_html__( 'When you submit a form on this site, your answers are stored in this site\'s database. Depending on the form, this may include your name, email address, phone number, and anything else the form asks for.', 'nestform' ) .
			'</p><p>' .
			esc_html__( 'Stored entries are visible to site administrators. You can request a copy of entries linked to your email address, or ask for them to be deleted, using the contact details in this policy.', 'nestform' ) .
			'</p>';

		wp_add_privacy_policy_content( 'Thimbleform', $content );
	}

	/**
	 * @param string $email Email.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @return array<int, WP_Post>
	 */
	private static function search_entries( $email, $limit, $offset ) {
		$email = trim( (string) $email );
		if ( $email === '' || ! is_email( $email ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => Nestform_Submissions::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => max( 1, (int) $limit ),
				'offset'                 => max( 0, (int) $offset ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'meta_query'             => array(
					array(
						'key'     => Nestform_Submissions::META_DATA,
						'value'   => $email,
						'compare' => 'LIKE',
					),
				),
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}

	/**
	 * @param WP_Post  $entry Entry post.
	 * @param WP_Post  $form  Form post.
	 * @param string   $email Request email.
	 * @return bool
	 */
	private static function entry_belongs_to( $entry, $form, $email ) {
		$email = strtolower( trim( (string) $email ) );
		if ( $email === '' ) {
			return false;
		}

		$payload = get_post_meta( $entry->ID, Nestform_Submissions::META_DATA, true );
		if ( ! is_array( $payload ) ) {
			return false;
		}

		foreach ( self::data_fields( (int) $form->ID ) as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( $name === '' || ! isset( $payload[ $name ] ) ) {
				continue;
			}

			$value = $payload[ $name ];
			foreach ( is_array( $value ) ? $value : array( $value ) as $single ) {
				if ( is_scalar( $single ) && strtolower( trim( (string) $single ) ) === $email ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function data_fields( $form_id ) {
		if ( ! class_exists( 'Nestform_Form_Config' ) ) {
			return array();
		}

		$fields = array();
		foreach ( Nestform_Form_Config::get_fields( $form_id ) as $field ) {
			if ( empty( $field['name'] ) || Nestform_Form_Config::is_layout_field( $field['type'] ) ) {
				continue;
			}
			if ( in_array( (string) $field['type'], array( 'hidden', 'password' ), true ) ) {
				continue;
			}
			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private static function format_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
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
