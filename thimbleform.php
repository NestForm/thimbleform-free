<?php
/**
 * Plugin Name: Thimbleform
 * Plugin URI: https://thimbleform.app
 * Description: Build lead and contact forms with an entries inbox, email alerts, spam protection, and webhooks.
 * Version: 2.3.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Thimbleform
 * Text Domain: thimbleform
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'THIMBLEFORM_VERSION' ) ) {
	return;
}

define( 'THIMBLEFORM_VERSION', '2.3.3' );
define( 'THIMBLEFORM_FILE', __FILE__ );
define( 'THIMBLEFORM_PATH', trailingslashit( dirname( __FILE__ ) ) );
define( 'THIMBLEFORM_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

require_once THIMBLEFORM_PATH . 'includes/kses.php';
require_once THIMBLEFORM_PATH . 'includes/uninstall-cleanup.php';

register_uninstall_hook( THIMBLEFORM_FILE, 'thimbleform_uninstall_cleanup' );

/**
 * Activation: install custom tables and schedule cleanup.
 */
function thimbleform_activate() {
	require_once THIMBLEFORM_PATH . 'includes/class-spam-log.php';
	Thimbleform_Spam_Log::install();

	require_once THIMBLEFORM_PATH . 'includes/class-email-log.php';
	Thimbleform_Email_Log::install();

	require_once THIMBLEFORM_PATH . 'includes/class-submissions.php';
	Thimbleform_Submissions::schedule_retention_cleanup();

	require_once THIMBLEFORM_PATH . 'includes/class-settings.php';
	require_once THIMBLEFORM_PATH . 'includes/class-capabilities.php';
	Thimbleform_Capabilities::install();

	require_once THIMBLEFORM_PATH . 'includes/class-onboarding.php';
	Thimbleform_Onboarding::schedule_redirect();
}
register_activation_hook( __FILE__, 'thimbleform_activate' );

/**
 * Plugin logo URL.
 *
 * @return string
 */
function thimbleform_logo_url() {
	return thimbleform_assets_url( 'images/logo.webp' );
}

/**
 * Style deps for Thimbleform admin.css.
 *
 * @return array<int, string>
 */
function thimbleform_admin_style_deps() {
	return array( 'dashicons' );
}

/**
 * Path under assets/ (plugin root).
 *
 * @param string $relative Relative path, e.g. admin.css or css/admin/01-tokens.css.
 * @return string
 */
function thimbleform_assets_path( $relative = '' ) {
	$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
	return $relative === '' ? THIMBLEFORM_PATH . 'assets/' : THIMBLEFORM_PATH . 'assets/' . $relative;
}

/**
 * URL under assets/.
 *
 * @param string $relative Relative path.
 * @return string
 */
function thimbleform_assets_url( $relative = '' ) {
	$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
	return $relative === '' ? THIMBLEFORM_URL . 'assets/' : THIMBLEFORM_URL . 'assets/' . $relative;
}

/**
 * Bundled admin stylesheet (built from assets/css/admin/*.css).
 *
 * @return string
 */
function thimbleform_admin_css_path() {
	return thimbleform_assets_path( 'css/admin.css' );
}

/**
 * @return string
 */
function thimbleform_admin_css_url() {
	return thimbleform_assets_url( 'css/admin.css' );
}

/**
 * Bundled front stylesheet (built from assets/css/front/forms.css).
 *
 * @return string
 */
function thimbleform_front_css_path() {
	return thimbleform_assets_path( 'css/front.css' );
}

/**
 * @return string
 */
function thimbleform_front_css_url() {
	return thimbleform_assets_url( 'css/front.css' );
}

/**
 * Whether to load minified JS bundles (*.min.js).
 *
 * @return bool
 */
function thimbleform_use_minified_js() {
	return ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
}

/**
 * Normalize a .js basename and optionally return the .min.js variant.
 *
 * @param string $file   e.g. admin.js or admin.min.js.
 * @param bool   $minify Force min/non-min; null uses SCRIPT_DEBUG.
 * @return string
 */
function thimbleform_js_basename( $file, $minify = null ) {
	$file = basename( str_replace( '\\', '/', (string) $file ) );
	$file = preg_replace( '/\.min\.js$/i', '', $file );
	$file = preg_replace( '/\.js$/i', '', $file ) . '.js';

	if ( null === $minify ) {
		$minify = thimbleform_use_minified_js();
	}

	if ( $minify ) {
		return preg_replace( '/\.js$/i', '.min.js', $file );
	}

	return $file;
}

/**
 * Resolve a plugin-relative JS path (prefers readable *.min.js when built).
 *
 * @param string $relative Path from plugin root, e.g. assets/js/admin/admin.js.
 * @return string Absolute filesystem path.
 */
function thimbleform_js_path( $relative ) {
	$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
	$dir      = dirname( $relative );
	$base     = basename( $relative );
	$resolved = ( '.' === $dir ? '' : $dir . '/' ) . thimbleform_js_basename( $base );
	$path     = THIMBLEFORM_PATH . $resolved;

	if ( ! is_readable( $path ) && thimbleform_use_minified_js() && preg_match( '/\.min\.js$/', $resolved ) ) {
		$fallback = ( '.' === $dir ? '' : $dir . '/' ) . thimbleform_js_basename( $base, false );
		$path     = THIMBLEFORM_PATH . $fallback;
	}

	return $path;
}

