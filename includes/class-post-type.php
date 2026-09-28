<?php
/**
 * CPT: nestform
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Post_Type {

	const POST_TYPE = 'nestform';
	const PAGE_SLUG = 'nestform-forms';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'replace_list_menu' ), 30 );
		add_action( 'load-edit.php', array( __CLASS__, 'redirect_default_list' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_action( 'admin_post_nestform_duplicate', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'duplicate_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'menu_icon_css' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( __CLASS__, 'bulk_updated_messages' ), 10, 2 );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                     => __( 'Forms', 'nestform' ),
					'singular_name'            => __( 'Form', 'nestform' ),
					'add_new'                  => __( 'Add New', 'nestform' ),
					'add_new_item'             => __( 'Add New Form', 'nestform' ),
					'edit_item'                => __( 'Edit Form', 'nestform' ),
					'new_item'                 => __( 'New Form', 'nestform' ),
					'view_item'                => __( 'View Form', 'nestform' ),
					'search_items'             => __( 'Search Forms', 'nestform' ),
					'not_found'                => __( 'No forms found.', 'nestform' ),
					'not_found_in_trash'       => __( 'No forms found in Trash.', 'nestform' ),
					'menu_name'                => __( 'Thimbleform', 'nestform' ),
					'item_published'           => __( 'Form published.', 'nestform' ),
					'item_published_privately' => __( 'Form published privately.', 'nestform' ),
					'item_reverted_to_draft'   => __( 'Form reverted to draft.', 'nestform' ),
					'item_trashed'             => __( 'Form moved to the Trash.', 'nestform' ),
					'item_scheduled'           => __( 'Form scheduled.', 'nestform' ),
					'item_updated'             => __( 'Form updated.', 'nestform' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 26,
				'menu_icon'           => nestform_logo_url(),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
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
	 * Replace WP "Post updated." notices with form wording.
	 *
	 * @param array<string, array<int, string|false>> $messages Messages keyed by post type.
	 * @return array<string, array<int, string|false>>
	 */
	public static function updated_messages( $messages ) {
		$scheduled_date = '';
		$post           = get_post();
		if ( $post instanceof WP_Post ) {
			$scheduled_date = sprintf(
				/* translators: Publish box date string. 1: Date, 2: Time. */
				__( '%1$s at %2$s', 'nestform' ),
				date_i18n( _x( 'M j, Y', 'publish box date format', 'nestform' ), strtotime( $post->post_date ) ),
				date_i18n( _x( 'H:i', 'publish box time format', 'nestform' ), strtotime( $post->post_date ) )
			);
		}

		$revision = '';
		if ( isset( $_GET['revision'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$revision = wp_post_revision_title( (int) $_GET['revision'], false ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Form updated.', 'nestform' ),
			2  => __( 'Custom field updated.', 'nestform' ),
			3  => __( 'Custom field deleted.', 'nestform' ),
			4  => __( 'Form updated.', 'nestform' ),
			5  => $revision ? sprintf(
				/* translators: %s: Date and time of the revision. */
				__( 'Form restored to revision from %s.', 'nestform' ),
				$revision
			) : false,
			6  => __( 'Form published.', 'nestform' ),
			7  => __( 'Form saved.', 'nestform' ),
			8  => __( 'Form submitted.', 'nestform' ),
			9  => sprintf(
				/* translators: %s: Scheduled date for the form. */
				__( 'Form scheduled for: %s.', 'nestform' ),
				'<strong>' . $scheduled_date . '</strong>'
			),
			10 => __( 'Form draft saved.', 'nestform' ),
		);

		return $messages;
	}

	/**
	 * Replace WP "post" bulk notices with form wording.
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

		/* translators: %s: Number of forms. */
		$updated_msg = _n( '%s form updated.', '%s forms updated.', $updated, 'nestform' );
		/* translators: %s: Number of forms. */
		$locked_msg = _n( '%s form not updated, somebody is editing it.', '%s forms not updated, somebody is editing them.', $locked, 'nestform' );
		/* translators: %s: Number of forms. */
		$deleted_msg = _n( '%s form permanently deleted.', '%s forms permanently deleted.', $deleted, 'nestform' );
		/* translators: %s: Number of forms. */
		$trashed_msg = _n( '%s form moved to the Trash.', '%s forms moved to the Trash.', $trashed, 'nestform' );
		/* translators: %s: Number of forms. */
		$untrashed_msg = _n( '%s form restored from the Trash.', '%s forms restored from the Trash.', $untrashed, 'nestform' );

		$bulk_messages[ self::POST_TYPE ] = array(
			'updated'   => $updated_msg,
			'locked'    => ( 1 === $locked )
				? __( '1 form not updated, somebody is editing it.', 'nestform' )
				: $locked_msg,
			'deleted'   => $deleted_msg,
			'trashed'   => $trashed_msg,
			'untrashed' => $untrashed_msg,
		);

		return $bulk_messages;
	}

	/**
	 * Fit the PNG logo in the WP admin menu.
	 */
	public static function menu_icon_css() {
		$css = '#adminmenu .menu-icon-nestform .wp-menu-image,#adminmenu #menu-posts-nestform .wp-menu-image{width:36px;height:34px;overflow:hidden}'
			. '#adminmenu .menu-icon-nestform .wp-menu-image img,#adminmenu #menu-posts-nestform .wp-menu-image img,#adminmenu .wp-menu-image img[src*="logo.webp"]{box-sizing:border-box;display:block;width:20px!important;height:20px!important;max-width:20px!important;max-height:20px!important;margin:7px auto 0;padding:0!important;object-fit:contain;opacity:1}';
		wp_add_inline_style( 'admin-menu', $css );
	}

	/**
	 * Replace default CPT table with styled hub.
	 */
	public static function replace_list_menu() {
		$parent = 'edit.php?post_type=' . self::POST_TYPE;
		remove_submenu_page( $parent, $parent );
		add_submenu_page(
			$parent,
			__( 'Forms', 'nestform' ),
			__( 'All Forms', 'nestform' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_hub' )
		);

		global $submenu;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}
		$hub  = null;
		$rest = array();
		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && self::PAGE_SLUG === $item[2] ) {
				$hub = $item;
				continue;
			}
			$rest[] = $item;
		}
		if ( $hub ) {
			array_unshift( $rest, $hub );
			$submenu[ $parent ] = $rest;
		}
	}

	/**
	 * Old All Forms URL → hub.
	 */
	public static function redirect_default_list() {
		if ( ! isset( $_GET['post_type'] ) || self::POST_TYPE !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_safe_redirect( self::hub_url() );
		exit;
	}

	/**
	 * @return string
	 */
	public static function hub_url() {
		return add_query_arg(
			array(
				'post_type' => self::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * @param string $parent_file Parent.
	 * @return string
	 */
	public static function parent_file( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && self::POST_TYPE === $screen->post_type ) {
			return 'edit.php?post_type=' . self::POST_TYPE;
		}
		return $parent_file;
	}

	/**
	 * @param string $submenu_file Submenu.
	 * @return string
	 */
	public static function submenu_file( $submenu_file ) {
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( class_exists( 'Nestform_Importer' ) && Nestform_Importer::PAGE_SLUG === $page ) {
			return Nestform_Importer::PAGE_SLUG;
		}
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return $submenu_file;
		}
		if ( 'nestform_page_' . self::PAGE_SLUG === $screen->id ) {
			return self::PAGE_SLUG;
		}
		if ( 'post' === $screen->base && 'add' !== $screen->action ) {
			return self::PAGE_SLUG;
		}
		return $submenu_file;
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_hub = ( self::PAGE_SLUG === $page );
		$is_edit = $screen && self::POST_TYPE === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true );
		if ( ! $is_hub && ! $is_edit ) {
			return $classes;
		}
		$classes .= ' nestform-admin-screen';
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page && 'nestform_page_' . self::PAGE_SLUG !== $hook && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver = (string) filemtime( nestform_admin_css_path() );
		wp_enqueue_style(
			'nestform-admin',
			nestform_admin_css_url(),
			nestform_admin_style_deps(),
			$ver ? $ver : NESTFORM_VERSION
		);
		$ver_js = (string) filemtime( nestform_admin_js_path( 'hub-list.js' ) );
		wp_enqueue_script(
			'nestform-hub-list',
			nestform_admin_js_url( 'hub-list.js' ),
			array(),
			$ver_js ? $ver_js : NESTFORM_VERSION,
			true
		);
		wp_localize_script(
			'nestform-hub-list',
			'nestformHub',
			array(
				'pageSize' => 20,
				'i18n'     => array(
					/* translators: %d: visible count */
					'showing'      => __( 'Showing %d', 'nestform' ),
					/* translators: 1: first item, 2: last item, 3: total */
					'showingRange' => __( 'Showing %1$s–%2$s of %3$s', 'nestform' ),
					'copied'       => __( 'Copied', 'nestform' ),
					'pagerLabel'   => __( 'Forms pagination', 'nestform' ),
				),
			)
		);
	}

	/**
	 * @return array<int, WP_Post>
	 */
	private static function get_forms() {
		$forms = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		if ( ! is_array( $forms ) ) {
			return array();
		}
		if ( ! class_exists( 'Nestform_Submissions' ) ) {
			return $forms;
		}
		$allowed = Nestform_Submissions::accessible_form_ids();
		if ( null === $allowed ) {
			return $forms;
		}
		if ( array() === $allowed ) {
			return array();
		}
		$map = array_fill_keys( array_map( 'intval', $allowed ), true );
		$out = array();
		foreach ( $forms as $form ) {
			if ( isset( $map[ (int) $form->ID ] ) ) {
				$out[] = $form;
			}
		}
		return $out;
	}

	public static function render_hub() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view forms.', 'nestform' ) );
		}

		$forms    = self::get_forms();
		$form_ids = array();
		foreach ( $forms as $form ) {
			$form_ids[] = (int) $form->ID;
		}
		$last_map = class_exists( 'Nestform_Submissions' )
			? Nestform_Submissions::latest_entry_times( $form_ids )
			: array();

		$rows = array();
		foreach ( $forms as $form ) {
			$fields  = Nestform_Form_Config::get_fields( (int) $form->ID );
			$form_id = (int) $form->ID;
			$rows[]  = array(
				'form'   => $form,
				'count'  => Nestform_Submissions::count_for_form( $form_id ),
				'new'    => Nestform_Submissions::count_new_for_form( $form_id ),
				'fields' => is_array( $fields ) ? count( $fields ) : 0,
				'last'   => isset( $last_map[ $form_id ] ) ? (int) $last_map[ $form_id ] : 0,
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['new'] !== $b['new'] ) {
					return $b['new'] <=> $a['new'];
				}
				if ( $a['count'] === $b['count'] ) {
					return strcasecmp( (string) $a['form']->post_title, (string) $b['form']->post_title );
				}
				return $b['count'] <=> $a['count'];
			}
		);

		$total_forms   = count( $rows );
		$published_n   = 0;
		$drafts_n      = 0;
		$with_new_n    = 0;
		$total_entries = 0;
		foreach ( $rows as $row ) {
			$total_entries += (int) $row['count'];
			if ( (int) $row['new'] > 0 ) {
				++$with_new_n;
			}
			if ( 'publish' === $row['form']->post_status ) {
				++$published_n;
			} else {
				++$drafts_n;
			}
		}

		$new_url = admin_url( 'post-new.php?post_type=' . self::POST_TYPE );
		$add_btn = '<a class="nestform-btn nestform-btn--primary" href="' . esc_url( $new_url ) . '">' . nestform_admin_icon_html( 'plus' ) . ' ' . esc_html__( 'Add New', 'nestform' ) . '</a>';
		if ( class_exists( 'Nestform_Form_IO' ) ) {
			$add_btn = Nestform_Form_IO::hub_import_menu_html() . $add_btn;
		}
		?>
		<div class="wrap nestform-hub" data-nestform-hub>
			<?php
			nestform_render_page_head(
				array(
					'title'        => __( 'Forms', 'nestform' ),
					'description'  => __( 'Edit forms, open entry inboxes, and copy shortcodes.', 'nestform' ),
					'actions_html' => $add_btn,
				)
			);
			?>
			<?php if ( array() === $rows ) : ?>
				<div class="nestform-hub__empty-state">
					<p class="nestform-hub__empty-state-title"><?php esc_html_e( 'No forms yet', 'nestform' ); ?></p>
					<p class="nestform-hub__empty-state-text"><?php esc_html_e( 'Create a blank form, or pick a starter template in the builder right after.', 'nestform' ); ?></p>
					<div class="nestform-hub__empty-actions">
						<a class="nestform-btn nestform-btn--primary" href="<?php echo esc_url( $new_url ); ?>">
							<?php nestform_admin_icon( 'plus' ); ?>
							<?php esc_html_e( 'Create your first form', 'nestform' ); ?>
						</a>
					</div>
					<?php if ( class_exists( 'Nestform_Templates' ) ) : ?>
						<?php
						$show = array();
						foreach ( Nestform_Templates::all() as $tpl_key => $tpl ) {
							if ( ! Nestform_Templates::template_allowed( $tpl_key ) ) {
								continue;
							}
							$show[] = $tpl;
							if ( count( $show ) >= 4 ) {
								break;
							}
						}
						?>
						<?php if ( array() !== $show ) : ?>
							<div class="nestform-hub__tpl-preview">
								<p class="nestform-hub__tpl-label"><?php esc_html_e( 'Popular starters', 'nestform' ); ?></p>
								<ul class="nestform-hub__tpl-list">
									<?php foreach ( $show as $tpl ) : ?>
										<li class="nestform-hub__tpl-item">
											<strong><?php echo esc_html( (string) $tpl['label'] ); ?></strong>
											<span><?php echo esc_html( (string) $tpl['description'] ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
								<p class="nestform-hub__tpl-note"><?php esc_html_e( 'Templates open in the builder after you create a form.', 'nestform' ); ?></p>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="nestform-hub__stats" role="toolbar" aria-label="<?php esc_attr_e( 'Filter forms', 'nestform' ); ?>">
					<button type="button" class="nestform-hub__stat is-active" data-nestform-hub-chip="all" aria-pressed="true">
						<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $total_forms ) ); ?></span>
						<span class="nestform-hub__stat-label"><?php esc_html_e( 'Forms', 'nestform' ); ?></span>
					</button>
					<button type="button" class="nestform-hub__stat" data-nestform-hub-chip="publish" aria-pressed="false">
						<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $published_n ) ); ?></span>
						<span class="nestform-hub__stat-label"><?php esc_html_e( 'Published', 'nestform' ); ?></span>
					</button>
					<button type="button" class="nestform-hub__stat" data-nestform-hub-chip="draft" aria-pressed="false">
						<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $drafts_n ) ); ?></span>
						<span class="nestform-hub__stat-label"><?php esc_html_e( 'Drafts', 'nestform' ); ?></span>
					</button>
					<button type="button" class="nestform-hub__stat<?php echo $with_new_n > 0 ? ' nestform-hub__stat--new' : ''; ?>" data-nestform-hub-chip="new" aria-pressed="false">
						<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $with_new_n ) ); ?></span>
						<span class="nestform-hub__stat-label"><?php esc_html_e( 'With new', 'nestform' ); ?></span>
					</button>
					<a class="nestform-hub__stat" href="<?php echo esc_url( Nestform_Submissions::hub_url() ); ?>">
						<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $total_entries ) ); ?></span>
						<span class="nestform-hub__stat-label"><?php esc_html_e( 'Entries', 'nestform' ); ?></span>
					</a>
				</div>
				<div class="nestform-hub__toolbar">
					<label class="nestform-hub__search-wrap">
						<span class="nestform-hub__search-icon" aria-hidden="true"><?php nestform_admin_icon( 'search' ); ?></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Search forms', 'nestform' ); ?></span>
						<input
							type="search"
							class="nestform-hub__search"
							placeholder="<?php esc_attr_e( 'Search by title or ID…', 'nestform' ); ?>"
							data-nestform-hub-search
							autocomplete="off"
						/>
					</label>
					<label class="nestform-hub__sort-wrap">
						<span class="screen-reader-text"><?php esc_html_e( 'Sort', 'nestform' ); ?></span>
						<select class="nestform-hub__sort" data-nestform-hub-sort>
							<option value="new"><?php esc_html_e( 'Most new', 'nestform' ); ?></option>
							<option value="count"><?php esc_html_e( 'Most entries', 'nestform' ); ?></option>
							<option value="last"><?php esc_html_e( 'Recent activity', 'nestform' ); ?></option>
							<option value="fields"><?php esc_html_e( 'Most fields', 'nestform' ); ?></option>
							<option value="title"><?php esc_html_e( 'Title A–Z', 'nestform' ); ?></option>
							<option value="id"><?php esc_html_e( 'ID', 'nestform' ); ?></option>
						</select>
					</label>
					<label class="nestform-hub__filter-wrap">
						<input type="checkbox" data-nestform-hub-has-count />
						<span><?php esc_html_e( 'Only with entries', 'nestform' ); ?></span>
					</label>
					<span class="nestform-hub__result" data-nestform-hub-result hidden></span>
				</div>
				<nav class="nestform-hub__pager" data-nestform-hub-pager hidden aria-label="<?php esc_attr_e( 'Forms pagination', 'nestform' ); ?>"></nav>
				<div class="nestform-hub__table" data-nestform-hub-list>
					<div class="nestform-hub__thead">
						<div class="nestform-hub__th nestform-hub__th--form"><?php esc_html_e( 'Form', 'nestform' ); ?></div>
						<div class="nestform-hub__th nestform-hub__th--activity"><?php esc_html_e( 'Last entry', 'nestform' ); ?></div>
						<div class="nestform-hub__th nestform-hub__th--entries"><?php esc_html_e( 'Entries', 'nestform' ); ?></div>
						<div class="nestform-hub__th nestform-hub__th--actions"></div>
					</div>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$form      = $row['form'];
						$count     = (int) $row['count'];
						$new       = (int) $row['new'];
						$fields_n  = (int) $row['fields'];
						$last_ts   = (int) $row['last'];
						$status    = get_post_status_object( $form->post_status );
						$has_title = $form->post_title !== '';
						$title     = $has_title ? $form->post_title : __( 'Untitled form', 'nestform' );
						$edit_url  = get_edit_post_link( (int) $form->ID, 'raw' );
						$entry_url = $new > 0
							? Nestform_Submissions::list_url( (int) $form->ID, Nestform_Submissions::STATUS_NEW )
							: Nestform_Submissions::list_url( (int) $form->ID );
						$shortcode = '[thimbleform id="' . (int) $form->ID . '"]';
						$item_class = 'nestform-hub__row' . ( $new > 0 ? ' nestform-hub__row--has-new' : '' );
						$status_mod = ( 'publish' === $form->post_status ) ? 'ok' : 'draft';
						$last_label = $last_ts > 0
							? sprintf(
								/* translators: %s: human time diff */
								__( '%s ago', 'nestform' ),
								human_time_diff( $last_ts, time() )
							)
							: '—';
						?>
						<div
							class="<?php echo esc_attr( $item_class ); ?>"
							data-nestform-hub-row
							<?php if ( $edit_url ) : ?>
								data-edit-url="<?php echo esc_url( $edit_url ); ?>"
								role="link"
								tabindex="0"
							<?php endif; ?>
							data-id="<?php echo (int) $form->ID; ?>"
							data-count="<?php echo esc_attr( (string) $count ); ?>"
							data-fields="<?php echo esc_attr( (string) $fields_n ); ?>"
							data-new="<?php echo esc_attr( (string) $new ); ?>"
							data-last="<?php echo esc_attr( (string) $last_ts ); ?>"
							data-status="<?php echo esc_attr( (string) $form->post_status ); ?>"
							data-title="<?php echo esc_attr( $title ); ?>"
						>
							<div class="nestform-hub__td nestform-hub__td--form">
								<div class="nestform-hub__heading">
									<a class="nestform-hub__title<?php echo $has_title ? '' : ' nestform-hub__title--untitled'; ?>" href="<?php echo esc_url( $edit_url ); ?>">
										<?php echo esc_html( $title ); ?>
									</a>
									<?php if ( $new > 0 ) : ?>
										<a class="nestform-badge nestform-badge--new" href="<?php echo esc_url( $entry_url ); ?>" onclick="event.stopPropagation();">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: new entry count */
													_n( '%s new', '%s new', $new, 'nestform' ),
													number_format_i18n( $new )
												)
											);
											?>
										</a>
									<?php endif; ?>
								</div>
								<div class="nestform-hub__meta">
									<?php if ( $status ) : ?>
										<span class="nestform-badge nestform-badge--<?php echo esc_attr( $status_mod ); ?>"><?php echo esc_html( strtoupper( $status->name ) ); ?></span>
									<?php endif; ?>
									<?php
									/**
									 * Extra form badges for the Forms hub (e.g. Job for Recruiting).
									 *
									 * Each item: label (string), url (string, optional), mod (string CSS modifier).
									 *
									 * @param array<int, array{label:string,url?:string,mod?:string}> $badges Badges.
									 * @param WP_Post                                                   $form   Form post.
									 */
									$hub_badges = apply_filters( 'nestform_hub_form_badges', array(), $form );
									if ( is_array( $hub_badges ) ) {
										foreach ( $hub_badges as $badge ) {
											if ( empty( $badge['label'] ) ) {
												continue;
											}
											$mod  = isset( $badge['mod'] ) ? sanitize_html_class( (string) $badge['mod'] ) : '';
											$url  = isset( $badge['url'] ) ? (string) $badge['url'] : '';
											$cls  = 'nestform-badge' . ( $mod !== '' ? ' nestform-badge--' . $mod : '' );
											$lbl  = (string) $badge['label'];
											if ( $url !== '' ) {
												printf(
													'<a class="%1$s" href="%2$s" onclick="event.stopPropagation();">%3$s</a>',
													esc_attr( $cls ),
													esc_url( $url ),
													esc_html( $lbl )
												);
											} else {
												printf(
													'<span class="%1$s">%2$s</span>',
													esc_attr( $cls ),
													esc_html( $lbl )
												);
											}
										}
									}
									?>
									<span class="nestform-hub__id">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: field count */
												_n( '%s field', '%s fields', $fields_n, 'nestform' ),
												number_format_i18n( $fields_n )
											)
										);
										?>
									</span>
								</div>
							</div>
							<div class="nestform-hub__td nestform-hub__td--activity">
								<span class="nestform-hub__activity<?php echo $last_ts <= 0 ? ' nestform-hub__activity--empty' : ''; ?>"><?php echo esc_html( $last_label ); ?></span>
							</div>
							<div class="nestform-hub__td nestform-hub__td--entries">
								<a
									class="nestform-hub__count"
									href="<?php echo esc_url( $entry_url ); ?>"
									onclick="event.stopPropagation();"
									aria-label="<?php
									echo esc_attr(
										sprintf(
											/* translators: %s: form title */
											__( 'Open entries for %s', 'nestform' ),
											$title
										)
									);
									?>"
								><?php echo esc_html( (string) $count ); ?></a>
							</div>
							<div class="nestform-hub__td nestform-hub__td--actions">
								<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( $edit_url ); ?>">
									<?php esc_html_e( 'Edit', 'nestform' ); ?>
								</a>
								<?php
								/**
								 * Extra primary actions before the “more” menu (e.g. Candidates).
								 *
								 * @param string  $html      Escaped HTML.
								 * @param WP_Post $form      Form post.
								 * @param string  $entry_url Entries/candidates URL.
								 */
								$extra_actions = (string) apply_filters( 'nestform_hub_row_actions_before_more', '', $form, $entry_url );
								echo wp_kses_post( $extra_actions );
								?>
								<div class="nestform-hub__more" data-nestform-hub-more>
									<button
										type="button"
										class="nestform-btn nestform-btn--ghost nestform-hub__more-toggle"
										aria-expanded="false"
										aria-haspopup="true"
										aria-label="<?php esc_attr_e( 'More actions', 'nestform' ); ?>"
									>
										···
									</button>
									<div class="nestform-hub__more-menu" hidden>
										<a class="nestform-btn nestform-btn--ghost" href="<?php echo esc_url( $entry_url ); ?>" onclick="event.stopPropagation();">
											<?php
											/**
											 * Label for the hub “Entries” action (Recruiting may use “Candidates”).
											 *
											 * @param string  $label Default label.
											 * @param WP_Post $form  Form post.
											 */
											echo esc_html( (string) apply_filters( 'nestform_hub_entries_action_label', __( 'Entries', 'nestform' ), $form ) );
											?>
										</a>
										<a class="nestform-btn nestform-btn--ghost" href="<?php echo esc_url( self::duplicate_url( (int) $form->ID ) ); ?>">
											<?php esc_html_e( 'Duplicate', 'nestform' ); ?>
										</a>
										<?php if ( class_exists( 'Nestform_Form_IO' ) ) : ?>
											<a class="nestform-btn nestform-btn--ghost" href="<?php echo esc_url( Nestform_Form_IO::export_url( (int) $form->ID ) ); ?>">
												<?php esc_html_e( 'Export', 'nestform' ); ?>
											</a>
										<?php endif; ?>
										<button
											type="button"
											class="nestform-btn nestform-btn--ghost"
											data-nestform-hub-copy="<?php echo esc_attr( $shortcode ); ?>"
										>
											<?php esc_html_e( 'Copy shortcode', 'nestform' ); ?>
										</button>
										<?php
										$trash_url = get_delete_post_link( (int) $form->ID, '', false );
										if ( $trash_url && current_user_can( 'delete_post', (int) $form->ID ) ) :
											$confirm = $count > 0
												? sprintf(
													/* translators: 1: form title, 2: entry count */
													__( 'Move “%1$s” to Trash? Its %2$d entries will stay, but the form shortcode will stop working.', 'nestform' ),
													$title,
													$count
												)
												: sprintf(
													/* translators: %s: form title */
													__( 'Move “%s” to Trash?', 'nestform' ),
													$title
												);
											?>
											<a
												class="nestform-btn nestform-btn--ghost nestform-btn--danger-text"
												href="<?php echo esc_url( $trash_url ); ?>"
												data-nestform-hub-delete
												data-confirm="<?php echo esc_attr( $confirm ); ?>"
											>
												<?php esc_html_e( 'Trash', 'nestform' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<nav class="nestform-hub__pager" data-nestform-hub-pager-bottom hidden aria-label="<?php esc_attr_e( 'Forms pagination', 'nestform' ); ?>"></nav>
				<p class="nestform-hub__empty" data-nestform-hub-empty hidden>
					<?php esc_html_e( 'No forms match your search.', 'nestform' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function duplicate_url( $form_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'nestform_duplicate',
					'form_id' => (int) $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			'nestform_duplicate_' . (int) $form_id
		);
	}

	public static function handle_duplicate() {
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
		if ( $form_id <= 0 || self::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'nestform' ), 400 );
		}
		check_admin_referer( 'nestform_duplicate_' . $form_id );
		if ( ! current_user_can( 'edit_post', $form_id ) || ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this form.', 'nestform' ), 403 );
		}

		$new_id = self::duplicate_form( $form_id );
		if ( ! $new_id ) {
			wp_die( esc_html__( 'Could not duplicate form.', 'nestform' ), 500 );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => self::PAGE_SLUG,
					'nestform_duplicated'    => $new_id,
				),
				admin_url( 'edit.php?post_type=' . self::POST_TYPE )
			)
		);
		exit;
	}

	/**
	 * @param int $form_id Source form ID.
	 * @return int New form ID or 0.
	 */
	public static function duplicate_form( $form_id ) {
		$source = get_post( $form_id );
		if ( ! $source || self::POST_TYPE !== $source->post_type ) {
			return 0;
		}

		$title = $source->post_title !== ''
			? sprintf(
				/* translators: %s: original form title */
				__( '%s (copy)', 'nestform' ),
				$source->post_title
			)
			: __( '(no title) (copy)', 'nestform' );

		$new_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return 0;
		}

		$meta_keys = array(
			Nestform_Form_Config::META_FIELDS,
			Nestform_Form_Config::META_MESSAGES,
			Nestform_Form_Config::META_MAIL,
			Nestform_Form_Config::META_SETTINGS,
		);
		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $form_id, $key, true );
			if ( '' !== $value && null !== $value ) {
				update_post_meta( (int) $new_id, $key, $value );
			}
		}

		return (int) $new_id;
	}

	public static function duplicate_notice() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['nestform_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$new_id = (int) $_GET['nestform_duplicated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$link   = get_edit_post_link( $new_id, 'raw' );
		if ( ! $link ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Form duplicated as draft.', 'nestform' ),
			esc_url( $link ),
			esc_html__( 'Edit copy', 'nestform' )
		);
	}
}
