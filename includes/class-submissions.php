<?php
/**
 * CPT: nestform_entry (submissions).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Submissions {

	const POST_TYPE = 'nestform_entry';
	const META_FORM = '_nestform_form_id';
	const META_DATA = '_nestform_payload';
	const META_IP   = '_nestform_ip';
	const META_STATUS = '_nestform_status';
	const META_STARRED = '_nestform_starred';
	const META_NOTES   = '_nestform_notes';
	const PAGE_SLUG = 'nestform-entries';
	const STATUS_NEW  = 'new';
	const STATUS_READ = 'read';
	const STATUS_SPAM = 'spam';

	const RETENTION_CRON = 'nestform_cleanup_entries';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'menu_under_forms' ), 20 );
		add_action( 'load-edit.php', array( __CLASS__, 'require_form_on_list' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_toolbar' ) );
		add_filter( 'disable_months_dropdown', array( __CLASS__, 'disable_months_dropdown' ), 10, 2 );
		add_filter( 'the_title', array( __CLASS__, 'list_entry_title' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_by_form' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_default_boxes' ), 100 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_entry_main_panel' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'post_class', array( __CLASS__, 'entry_post_class' ), 10, 3 );
		add_action( 'load-post.php', array( __CLASS__, 'on_load_entry_edit' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_entry_notes_form_footer' ) );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE, array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_admin_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notes_saved_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'list_page_head' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'entry_page_head' ), 1 );
		add_filter( 'views_edit-' . self::POST_TYPE, array( __CLASS__, 'list_views' ) );
		add_action( 'admin_post_nestform_set_entry_status', array( __CLASS__, 'handle_set_status' ) );
		add_action( 'admin_post_nestform_toggle_entry_star', array( __CLASS__, 'handle_toggle_star' ) );
		add_action( 'admin_post_nestform_save_entry_notes', array( __CLASS__, 'handle_save_notes' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( __CLASS__, 'bulk_updated_messages' ), 10, 2 );
		add_action( self::RETENTION_CRON, array( __CLASS__, 'cleanup_old_entries' ) );
		self::schedule_retention_cleanup();
	}

	/**
	 * Schedule daily purge of expired entries.
	 */
	public static function schedule_retention_cleanup() {
		if ( ! wp_next_scheduled( self::RETENTION_CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETENTION_CRON );
		}
	}

	/**
	 * @return int Days to keep entries (0 = forever).
	 */
	public static function retention_days() {
		if ( ! class_exists( 'Nestform_Settings' ) ) {
			return 0;
		}
		$s = Nestform_Settings::get();
		return max( 0, (int) ( $s['entry_retention_days'] ?? 0 ) );
	}

	/**
	 * Delete entries older than the retention window.
	 */
	public static function cleanup_old_entries() {
		$days = self::retention_days();
		if ( $days <= 0 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$batch  = 100;

		do {
			$query = new WP_Query(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => 'any',
					'posts_per_page'         => $batch,
					'fields'                 => 'ids',
					'orderby'                => 'date',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'date_query'             => array(
						array(
							'column' => 'post_date_gmt',
							'before' => $cutoff,
							'inclusive' => false,
						),
					),
				)
			);

			$ids = is_array( $query->posts ) ? $query->posts : array();
			foreach ( $ids as $entry_id ) {
				$entry_id = (int) $entry_id;
				/**
				 * Fires before an entry is removed by retention.
				 *
				 * @param int $entry_id Entry ID.
				 */
				do_action( 'nestform_entry_before_retention_delete', $entry_id );
				wp_delete_post( $entry_id, true );
			}
		} while ( count( $ids ) === $batch );
	}

	/**
	 * @return array<string, string>
	 */
	public static function status_labels() {
		return array(
			self::STATUS_NEW  => __( 'New', 'nestform' ),
			self::STATUS_READ => __( 'Read', 'nestform' ),
			self::STATUS_SPAM => __( 'Spam', 'nestform' ),
		);
	}

	/**
	 * Badge modifier for hub/dashboard chips (`nestform-badge--{mod}`).
	 *
	 * @param string $status new|read|spam.
	 * @return string new|read|danger
	 */
	public static function badge_modifier( $status ) {
		if ( self::STATUS_SPAM === $status ) {
			return 'danger';
		}
		if ( self::STATUS_READ === $status ) {
			return 'read';
		}
		return 'new';
	}

	/**
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function get_status( $entry_id ) {
		$status = (string) get_post_meta( (int) $entry_id, self::META_STATUS, true );
		$labels = self::status_labels();
		if ( ! isset( $labels[ $status ] ) ) {
			return self::STATUS_NEW;
		}
		return $status;
	}

	/**
	 * @param int    $entry_id Entry ID.
	 * @param string $status   Status key.
	 * @return bool
	 */
	public static function set_status( $entry_id, $status ) {
		$labels = self::status_labels();
		if ( ! isset( $labels[ $status ] ) ) {
			return false;
		}
		update_post_meta( (int) $entry_id, self::META_STATUS, $status );
		return true;
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Entries', 'nestform' ),
					'singular_name'      => __( 'Entry', 'nestform' ),
					'edit_item'          => __( 'View Entry', 'nestform' ),
					'search_items'       => __( 'Search Entries', 'nestform' ),
					'not_found'          => __( 'No entries found for this form.', 'nestform' ),
					'not_found_in_trash' => __( 'No entries found in Trash.', 'nestform' ),
					'menu_name'          => __( 'Entries', 'nestform' ),
					'item_updated'       => __( 'Entry updated.', 'nestform' ),
					'item_trashed'       => __( 'Entry moved to the Trash.', 'nestform' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'show_in_rest'        => false,
			)
		);
	}

	/**
	 * Replace WP "Post updated." notices with entry wording.
	 *
	 * @param array<string, array<int, string|false>> $messages Messages keyed by post type.
	 * @return array<string, array<int, string|false>>
	 */
	public static function updated_messages( $messages ) {
		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Entry updated.', 'nestform' ),
			2  => __( 'Custom field updated.', 'nestform' ),
			3  => __( 'Custom field deleted.', 'nestform' ),
			4  => __( 'Entry updated.', 'nestform' ),
			5  => false,
			6  => __( 'Entry published.', 'nestform' ),
			7  => __( 'Entry saved.', 'nestform' ),
			8  => __( 'Entry submitted.', 'nestform' ),
			9  => __( 'Entry scheduled.', 'nestform' ),
			10 => __( 'Entry draft saved.', 'nestform' ),
		);

		return $messages;
	}

	/**
	 * Replace WP "post" bulk notices with entry wording.
	 *
	 * @param array<string, array<string, string>> $bulk_messages Messages keyed by post type.
	 * @param array<string, int>                   $bulk_counts   Counts per action.
	 * @return array<string, array<string, string>>
	 */
	public static function bulk_updated_messages( $bulk_messages, $bulk_counts ) {
		$updated   = isset( $bulk_counts['updated'] ) ? (int) $bulk_counts['updated'] : 0;
		$locked    = isset( $bulk_counts['locked'] ) ? (int) $bulk_counts['locked'] : 0;
		$deleted   = isset( $bulk_counts['deleted'] ) ? (int) $bulk_counts['deleted'] : 0;
		$trashed   = isset( $bulk_counts['trashed'] ) ? (int) $bulk_counts['trashed'] : 0;
		$untrashed = isset( $bulk_counts['untrashed'] ) ? (int) $bulk_counts['untrashed'] : 0;

		/* translators: %s: Number of entries. */
		$updated_msg = _n( '%s entry updated.', '%s entries updated.', $updated, 'nestform' );
		/* translators: %s: Number of entries. */
		$locked_msg = _n( '%s entry not updated, somebody is editing it.', '%s entries not updated, somebody is editing them.', $locked, 'nestform' );
		/* translators: %s: Number of entries. */
		$deleted_msg = _n( '%s entry permanently deleted.', '%s entries permanently deleted.', $deleted, 'nestform' );
		/* translators: %s: Number of entries. */
		$trashed_msg = _n( '%s entry moved to the Trash.', '%s entries moved to the Trash.', $trashed, 'nestform' );
		/* translators: %s: Number of entries. */
		$untrashed_msg = _n( '%s entry restored from the Trash.', '%s entries restored from the Trash.', $untrashed, 'nestform' );

		$bulk_messages[ self::POST_TYPE ] = array(
			'updated'   => $updated_msg,
			'locked'    => ( 1 === $locked )
				? __( '1 entry not updated, somebody is editing it.', 'nestform' )
				: $locked_msg,
			'deleted'   => $deleted_msg,
			'trashed'   => $trashed_msg,
			'untrashed' => $untrashed_msg,
		);

		return $bulk_messages;
	}

	public static function menu_under_forms() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Entries', 'nestform' ),
			__( 'Entries', 'nestform' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_hub' )
		);
	}

	/**
	 * Entries list only after a form is chosen.
	 */
	public static function require_form_on_list() {
		if ( ! isset( $_GET['post_type'] ) || self::POST_TYPE !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$form_id = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $form_id > 0 ) {
			if ( ! self::user_can_manage_form_entries( $form_id ) ) {
				wp_die( esc_html__( 'You do not have permission to view entries for this form.', 'nestform' ) );
			}
			return;
		}
		wp_safe_redirect( self::hub_url() );
		exit;
	}

	/**
	 * Whether current admin request is an Entries screen.
	 *
	 * @param string $hook Admin page hook.
	 * @return bool
	 */
	public static function is_entries_screen( $hook = '' ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			return true;
		}
		if ( is_string( $hook ) && false !== strpos( $hook, self::PAGE_SLUG ) ) {
			return true;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		if ( ! empty( $screen->id ) && false !== strpos( (string) $screen->id, self::PAGE_SLUG ) ) {
			return true;
		}
		return self::POST_TYPE === $screen->post_type;
	}

	/**
	 * Whether current admin request is the nestform_entry list table.
	 *
	 * @return bool
	 */
	public static function is_entries_list_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return (bool) ( $screen && 'edit' === $screen->base && self::POST_TYPE === $screen->post_type );
	}

	/**
	 * Whether current admin request is a single nestform_entry edit screen.
	 *
	 * @return bool
	 */
	public static function is_entry_edit_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return (bool) ( $screen && 'post' === $screen->base && self::POST_TYPE === $screen->post_type );
	}

	/**
	 * Thimbleform page head above the WP entries list.
	 */
	public static function list_page_head() {
		if ( ! self::is_entries_list_screen() ) {
			return;
		}

		$form_id = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form    = $form_id > 0 ? get_post( $form_id ) : null;
		$title   = ( $form && $form->post_title !== '' )
			? $form->post_title
			: __( 'Entries', 'nestform' );

		$actions  = '<a class="nestform-btn nestform-btn--outline" href="' . esc_url( self::hub_url() ) . '">';
		$actions .= nestform_admin_icon_html( 'back' ) . ' ' . esc_html__( 'All entries', 'nestform' );
		$actions .= '</a>';
		if ( $form_id > 0 ) {
			$edit_form = get_edit_post_link( $form_id, 'raw' );
			if ( $edit_form ) {
				$actions .= ' <a class="nestform-btn nestform-btn--outline" href="' . esc_url( $edit_form ) . '">';
				$actions .= nestform_admin_icon_html( 'forms' ) . ' ' . esc_html__( 'Edit form', 'nestform' );
				$actions .= '</a>';
			}
		}
		if ( $form_id > 0 && class_exists( 'Nestform_Response_Summary' ) ) {
			$actions .= ' <a class="nestform-btn nestform-btn--outline" href="' . esc_url( Nestform_Response_Summary::url( $form_id ) ) . '">';
			$actions .= nestform_admin_icon_html( 'analytics' ) . ' ' . esc_html__( 'Summary', 'nestform' );
			$actions .= '</a>';
		}
		if ( $form_id > 0 && class_exists( 'Nestform_Export' ) ) {
			$actions .= ' ' . Nestform_Export::dropdown_html( $form_id );
		}

		nestform_render_page_head(
			array(
				'title'        => $title,
				'description'  => __( 'Submissions for this form.', 'nestform' ),
				'actions_html' => $actions,
				'icon'         => 'entries',
			)
		);
	}

	/**
	 * Thimbleform page head above a single entry view.
	 */
	public static function entry_page_head() {
		if ( ! self::is_entry_edit_screen() ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$form_id    = (int) get_post_meta( $post->ID, self::META_FORM, true );
		$form_post  = $form_id > 0 ? get_post( $form_id ) : null;
		$form_title = ( $form_post && $form_post->post_title !== '' ) ? $form_post->post_title : '';
		$status     = self::get_status( $post->ID );
		$labels     = self::status_labels();
		$status_lbl = $labels[ $status ] ?? $status;

		$payload = get_post_meta( $post->ID, self::META_DATA, true );
		$payload = is_array( $payload ) ? $payload : array();
		$title   = self::payload_name( $payload, (string) $post->post_title, $form_title, $form_id );
		if ( $title === '' ) {
			$title = sprintf(
				/* translators: %d: entry ID */
				__( 'Entry #%d', 'nestform' ),
				(int) $post->ID
			);
		}

		$desc_parts = array();
		if ( $form_title !== '' ) {
			$desc_parts[] = $form_title;
		}
		$desc_parts[] = sprintf(
			/* translators: %s: status label */
			__( 'Status: %s', 'nestform' ),
			$status_lbl
		);
		$desc_parts[] = sprintf(
			/* translators: %s: datetime */
			__( 'Submitted %s', 'nestform' ),
			class_exists( 'Nestform_Settings' ) ? Nestform_Settings::format_entry_datetime( $post ) : get_the_date( 'Y-m-d H:i', $post )
		);

		$actions = '';
		if ( $form_id > 0 ) {
			$actions .= '<a class="nestform-btn nestform-btn--outline" href="' . esc_url( self::list_url( $form_id ) ) . '">';
			$actions .= nestform_admin_icon_html( 'back' ) . ' ' . esc_html__( 'Form inbox', 'nestform' );
			$actions .= '</a>';
		} else {
			$actions .= '<a class="nestform-btn nestform-btn--outline" href="' . esc_url( self::hub_url() ) . '">';
			$actions .= nestform_admin_icon_html( 'back' ) . ' ' . esc_html__( 'All entries', 'nestform' );
			$actions .= '</a>';
		}

		if ( class_exists( 'Nestform_Entry_Print' ) ) {
			$actions .= ' <a class="nestform-btn nestform-btn--outline" href="' . esc_url( Nestform_Entry_Print::url( $post->ID ) ) . '" target="_blank" rel="noopener noreferrer">';
			$actions .= esc_html__( 'Print', 'nestform' );
			$actions .= '</a>';
		}

		$trash = get_delete_post_link( $post->ID, '', false );
		if ( $trash ) {
			$actions .= ' <a class="nestform-btn nestform-btn--danger-text" href="' . esc_url( $trash ) . '">';
			$actions .= esc_html__( 'Move to Trash', 'nestform' );
			$actions .= '</a>';
		}

		nestform_render_page_head(
			array(
				'title'        => $title,
				'description'  => implode( ' · ', $desc_parts ),
				'actions_html' => $actions,
				'icon'         => 'entries',
			)
		);
	}

	/**
	 * Replace WP All/Mine/Published views with Thimbleform status filters.
	 *
	 * @param array<string, string> $views Views.
	 * @return array<string, string>
	 */
	public static function list_views( $views ) {
		$form_id = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $form_id <= 0 ) {
			return array();
		}

		$current = isset( $_GET['nestform_status'] ) ? sanitize_key( wp_unslash( $_GET['nestform_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $current !== '' && ! isset( self::status_labels()[ $current ] ) ) {
			$current = '';
		}
		$starred_only = isset( $_GET['starred'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['starred'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$out   = array();
		$all_n = self::count_for_form( $form_id );
		$out['all'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( self::list_url( $form_id ) ),
			( '' === $current && ! $starred_only ) ? ' class="current" aria-current="page"' : '',
			esc_html__( 'All', 'nestform' ),
			esc_html( number_format_i18n( $all_n ) )
		);

		foreach ( self::status_labels() as $key => $label ) {
			$count = self::count_entries(
				array(
					'form_id' => $form_id,
					'status'  => $key,
				)
			);
			$out[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( self::list_url( $form_id, $key ) ),
				( $current === $key && ! $starred_only ) ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		$starred_n = self::count_entries(
			array(
				'form_id' => $form_id,
				'starred' => true,
			)
		);
		$out['starred'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( self::list_url( $form_id, '', array( 'starred' => '1' ) ) ),
			$starred_only ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Starred', 'nestform' ),
			esc_html( number_format_i18n( $starred_n ) )
		);

		return $out;
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( ! self::is_entries_screen() ) {
			return $classes;
		}
		$classes .= ' nestform-admin-screen nestform-entries-screen';
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( ! self::is_entries_screen( $hook ) ) {
			return;
		}
		$ver = (string) filemtime( nestform_admin_css_path() );
		wp_enqueue_style(
			'nestform-admin',
			nestform_admin_css_url(),
			nestform_admin_style_deps(),
			$ver ? $ver : NESTFORM_VERSION
		);
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $status  Optional status filter (new|read|spam).
	 * @return string
	 */
	public static function list_url( $form_id, $status = '', $extra = array() ) {
		$args = array(
			'post_type'          => self::POST_TYPE,
			'nestform_form_id' => (int) $form_id,
		);
		$status = sanitize_key( (string) $status );
		if ( $status !== '' && isset( self::status_labels()[ $status ] ) ) {
			$args['nestform_status'] = $status;
		}
		if ( is_array( $extra ) && array() !== $extra ) {
			$args = array_merge( $args, $extra );
		}
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public static function is_starred( $entry_id ) {
		return '1' === (string) get_post_meta( (int) $entry_id, self::META_STARRED, true );
	}

	/**
	 * @param int  $entry_id Entry ID.
	 * @param bool $starred  Starred.
	 * @return bool
	 */
	public static function set_starred( $entry_id, $starred ) {
		$entry_id = (int) $entry_id;
		if ( $entry_id <= 0 || self::POST_TYPE !== get_post_type( $entry_id ) ) {
			return false;
		}
		update_post_meta( $entry_id, self::META_STARRED, $starred ? '1' : '0' );
		return true;
	}

	/**
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function get_notes( $entry_id ) {
		return (string) get_post_meta( (int) $entry_id, self::META_NOTES, true );
	}

	/**
	 * @param int    $entry_id Entry ID.
	 * @param string $notes    Notes.
	 * @return bool
	 */
	public static function set_notes( $entry_id, $notes ) {
		$entry_id = (int) $entry_id;
		if ( $entry_id <= 0 || self::POST_TYPE !== get_post_type( $entry_id ) ) {
			return false;
		}
		update_post_meta( $entry_id, self::META_NOTES, sanitize_textarea_field( (string) $notes ) );
		return true;
	}

	/**
	 * Admin-post URL to toggle entry star.
	 *
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function star_action_url( $entry_id, array $args = array() ) {
		$entry_id = (int) $entry_id;
		$form_id  = isset( $args['form_id'] ) ? (int) $args['form_id'] : (int) get_post_meta( $entry_id, self::META_FORM, true );
		$params   = array(
			'action'           => 'nestform_toggle_entry_star',
			'entry_id'         => $entry_id,
			'nestform_form_id' => $form_id,
		);
		if ( ! empty( $args['redirect_to'] ) ) {
			$params['redirect_to'] = sanitize_key( (string) $args['redirect_to'] );
		} else {
			$params['redirect_to'] = 'list';
		}
		return wp_nonce_url(
			add_query_arg( $params, admin_url( 'admin-post.php' ) ),
			'nestform_toggle_entry_star_' . $entry_id
		);
	}

	/**
	 * Build admin-post URL to change entry status.
	 *
	 * @param int                  $entry_id Entry ID.
	 * @param string               $status   Target status.
	 * @param array<string, mixed> $args     Optional form_id, redirect_to.
	 * @return string
	 */
	public static function status_action_url( $entry_id, $status, array $args = array() ) {
		$entry_id = (int) $entry_id;
		$form_id  = isset( $args['form_id'] ) ? (int) $args['form_id'] : (int) get_post_meta( $entry_id, self::META_FORM, true );
		$params   = array(
			'action'             => 'nestform_set_entry_status',
			'entry_id'           => $entry_id,
			'status'             => sanitize_key( (string) $status ),
			'nestform_form_id' => $form_id,
		);
		if ( ! empty( $args['redirect_to'] ) ) {
			$params['redirect_to'] = sanitize_key( (string) $args['redirect_to'] );
		}
		return wp_nonce_url(
			add_query_arg( $params, admin_url( 'admin-post.php' ) ),
			'nestform_set_entry_status_' . $entry_id
		);
	}

	/**
	 * @param array<string, mixed> $args Optional nestform_status.
	 * @return string
	 */
	public static function hub_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => Nestform_Post_Type::POST_TYPE,
					'page'      => self::PAGE_SLUG,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Tie entry capabilities to the parent form (not entry author).
	 *
	 * @param array<int, string> $caps    Required caps.
	 * @param string             $cap     Capability.
	 * @param int                $user_id User ID.
	 * @param array<int, mixed>  $args    Extra args (post ID).
	 * @return array<int, string>
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) ) {
			return $caps;
		}
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $post_id <= 0 ) {
			return $caps;
		}
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return $caps;
		}
		$form_id = (int) get_post_meta( $post_id, self::META_FORM, true );
		if ( $form_id <= 0 ) {
			return array( 'do_not_allow' );
		}
		$allowed = user_can( $user_id, 'edit_post', $form_id );
		/**
		 * Whether a user may manage entries for a form (addons may tighten HR ownership).
		 *
		 * @param bool $allowed Whether allowed.
		 * @param int  $form_id Form ID.
		 * @param int  $user_id User ID.
		 */
		$allowed = (bool) apply_filters( 'nestform_user_can_manage_form_entries', $allowed, $form_id, $user_id );
		if ( ! $allowed ) {
			return array( 'do_not_allow' );
		}
		return array();
	}

	/**
	 * Whether the current user may manage entries for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function user_can_manage_form_entries( $form_id ) {
		$form_id = (int) $form_id;
		$allowed = $form_id > 0 && current_user_can( 'edit_post', $form_id );
		/**
		 * Whether the current user may manage entries for a form.
		 *
		 * @param bool $allowed Whether allowed.
		 * @param int  $form_id Form ID.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'nestform_user_can_manage_form_entries', $allowed, $form_id, get_current_user_id() );
	}

	/**
	 * Form IDs the current user may inspect entries for.
	 * null = unrestricted (edit_others_posts), array = explicit allow-list.
	 *
	 * @return array<int, int>|null
	 */
	public static function accessible_form_ids() {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		if ( current_user_can( 'edit_others_posts' ) ) {
			/**
			 * Form IDs the current user may inspect entries for.
			 * null = unrestricted; array = allow-list.
			 *
			 * @param array<int, int>|null $ids     Form IDs or null.
			 * @param int                  $user_id User ID.
			 */
			return apply_filters( 'nestform_accessible_form_ids', null, get_current_user_id() );
		}
		$forms = get_posts(
			array(
				'post_type'              => Nestform_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! is_array( $forms ) ) {
			return apply_filters( 'nestform_accessible_form_ids', array(), get_current_user_id() );
		}
		$ids = array();
		foreach ( $forms as $fid ) {
			$fid = (int) $fid;
			if ( $fid > 0 && current_user_can( 'edit_post', $fid ) ) {
				$ids[] = $fid;
			}
		}
		return apply_filters( 'nestform_accessible_form_ids', $ids, get_current_user_id() );
	}

	/**
	 * @return array<int, WP_Post>
	 */
	private static function get_forms() {
		$forms = get_posts(
			array(
				'post_type'      => Nestform_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		if ( ! is_array( $forms ) ) {
			return array();
		}
		$allowed = self::accessible_form_ids();
		if ( null === $allowed ) {
			return $forms;
		}
		if ( array() === $allowed ) {
			return array();
		}
		$allow = array_fill_keys( $allowed, true );
		return array_values(
			array_filter(
				$forms,
				static function ( $form ) use ( $allow ) {
					return isset( $allow[ (int) $form->ID ] );
				}
			)
		);
	}

	/**
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public static function count_for_form( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return 0;
		}
		return self::count_entries(
			array(
				'form_id' => $form_id,
			)
		);
	}

	/**
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public static function count_new_for_form( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return 0;
		}
		return self::count_entries(
			array(
				'form_id' => $form_id,
				'status'  => self::STATUS_NEW,
			)
		);
	}

	/**
	 * Latest entry timestamps (GMT unix) keyed by form ID.
	 *
	 * @param array<int, int> $form_ids Form IDs.
	 * @return array<int, int>
	 */
	public static function latest_entry_times( array $form_ids ) {
		$form_ids = array_values(
			array_filter(
				array_map( 'intval', $form_ids ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		);
		if ( array() === $form_ids ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		$query        = "SELECT CAST(pm.meta_value AS UNSIGNED) AS form_id, MAX(p.post_date_gmt) AS last_gmt
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
			GROUP BY form_id";
		$args = array_merge( array( self::META_FORM, self::POST_TYPE ), $form_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- dynamic IN list.
		$sql  = $wpdb->prepare( $query, ...$args );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$fid = isset( $row->form_id ) ? (int) $row->form_id : 0;
				$gmt = isset( $row->last_gmt ) ? (string) $row->last_gmt : '';
				if ( $fid > 0 && $gmt !== '' && '0000-00-00 00:00:00' !== $gmt ) {
					$out[ $fid ] = (int) strtotime( $gmt . ' UTC' );
				}
			}
		}
		return $out;
	}

	/**
	 * Optional form_ids / exclude_form_ids (Entries hub kind filters).
	 * Skipped when a single form_id is already set.
	 *
	 * @param array<int, mixed>    $meta_query Meta query clauses.
	 * @param array<string, mixed> $args       Query args.
	 * @return array<int, mixed>|false Updated meta query, or false for empty result.
	 */
	private static function apply_extra_form_scope( array $meta_query, array $args ) {
		if ( isset( $args['form_id'] ) && (int) $args['form_id'] > 0 ) {
			return $meta_query;
		}

		if ( array_key_exists( 'form_ids', $args ) ) {
			$ids = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', (array) $args['form_ids'] )
					)
				)
			);
			if ( array() === $ids ) {
				return false;
			}
			$meta_query[] = array(
				'key'     => self::META_FORM,
				'value'   => $ids,
				'compare' => 'IN',
			);
		}

		if ( ! empty( $args['exclude_form_ids'] ) ) {
			$exclude = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', (array) $args['exclude_form_ids'] )
					)
				)
			);
			if ( array() !== $exclude ) {
				$meta_query[] = array(
					'key'     => self::META_FORM,
					'value'   => $exclude,
					'compare' => 'NOT IN',
				);
			}
		}

		return $meta_query;
	}

	/**
	 * Count entries with optional form + date range + status.
	 *
	 * @param array{form_id?:int,form_ids?:array<int,int>,exclude_form_ids?:array<int,int>,after?:string,before?:string,status?:string,exclude_spam?:bool,starred?:bool,skip_access_check?:bool} $args Args.
	 * @return int
	 */
	public static function count_entries( array $args = array() ) {
		$query_args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$meta_query = array( 'relation' => 'AND' );
		$form_id    = isset( $args['form_id'] ) ? (int) $args['form_id'] : 0;
		if ( $form_id > 0 ) {
			if ( is_admin() && empty( $args['skip_access_check'] ) && ! self::user_can_manage_form_entries( $form_id ) ) {
				return 0;
			}
			$meta_query[] = array(
				'key'   => self::META_FORM,
				'value' => $form_id,
			);
		} elseif ( is_admin() && empty( $args['skip_access_check'] ) ) {
			$accessible = self::accessible_form_ids();
			if ( is_array( $accessible ) ) {
				if ( array() === $accessible ) {
					return 0;
				}
				$meta_query[] = array(
					'key'     => self::META_FORM,
					'value'   => $accessible,
					'compare' => 'IN',
				);
			}
		}

		$meta_query = self::apply_extra_form_scope( $meta_query, $args );
		if ( false === $meta_query ) {
			return 0;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( $status !== '' && isset( self::status_labels()[ $status ] ) ) {
			if ( self::STATUS_NEW === $status ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'   => self::META_STATUS,
						'value' => self::STATUS_NEW,
					),
					array(
						'key'     => self::META_STATUS,
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$meta_query[] = array(
					'key'   => self::META_STATUS,
					'value' => $status,
				);
			}
		} elseif ( ! empty( $args['exclude_spam'] ) ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_STATUS,
					'value'   => self::STATUS_SPAM,
					'compare' => '!=',
				),
				array(
					'key'     => self::META_STATUS,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		if ( ! empty( $args['starred'] ) ) {
			$meta_query[] = array(
				'key'   => self::META_STARRED,
				'value' => '1',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$date_query = array();
		if ( ! empty( $args['after'] ) ) {
			$date_query['after'] = (string) $args['after'];
		}
		if ( ! empty( $args['before'] ) ) {
			$date_query['before'] = (string) $args['before'];
		}
		if ( array() !== $date_query ) {
			$date_query['inclusive']  = true;
			$query_args['date_query'] = array( $date_query );
		}

		$q = new WP_Query( $query_args );
		return (int) $q->found_posts;
	}

	/**
	 * Daily entry counts for a date range (inclusive calendar days).
	 *
	 * @param string               $after   Local datetime Y-m-d H:i:s.
	 * @param string               $before  Local datetime Y-m-d H:i:s.
	 * @param int                  $form_id Optional form filter.
	 * @param array<string, mixed> $args    Optional: exclude_spam, skip_access_check.
	 * @return array<string, int> Map Y-m-d => count.
	 */
	public static function daily_counts( $after, $before, $form_id = 0, array $args = array() ) {
		global $wpdb;

		$after_local  = self::normalize_local_datetime( $after, false );
		$before_local = self::normalize_local_datetime( $before, true );
		if ( $after_local === '' || $before_local === '' ) {
			return array();
		}

		$fill_zeros = static function () use ( $after_local, $before_local ) {
			$start  = substr( $after_local, 0, 10 );
			$end    = substr( $before_local, 0, 10 );
			$filled = array();
			try {
				$cursor = new DateTimeImmutable( $start . ' 00:00:00' );
				$last   = new DateTimeImmutable( $end . ' 00:00:00' );
			} catch ( Exception $e ) {
				return array();
			}
			while ( $cursor <= $last ) {
				$filled[ $cursor->format( 'Y-m-d' ) ] = 0;
				$cursor = $cursor->modify( '+1 day' );
			}
			return $filled;
		};

		$form_id      = (int) $form_id;
		$exclude_spam = ! empty( $args['exclude_spam'] );
		$skip_access  = ! empty( $args['skip_access_check'] );

		$join_sql            = '';
		$where_extra         = '';
		$join_params         = array();
		$where_extra_params  = array();

		if ( $form_id > 0 ) {
			if ( is_admin() && ! $skip_access && ! self::user_can_manage_form_entries( $form_id ) ) {
				return $fill_zeros();
			}
			$join_sql     .= " INNER JOIN {$wpdb->postmeta} mf ON mf.post_id = p.ID AND mf.meta_key = %s AND mf.meta_value = %s ";
			$join_params[] = self::META_FORM;
			$join_params[] = (string) $form_id;
		} elseif ( is_admin() && ! $skip_access ) {
			$accessible = self::accessible_form_ids();
			if ( is_array( $accessible ) ) {
				if ( array() === $accessible ) {
					return $fill_zeros();
				}
				$placeholders = implode( ',', array_fill( 0, count( $accessible ), '%s' ) );
				$join_sql    .= " INNER JOIN {$wpdb->postmeta} mf ON mf.post_id = p.ID AND mf.meta_key = %s ";
				$join_params[] = self::META_FORM;
				$where_extra .= " AND mf.meta_value IN ({$placeholders}) ";
				foreach ( $accessible as $fid ) {
					$where_extra_params[] = (string) (int) $fid;
				}
			}
		}

		if ( $exclude_spam ) {
			$join_sql     .= " LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = %s ";
			$join_params[] = self::META_STATUS;
			$where_extra  .= ' AND (st.meta_id IS NULL OR st.meta_value <> %s) ';
			$where_extra_params[] = self::STATUS_SPAM;
		}

		$sql = "SELECT DATE(p.post_date) AS day_key, COUNT(DISTINCT p.ID) AS total
			FROM {$wpdb->posts} p
			{$join_sql}
			WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND p.post_date >= %s
				AND p.post_date <= %s
				{$where_extra}
			GROUP BY DATE(p.post_date)
			ORDER BY day_key ASC";

		$prepare_args = array_merge(
			$join_params,
			array( self::POST_TYPE, $after_local, $before_local ),
			$where_extra_params
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare_args ), ARRAY_A );

		$map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$key = isset( $row['day_key'] ) ? (string) $row['day_key'] : '';
				if ( $key !== '' ) {
					$map[ $key ] = (int) $row['total'];
				}
			}
		}

		$filled = $fill_zeros();
		foreach ( $filled as $key => $_ ) {
			$filled[ $key ] = isset( $map[ $key ] ) ? $map[ $key ] : 0;
		}
		return $filled;
	}

	/**
	 * Top forms by entry volume in range.
	 *
	 * @param string $after  Local datetime.
	 * @param string $before Local datetime.
	 * @param int    $limit  Max rows.
	 * @return array<int, array{form_id:int,count:int}>
	 */
	public static function top_forms( $after, $before, $limit = 8 ) {
		global $wpdb;

		$after_local  = self::normalize_local_datetime( $after, false );
		$before_local = self::normalize_local_datetime( $before, true );
		if ( $after_local === '' || $before_local === '' ) {
			return array();
		}
		$limit = max( 1, min( 50, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value AS form_id, COUNT(p.ID) AS total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND p.post_date >= %s
					AND p.post_date <= %s
				GROUP BY m.meta_value
				ORDER BY total DESC
				LIMIT %d",
				self::META_FORM,
				self::POST_TYPE,
				$after_local,
				$before_local,
				$limit
			),
			ARRAY_A
		);

		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$fid = (int) ( $row['form_id'] ?? 0 );
			if ( $fid <= 0 ) {
				continue;
			}
			$out[] = array(
				'form_id' => $fid,
				'count'   => (int) ( $row['total'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param string $value Raw datetime.
	 * @param bool   $end_of_day Force 23:59:59 when only a date is given.
	 * @return string
	 */
	private static function normalize_local_datetime( $value, $end_of_day ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value . ( $end_of_day ? ' 23:59:59' : ' 00:00:00' );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}
		$ts = strtotime( $value );
		if ( ! $ts ) {
			return '';
		}
		return wp_date( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Recent entries.
	 *
	 * @param int $limit   Limit.
	 * @param int $form_id Optional form.
	 * @return array<int, WP_Post>
	 */
	/**
	 * Recent entries for activity feed.
	 *
	 * @param int   $limit   Max posts.
	 * @param int   $form_id Optional form filter.
	 * @param array $args    Optional { after?: string, before?: string } Y-m-d H:i:s.
	 * @return array<int, WP_Post>
	 */
	public static function recent_entries( $limit = 8, $form_id = 0, $args = array() ) {
		$query = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => max( 1, min( 50, (int) $limit ) ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);
		$form_id = (int) $form_id;
		if ( $form_id > 0 ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => self::META_FORM,
					'value' => $form_id,
				),
			);
		}

		$after  = isset( $args['after'] ) ? (string) $args['after'] : '';
		$before = isset( $args['before'] ) ? (string) $args['before'] : '';
		if ( $after !== '' || $before !== '' ) {
			$date_query = array( 'inclusive' => true );
			if ( $after !== '' ) {
				$date_query['after'] = $after;
			}
			if ( $before !== '' ) {
				$date_query['before'] = $before;
			}
			$query['date_query'] = array( $date_query );
		}

		$posts = get_posts( $query );
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Query entries with optional status / form / pagination.
	 *
	 * @param array{form_id?:int,form_ids?:array<int,int>,exclude_form_ids?:array<int,int>,status?:string,limit?:int,paged?:int} $args Args.
	 * @return array{posts: array<int, WP_Post>, total: int, pages: int, paged: int, per_page: int}
	 */
	public static function query_entries( array $args = array() ) {
		$per_page = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;

		$query_args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $paged,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'no_found_rows'          => false,
		);

		$meta_query = array( 'relation' => 'AND' );
		$form_id    = isset( $args['form_id'] ) ? (int) $args['form_id'] : 0;
		if ( $form_id > 0 ) {
			if ( is_admin() && empty( $args['skip_access_check'] ) && ! self::user_can_manage_form_entries( $form_id ) ) {
				return array(
					'posts'    => array(),
					'total'    => 0,
					'pages'    => 1,
					'paged'    => 1,
					'per_page' => $per_page,
				);
			}
			$meta_query[] = array(
				'key'   => self::META_FORM,
				'value' => $form_id,
			);
		} elseif ( is_admin() && empty( $args['skip_access_check'] ) ) {
			$accessible = self::accessible_form_ids();
			if ( is_array( $accessible ) ) {
				if ( array() === $accessible ) {
					return array(
						'posts'    => array(),
						'total'    => 0,
						'pages'    => 1,
						'paged'    => 1,
						'per_page' => $per_page,
					);
				}
				$meta_query[] = array(
					'key'     => self::META_FORM,
					'value'   => $accessible,
					'compare' => 'IN',
				);
			}
		}

		$meta_query = self::apply_extra_form_scope( $meta_query, $args );
		if ( false === $meta_query ) {
			return array(
				'posts'    => array(),
				'total'    => 0,
				'pages'    => 1,
				'paged'    => 1,
				'per_page' => $per_page,
			);
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( $status !== '' && isset( self::status_labels()[ $status ] ) ) {
			if ( self::STATUS_NEW === $status ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'   => self::META_STATUS,
						'value' => self::STATUS_NEW,
					),
					array(
						'key'     => self::META_STATUS,
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$meta_query[] = array(
					'key'   => self::META_STATUS,
					'value' => $status,
				);
			}
		}

		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$q = new WP_Query( $query_args );
		$pages = max( 1, (int) $q->max_num_pages );

		return array(
			'posts'    => is_array( $q->posts ) ? $q->posts : array(),
			'total'    => (int) $q->found_posts,
			'pages'    => $pages,
			'paged'    => min( $paged, $pages ),
			'per_page' => $per_page,
		);
	}

	/**
	 * Hub pagination markup.
	 *
	 * @param int                  $total    Total entries in filter.
	 * @param int                  $paged    Current page.
	 * @param int                  $pages    Total pages.
	 * @param int                  $per_page Per page.
	 * @param array<string, mixed> $url_args Extra hub URL args (status).
	 */
	private static function render_hub_pagination( $total, $paged, $pages, $per_page, array $url_args = array() ) {
		$total    = (int) $total;
		$paged    = max( 1, (int) $paged );
		$pages    = max( 1, (int) $pages );
		$per_page = max( 1, (int) $per_page );

		if ( $total <= 0 ) {
			return;
		}

		$from = ( ( $paged - 1 ) * $per_page ) + 1;
		$to   = min( $total, $paged * $per_page );
		?>
		<nav class="nestform-entries__pager" aria-label="<?php esc_attr_e( 'Entries pagination', 'nestform' ); ?>">
			<p class="nestform-entries__pager-meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: first item, 2: last item, 3: total */
						__( 'Showing %1$s–%2$s of %3$s', 'nestform' ),
						number_format_i18n( $from ),
						number_format_i18n( $to ),
						number_format_i18n( $total )
					)
				);
				?>
			</p>
			<?php if ( $pages > 1 ) : ?>
				<?php
				$base = remove_query_arg( 'paged', self::hub_url( $url_args ) );
				$base = add_query_arg( 'paged', '%#%', $base );
				$links = paginate_links(
					array(
						'base'      => esc_url_raw( $base ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'type'      => 'array',
						'prev_text' => '&lsaquo;',
						'next_text' => '&rsaquo;',
						'end_size'  => 1,
						'mid_size'  => 2,
					)
				);
				?>
				<?php if ( is_array( $links ) && array() !== $links ) : ?>
					<div class="nestform-entries__pager-links">
						<?php foreach ( $links as $link ) : ?>
							<?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links HTML ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * @param array<string, mixed> $payload  Payload.
	 * @param string               $fallback Title fallback.
	 * @param string               $exclude  Optional label to ignore from title fallback (e.g. form title).
	 * @param int                  $form_id  Optional form ID to resolve name fields by label.
	 * @return string
	 */
	public static function payload_name( array $payload, $fallback = '', $exclude = '', $form_id = 0 ) {
		$person = self::payload_person_name( $payload, (int) $form_id );
		if ( $person !== '' ) {
			return $person;
		}
		$email = self::payload_email( $payload );
		if ( $email !== '' ) {
			return $email;
		}
		$payment = self::payload_payment_summary( $payload, (int) $form_id );
		if ( $payment !== '' ) {
			return $payment;
		}
		$title = trim( (string) $fallback );
		if ( $title !== '' ) {
			$parts = preg_split( '/\s+[—–-]\s+/u', $title );
			if ( is_array( $parts ) && isset( $parts[0] ) && trim( $parts[0] ) !== '' ) {
				$first = trim( $parts[0] );
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $first ) ) {
					$exclude = trim( (string) $exclude );
					if ( $exclude !== '' && 0 === strcasecmp( $first, $exclude ) ) {
						return __( 'Unknown', 'nestform' );
					}
					return $first;
				}
			}
		}
		return __( 'Unknown', 'nestform' );
	}

	/**
	 * Person name from submission payload (Name / Full name / first+last / label match).
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param int                  $form_id Optional form ID for label-based lookup.
	 * @return string
	 */
	public static function payload_person_name( array $payload, $form_id = 0 ) {
		foreach ( self::person_name_keys() as $key ) {
			$text = self::payload_scalar_text( $payload, $key );
			if ( $text !== '' ) {
				return $text;
			}
		}

		$composed = self::payload_composed_name( $payload );
		if ( $composed !== '' ) {
			return $composed;
		}

		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) || is_array( $value ) ) {
				continue;
			}
			if ( ! self::is_person_name_key( $key ) ) {
				continue;
			}
			$text = self::payload_scalar_text( $payload, $key );
			if ( $text !== '' ) {
				return $text;
			}
		}

		$form_id = (int) $form_id;
		if ( $form_id > 0 && class_exists( 'Nestform_Form_Config' ) ) {
			$fields = Nestform_Form_Config::get_fields( $form_id );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
					$label = isset( $field['label'] ) ? strtolower( trim( wp_strip_all_tags( (string) $field['label'] ) ) ) : '';
					$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
					if ( $name === '' || Nestform_Form_Config::is_layout_field( $type ) ) {
						continue;
					}
					if ( ! self::is_person_name_label( $label ) && ! self::is_person_name_key( $name ) ) {
						continue;
					}
					$text = self::payload_scalar_text( $payload, $name );
					if ( $text !== '' ) {
						return $text;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Short payment summary from submission payload (amount + currency).
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param int                  $form_id Optional form ID to prefer payment-typed fields.
	 * @return string
	 */
	public static function payload_payment_summary( array $payload, $form_id = 0 ) {
		$form_id = (int) $form_id;
		if ( $form_id > 0 && class_exists( 'Nestform_Form_Config' ) ) {
			$fields = Nestform_Form_Config::get_fields( $form_id );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) || 'payment' !== (string) ( $field['type'] ?? '' ) ) {
						continue;
					}
					$name = isset( $field['name'] ) ? (string) $field['name'] : '';
					if ( $name === '' || ! isset( $payload[ $name ] ) ) {
						continue;
					}
					$summary = self::format_payment_contact( $payload[ $name ] );
					if ( $summary !== '' ) {
						return $summary;
					}
				}
			}
		}

		foreach ( $payload as $value ) {
			$summary = self::format_payment_contact( $value );
			if ( $summary !== '' ) {
				return $summary;
			}
		}

		return '';
	}

	/**
	 * @param mixed $value Stored payment value.
	 * @return string
	 */
	private static function format_payment_contact( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['amount'], $value['currency'] ) ) {
			return '';
		}
		$amount   = trim( (string) $value['amount'] );
		$currency = trim( (string) $value['currency'] );
		if ( $amount === '' || $currency === '' ) {
			return '';
		}
		$line = sprintf(
			/* translators: 1: amount, 2: currency */
			__( 'Paid %1$s %2$s', 'nestform' ),
			$amount,
			$currency
		);
		if ( ! empty( $value['mode'] ) && 'test' === (string) $value['mode'] ) {
			$line .= ' · ' . __( 'test', 'nestform' );
		}
		return $line;
	}

	/**
	 * @return array<int, string>
	 */
	private static function person_name_keys() {
		return array(
			'name',
			'full_name',
			'fullname',
			'your_name',
			'contact_name',
			'applicant_name',
			'candidate_name',
			'person_name',
			'client_name',
			'fio',
		);
	}

	/**
	 * @param string $key Field name.
	 * @return bool
	 */
	private static function is_person_name_key( $key ) {
		$key = strtolower( str_replace( '-', '_', (string) $key ) );
		if ( in_array( $key, self::person_name_keys(), true ) ) {
			return true;
		}
		if ( in_array(
			$key,
			array(
				'first_name',
				'firstname',
				'given_name',
				'last_name',
				'lastname',
				'surname',
				'family_name',
			),
			true
		) ) {
			return true;
		}
		if ( preg_match( '/^(company|business|file|user|form|brand|product|org|organization|site|page)_name$/', $key ) ) {
			return false;
		}
		return (bool) preg_match( '/(^|_)(full_)?name$|(^|_)fio$/', $key );
	}

	/**
	 * @param string $label Normalized lowercase label.
	 * @return bool
	 */
	private static function is_person_name_label( $label ) {
		$label = preg_replace( '/\s+/u', ' ', (string) $label );
		$label = is_string( $label ) ? trim( $label ) : '';
		if ( $label === '' ) {
			return false;
		}
		$known = array(
			'name',
			'full name',
			'your name',
			'contact name',
			'applicant name',
			'candidate name',
			'person name',
			'client name',
			'first name',
			'last name',
			'fio',
			'имя',
			'фио',
			'полное имя',
			'ваше имя',
		);
		return in_array( $label, $known, true );
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return string
	 */
	private static function payload_composed_name( array $payload ) {
		$first = '';
		foreach ( array( 'first_name', 'firstname', 'given_name', 'first' ) as $key ) {
			$first = self::payload_scalar_text( $payload, $key );
			if ( $first !== '' ) {
				break;
			}
		}
		$last = '';
		foreach ( array( 'last_name', 'lastname', 'surname', 'family_name', 'last' ) as $key ) {
			$last = self::payload_scalar_text( $payload, $key );
			if ( $last !== '' ) {
				break;
			}
		}
		$parts = array_filter( array( $first, $last ) );
		return array() === $parts ? '' : implode( ' ', $parts );
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @param string               $key     Field key.
	 * @return string
	 */
	private static function payload_scalar_text( array $payload, $key ) {
		if ( ! isset( $payload[ $key ] ) || is_array( $payload[ $key ] ) || is_bool( $payload[ $key ] ) ) {
			return '';
		}
		return trim( wp_strip_all_tags( (string) $payload[ $key ] ) );
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return string
	 */
	public static function payload_email( array $payload ) {
		foreach ( array( 'email', 'e-mail', 'mail', 'your_email', 'work_email', 'email_address' ) as $key ) {
			if ( empty( $payload[ $key ] ) || is_array( $payload[ $key ] ) ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( (string) $payload[ $key ] ) );
			if ( $text !== '' && false !== strpos( $text, '@' ) ) {
				return $text;
			}
		}
		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) || is_array( $value ) ) {
				continue;
			}
			$norm = strtolower( str_replace( '-', '_', $key ) );
			if ( ! preg_match( '/(^|_)e?mail(_|$)|(^|_)email_address$/', $norm ) ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( (string) $value ) );
			if ( $text !== '' && false !== strpos( $text, '@' ) ) {
				return $text;
			}
		}
		return '';
	}

	public static function render_hub() {
		$can_view = class_exists( 'Nestform_Capabilities' )
			? Nestform_Capabilities::can_view_entries()
			: current_user_can( 'edit_posts' );
		if ( ! $can_view ) {
			wp_die( esc_html__( 'You do not have permission to view entries.', 'nestform' ) );
		}

		$want_summary = isset( $_GET['summary'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['summary'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $want_summary && class_exists( 'Nestform_Response_Summary' ) && Nestform_Response_Summary::render_summary_screen() ) {
			return;
		}

		$status = isset( $_GET['nestform_status'] ) ? sanitize_key( wp_unslash( $_GET['nestform_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $status !== '' && ! isset( self::status_labels()[ $status ] ) ) {
			$status = '';
		}

		/**
		 * Optional Entries hub kind tabs (e.g. All / Forms / Jobs).
		 *
		 * @param array<string, string> $filters Map of kind key => label. Empty key = All.
		 */
		$kind_filters = (array) apply_filters( 'nestform_entries_kind_filters', array() );
		$kind         = isset( $_GET['nestform_kind'] ) ? sanitize_key( wp_unslash( $_GET['nestform_kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $kind !== '' && ! isset( $kind_filters[ $kind ] ) ) {
			$kind = '';
		}

		/**
		 * Merge form_ids / exclude_form_ids into Entries hub queries.
		 *
		 * @param array<string, mixed> $args Query args.
		 * @param array{kind:string}   $ctx  Hub context.
		 */
		$scope = (array) apply_filters(
			'nestform_entries_hub_query_args',
			array(),
			array(
				'kind' => $kind,
			)
		);

		$all_n  = self::count_entries( $scope );
		$new_n  = self::count_entries( array_merge( $scope, array( 'status' => self::STATUS_NEW ) ) );
		$read_n = self::count_entries( array_merge( $scope, array( 'status' => self::STATUS_READ ) ) );
		$spam_n = self::count_entries( array_merge( $scope, array( 'status' => self::STATUS_SPAM ) ) );

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query    = array_merge(
			$scope,
			array(
				'limit' => $per_page,
				'paged' => $paged,
			)
		);
		$url_args = array();
		if ( $kind !== '' ) {
			$url_args['nestform_kind'] = $kind;
		}
		if ( $status !== '' ) {
			$query['status']             = $status;
			$url_args['nestform_status'] = $status;
		}

		$result  = self::query_entries( $query );
		$entries = $result['posts'];
		$total   = (int) $result['total'];
		$pages   = (int) $result['pages'];
		$paged   = (int) $result['paged'];

		if ( $paged > 1 && array() === $entries && $total > 0 ) {
			$query['paged'] = $pages;
			$result         = self::query_entries( $query );
			$entries        = $result['posts'];
			$paged          = (int) $result['paged'];
			$pages          = (int) $result['pages'];
		}

		$filters = array(
			''                => array( __( 'All', 'nestform' ), $all_n ),
			self::STATUS_NEW  => array( __( 'New', 'nestform' ), $new_n ),
			self::STATUS_READ => array( __( 'Read', 'nestform' ), $read_n ),
			self::STATUS_SPAM => array( __( 'Spam', 'nestform' ), $spam_n ),
		);

		$kind_url_base = $kind !== '' ? array( 'nestform_kind' => $kind ) : array();

		$lead_html = '';
		if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::LEAD_INSIGHTS ) ) {
			/**
			 * Lead insights meta HTML for Entries page head (Pro).
			 *
			 * @param string $html Empty.
			 * @param array  $ctx  Counts context.
			 */
			$lead_html = (string) apply_filters(
				'nestform_entries_lead_insights',
				'',
				array(
					'all'  => $all_n,
					'new'  => $new_n,
					'read' => $read_n,
					'spam' => $spam_n,
					'kind' => $kind,
				)
			);
		}
		?>
		<div class="wrap nestform-hub nestform-hub--entries">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Entries', 'nestform' ),
					'description' => __( 'Recent submissions across all forms. Open a form name to view its full inbox.', 'nestform' ),
					'meta_html'   => $lead_html,
				)
			);
			?>
			<?php if ( array() !== $kind_filters ) : ?>
				<nav class="nestform-entries__kinds" role="navigation" aria-label="<?php esc_attr_e( 'Entry type', 'nestform' ); ?>">
					<?php foreach ( $kind_filters as $kind_key => $kind_label ) : ?>
						<?php
						$kind_key = (string) $kind_key;
						$k_args   = '' === $kind_key ? array() : array( 'nestform_kind' => $kind_key );
						if ( $status !== '' ) {
							$k_args['nestform_status'] = $status;
						}
						$k_url    = self::hub_url( $k_args );
						$k_active = $kind === $kind_key;
						$k_scope  = (array) apply_filters(
							'nestform_entries_hub_query_args',
							array(),
							array(
								'kind' => $kind_key,
							)
						);
						$k_count = self::count_entries( $k_scope );
						?>
						<a
							class="nestform-entries__kind<?php echo $k_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( $k_url ); ?>"
							<?php echo $k_active ? ' aria-current="page"' : ''; ?>
						>
							<span class="nestform-entries__kind-label"><?php echo esc_html( (string) $kind_label ); ?></span>
							<span class="nestform-entries__kind-count"><?php echo esc_html( number_format_i18n( $k_count ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<div class="nestform-entries__stats" role="navigation" aria-label="<?php esc_attr_e( 'Filter entries', 'nestform' ); ?>">
				<?php foreach ( $filters as $key => $meta ) : ?>
					<?php
					$url     = '' === $key
						? self::hub_url( $kind_url_base )
						: self::hub_url( array_merge( $kind_url_base, array( 'nestform_status' => $key ) ) );
					$active  = $status === (string) $key;
					$mod     = '';
					if ( self::STATUS_NEW === $key ) {
						$mod = ' nestform-entries__stat--new';
					} elseif ( self::STATUS_READ === $key ) {
						$mod = ' nestform-entries__stat--ok';
					} elseif ( self::STATUS_SPAM === $key ) {
						$mod = ' nestform-entries__stat--spam';
					}
					$label = '' === $key ? __( 'Total entries', 'nestform' ) : (string) $meta[0];
					if ( self::STATUS_SPAM === $key ) {
						$label = __( 'Spam blocked', 'nestform' );
					}
					$class = 'nestform-entries__stat' . $mod . ( $active ? ' is-active' : '' );
					?>
					<a
						class="<?php echo esc_attr( $class ); ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $active ? ' aria-current="page"' : ''; ?>
					>
						<span class="nestform-entries__stat-value"><?php echo esc_html( number_format_i18n( (int) $meta[1] ) ); ?></span>
						<span class="nestform-entries__stat-label"><?php echo esc_html( $label ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<?php if ( array() === $entries ) : ?>
				<div class="nestform-hub__empty-state">
					<?php if ( $status !== '' || $kind !== '' ) : ?>
						<p class="nestform-hub__empty-state-title"><?php esc_html_e( 'No matching entries', 'nestform' ); ?></p>
						<p class="nestform-hub__empty-state-text"><?php esc_html_e( 'Try another filter, or open a form inbox from All Forms.', 'nestform' ); ?></p>
						<p>
							<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( self::hub_url() ); ?>">
								<?php esc_html_e( 'Show all entries', 'nestform' ); ?>
							</a>
						</p>
					<?php else : ?>
						<p class="nestform-hub__empty-state-title"><?php esc_html_e( 'No entries yet', 'nestform' ); ?></p>
						<p class="nestform-hub__empty-state-text"><?php esc_html_e( 'Submissions will show up here as soon as a form is sent. You can also open a form inbox from All Forms.', 'nestform' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php self::render_hub_pagination( $total, $paged, $pages, $per_page, $url_args ); ?>
				<div class="nestform-entries__table">
					<div class="nestform-entries__thead">
						<div class="nestform-entries__th nestform-entries__th--status"><?php esc_html_e( 'Status', 'nestform' ); ?></div>
						<div class="nestform-entries__th nestform-entries__th--from"><?php esc_html_e( 'Contact', 'nestform' ); ?></div>
						<div class="nestform-entries__th nestform-entries__th--form"><?php esc_html_e( 'Form', 'nestform' ); ?></div>
						<div class="nestform-entries__th nestform-entries__th--date"><?php esc_html_e( 'Date', 'nestform' ); ?></div>
						<div class="nestform-entries__th nestform-entries__th--actions"></div>
					</div>
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						$eid     = (int) $entry->ID;
						$efid    = (int) get_post_meta( $eid, self::META_FORM, true );
						$payload = get_post_meta( $eid, self::META_DATA, true );
						$payload = is_array( $payload ) ? $payload : array();
						$eform   = $efid > 0 ? get_post( $efid ) : null;
						$ftitle  = ( $eform && $eform->post_title !== '' ) ? $eform->post_title : ( $efid ? '#' . $efid : '—' );
						$who     = self::payload_name( $payload, (string) $entry->post_title, $ftitle, $efid );
						$email   = self::payload_email( $payload );
						$estatus = self::get_status( $eid );
						$ago     = human_time_diff( get_post_time( 'U', true, $entry ), current_time( 'timestamp', true ) );
						$view    = get_edit_post_link( $eid, 'raw' );
						$badge   = self::badge_modifier( $estatus );
						$row_mod = 'new' === $estatus ? ' nestform-entries__row--new' : '';
						$who_unknown = 0 === strcasecmp( $who, __( 'Unknown', 'nestform' ) );
						?>
						<div class="nestform-entries__row<?php echo esc_attr( $row_mod ); ?>">
							<div class="nestform-entries__td nestform-entries__td--status">
								<span class="nestform-badge nestform-badge--<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( strtoupper( $estatus ) ); ?></span>
							</div>
							<div class="nestform-entries__td nestform-entries__td--from">
								<?php if ( $view ) : ?>
									<a class="nestform-entries__who<?php echo $who_unknown ? ' nestform-entries__who--muted' : ''; ?>" href="<?php echo esc_url( $view ); ?>">
										<?php echo esc_html( $who ); ?>
									</a>
								<?php else : ?>
									<div class="nestform-entries__who<?php echo $who_unknown ? ' nestform-entries__who--muted' : ''; ?>"><?php echo esc_html( $who ); ?></div>
								<?php endif; ?>
								<?php if ( $email !== '' && 0 !== strcasecmp( $email, $who ) ) : ?>
									<div class="nestform-entries__email"><?php echo esc_html( $email ); ?></div>
								<?php endif; ?>
							</div>
							<div class="nestform-entries__td nestform-entries__td--form">
								<?php if ( $efid > 0 ) : ?>
									<a class="nestform-entries__form-link" href="<?php echo esc_url( self::list_url( $efid ) ); ?>" title="<?php esc_attr_e( 'Open form inbox', 'nestform' ); ?>">
										<?php echo esc_html( $ftitle ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $ftitle ); ?>
								<?php endif; ?>
							</div>
							<div class="nestform-entries__td nestform-entries__td--date">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: relative time */
										__( '%s ago', 'nestform' ),
										$ago
									)
								);
								?>
							</div>
							<div class="nestform-entries__td nestform-entries__td--actions">
								<?php if ( $view ) : ?>
									<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( $view ); ?>"><?php esc_html_e( 'View', 'nestform' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<?php self::render_hub_pagination( $total, $paged, $pages, $per_page, $url_args ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Sanitized field values.
	 * @param string               $ip      Remote IP.
	 * @return int Entry ID or 0.
	 */
	public static function create( $form_id, array $data, $ip = '' ) {
		$form_id = (int) $form_id;
		$form    = get_post( $form_id );
		$who     = self::guess_contact( $data, $form_id );
		$when    = wp_date( 'Y-m-d H:i:s' );
		if ( $who !== '' && $form ) {
			$title = sprintf(
				/* translators: 1: visitor name/email, 2: form title, 3: datetime */
				__( '%1$s — %2$s — %3$s', 'nestform' ),
				$who,
				$form->post_title,
				$when
			);
		} elseif ( $form ) {
			$title = sprintf(
				/* translators: 1: form title, 2: datetime */
				__( '%1$s — %2$s', 'nestform' ),
				$form->post_title,
				$when
			);
		} else {
			$title = $when;
		}

		$entry_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $entry_id ) || ! $entry_id ) {
			return 0;
		}

		update_post_meta( (int) $entry_id, self::META_FORM, $form_id );
		update_post_meta( (int) $entry_id, self::META_DATA, $data );
		update_post_meta( (int) $entry_id, self::META_STATUS, self::STATUS_NEW );
		if ( $ip !== '' ) {
			update_post_meta( (int) $entry_id, self::META_IP, sanitize_text_field( $ip ) );
		}

		return (int) $entry_id;
	}

	/**
	 * @param array<string, mixed> $data    Payload.
	 * @param int                  $form_id Form ID for label-based name lookup.
	 * @return string
	 */
	private static function guess_contact( array $data, $form_id = 0 ) {
		$person = self::payload_person_name( $data, (int) $form_id );
		if ( $person !== '' ) {
			return $person;
		}
		$email = self::payload_email( $data );
		if ( $email !== '' ) {
			return $email;
		}
		$payment = self::payload_payment_summary( $data, (int) $form_id );
		if ( $payment !== '' ) {
			return $payment;
		}
		foreach ( array( 'phone', 'tel', 'mobile', 'telephone' ) as $key ) {
			$text = self::payload_scalar_text( $data, $key );
			if ( $text !== '' ) {
				return $text;
			}
		}
		return '';
	}

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'                 => $columns['cb'] ?? '',
			'title'              => __( 'Entry', 'nestform' ),
			'nestform_status'    => __( 'Status', 'nestform' ),
			'nestform_preview'   => __( 'Preview', 'nestform' ),
			'nestform_date'      => __( 'Date', 'nestform' ),
		);
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		$data = get_post_meta( $post_id, self::META_DATA, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( 'nestform_status' === $column ) {
			$status = self::get_status( $post_id );
			$labels = self::status_labels();
			printf(
				'<span class="nestform-entry-status nestform-entry-status--%1$s">%2$s</span>',
				esc_attr( $status ),
				esc_html( $labels[ $status ] ?? $status )
			);
			return;
		}

		if ( 'nestform_preview' === $column ) {
			$form_id = (int) get_post_meta( $post_id, self::META_FORM, true );
			$parts   = self::preview_parts( $form_id, $data );
			if ( array() === $parts ) {
				echo '—';
				return;
			}
			echo esc_html( implode( ' · ', $parts ) );
			return;
		}

		if ( 'nestform_date' === $column ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				echo '—';
				return;
			}
			$formatted = class_exists( 'Nestform_Settings' )
				? Nestform_Settings::format_entry_datetime( $post )
				: get_the_date( 'Y-m-d H:i', $post );
			$ago = human_time_diff( get_post_time( 'U', true, $post ), current_time( 'timestamp', true ) );
			printf(
				'<span class="nestform-entry-date" title="%1$s">%2$s</span>',
				esc_attr( $formatted ),
				esc_html(
					sprintf(
						/* translators: %s: relative time */
						__( '%s ago', 'nestform' ),
						$ago
					)
				)
			);
		}
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Payload.
	 * @return array<int, string>
	 */
	private static function preview_parts( $form_id, array $data ) {
		$parts  = array();
		$fields = $form_id > 0 ? Nestform_Form_Config::get_fields( $form_id ) : array();
		$skip   = array( 'email', 'e-mail', 'mail', 'phone', 'tel', 'telephone', 'mobile', 'acceptance' );

		if ( is_array( $fields ) && array() !== $fields ) {
			foreach ( $fields as $field ) {
				if ( count( $parts ) >= 3 ) {
					break;
				}
				$name = (string) ( $field['name'] ?? '' );
				$type = (string) ( $field['type'] ?? '' );
				if ( $name === '' || Nestform_Form_Config::is_layout_field( $type ) || in_array( $type, array( 'hidden', 'acceptance' ), true ) || in_array( $name, $skip, true ) ) {
					continue;
				}
				if ( ! array_key_exists( $name, $data ) ) {
					continue;
				}
				$display = self::format_value( $data[ $name ], true, $type );
				if ( $display === '' || '—' === $display ) {
					continue;
				}
				$parts[] = $display;
			}
		}

		if ( array() === $parts ) {
			foreach ( $data as $key => $value ) {
				if ( count( $parts ) >= 3 ) {
					break;
				}
				if ( in_array( (string) $key, $skip, true ) ) {
					continue;
				}
				$display = self::format_value( $value );
				if ( $display === '' || '—' === $display ) {
					continue;
				}
				$parts[] = $display;
			}
		}

		return $parts;
	}

	/**
	 * @param array<string, mixed> $data Payload.
	 * @param array<int, string>   $keys Keys to try.
	 * @return string
	 */
	private static function find_value( array $data, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $data[ $key ] ) && is_scalar( $data[ $key ] ) && ! is_bool( $data[ $key ] ) ) {
				return trim( (string) $data[ $key ] );
			}
		}
		return '';
	}

	/**
	 * @param mixed  $value     Value.
	 * @param bool   $truncate  Truncate long strings.
	 * @param string $type      Field type (optional).
	 * @return string
	 */
	private static function format_value( $value, $truncate = true, $type = '' ) {
		/**
		 * Filter entry value display HTML/text.
		 *
		 * @param string|null $custom   Custom display or null for default.
		 * @param mixed       $value    Stored value.
		 * @param bool        $truncate Truncate.
		 * @param string      $type     Field type.
		 */
		$custom = apply_filters( 'nestform_format_entry_value', null, $value, $truncate, $type );
		if ( is_string( $custom ) ) {
			return $custom;
		}

		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'nestform' ) : __( 'No', 'nestform' );
		}
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			$name = ! empty( $value['name'] ) ? (string) $value['name'] : basename( (string) $value['url'] );
			return $name;
		}
		/* Payment payloads from Thimbleform Pro (even if type metadata is missing). */
		if ( is_array( $value ) && isset( $value['intent_id'], $value['amount'], $value['currency'] ) ) {
			$line = sprintf(
				/* translators: 1: amount, 2: currency */
				__( 'Paid %1$s %2$s', 'nestform' ),
				(string) $value['amount'],
				(string) $value['currency']
			);
			if ( ! empty( $value['mode'] ) && 'test' === (string) $value['mode'] ) {
				$line .= ' · ' . __( 'test', 'nestform' );
			}
			if ( ! empty( $value['intent_id'] ) ) {
				$line .= ' (' . (string) $value['intent_id'] . ')';
			}
			if ( $truncate && strlen( $line ) > 80 ) {
				$line = substr( $line, 0, 77 ) . '…';
			}
			return $line;
		}
		if ( is_array( $value ) ) {
			// Repeater rows.
			if ( isset( $value[0] ) && is_array( $value[0] ) && ! isset( $value[0]['url'] ) ) {
				$parts = array();
				foreach ( $value as $i => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$inner = array();
					foreach ( $row as $k => $v ) {
						$inner[] = $k . ': ' . self::format_value( $v, false );
					}
					$parts[] = '#' . ( (int) $i + 1 ) . ' — ' . implode( '; ', $inner );
				}
				$text = implode( "\n", $parts );
				if ( $truncate && strlen( $text ) > 200 ) {
					$text = substr( $text, 0, 197 ) . '…';
				}
				return $text;
			}
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
			return implode( ', ', $flat );
		}
		$text = trim( (string) $value );
		if ( $truncate && strlen( $text ) > 80 ) {
			$text = substr( $text, 0, 77 ) . '…';
		}
		return $text;
	}

	public static function list_toolbar() {
		global $typenow;
		if ( self::POST_TYPE !== $typenow ) {
			return;
		}
		$selected = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $selected <= 0 ) {
			return;
		}

		$status_filter = isset( $_GET['nestform_status'] ) ? sanitize_key( wp_unslash( $_GET['nestform_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$starred       = isset( $_GET['starred'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['starred'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<span class="nestform-entries-toolbar">';
		printf(
			'<input type="hidden" name="nestform_form_id" value="%s" />',
			esc_attr( (string) $selected )
		);
		if ( $status_filter !== '' && isset( self::status_labels()[ $status_filter ] ) ) {
			printf(
				'<input type="hidden" name="nestform_status" value="%s" />',
				esc_attr( $status_filter )
			);
		}
		if ( $starred ) {
			echo '<input type="hidden" name="starred" value="1" />';
		}

		$count = self::count_for_form( $selected );
		echo '<span class="nestform-entries-toolbar__meta">';
		echo esc_html(
			sprintf(
				/* translators: %d: entry count */
				_n( '%d entry', '%d entries', $count, 'nestform' ),
				$count
			)
		);
		echo '</span>';
		echo '</span>';
	}

	/**
	 * Hide the core months dropdown on the form inbox list.
	 *
	 * @param bool   $disable   Whether to disable.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function disable_months_dropdown( $disable, $post_type ) {
		if ( self::POST_TYPE === $post_type ) {
			return true;
		}
		return (bool) $disable;
	}

	/**
	 * Short contact-only title in the form inbox list table.
	 *
	 * @param string $title   Post title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function list_entry_title( $title, $post_id = 0 ) {
		if ( ! self::is_entries_list_screen() ) {
			return $title;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return $title;
		}
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return $title;
		}

		$payload = get_post_meta( $post_id, self::META_DATA, true );
		$payload = is_array( $payload ) ? $payload : array();
		$form_id = (int) get_post_meta( $post_id, self::META_FORM, true );
		$form    = $form_id > 0 ? get_post( $form_id ) : null;
		$exclude = ( $form && $form->post_title !== '' ) ? $form->post_title : '';

		return self::payload_name( $payload, (string) $post->post_title, $exclude, $form_id );
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function filter_by_form( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$form_id = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$meta_query = array( 'relation' => 'AND' );
		if ( $form_id > 0 ) {
			if ( ! self::user_can_manage_form_entries( $form_id ) ) {
				$meta_query[] = array(
					'key'   => self::META_FORM,
					'value' => 0,
				);
			} else {
				$meta_query[] = array(
					'key'   => self::META_FORM,
					'value' => $form_id,
				);
			}
		} else {
			$accessible = self::accessible_form_ids();
			if ( is_array( $accessible ) ) {
				if ( array() === $accessible ) {
					$meta_query[] = array(
						'key'   => self::META_FORM,
						'value' => 0,
					);
				} else {
					$meta_query[] = array(
						'key'     => self::META_FORM,
						'value'   => $accessible,
						'compare' => 'IN',
					);
				}
			}
		}
		$status = isset( $_GET['nestform_status'] ) ? sanitize_key( wp_unslash( $_GET['nestform_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $status !== '' && isset( self::status_labels()[ $status ] ) ) {
			if ( self::STATUS_NEW === $status ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'   => self::META_STATUS,
						'value' => self::STATUS_NEW,
					),
					array(
						'key'     => self::META_STATUS,
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$meta_query[] = array(
					'key'   => self::META_STATUS,
					'value' => $status,
				);
			}
		}
		$starred_only = isset( $_GET['starred'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['starred'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $starred_only ) {
			$meta_query[] = array(
				'key'   => self::META_STARRED,
				'value' => '1',
			);
		}
		if ( count( $meta_query ) > 1 ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );

		$status = self::get_status( $post->ID );
		$form_id = (int) get_post_meta( $post->ID, self::META_FORM, true );
		$base    = array(
			'action'             => 'nestform_set_entry_status',
			'entry_id'           => (int) $post->ID,
			'nestform_form_id' => $form_id,
		);

		if ( self::STATUS_NEW === $status ) {
			$actions['nestform_mark_read'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( add_query_arg( array_merge( $base, array( 'status' => self::STATUS_READ ) ), admin_url( 'admin-post.php' ) ), 'nestform_set_entry_status_' . (int) $post->ID ) ),
				esc_html__( 'Mark read', 'nestform' )
			);
		} elseif ( self::STATUS_READ === $status ) {
			$actions['nestform_mark_new'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( add_query_arg( array_merge( $base, array( 'status' => self::STATUS_NEW ) ), admin_url( 'admin-post.php' ) ), 'nestform_set_entry_status_' . (int) $post->ID ) ),
				esc_html__( 'Mark new', 'nestform' )
			);
		}

		if ( self::STATUS_SPAM !== $status ) {
			$actions['nestform_mark_spam'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( add_query_arg( array_merge( $base, array( 'status' => self::STATUS_SPAM ) ), admin_url( 'admin-post.php' ) ), 'nestform_set_entry_status_' . (int) $post->ID ) ),
				esc_html__( 'Spam', 'nestform' )
			);
		} else {
			$actions['nestform_mark_read'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( add_query_arg( array_merge( $base, array( 'status' => self::STATUS_READ ) ), admin_url( 'admin-post.php' ) ), 'nestform_set_entry_status_' . (int) $post->ID ) ),
				esc_html__( 'Not spam', 'nestform' )
			);
		}

		if ( class_exists( 'Nestform_Entry_Print' ) ) {
			$actions['nestform_print'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( Nestform_Entry_Print::url( (int) $post->ID ) ),
				esc_html__( 'Print', 'nestform' )
			);
		}

		$starred = self::is_starred( $post->ID );
		$actions['nestform_star'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				self::star_action_url(
					(int) $post->ID,
					array(
						'form_id'     => (int) get_post_meta( $post->ID, self::META_FORM, true ),
						'redirect_to' => 'list',
					)
				)
			),
			$starred ? esc_html__( 'Unstar', 'nestform' ) : esc_html__( 'Star', 'nestform' )
		);

		return $actions;
	}

	public static function on_load_entry_edit() {
		if ( ! isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		remove_post_type_support( self::POST_TYPE, 'title' );
		self::maybe_mark_read();
	}

	/**
	 * @return void
	 */
	public static function maybe_mark_read() {
		if ( ! isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( self::STATUS_NEW === self::get_status( $post_id ) ) {
			if ( class_exists( 'Nestform_Settings' ) && ! Nestform_Settings::auto_mark_read_enabled() ) {
				return;
			}
			self::set_status( $post_id, self::STATUS_READ );
		}
	}

	/**
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		$actions['nestform_mark_read'] = __( 'Mark as read', 'nestform' );
		$actions['nestform_mark_new']  = __( 'Mark as new', 'nestform' );
		$actions['nestform_mark_spam'] = __( 'Mark as spam', 'nestform' );
		return $actions;
	}

	/**
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param array  $post_ids IDs.
	 * @return string
	 */
	public static function handle_bulk_actions( $redirect, $action, $post_ids ) {
		$map = array(
			'nestform_mark_read' => self::STATUS_READ,
			'nestform_mark_new'  => self::STATUS_NEW,
			'nestform_mark_spam' => self::STATUS_SPAM,
		);
		if ( ! isset( $map[ $action ] ) ) {
			return $redirect;
		}
		$updated = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( self::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			if ( self::set_status( $post_id, $map[ $action ] ) ) {
				++$updated;
			}
		}
		return add_query_arg( 'nestform_status_updated', $updated, $redirect );
	}

	public static function bulk_admin_notice() {
		if ( ! isset( $_GET['nestform_status_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$count = (int) $_GET['nestform_status_updated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $count <= 0 ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of entries */
					_n( 'Updated status for %d entry.', 'Updated status for %d entries.', $count, 'nestform' ),
					$count
				)
			)
		);
	}

	public static function handle_set_status() {
		$entry_id    = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0;
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$form_id     = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0;
		$redirect_to = isset( $_GET['redirect_to'] ) ? sanitize_key( wp_unslash( $_GET['redirect_to'] ) ) : '';

		if ( $entry_id <= 0 || self::POST_TYPE !== get_post_type( $entry_id ) ) {
			wp_die( esc_html__( 'Invalid entry.', 'nestform' ), 400 );
		}
		check_admin_referer( 'nestform_set_entry_status_' . $entry_id );
		if ( ! current_user_can( 'edit_post', $entry_id ) ) {
			wp_die( esc_html__( 'You do not have permission to update this entry.', 'nestform' ), 403 );
		}
		self::set_status( $entry_id, $status );

		if ( 'edit' === $redirect_to ) {
			$edit = get_edit_post_link( $entry_id, 'raw' );
			if ( $edit ) {
				wp_safe_redirect( $edit );
				exit;
			}
		}

		if ( $form_id <= 0 ) {
			$form_id = (int) get_post_meta( $entry_id, self::META_FORM, true );
		}
		wp_safe_redirect( self::list_url( $form_id ) );
		exit;
	}

	public static function handle_toggle_star() {
		$entry_id = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0;
		$form_id  = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0;

		if ( $entry_id <= 0 || self::POST_TYPE !== get_post_type( $entry_id ) ) {
			wp_die( esc_html__( 'Invalid entry.', 'nestform' ), 400 );
		}
		check_admin_referer( 'nestform_toggle_entry_star_' . $entry_id );
		if ( ! current_user_can( 'edit_post', $entry_id ) ) {
			wp_die( esc_html__( 'You do not have permission to update this entry.', 'nestform' ), 403 );
		}

		self::set_starred( $entry_id, ! self::is_starred( $entry_id ) );

		if ( $form_id <= 0 ) {
			$form_id = (int) get_post_meta( $entry_id, self::META_FORM, true );
		}

		$redirect_to = isset( $_GET['redirect_to'] ) ? sanitize_key( wp_unslash( $_GET['redirect_to'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $redirect_to ) {
			$redirect = get_edit_post_link( $entry_id, 'raw' );
			if ( ! $redirect ) {
				$redirect = self::list_url( $form_id );
			}
		} else {
			$redirect = self::list_url( $form_id );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_save_notes() {
		$entry_id = isset( $_POST['entry_id'] ) ? (int) $_POST['entry_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $entry_id <= 0 || self::POST_TYPE !== get_post_type( $entry_id ) ) {
			wp_die( esc_html__( 'Invalid entry.', 'nestform' ), 400 );
		}
		check_admin_referer( 'nestform_save_entry_notes_' . $entry_id );
		if ( ! current_user_can( 'edit_post', $entry_id ) ) {
			wp_die( esc_html__( 'You do not have permission to update this entry.', 'nestform' ), 403 );
		}

		$notes   = isset( $_POST['nestform_notes'] ) ? wp_unslash( $_POST['nestform_notes'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		$starred = ! empty( $_POST['nestform_starred'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		self::set_notes( $entry_id, $notes );
		self::set_starred( $entry_id, $starred );

		$edit = get_edit_post_link( $entry_id, 'raw' );
		wp_safe_redirect( $edit ? add_query_arg( 'nestform_notes_saved', '1', $edit ) : self::hub_url() );
		exit;
	}

	/**
	 * Highlight new entries in the list table.
	 *
	 * @param array<int, string> $classes Classes.
	 * @param string[]|string    $class   Class.
	 * @param int                $post_id Post ID.
	 * @return array<int, string>
	 */
	public static function entry_post_class( $classes, $class, $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $classes;
		}
		$status     = self::get_status( $post_id );
		$classes[]  = 'nestform-entry--' . $status;
		if ( self::is_starred( $post_id ) ) {
			$classes[] = 'nestform-entry--starred';
		}
		return $classes;
	}

	public static function meta_boxes() {
		add_meta_box(
			'nestform_entry_meta',
			__( 'Entry details', 'nestform' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			'nestform_entry_notes',
			__( 'Notes & star', 'nestform' ),
			array( __CLASS__, 'render_notes_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Drop WP Publish / slug chrome — entries are view + triage only.
	 */
	public static function remove_default_boxes() {
		$boxes = array(
			'nestform_entry_payload',
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
			remove_meta_box( $box, self::POST_TYPE, 'normal' );
			remove_meta_box( $box, self::POST_TYPE, 'side' );
			remove_meta_box( $box, self::POST_TYPE, 'advanced' );
		}
	}

	/**
	 * Main submission panel (outside WP sortables — survives layout chrome).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_entry_main_panel( $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		?>
		<div class="nestform-entry-main">
			<div class="nestform-entry-main__head">
				<h2 class="nestform-entry-main__title"><?php esc_html_e( 'Submission data', 'nestform' ); ?></h2>
			</div>
			<div class="nestform-entry-main__body">
				<?php self::render_payload_box( $post ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$form_id = (int) get_post_meta( $post->ID, self::META_FORM, true );
		$ip      = (string) get_post_meta( $post->ID, self::META_IP, true );
		$status  = self::get_status( $post->ID );
		$labels  = self::status_labels();
		$triage  = array(
			'form_id'     => $form_id,
			'redirect_to' => 'edit',
		);
		?>
		<div class="nestform-entry-meta">
			<div class="nestform-entry-meta__triage">
				<p class="nestform-entry-meta__triage-label"><?php esc_html_e( 'Status', 'nestform' ); ?></p>
				<span class="nestform-entry-status nestform-entry-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $labels[ $status ] ?? $status ); ?></span>
				<div class="nestform-entry-meta__triage-actions">
					<?php if ( self::STATUS_NEW === $status ) : ?>
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( self::status_action_url( $post->ID, self::STATUS_READ, $triage ) ); ?>"><?php esc_html_e( 'Mark read', 'nestform' ); ?></a>
					<?php else : ?>
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( self::status_action_url( $post->ID, self::STATUS_NEW, $triage ) ); ?>"><?php esc_html_e( 'Mark new', 'nestform' ); ?></a>
					<?php endif; ?>
					<?php if ( self::STATUS_SPAM !== $status ) : ?>
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( self::status_action_url( $post->ID, self::STATUS_SPAM, $triage ) ); ?>"><?php esc_html_e( 'Spam', 'nestform' ); ?></a>
					<?php else : ?>
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( self::status_action_url( $post->ID, self::STATUS_READ, $triage ) ); ?>"><?php esc_html_e( 'Not spam', 'nestform' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			/**
			 * After entry status triage (addons: recruiting stage — keep high in the sidebar).
			 *
			 * @param WP_Post $post Entry post.
			 */
			do_action( 'nestform_entry_meta_after_triage', $post );
			?>
			<div class="nestform-entry-meta__rows">
				<div class="nestform-entry-meta__row">
					<span class="nestform-entry-meta__label"><?php esc_html_e( 'Entry ID', 'nestform' ); ?></span>
					<span class="nestform-entry-meta__value"><?php echo (int) $post->ID; ?></span>
				</div>
				<div class="nestform-entry-meta__row">
					<span class="nestform-entry-meta__label"><?php esc_html_e( 'Submitted', 'nestform' ); ?></span>
					<span class="nestform-entry-meta__value"><?php echo esc_html( class_exists( 'Nestform_Settings' ) ? Nestform_Settings::format_entry_datetime( $post ) : get_the_date( 'Y-m-d H:i:s', $post ) ); ?></span>
				</div>
				<?php if ( $form_id > 0 ) : ?>
					<div class="nestform-entry-meta__row">
						<span class="nestform-entry-meta__label"><?php esc_html_e( 'Form', 'nestform' ); ?></span>
						<span class="nestform-entry-meta__value">
							<?php
							$edit_link = get_edit_post_link( $form_id );
							$title     = get_the_title( $form_id );
							$inbox     = self::list_url( $form_id );
							if ( $inbox ) {
								echo '<a href="' . esc_url( $inbox ) . '">' . esc_html( $title ) . '</a>';
							} elseif ( $edit_link ) {
								echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
							} else {
								echo esc_html( $title );
							}
							?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ( $ip !== '' ) : ?>
					<div class="nestform-entry-meta__row">
						<span class="nestform-entry-meta__label"><?php esc_html_e( 'IP address', 'nestform' ); ?></span>
						<span class="nestform-entry-meta__value"><code><?php echo esc_html( $ip ); ?></code></span>
					</div>
				<?php endif; ?>
			</div>
			<?php
			/**
			 * After entry meta rows (addons: recruiting stage, etc.).
			 *
			 * @param WP_Post $post Entry post.
			 */
			do_action( 'nestform_entry_meta_after', $post );
			?>
		</div>
		<?php
	}

	public static function notes_saved_notice() {
		if ( ! self::is_entry_edit_screen() ) {
			return;
		}
		if ( empty( $_GET['nestform_notes_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Notes saved.', 'nestform' )
		);
	}

	/**
	 * Entry notes form shell (outside WP #post — avoids nested forms).
	 */
	public static function render_entry_notes_form_footer() {
		if ( ! self::is_entry_edit_screen() ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$form_id = 'nestform-entry-notes-form-' . $post_id;
		?>
		<form
			id="<?php echo esc_attr( $form_id ); ?>"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			class="nestform-entry-notes-form"
			hidden
		>
			<input type="hidden" name="action" value="nestform_save_entry_notes" />
			<input type="hidden" name="entry_id" value="<?php echo (int) $post_id; ?>" />
			<?php wp_nonce_field( 'nestform_save_entry_notes_' . (int) $post_id ); ?>
		</form>
		<?php
	}

	/**
	 * Notes + star metabox on entry edit.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_notes_box( $post ) {
		$starred  = self::is_starred( $post->ID );
		$notes    = self::get_notes( $post->ID );
		$form_id  = 'nestform-entry-notes-form-' . (int) $post->ID;
		$star_id  = 'nestform_starred_' . (int) $post->ID;
		$notes_id = 'nestform_notes_' . (int) $post->ID;
		?>
		<div class="nestform-entry-notes">
			<label class="nestform-admin__check nestform-entry-notes__star" for="<?php echo esc_attr( $star_id ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $star_id ); ?>"
					name="nestform_starred"
					value="1"
					form="<?php echo esc_attr( $form_id ); ?>"
					<?php checked( $starred ); ?>
				/>
				<span><?php esc_html_e( 'Starred', 'nestform' ); ?></span>
			</label>
			<div class="nestform-entry-notes__field">
				<label class="nestform-admin__label" for="<?php echo esc_attr( $notes_id ); ?>"><?php esc_html_e( 'Internal notes', 'nestform' ); ?></label>
				<textarea
					class="nestform-admin__input nestform-admin__textarea nestform-entry-notes__textarea"
					rows="5"
					id="<?php echo esc_attr( $notes_id ); ?>"
					name="nestform_notes"
					form="<?php echo esc_attr( $form_id ); ?>"
				><?php echo esc_textarea( $notes ); ?></textarea>
			</div>
			<button type="submit" class="nestform-btn nestform-btn--primary" form="<?php echo esc_attr( $form_id ); ?>">
				<?php nestform_admin_icon( 'save' ); ?>
				<?php esc_html_e( 'Save notes', 'nestform' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_payload_box( $post ) {
		$form_id = (int) get_post_meta( $post->ID, self::META_FORM, true );
		$data    = get_post_meta( $post->ID, self::META_DATA, true );

		if ( ! is_array( $data ) || array() === $data ) {
			echo '<p>' . esc_html__( 'No payload stored.', 'nestform' ) . '</p>';
			return;
		}

		$fields = $form_id > 0 ? Nestform_Form_Config::get_fields( $form_id ) : array();
		$labels = array();
		$types  = array();
		$order  = array();
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				$name = (string) ( $field['name'] ?? '' );
				if ( $name === '' ) {
					continue;
				}
				$label = (string) ( $field['label'] ?? '' );
				$labels[ $name ] = $label !== '' ? wp_strip_all_tags( $label ) : $name;
				$types[ $name ]  = (string) ( $field['type'] ?? '' );
				$order[]         = $name;
			}
		}

		$rows = array();
		foreach ( $order as $name ) {
			if ( array_key_exists( $name, $data ) ) {
				$rows[ $name ] = $data[ $name ];
			}
		}
		foreach ( $data as $key => $value ) {
			if ( ! array_key_exists( $key, $rows ) ) {
				$rows[ $key ] = $value;
			}
		}

		echo '<table class="nestform-entry-payload">';
		echo '<thead><tr><th>' . esc_html__( 'Field', 'nestform' ) . '</th><th>' . esc_html__( 'Value', 'nestform' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $key => $value ) {
			$label   = $labels[ $key ] ?? (string) $key;
			$key_s   = (string) $key;
			$ftype   = $types[ $key_s ] ?? '';
			if ( 'password' === $ftype || ( is_string( $value ) && '[redacted]' === $value ) ) {
				$display = esc_html__( '[redacted]', 'nestform' );
			} else {
				$display = self::format_value( $value, false, $ftype );
			}
			echo '<tr>';
			echo '<th scope="row"><span class="nestform-entry-payload__label" title="' . esc_attr( $key_s ) . '">' . esc_html( $label ) . '</span>';
			if ( $label !== $key_s ) {
				echo '<span class="screen-reader-text"> (' . esc_html( $key_s ) . ')</span>';
			}
			echo '</th><td>';
			if ( 'password' === $ftype || ( is_string( $value ) && '[redacted]' === $value ) ) {
				echo esc_html( $display );
			} elseif ( 'signature' === $ftype && is_array( $value ) && ! empty( $value['url'] ) ) {
				echo $display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built via format filter with esc_url.
			} elseif ( is_array( $value ) && isset( $value[0] ) && is_array( $value[0] ) && ! empty( $value[0]['url'] ) ) {
				echo '<ul class="nestform-entry-payload__files">';
				foreach ( $value as $file_row ) {
					if ( ! is_array( $file_row ) || empty( $file_row['url'] ) ) {
						continue;
					}
					$fname = ! empty( $file_row['name'] ) ? (string) $file_row['name'] : basename( (string) $file_row['url'] );
					echo '<li><a href="' . esc_url( (string) $file_row['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $fname ) . '</a>';
					if ( ! empty( $file_row['size'] ) ) {
						echo ' <span class="description">(' . esc_html( size_format( (int) $file_row['size'] ) ) . ')</span>';
					}
					echo '</li>';
				}
				echo '</ul>';
			} elseif ( is_array( $value ) && ! empty( $value['url'] ) ) {
				$fname = ! empty( $value['name'] ) ? (string) $value['name'] : basename( (string) $value['url'] );
				echo '<a href="' . esc_url( (string) $value['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $fname ) . '</a>';
				if ( ! empty( $value['size'] ) ) {
					echo ' <span class="description">(' . esc_html( size_format( (int) $value['size'] ) ) . ')</span>';
				}
			} elseif ( is_string( $value ) && is_email( $value ) ) {
				echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
			} elseif ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
				echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
			} else {
				echo nl2br( esc_html( $display ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