/**
 * @param string $relative Path from plugin root.
 * @return string
 */
function thimbleform_js_url( $relative ) {
	$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
	$dir      = dirname( $relative );
	$base     = basename( $relative );
	$resolved = ( '.' === $dir ? '' : $dir . '/' ) . thimbleform_js_basename( $base );

	if ( ! is_readable( THIMBLEFORM_PATH . $resolved ) && thimbleform_use_minified_js() && preg_match( '/\.min\.js$/', $resolved ) ) {
		$resolved = ( '.' === $dir ? '' : $dir . '/' ) . thimbleform_js_basename( $base, false );
	}

	return THIMBLEFORM_URL . $resolved;
}

/**
 * Admin script path under assets/js/admin/.
 *
 * @param string $file Basename, e.g. admin.js or admin-notices.js.
 * @return string
 */
function thimbleform_admin_js_path( $file = 'admin.js' ) {
	$file = ltrim( str_replace( '\\', '/', (string) $file ), '/' );
	return thimbleform_js_path( 'assets/js/admin/' . $file );
}

/**
 * @param string $file Basename.
 * @return string
 */
function thimbleform_admin_js_url( $file = 'admin.js' ) {
	$file = ltrim( str_replace( '\\', '/', (string) $file ), '/' );
	return thimbleform_js_url( 'assets/js/admin/' . $file );
}

/**
 * Front script path (assets/js/front/front.js).
 *
 * @return string
 */
function thimbleform_front_js_path() {
	return thimbleform_js_path( 'assets/js/front/front.js' );
}

/**
 * @return string
 */
function thimbleform_front_js_url() {
	return thimbleform_js_url( 'assets/js/front/front.js' );
}

/**
 * Admin counters for chrome (forms / entries).
 *
 * @return array{forms:int,new:int,entries:int,today:int}
 */
function thimbleform_admin_stats() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$forms_n = 0;
	if ( class_exists( 'Thimbleform_Post_Type' ) ) {
		$counts = wp_count_posts( Thimbleform_Post_Type::POST_TYPE );
		if ( $counts ) {
			$forms_n  = (int) $counts->publish;
			$forms_n += isset( $counts->draft ) ? (int) $counts->draft : 0;
			$forms_n += isset( $counts->private ) ? (int) $counts->private : 0;
			$forms_n += isset( $counts->pending ) ? (int) $counts->pending : 0;
		}
	}

	$new_n     = 0;
	$entries_n = 0;
	$today_n   = 0;
	if ( class_exists( 'Thimbleform_Submissions' ) ) {
		$new_n     = (int) Thimbleform_Submissions::count_entries(
			array(
				'status' => Thimbleform_Submissions::STATUS_NEW,
			)
		);
		$entries_n = (int) Thimbleform_Submissions::count_entries();
		$today_n   = (int) Thimbleform_Submissions::count_entries(
			array(
				'after'  => wp_date( 'Y-m-d' ) . ' 00:00:00',
				'before' => wp_date( 'Y-m-d' ) . ' 23:59:59',
			)
		);
	}

	$cache = array(
		'forms'   => $forms_n,
		'new'     => $new_n,
		'entries' => $entries_n,
		'today'   => $today_n,
	);
	return $cache;
}

/**
 * Inline nav icon HTML.
 *
 * @param string $name    Icon key.
 * @param string $variant Unused (kept for call-site compatibility).
 * @return string
 */
