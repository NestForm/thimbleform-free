<?php
/**
 * CPT: thimbleform
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Post_Type {

	const POST_TYPE = 'thimbleform';
	const PAGE_SLUG = 'thimbleform-forms';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'replace_list_menu' ), 30 );
		add_action( 'load-edit.php', array( __CLASS__, 'redirect_default_list' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_action( 'admin_post_thimbleform_duplicate', array( __CLASS__, 'handle_duplicate' ) );
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
					'name'                     => __( 'Forms', 'thimbleform' ),
					'singular_name'            => __( 'Form', 'thimbleform' ),
					'add_new'                  => __( 'Add New', 'thimbleform' ),
					'add_new_item'             => __( 'Add New Form', 'thimbleform' ),
					'edit_item'                => __( 'Edit Form', 'thimbleform' ),
					'new_item'                 => __( 'New Form', 'thimbleform' ),
					'view_item'                => __( 'View Form', 'thimbleform' ),
					'search_items'             => __( 'Search Forms', 'thimbleform' ),
					'not_found'                => __( 'No forms found.', 'thimbleform' ),
					'not_found_in_trash'       => __( 'No forms found in Trash.', 'thimbleform' ),
					'menu_name'                => __( 'Thimbleform', 'thimbleform' ),
					'item_published'           => __( 'Form published.', 'thimbleform' ),
					'item_published_privately' => __( 'Form published privately.', 'thimbleform' ),
					'item_reverted_to_draft'   => __( 'Form reverted to draft.', 'thimbleform' ),
					'item_trashed'             => __( 'Form moved to the Trash.', 'thimbleform' ),
					'item_scheduled'           => __( 'Form scheduled.', 'thimbleform' ),
					'item_updated'             => __( 'Form updated.', 'thimbleform' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 26,
				'menu_icon'           => thimbleform_logo_url(),
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
				__( '%1$s at %2$s', 'thimbleform' ),
				date_i18n( _x( 'M j, Y', 'publish box date format', 'thimbleform' ), strtotime( $post->post_date ) ),
				date_i18n( _x( 'H:i', 'publish box time format', 'thimbleform' ), strtotime( $post->post_date ) )
			);
		}

		$revision = '';
		if ( isset( $_GET['revision'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$revision = wp_post_revision_title( (int) $_GET['revision'], false ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Form updated.', 'thimbleform' ),
			2  => __( 'Custom field updated.', 'thimbleform' ),
			3  => __( 'Custom field deleted.', 'thimbleform' ),
			4  => __( 'Form updated.', 'thimbleform' ),
			5  => $revision ? sprintf(
				/* translators: %s: Date and time of the revision. */
				__( 'Form restored to revision from %s.', 'thimbleform' ),
				$revision
			) : false,
			6  => __( 'Form published.', 'thimbleform' ),
			7  => __( 'Form saved.', 'thimbleform' ),
			8  => __( 'Form submitted.', 'thimbleform' ),
			9  => sprintf(
				/* translators: %s: Scheduled date for the form. */
				__( 'Form scheduled for: %s.', 'thimbleform' ),
				'<strong>' . $scheduled_date . '</strong>'
			),
			10 => __( 'Form draft saved.', 'thimbleform' ),
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
		$updated_msg = _n( '%s form updated.', '%s forms updated.', $updated, 'thimbleform' );
		/* translators: %s: Number of forms. */
		$locked_msg = _n( '%s form not updated, somebody is editing it.', '%s forms not updated, somebody is editing them.', $locked, 'thimbleform' );
		/* translators: %s: Number of forms. */
		$deleted_msg = _n( '%s form permanently deleted.', '%s forms permanently deleted.', $deleted, 'thimbleform' );
		/* translators: %s: Number of forms. */
		$trashed_msg = _n( '%s form moved to the Trash.', '%s forms moved to the Trash.', $trashed, 'thimbleform' );
		/* translators: %s: Number of forms. */
		$untrashed_msg = _n( '%s form restored from the Trash.', '%s forms restored from the Trash.', $untrashed, 'thimbleform' );

		$bulk_messages[ self::POST_TYPE ] = array(
			'updated'   => $updated_msg,
			'locked'    => ( 1 === $locked )
				? __( '1 form not updated, somebody is editing it.', 'thimbleform' )
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
		$css = '#adminmenu .menu-icon-thimbleform .wp-menu-image,#adminmenu #menu-posts-thimbleform .wp-menu-image{width:36px;height:34px;overflow:hidden}'
			. '#adminmenu .menu-icon-thimbleform .wp-menu-image img,#adminmenu #menu-posts-thimbleform .wp-menu-image img,#adminmenu .wp-menu-image img[src*="logo.webp"]{box-sizing:border-box;display:block;width:20px!important;height:20px!important;max-width:20px!important;max-height:20px!important;margin:7px auto 0;padding:0!important;object-fit:contain;opacity:1}';
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
			__( 'Forms', 'thimbleform' ),
			__( 'All Forms', 'thimbleform' ),
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
		if ( class_exists( 'Thimbleform_Importer' ) && Thimbleform_Importer::PAGE_SLUG === $page ) {
			return Thimbleform_Importer::PAGE_SLUG;
		}
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return $submenu_file;
		}
		if ( 'thimbleform_page_' . self::PAGE_SLUG === $screen->id ) {
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
		$classes .= ' thimbleform-admin-screen';
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page && 'thimbleform_page_' . self::PAGE_SLUG !== $hook && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver = (string) filemtime( thimbleform_admin_css_path() );
		wp_enqueue_style(
			'thimbleform-admin',
			thimbleform_admin_css_url(),
			thimbleform_admin_style_deps(),
			$ver ? $ver : THIMBLEFORM_VERSION
		);
		$ver_js = (string) filemtime( thimbleform_admin_js_path( 'hub-list.js' ) );
		wp_enqueue_script(
			'thimbleform-hub-list',
			thimbleform_admin_js_url( 'hub-list.js' ),
			array(),
			$ver_js ? $ver_js : THIMBLEFORM_VERSION,
			true
		);
		wp_localize_script(
			'thimbleform-hub-list',
			'thimbleformHub',
			array(
				'pageSize' => 20,
				'i18n'     => array(
					/* translators: %d: visible count */
					'showing'      => __( 'Showing %d', 'thimbleform' ),
					/* translators: 1: first item, 2: last item, 3: total */
					'showingRange' => __( 'Showing %1$s–%2$s of %3$s', 'thimbleform' ),
					'copied'       => __( 'Copied', 'thimbleform' ),
					'pagerLabel'   => __( 'Forms pagination', 'thimbleform' ),
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
		if ( ! class_exists( 'Thimbleform_Submissions' ) ) {
			return $forms;
		}
		$allowed = Thimbleform_Submissions::accessible_form_ids();
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
			wp_die( esc_html__( 'You do not have permission to view forms.', 'thimbleform' ) );
		}

		$forms    = self::get_forms();
		$form_ids = array();
		foreach ( $forms as $form ) {
			$form_ids[] = (int) $form->ID;
		}
		$last_map = class_exists( 'Thimbleform_Submissions' )
			? Thimbleform_Submissions::latest_entry_times( $form_ids )
			: array();

		$rows = array();
		foreach ( $forms as $form ) {
			$fields  = Thimbleform_Form_Config::get_fields( (int) $form->ID );
			$form_id = (int) $form->ID;
			$rows[]  = array(
				'form'   => $form,
				'count'  => Thimbleform_Submissions::count_for_form( $form_id ),
				'new'    => Thimbleform_Submissions::count_new_for_form( $form_id ),
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
		$add_btn = '<a class="thimbleform-btn thimbleform-btn--primary" href="' . esc_url( $new_url ) . '">' . thimbleform_admin_icon_html( 'plus' ) . ' ' . esc_html__( 'Add New', 'thimbleform' ) . '</a>';
		if ( class_exists( 'Thimbleform_Form_IO' ) ) {
			$add_btn = Thimbleform_Form_IO::hub_import_menu_html() . $add_btn;
		}
		?>
		<div class="wrap thimbleform-hub" data-thimbleform-hub>
			<?php
			thimbleform_render_page_head(
				array(
					'title'        => __( 'Forms', 'thimbleform' ),
					'description'  => __( 'Edit forms, open entry inboxes, and copy shortcodes.', 'thimbleform' ),
					'actions_html' => $add_btn,
				)
			);
			?>
			<?php if ( array() === $rows ) : ?>
				<div class="thimbleform-hub__empty-state">
					<p class="thimbleform-hub__empty-state-title"><?php esc_html_e( 'No forms yet', 'thimbleform' ); ?></p>
					<p class="thimbleform-hub__empty-state-text"><?php esc_html_e( 'Create a blank form, or pick a starter template in the builder right after.', 'thimbleform' ); ?></p>
					<div class="thimbleform-hub__empty-actions">
						<a class="thimbleform-btn thimbleform-btn--primary" href="<?php echo esc_url( $new_url ); ?>">
							<?php thimbleform_admin_icon( 'plus' ); ?>
							<?php esc_html_e( 'Create your first form', 'thimbleform' ); ?>
						</a>
					</div>
					<?php if ( class_exists( 'Thimbleform_Templates' ) ) : ?>
						<?php
						$show = array();
						foreach ( Thimbleform_Templates::all() as $tpl_key => $tpl ) {
							if ( ! Thimbleform_Templates::template_allowed( $tpl_key ) ) {
								continue;
							}
							$show[] = $tpl;
							if ( count( $show ) >= 4 ) {
								break;
							}
						}
						?>
						<?php if ( array() !== $show ) : ?>
							<div class="thimbleform-hub__tpl-preview">
								<p class="thimbleform-hub__tpl-label"><?php esc_html_e( 'Popular starters', 'thimbleform' ); ?></p>
								<ul class="thimbleform-hub__tpl-list">
									<?php foreach ( $show as $tpl ) : ?>
										<li class="thimbleform-hub__tpl-item">
											<strong><?php echo esc_html( (string) $tpl['label'] ); ?></strong>
											<span><?php echo esc_html( (string) $tpl['description'] ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
								<p class="thimbleform-hub__tpl-note"><?php esc_html_e( 'Templates open in the builder after you create a form.', 'thimbleform' ); ?></p>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="thimbleform-hub__stats" role="toolbar" aria-label="<?php esc_attr_e( 'Filter forms', 'thimbleform' ); ?>">
					<button type="button" class="thimbleform-hub__stat is-active" data-thimbleform-hub-chip="all" aria-pressed="true">
						<span class="thimbleform-hub__stat-value"><?php echo esc_html( number_format_i18n( $total_forms ) ); ?></span>
						<span class="thimbleform-hub__stat-label"><?php esc_html_e( 'Forms', 'thimbleform' ); ?></span>
					</button>
					<button type="button" class="thimbleform-hub__stat" data-thimbleform-hub-chip="publish" aria-pressed="false">
						<span class="thimbleform-hub__stat-value"><?php echo esc_html( number_format_i18n( $published_n ) ); ?></span>
						<span class="thimbleform-hub__stat-label"><?php esc_html_e( 'Published', 'thimbleform' ); ?></span>
					</button>
					<button type="button" class="thimbleform-hub__stat" data-thimbleform-hub-chip="draft" aria-pressed="false">
						<span class="thimbleform-hub__stat-value"><?php echo esc_html( number_format_i18n( $drafts_n ) ); ?></span>
						<span class="thimbleform-hub__stat-label"><?php esc_html_e( 'Drafts', 'thimbleform' ); ?></span>
					</button>
					<button type="button" class="thimbleform-hub__stat<?php echo $with_new_n > 0 ? ' thimbleform-hub__stat--new' : ''; ?>" data-thimbleform-hub-chip="new" aria-pressed="false">
						<span class="thimbleform-hub__stat-value"><?php echo esc_html( number_format_i18n( $with_new_n ) ); ?></span>
						<span class="thimbleform-hub__stat-label"><?php esc_html_e( 'With new', 'thimbleform' ); ?></span>
					</button>
					<a class="thimbleform-hub__stat" href="<?php echo esc_url( Thimbleform_Submissions::hub_url() ); ?>">
						<span class="thimbleform-hub__stat-value"><?php echo esc_html( number_format_i18n( $total_entries ) ); ?></span>
						<span class="thimbleform-hub__stat-label"><?php esc_html_e( 'Entries', 'thimbleform' ); ?></span>
					</a>
				</div>
				<div class="thimbleform-hub__toolbar">
					<label class="thimbleform-hub__search-wrap">
						<span class="thimbleform-hub__search-icon" aria-hidden="true"><?php thimbleform_admin_icon( 'search' ); ?></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Search forms', 'thimbleform' ); ?></span>
						<input
							type="search"
							class="thimbleform-hub__search"
							placeholder="<?php esc_attr_e( 'Search by title or ID…', 'thimbleform' ); ?>"
							data-thimbleform-hub-search
							autocomplete="off"
						/>
					</label>
					<label class="thimbleform-hub__sort-wrap">
						<span class="screen-reader-text"><?php esc_html_e( 'Sort', 'thimbleform' ); ?></span>
						<select class="thimbleform-hub__sort" data-thimbleform-hub-sort>
							<option value="new"><?php esc_html_e( 'Most new', 'thimbleform' ); ?></option>
							<option value="count"><?php esc_html_e( 'Most entries', 'thimbleform' ); ?></option>
							<option value="last"><?php esc_html_e( 'Recent activity', 'thimbleform' ); ?></option>
							<option value="fields"><?php esc_html_e( 'Most fields', 'thimbleform' ); ?></option>
							<option value="title"><?php esc_html_e( 'Title A–Z', 'thimbleform' ); ?></option>
							<option value="id"><?php esc_html_e( 'ID', 'thimbleform' ); ?></option>
						</select>
					</label>
					<label class="thimbleform-hub__filter-wrap">
						<input type="checkbox" data-thimbleform-hub-has-count />
						<span><?php esc_html_e( 'Only with entries', 'thimbleform' ); ?></span>
					</label>
					<span class="thimbleform-hub__result" data-thimbleform-hub-result hidden></span>
				</div>
				<nav class="thimbleform-hub__pager" data-thimbleform-hub-pager hidden aria-label="<?php esc_attr_e( 'Forms pagination', 'thimbleform' ); ?>"></nav>
				<div class="thimbleform-hub__table" data-thimbleform-hub-list>
					<div class="thimbleform-hub__thead">
						<div class="thimbleform-hub__th thimbleform-hub__th--form"><?php esc_html_e( 'Form', 'thimbleform' ); ?></div>
						<div class="thimbleform-hub__th thimbleform-hub__th--activity"><?php esc_html_e( 'Last entry', 'thimbleform' ); ?></div>
						<div class="thimbleform-hub__th thimbleform-hub__th--entries"><?php esc_html_e( 'Entries', 'thimbleform' ); ?></div>
						<div class="thimbleform-hub__th thimbleform-hub__th--actions"></div>
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
						$title     = $has_title ? $form->post_title : __( 'Untitled form', 'thimbleform' );
						$edit_url  = get_edit_post_link( (int) $form->ID, 'raw' );
						$entry_url = $new > 0
							? Thimbleform_Submissions::list_url( (int) $form->ID, Thimbleform_Submissions::STATUS_NEW )
							: Thimbleform_Submissions::list_url( (int) $form->ID );
						$shortcode = '[thimbleform id="' . (int) $form->ID . '"]';
						$item_class = 'thimbleform-hub__row' . ( $new > 0 ? ' thimbleform-hub__row--has-new' : '' );
						$status_mod = ( 'publish' === $form->post_status ) ? 'ok' : 'draft';
						$last_label = $last_ts > 0
							? sprintf(
								/* translators: %s: human time diff */
								__( '%s ago', 'thimbleform' ),
								human_time_diff( $last_ts, time() )
							)
							: '—';
						?>
						<div
							class="<?php echo esc_attr( $item_class ); ?>"
							data-thimbleform-hub-row
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
							<div class="thimbleform-hub__td thimbleform-hub__td--form">
								<div class="thimbleform-hub__heading">
									<a class="thimbleform-hub__title<?php echo $has_title ? '' : ' thimbleform-hub__title--untitled'; ?>" href="<?php echo esc_url( $edit_url ); ?>">
										<?php echo esc_html( $title ); ?>
									</a>
									<?php if ( $new > 0 ) : ?>
										<a class="thimbleform-badge thimbleform-badge--new" href="<?php echo esc_url( $entry_url ); ?>" onclick="event.stopPropagation();">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: new entry count */
													_n( '%s new', '%s new', $new, 'thimbleform' ),
													number_format_i18n( $new )
												)
											);
											?>
										</a>
									<?php endif; ?>
								</div>
								<div class="thimbleform-hub__meta">
									<?php if ( $status ) : ?>
										<span class="thimbleform-badge thimbleform-badge--<?php echo esc_attr( $status_mod ); ?>"><?php echo esc_html( strtoupper( $status->name ) ); ?></span>
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
									$hub_badges = apply_filters( 'thimbleform_hub_form_badges', array(), $form );
									if ( is_array( $hub_badges ) ) {
										foreach ( $hub_badges as $badge ) {
											if ( empty( $badge['label'] ) ) {
												continue;
											}
											$mod  = isset( $badge['mod'] ) ? sanitize_html_class( (string) $badge['mod'] ) : '';
											$url  = isset( $badge['url'] ) ? (string) $badge['url'] : '';
											$cls  = 'thimbleform-badge' . ( $mod !== '' ? ' thimbleform-badge--' . $mod : '' );
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
									<span class="thimbleform-hub__id">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: field count */
												_n( '%s field', '%s fields', $fields_n, 'thimbleform' ),
												number_format_i18n( $fields_n )
											)
										);
										?>
									</span>
								</div>
							</div>
							<div class="thimbleform-hub__td thimbleform-hub__td--activity">
								<span class="thimbleform-hub__activity<?php echo $last_ts <= 0 ? ' thimbleform-hub__activity--empty' : ''; ?>"><?php echo esc_html( $last_label ); ?></span>
							</div>
							<div class="thimbleform-hub__td thimbleform-hub__td--entries">
								<a
									class="thimbleform-hub__count"
									href="<?php echo esc_url( $entry_url ); ?>"
									onclick="event.stopPropagation();"
									aria-label="<?php
									echo esc_attr(
										sprintf(
											/* translators: %s: form title */
											__( 'Open entries for %s', 'thimbleform' ),
											$title
										)
									);
									?>"
								><?php echo esc_html( (string) $count ); ?></a>
							</div>
							<div class="thimbleform-hub__td thimbleform-hub__td--actions">
								<a class="thimbleform-btn thimbleform-btn--outline" href="<?php echo esc_url( $edit_url ); ?>">
									<?php esc_html_e( 'Edit', 'thimbleform' ); ?>
								</a>
								<?php
								/**
								 * Extra primary actions before the “more” menu (e.g. Candidates).
								 *
								 * @param string  $html      Escaped HTML.
								 * @param WP_Post $form      Form post.
								 * @param string  $entry_url Entries/candidates URL.
								 */
								$extra_actions = (string) apply_filters( 'thimbleform_hub_row_actions_before_more', '', $form, $entry_url );
								echo wp_kses_post( $extra_actions );
								?>
								<div class="thimbleform-hub__more" data-thimbleform-hub-more>
									<button
										type="button"
										class="thimbleform-btn thimbleform-btn--ghost thimbleform-hub__more-toggle"
										aria-expanded="false"
										aria-haspopup="true"
										aria-label="<?php esc_attr_e( 'More actions', 'thimbleform' ); ?>"
									>
										···
									</button>
									<div class="thimbleform-hub__more-menu" hidden>
										<a class="thimbleform-btn thimbleform-btn--ghost" href="<?php echo esc_url( $entry_url ); ?>" onclick="event.stopPropagation();">
											<?php
											/**
											 * Label for the hub “Entries” action (Recruiting may use “Candidates”).
											 *
											 * @param string  $label Default label.
											 * @param WP_Post $form  Form post.
											 */
											echo esc_html( (string) apply_filters( 'thimbleform_hub_entries_action_label', __( 'Entries', 'thimbleform' ), $form ) );
											?>
										</a>
										<a class="thimbleform-btn thimbleform-btn--ghost" href="<?php echo esc_url( self::duplicate_url( (int) $form->ID ) ); ?>">
											<?php esc_html_e( 'Duplicate', 'thimbleform' ); ?>
										</a>
										<?php if ( class_exists( 'Thimbleform_Form_IO' ) ) : ?>
											<a class="thimbleform-btn thimbleform-btn--ghost" href="<?php echo esc_url( Thimbleform_Form_IO::export_url( (int) $form->ID ) ); ?>">
												<?php esc_html_e( 'Export', 'thimbleform' ); ?>
											</a>
										<?php endif; ?>
										<button
											type="button"
											class="thimbleform-btn thimbleform-btn--ghost"
											data-thimbleform-hub-copy="<?php echo esc_attr( $shortcode ); ?>"
										>
											<?php esc_html_e( 'Copy shortcode', 'thimbleform' ); ?>
										</button>
										<?php
										$trash_url = get_delete_post_link( (int) $form->ID, '', false );
										if ( $trash_url && current_user_can( 'delete_post', (int) $form->ID ) ) :
											$confirm = $count > 0
												? sprintf(
													/* translators: 1: form title, 2: entry count */
													__( 'Move “%1$s” to Trash? Its %2$d entries will stay, but the form shortcode will stop working.', 'thimbleform' ),
													$title,
													$count
												)
												: sprintf(
													/* translators: %s: form title */
													__( 'Move “%s” to Trash?', 'thimbleform' ),
													$title
												);
											?>
											<a
												class="thimbleform-btn thimbleform-btn--ghost thimbleform-btn--danger-text"
												href="<?php echo esc_url( $trash_url ); ?>"
												data-thimbleform-hub-delete
												data-confirm="<?php echo esc_attr( $confirm ); ?>"
											>
												<?php esc_html_e( 'Trash', 'thimbleform' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<nav class="thimbleform-hub__pager" data-thimbleform-hub-pager-bottom hidden aria-label="<?php esc_attr_e( 'Forms pagination', 'thimbleform' ); ?>"></nav>
				<p class="thimbleform-hub__empty" data-thimbleform-hub-empty hidden>
					<?php esc_html_e( 'No forms match your search.', 'thimbleform' ); ?>
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
					'action'  => 'thimbleform_duplicate',
					'form_id' => (int) $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			'thimbleform_duplicate_' . (int) $form_id
		);
	}

	public static function handle_duplicate() {
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
		if ( $form_id <= 0 || self::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'thimbleform' ), 400 );
		}
		check_admin_referer( 'thimbleform_duplicate_' . $form_id );
		if ( ! current_user_can( 'edit_post', $form_id ) || ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this form.', 'thimbleform' ), 403 );
		}

		$new_id = self::duplicate_form( $form_id );
		if ( ! $new_id ) {
			wp_die( esc_html__( 'Could not duplicate form.', 'thimbleform' ), 500 );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => self::PAGE_SLUG,
					'thimbleform_duplicated'    => $new_id,
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
				__( '%s (copy)', 'thimbleform' ),
				$source->post_title
			)
			: __( '(no title) (copy)', 'thimbleform' );

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
			Thimbleform_Form_Config::META_FIELDS,
			Thimbleform_Form_Config::META_MESSAGES,
			Thimbleform_Form_Config::META_MAIL,
			Thimbleform_Form_Config::META_SETTINGS,
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
		if ( empty( $_GET['thimbleform_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$new_id = (int) $_GET['thimbleform_duplicated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$link   = get_edit_post_link( $new_id, 'raw' );
		if ( ! $link ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Form duplicated as draft.', 'thimbleform' ),
			esc_url( $link ),
			esc_html__( 'Edit copy', 'thimbleform' )
		);
	}
}