function thimbleform_admin_icon_html( $name, $variant = '' ) {
	unset( $variant );
	/*
	 * Unified icon language: 24×24 grid, 1.75 stroke, round caps/joins.
	 * Display size is often overridden in CSS (nav 16px, buttons ~14px).
	 */
	$a = 'width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
	$icons = array(
		'forms'        => '<svg ' . $a . '><rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M8 8h.01"/><path d="M12 8h4"/><path d="M8 12h.01"/><path d="M12 12h4"/><path d="M8 16h.01"/><path d="M12 16h3"/></svg>',
		'analytics'    => '<svg ' . $a . '><rect x="3" y="12" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="17" rx="1"/></svg>',
		'entries'      => '<svg ' . $a . '><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
		'recruiting'   => '<svg ' . $a . '><rect x="2" y="7" width="20" height="14" rx="2.5"/><path d="M16 7V5.5A2.5 2.5 0 0 0 13.5 3h-3A2.5 2.5 0 0 0 8 5.5V7"/><path d="M12 12v3"/><path d="M2 12h20"/></svg>',
		'settings'     => '<svg ' . $a . '><path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/></svg>',
		'license'      => '<svg ' . $a . '><circle cx="8" cy="15" r="5"/><path d="m16 3 5 5"/><path d="m13.5 8.5 3 3L21 7l-3-3"/><circle cx="8" cy="15" r="1.5"/></svg>',
		'plus'         => '<svg ' . $a . '><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
		'search'       => '<svg ' . $a . '><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
		'copy'         => '<svg ' . $a . '><rect x="9" y="9" width="13" height="13" rx="2.5"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
		'save'         => '<svg ' . $a . '><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>',
		'draft'        => '<svg ' . $a . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>',
		'unpublish'    => '<svg ' . $a . '><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>',
		'publish'      => '<svg ' . $a . '><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>',
		'preview'      => '<svg ' . $a . '><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
		'eye-off'      => '<svg ' . $a . '><path d="M10.7 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-2 2.9"/><path d="M6.6 6.6A18 18 0 0 0 2 12s3.5 7 10 7a10.4 10.4 0 0 0 4.4-.9"/><path d="m2 2 20 20"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>',
		'undo'         => '<svg ' . $a . '><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6.3 2.6L3 13"/></svg>',
		'external'     => '<svg ' . $a . '><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
		'back'         => '<svg ' . $a . '><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>',
		'forward'      => '<svg ' . $a . '><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
		'chevron'      => '<svg ' . $a . '><path d="m9 18 6-6-6-6"/></svg>',
		'download'     => '<svg ' . $a . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>',
		/*
		 * Thimbleform Pro mark: nested form cards + spark (product DNA).
		 * `crown` kept as alias for older call sites.
		 */
		'pro'          => '<svg class="thimbleform-icon-pro" ' . $a . '><rect x="8" y="3" width="12.5" height="14.5" rx="2.25"/><rect x="3.5" y="7.5" width="12.5" height="14.5" rx="2.25"/><path d="M19.2 2.2l.5 1.25 1.25.5-1.25.5-.5 1.25-.5-1.25-1.25-.5 1.25-.5z" fill="currentColor" stroke="none"/></svg>',
		'crown'        => '<svg class="thimbleform-icon-pro" ' . $a . '><rect x="8" y="3" width="12.5" height="14.5" rx="2.25"/><rect x="3.5" y="7.5" width="12.5" height="14.5" rx="2.25"/><path d="M19.2 2.2l.5 1.25 1.25.5-1.25.5-.5 1.25-.5-1.25-1.25-.5 1.25-.5z" fill="currentColor" stroke="none"/></svg>',
		'sparkle'      => '<svg class="thimbleform-icon-pro" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l1.2 4.1L17.5 8 13.2 9.4 12 13.5l-1.2-4.1L6.5 8l4.3-1.4L12 2.5zm6.8 8.2l.7 2.3 2.3.7-2.3.7-.7 2.3-.7-2.3-2.3-.7 2.3-.7.7-2.3zM5.5 14.2l.55 1.85 1.85.55-1.85.55-.55 1.85-.55-1.85-1.85-.55 1.85-.55.55-1.85z"/></svg>',
		'check'        => '<svg ' . $a . '><path d="M20 6 9 17l-5-5"/></svg>',
		'developers'   => '<svg ' . $a . '><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="m8 10 2.5 2.5L8 15"/><path d="M13 15h4"/></svg>',
		'docs'         => '<svg ' . $a . '><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8"/><path d="M8 11h6"/></svg>',
		'integrations' => '<svg ' . $a . '><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/><circle cx="12" cy="12" r="3.5"/></svg>',
		'trash'        => '<svg ' . $a . '><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
	);

	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/**
 * Echo inline nav icon.
 *
 * @param string $name Icon key.
 */
function thimbleform_admin_icon( $name ) {
	echo wp_kses( thimbleform_admin_icon_html( $name ), thimbleform_svg_allowed_html() );
}

/**
 * Open Thimbleform app shell (sidebar + main).
 *
 * @param string $current Active nav: dashboard|forms|entries|settings|integrations|developers|upgrade|license|editor.
 */
function thimbleform_render_app_open( $current ) {
	$stats       = thimbleform_admin_stats();
	$forms_n     = (int) $stats['forms'];
	$new_n       = (int) $stats['new'];
	$entries_n   = isset( $stats['entries'] ) ? (int) $stats['entries'] : 0;
	$today_n     = isset( $stats['today'] ) ? (int) $stats['today'] : 0;
	$entries_url = class_exists( 'Thimbleform_Submissions' ) ? Thimbleform_Submissions::hub_url() : '';
	$new_url     = class_exists( 'Thimbleform_Submissions' )
		? Thimbleform_Submissions::hub_url( array( 'thimbleform_status' => Thimbleform_Submissions::STATUS_NEW ) )
		: $entries_url;
	$analytics_url = class_exists( 'Thimbleform_Dashboard' ) ? Thimbleform_Dashboard::url() : '';

	$can_edit_posts = current_user_can( 'edit_posts' );
	$can_manage_opts = current_user_can( 'manage_options' );
	$can_view_entries = class_exists( 'Thimbleform_Capabilities' )
		? Thimbleform_Capabilities::can_view_entries()
		: $can_edit_posts;
	$can_manage_forms = class_exists( 'Thimbleform_Capabilities' )
		? Thimbleform_Capabilities::can_manage()
		: $can_edit_posts;

	$items = array(
		array(
			'id'    => 'dashboard',
			'label' => __( 'Dashboard', 'thimbleform' ),
			'url'   => ( $can_edit_posts && class_exists( 'Thimbleform_Dashboard' ) ) ? Thimbleform_Dashboard::url() : '',
			'icon'  => 'analytics',
			'group' => 'primary',
		),
		array(
			'id'    => 'forms',
			'label' => __( 'Forms', 'thimbleform' ),
			'url'   => ( $can_manage_forms && class_exists( 'Thimbleform_Post_Type' ) ) ? Thimbleform_Post_Type::hub_url() : '',
			'icon'  => 'forms',
			'group' => 'primary',
		),
		array(
			'id'    => 'entries',
			'label' => __( 'Entries', 'thimbleform' ),
			'url'   => ( $can_view_entries && class_exists( 'Thimbleform_Submissions' ) ) ? Thimbleform_Submissions::hub_url() : '',
			'icon'  => 'entries',
			'group' => 'primary',
		),
		array(
			'id'    => 'integrations',
			'label' => __( 'Integrations', 'thimbleform' ),
			'url'   => ( $can_manage_opts && class_exists( 'Thimbleform_Integrations' ) ) ? Thimbleform_Integrations::url() : '',
			'icon'  => 'integrations',
			'group' => 'primary',
		),
		array(
			'id'    => 'settings',
			'label' => __( 'Settings', 'thimbleform' ),
			'url'   => ( $can_manage_opts && class_exists( 'Thimbleform_Settings' ) ) ? Thimbleform_Settings::url() : '',
			'icon'  => 'settings',
			'group' => 'primary',
		),
		array(
			'id'    => 'import',
			'label' => __( 'Import forms', 'thimbleform' ),
			'url'   => ( class_exists( 'Thimbleform_Importer' ) && Thimbleform_Importer::user_can_import() ) ? Thimbleform_Importer::url() : '',
			'icon'  => 'download',
			'group' => 'tools',
		),
	);

	if ( class_exists( 'Thimbleform_Promotion' ) && Thimbleform_Promotion::should_promote() && $can_edit_posts ) {
		$items[] = array(
			'id'    => 'pro',
			'label' => __( 'Pro', 'thimbleform' ),
			'url'   => Thimbleform_Promotion::url(),
			'icon'  => 'pro',
			'group' => 'account',
		);
	}

	if ( $can_manage_opts && class_exists( 'Thimbleform_Pro_License' ) ) {
		$items[] = array(
			'id'    => 'license',
			'label' => __( 'License', 'thimbleform' ),
			'url'   => Thimbleform_Pro_License::url(),
			'icon'  => 'license',
			'group' => 'account',
		);
	}

	/**
	 * Filter Thimbleform app sidebar nav items.
	 *
	 * Empty `url` hides the item. Match WordPress submenu capabilities.
	 *
	 * @param array<int, array{id:string,label:string,url:string,icon:string,group?:string}> $items   Nav items.
	 * @param string                                                                         $current Active view id.
	 */
	$items = apply_filters( 'thimbleform_app_nav_items', $items, $current );

	$nav_current = ( 'editor' === $current ) ? 'forms' : $current;
	$nav_groups  = array(
		'primary' => array(),
		'tools'   => array(),
		'account' => array(),
	);
	foreach ( $items as $item ) {
		if ( empty( $item['url'] ) ) {
			continue;
		}
		$group = isset( $item['group'] ) ? sanitize_key( (string) $item['group'] ) : 'primary';
		if ( ! isset( $nav_groups[ $group ] ) ) {
			$group = 'primary';
		}
		$nav_groups[ $group ][] = $item;
	}
	?>
	<div class="thimbleform-app" data-thimbleform-app>
		<aside class="thimbleform-app__sidebar" id="thimbleform-app-sidebar">
			<div class="thimbleform-app__brand">
				<img
					class="thimbleform-app__logo"
					src="<?php echo esc_url( thimbleform_logo_url() ); ?>"
					alt="<?php esc_attr_e( 'Thimbleform', 'thimbleform' ); ?>"
					width="48"
					height="48"
				/>
				<div class="thimbleform-app__brand-copy">
					<div class="thimbleform-app__name">Thimble<span class="thimbleform-app__name-accent">form</span></div>
					<div class="thimbleform-app__ver">v<?php echo esc_html( THIMBLEFORM_VERSION ); ?></div>
				</div>
			</div>
			<nav class="thimbleform-app__nav" aria-label="<?php esc_attr_e( 'Thimbleform', 'thimbleform' ); ?>">
				<?php
				$group_i = 0;
				foreach ( $nav_groups as $group_id => $group_items ) :
					if ( array() === $group_items ) {
						continue;
					}
					if ( $group_i > 0 ) :
						?>
						<span class="thimbleform-app__nav-sep" aria-hidden="true"></span>
						<?php
					endif;
					++$group_i;
					foreach ( $group_items as $item ) :
						$is_on = ( $nav_current === $item['id'] );
						$class = 'thimbleform-app__nav-item' . ( $is_on ? ' thimbleform-app__nav-item--active' : '' );
						$label = (string) $item['label'];
						?>
						<a
							class="<?php echo esc_attr( $class ); ?>"
							href="<?php echo esc_url( $item['url'] ); ?>"
							title="<?php echo esc_attr( $label ); ?>"
						>
							<span class="thimbleform-app__nav-icon"><?php thimbleform_admin_icon( isset( $item['icon'] ) ? (string) $item['icon'] : 'forms' ); ?></span>
							<span class="thimbleform-app__nav-label"><?php echo esc_html( $label ); ?></span>
							<?php if ( 'entries' === $item['id'] && $new_n > 0 ) : ?>
								<span class="thimbleform-app__nav-count"><?php echo esc_html( number_format_i18n( $new_n ) ); ?></span>
							<?php endif; ?>
						</a>
						<?php
					endforeach;
				endforeach;
				?>
			</nav>
			<div class="thimbleform-app__foot">
				<?php if ( class_exists( 'Thimbleform_Upgrade' ) ) : ?>
					<?php Thimbleform_Upgrade::render_sidebar( $forms_n ); ?>
				<?php endif; ?>

				<div class="thimbleform-app__foot-card">
					<div class="thimbleform-app__foot-card-head">
						<span class="thimbleform-app__foot-card-title"><?php esc_html_e( 'Inbox', 'thimbleform' ); ?></span>
						<?php if ( $entries_url !== '' ) : ?>
							<a class="thimbleform-app__foot-card-link" href="<?php echo esc_url( $new_n > 0 ? $new_url : $entries_url ); ?>">
								<?php echo esc_html( $new_n > 0 ? __( 'Review', 'thimbleform' ) : __( 'Open', 'thimbleform' ) ); ?>
								<?php echo wp_kses( thimbleform_admin_icon_html( 'forward' ), thimbleform_svg_allowed_html() ); ?>
							</a>
						<?php endif; ?>
					</div>

					<?php if ( $new_n > 0 ) : ?>
						<a class="thimbleform-app__foot-cta" href="<?php echo esc_url( $new_url ); ?>">
							<span class="thimbleform-app__foot-cta-count"><?php echo esc_html( number_format_i18n( $new_n ) ); ?></span>
							<span class="thimbleform-app__foot-cta-text">
								<?php
								echo esc_html(
									_n( 'new entry to review', 'new entries to review', $new_n, 'thimbleform' )
								);
								?>
							</span>
						</a>
					<?php else : ?>
						<p class="thimbleform-app__foot-clear">
							<?php esc_html_e( 'No new entries', 'thimbleform' ); ?>
						</p>
					<?php endif; ?>

					<div class="thimbleform-app__foot-grid thimbleform-app__foot-grid--compact" aria-label="<?php esc_attr_e( 'Quick stats', 'thimbleform' ); ?>">
						<a class="thimbleform-app__foot-cell" href="<?php echo esc_url( $analytics_url !== '' ? $analytics_url : $entries_url ); ?>">
							<span class="thimbleform-app__foot-val"><?php echo esc_html( number_format_i18n( $today_n ) ); ?></span>
							<span class="thimbleform-app__foot-label"><?php esc_html_e( 'Today', 'thimbleform' ); ?></span>
						</a>
						<a class="thimbleform-app__foot-cell" href="<?php echo esc_url( $entries_url ); ?>">
							<span class="thimbleform-app__foot-val"><?php echo esc_html( number_format_i18n( $entries_n ) ); ?></span>
							<span class="thimbleform-app__foot-label"><?php esc_html_e( 'Entries', 'thimbleform' ); ?></span>
						</a>
					</div>
				</div>
			</div>
			<button
				type="button"
				class="thimbleform-app__sidebar-toggle"
				data-thimbleform-sidebar-toggle
				aria-controls="thimbleform-app-sidebar"
				aria-expanded="true"
				title="<?php esc_attr_e( 'Collapse sidebar', 'thimbleform' ); ?>"
			>
				<span class="thimbleform-app__sidebar-toggle-icon" aria-hidden="true">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
				</span>
				<span class="thimbleform-app__sidebar-toggle-label"><?php esc_html_e( 'Collapse', 'thimbleform' ); ?></span>
			</button>
			<a class="thimbleform-app__wp" href="<?php echo esc_url( admin_url() ); ?>" title="<?php esc_attr_e( 'WordPress admin', 'thimbleform' ); ?>">
				<span class="thimbleform-app__wp-icon"><?php thimbleform_admin_icon( 'external' ); ?></span>
				<span class="thimbleform-app__wp-label"><?php esc_html_e( 'WordPress admin', 'thimbleform' ); ?></span>
			</a>
		</aside>
		<div class="thimbleform-app__main">
	<?php
}

/**
 * Close Thimbleform app shell.
 */
function thimbleform_render_app_close() {
	echo '</div></div>';
}

/**
 * Current Thimbleform admin view, or empty outside the plugin UI.
 *
 * @return string dashboard|forms|entries|settings|integrations|developers|upgrade|license|editor|
 */
function thimbleform_admin_current_view() {
	if ( ! is_admin() ) {
		return '';
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$map  = array(
		'thimbleform-forms'       => 'forms',
		'thimbleform-import'      => 'import',
		'thimbleform-dashboard'   => 'dashboard',
		'thimbleform-entries'     => 'entries',
		'thimbleform-settings'      => 'settings',
		'thimbleform-integrations'  => 'integrations',
		'thimbleform-developers'    => 'developers',
		'thimbleform-pro'               => 'pro',
		'thimbleform-upgrade'           => 'pro',
		'thimbleform-account'           => 'license',
		'thimbleform-forms-account'     => 'license',
		'thimbleform-pro-license'       => 'license',
		'thimbleform-recruiting'        => 'recruiting',
	);
	if ( isset( $map[ $page ] ) ) {
		return $map[ $page ];
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return '';
	}
	if ( 'thimbleform' === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		return 'editor';
	}
	if ( 'thimbleform_entry' === $screen->post_type ) {
		return 'entries';
	}
	return '';
}

/**
 * Pin notices to the bottom before first paint (avoids WP default flash).
 * Hide core update nags on Thimbleform screens only.
 */
function thimbleform_admin_notice_boot_css() {
	if ( '' === thimbleform_admin_current_view() ) {
		return;
	}
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
	if ( ! wp_style_is( 'thimbleform-admin', 'enqueued' ) ) {
		return;
	}
	$css = 'body.thimbleform-admin-screen .update-nag,'
		. 'body.thimbleform-admin-screen div.notice.update-nag,'
		. 'body.thimbleform-editor-app .update-nag,'
		. 'body.thimbleform-editor-app div.notice.update-nag{display:none!important;}'
		. 'body.thimbleform-admin-screen div.notice:not(.inline):not(.hidden):not(.update-nag),'
		. 'body.thimbleform-admin-screen div.updated:not(.inline):not(.hidden),'
		. 'body.thimbleform-admin-screen div.error:not(.inline):not(.hidden){'
		. 'position:fixed!important;top:auto!important;right:0!important;bottom:24px!important;'
		. 'left:200px!important;width:min(440px,calc(100vw - 248px))!important;margin:0 auto!important;'
		. 'z-index:100000!important;box-sizing:border-box!important;'
		. 'padding:10px 40px 10px 14px!important;border:1px solid #bfdbfe!important;'
		. 'border-radius:8px!important;background:#eff6ff!important;box-shadow:0 4px 24px rgba(0,0,0,.06)!important;'
		. 'color:#1d4ed8!important;font-size:13px!important;line-height:1.45!important;'
		. '}'
		. 'body.thimbleform-admin-screen .thimbleform-toasts div.notice:not(.inline):not(.hidden):not(.update-nag),'
		. 'body.thimbleform-admin-screen .thimbleform-toasts div.updated:not(.inline):not(.hidden),'
		. 'body.thimbleform-admin-screen .thimbleform-toasts div.error:not(.inline):not(.hidden),'
		. 'body.thimbleform-editor-app .thimbleform-toasts div.notice:not(.inline):not(.hidden):not(.update-nag),'
		. 'body.thimbleform-editor-app .thimbleform-toasts div.updated:not(.inline):not(.hidden),'
		. 'body.thimbleform-editor-app .thimbleform-toasts div.error:not(.inline):not(.hidden){'
		. 'position:relative!important;left:auto!important;right:auto!important;bottom:auto!important;top:auto!important;'
		. 'width:min(440px,100%)!important;margin:0!important;'
		. '}'
		. 'body.thimbleform-admin-screen div.notice.notice-success:not(.inline):not(.hidden),'
		. 'body.thimbleform-admin-screen div.updated:not(.inline):not(.hidden),'
		. 'body.thimbleform-admin-screen div.notice.updated:not(.inline):not(.hidden){'
		. 'border-color:#b5e5dc!important;background:#e8f8f5!important;color:#0d9b87!important;'
		. '}'
		. 'body.thimbleform-admin-screen div.notice.notice-error:not(.inline):not(.hidden),'
		. 'body.thimbleform-admin-screen div.error:not(.inline):not(.hidden){'
		. 'border-color:#f0c5ce!important;background:#fdf0f3!important;color:#d14b63!important;'
		. '}'
		. 'body.thimbleform-admin-screen div.notice.notice-warning:not(.inline):not(.hidden){'
		. 'border-color:#bfdbfe!important;background:#eff6ff!important;color:#3b82f6!important;'
		. '}'
		. 'body.thimbleform-admin-screen div.notice.notice-info:not(.inline):not(.hidden){'
		. 'border-color:#bfdbfe!important;background:#eff6ff!important;color:#1d4ed8!important;'
		. '}'
		. 'body.thimbleform-admin-screen .postbox div.notice,'
		. 'body.thimbleform-admin-screen .postbox div.updated,'
		. 'body.thimbleform-admin-screen .postbox div.error,'
		. 'body.thimbleform-admin-screen #screen-meta div.notice,'
		. 'body.thimbleform-admin-screen .media-modal div.notice{position:relative!important;left:auto!important;right:auto!important;bottom:auto!important;width:auto!important;margin:0 0 12px!important;}'
		. 'body.thimbleform-editor-app div.notice:not(.inline):not(.hidden):not(.update-nag),'
		. 'body.thimbleform-editor-app div.updated:not(.inline),'
		. 'body.thimbleform-editor-app div.error:not(.inline){right:240px!important;}'
		. '@media screen and (max-width:782px){body.thimbleform-admin-screen div.notice:not(.inline):not(.hidden):not(.update-nag),body.thimbleform-admin-screen div.updated:not(.inline),body.thimbleform-admin-screen div.error:not(.inline){left:16px!important;right:16px!important;width:auto!important;}}';
	wp_add_inline_style( 'thimbleform-admin', $css );
}

add_action( 'admin_enqueue_scripts', 'thimbleform_admin_notice_boot_css', 20 );

add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$view = thimbleform_admin_current_view();
		if ( '' === $view ) {
			return $classes;
		}
		$classes .= ' thimbleform-admin-screen thimbleform-app-screen';
		if ( 'editor' === $view ) {
			$classes .= ' thimbleform-editor-app';
		}
		if ( 'license' === $view ) {
			$classes .= ' thimbleform-license-screen';
		}
		if ( 'pro' === $view ) {
			$classes .= ' thimbleform-upgrade-screen';
		}
		return $classes;
	}
);

add_action(
	'in_admin_header',
	static function () {
		$view = thimbleform_admin_current_view();
		if ( '' === $view ) {
			return;
		}
		thimbleform_render_app_open( $view );
	}
);

add_action(
	'admin_footer',
	static function () {
		if ( '' === thimbleform_admin_current_view() ) {
			return;
		}
		thimbleform_render_app_close();
	},
	1
);

/**
 * Page title row inside the app main column.
 *
 * @param array<string, mixed> $args {
 *     @type string $title         Page title.
 *     @type string $description   Short help text.
 *     @type string $actions_html  Escaped HTML for the right side.
 *     @type string $meta_html     Optional compact meta (e.g. Pro insights) near actions.
 *     @type string $icon          Optional lead icon key (forms|analytics|entries|settings|developers|pro).
 * }
 */
function thimbleform_render_page_head( array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'        => '',
			'description'  => '',
			'actions_html' => '',
			'meta_html'    => '',
			'icon'         => '',
		)
	);

	$icon = sanitize_key( (string) $args['icon'] );
	if ( $icon === '' ) {
		$view = thimbleform_admin_current_view();
		$map  = array(
			'forms'        => 'forms',
			'editor'       => 'forms',
			'import'       => 'forms',
			'dashboard'    => 'analytics',
			'analytics'    => 'analytics',
			'entries'      => 'entries',
			'settings'     => 'settings',
			'integrations' => 'integrations',
			'developers'   => 'developers',
			'docs'         => 'docs',
			'pro'          => 'pro',
			'license'      => 'license',
		);
		$icon = isset( $map[ $view ] ) ? $map[ $view ] : '';
	}
	$icon_html = $icon !== '' ? thimbleform_admin_icon_html( $icon ) : '';
	$actions   = (string) $args['actions_html'];
	$meta      = (string) $args['meta_html'];
	?>
	<header class="thimbleform-page-head">
		<div class="thimbleform-page-head__lead">
			<?php if ( $icon_html !== '' ) : ?>
				<span class="thimbleform-page-head__icon thimbleform-page-head__icon--<?php echo esc_attr( $icon ); ?>" aria-hidden="true"><?php echo wp_kses( $icon_html, thimbleform_svg_allowed_html() ); ?></span>
			<?php endif; ?>
			<div class="thimbleform-page-head__copy">
				<h1 class="thimbleform-page-head__title"><?php echo esc_html( (string) $args['title'] ); ?></h1>
				<?php if ( (string) $args['description'] !== '' ) : ?>
					<p class="thimbleform-page-head__desc"><?php echo esc_html( (string) $args['description'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $meta !== '' || $actions !== '' ) : ?>
			<div class="thimbleform-page-head__aside">
				<?php if ( $meta !== '' ) : ?>
					<div class="thimbleform-page-head__meta">
						<?php echo wp_kses( $meta, thimbleform_admin_allowed_html() ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $actions !== '' ) : ?>
					<div class="thimbleform-page-head__actions">
						<?php echo wp_kses( $actions, thimbleform_admin_allowed_html() ); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>
	<hr class="wp-header-end" />
	<?php
}

require_once THIMBLEFORM_PATH . 'includes/class-migration.php';
require_once THIMBLEFORM_PATH . 'includes/class-compat.php';
require_once THIMBLEFORM_PATH . 'includes/class-features.php';
require_once THIMBLEFORM_PATH . 'includes/class-form-config.php';
require_once THIMBLEFORM_PATH . 'includes/class-security.php';
require_once THIMBLEFORM_PATH . 'includes/class-spam-filter.php';
require_once THIMBLEFORM_PATH . 'includes/class-spam-log.php';
require_once THIMBLEFORM_PATH . 'includes/class-email-log.php';
require_once THIMBLEFORM_PATH . 'includes/class-phone.php';
require_once THIMBLEFORM_PATH . 'includes/class-capabilities.php';
require_once THIMBLEFORM_PATH . 'includes/class-post-type.php';
require_once THIMBLEFORM_PATH . 'includes/class-submissions.php';
require_once THIMBLEFORM_PATH . 'includes/class-response-summary.php';
require_once THIMBLEFORM_PATH . 'includes/class-qr-code.php';
require_once THIMBLEFORM_PATH . 'includes/class-dashboard.php';
require_once THIMBLEFORM_PATH . 'includes/class-settings.php';
require_once THIMBLEFORM_PATH . 'includes/class-integrations.php';
require_once THIMBLEFORM_PATH . 'includes/class-developers.php';
require_once THIMBLEFORM_PATH . 'includes/class-webhook.php';
require_once THIMBLEFORM_PATH . 'includes/class-upgrade.php';
require_once THIMBLEFORM_PATH . 'includes/class-promotion.php';
require_once THIMBLEFORM_PATH . 'includes/class-review-request.php';
require_once THIMBLEFORM_PATH . 'includes/class-privacy.php';
require_once THIMBLEFORM_PATH . 'includes/class-onboarding.php';
require_once THIMBLEFORM_PATH . 'includes/class-admin-ui.php';
require_once THIMBLEFORM_PATH . 'includes/class-admin-theme.php';
require_once THIMBLEFORM_PATH . 'includes/class-renderer.php';
require_once THIMBLEFORM_PATH . 'includes/class-submit.php';
require_once THIMBLEFORM_PATH . 'includes/class-captcha.php';
require_once THIMBLEFORM_PATH . 'includes/class-xlsx-export.php';
require_once THIMBLEFORM_PATH . 'includes/class-export.php';
require_once THIMBLEFORM_PATH . 'includes/class-entry-print.php';
require_once THIMBLEFORM_PATH . 'includes/class-form-io.php';
require_once THIMBLEFORM_PATH . 'includes/class-importer.php';
require_once THIMBLEFORM_PATH . 'includes/class-backup.php';
require_once THIMBLEFORM_PATH . 'includes/class-templates.php';
require_once THIMBLEFORM_PATH . 'includes/class-block.php';
require_once THIMBLEFORM_PATH . 'includes/class-elementor.php';

add_action(
	'plugins_loaded',
	static function () {
		Thimbleform_Migration::init();
		Thimbleform_Compat::init();
		Thimbleform_Capabilities::init();
		Thimbleform_Post_Type::init();
		if ( class_exists( 'Thimbleform_Security' ) ) {
			Thimbleform_Security::init();
		}
		if ( class_exists( 'Thimbleform_Spam_Log' ) ) {
			Thimbleform_Spam_Log::init();
		}
		if ( class_exists( 'Thimbleform_Email_Log' ) ) {
			Thimbleform_Email_Log::init();
		}
		Thimbleform_Submissions::init();
		Thimbleform_Response_Summary::init();
		Thimbleform_Dashboard::init();
		Thimbleform_Settings::init();
		Thimbleform_Integrations::init();
		Thimbleform_Developers::init();
		Thimbleform_Webhook::init();
		Thimbleform_Upgrade::init();
		Thimbleform_Promotion::init();
		Thimbleform_Review_Request::init();
		Thimbleform_Privacy::init();
		Thimbleform_Onboarding::init();
		Thimbleform_Admin_UI::init();
		Thimbleform_Admin_Theme::init();
		Thimbleform_Renderer::init();
		Thimbleform_Submit::init();
		Thimbleform_Captcha::init();
		Thimbleform_Export::init();
		Thimbleform_Entry_Print::init();
		Thimbleform_Form_IO::init();
		Thimbleform_Importer::init();
		Thimbleform_Backup::init();
		Thimbleform_Templates::init();
		Thimbleform_Block::init();
		Thimbleform_Elementor::init();

		/**
		 * Fires after Thimbleform Free is loaded. Pro registers features here.
		 */
		do_action( 'thimbleform_loaded' );
	},
	5
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( '' === thimbleform_admin_current_view() ) {
			return;
		}
		$ver = (string) filemtime( thimbleform_admin_css_path() );
		wp_enqueue_style(
			'thimbleform-admin',
			thimbleform_admin_css_url(),
			thimbleform_admin_style_deps(),
			$ver ? $ver : THIMBLEFORM_VERSION
		);
		$ver_js = (string) filemtime( thimbleform_admin_js_path( 'admin-notices.js' ) );
		wp_enqueue_script(
			'thimbleform-admin-notices',
			thimbleform_admin_js_url( 'admin-notices.js' ),
			array( 'jquery', 'common' ),
			$ver_js ? $ver_js : THIMBLEFORM_VERSION,
			true
		);
		$ver_export = (string) filemtime( thimbleform_admin_js_path( 'admin-export-menu.js' ) );
		wp_enqueue_script(
			'thimbleform-admin-export-menu',
			thimbleform_admin_js_url( 'admin-export-menu.js' ),
			array(),
			$ver_export ? $ver_export : THIMBLEFORM_VERSION,
			true
		);
		$ver_boot = (string) filemtime( thimbleform_admin_js_path( 'sidebar-boot.js' ) );
		wp_enqueue_script(
			'thimbleform-sidebar-boot',
			thimbleform_admin_js_url( 'sidebar-boot.js' ),
			array(),
			$ver_boot ? $ver_boot : THIMBLEFORM_VERSION,
			false
		);
		$ver_pro = (string) filemtime( thimbleform_admin_js_path( 'admin-pro.js' ) );
		wp_enqueue_script(
			'thimbleform-admin-pro',
			thimbleform_admin_js_url( 'admin-pro.js' ),
			array( 'thimbleform-sidebar-boot' ),
			$ver_pro ? $ver_pro : THIMBLEFORM_VERSION,
			true
		);
		$features = array();
		if ( class_exists( 'Thimbleform_Features' ) ) {
			foreach ( Thimbleform_Features::all_keys() as $key ) {
				$features[ $key ] = Thimbleform_Features::can( $key );
			}
		}

		wp_localize_script(
			'thimbleform-admin-pro',
			'thimbleformPro',
			array(
				'isPro'     => class_exists( 'Thimbleform_Upgrade' ) ? Thimbleform_Upgrade::is_pro() : false,
				'url'       => class_exists( 'Thimbleform_Promotion' ) ? Thimbleform_Promotion::url() : '',
				'storeUrl'  => class_exists( 'Thimbleform_Promotion' ) ? Thimbleform_Promotion::store_url() : 'https://thimbleform.app/pro',
				'features'  => $features,
				'i18n'      => array(
					'sidebar'         => array(
						'collapse' => __( 'Collapse', 'thimbleform' ),
						'expand'   => __( 'Expand', 'thimbleform' ),
					),
				),
			)
		);
	},
	5
);
