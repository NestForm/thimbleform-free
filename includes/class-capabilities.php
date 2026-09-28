<?php
/**
 * Role capabilities for managing forms and viewing entries.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Capabilities {

	const MANAGE = 'thimbleform_manage_forms';
	const VIEW   = 'thimbleform_view_entries';

	const ROLE_VERSION        = '1';
	const ROLE_VERSION_OPTION = 'thimbleform_role_version';

	public static function init() {
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_implied' ), 10, 4 );
		add_action( 'update_option_thimbleform_settings', array( __CLASS__, 'sync_roles' ) );
		add_action( 'add_option_thimbleform_settings', array( __CLASS__, 'sync_roles' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
	}

	public static function maybe_install() {
		if ( self::ROLE_VERSION === get_option( self::ROLE_VERSION_OPTION ) ) {
			return;
		}
		self::install();
		update_option( self::ROLE_VERSION_OPTION, self::ROLE_VERSION, false );
	}

	/**
	 * @return array<int, string>
	 */
	public static function all() {
		return array( self::MANAGE, self::VIEW );
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			self::MANAGE => __( 'Manage forms', 'thimbleform' ),
			self::VIEW   => __( 'View entries', 'thimbleform' ),
		);
	}

	/**
	 * @param array<string, bool> $allcaps All caps.
	 * @param array<int, string>  $caps    Requested.
	 * @param array               $args    Args.
	 * @param WP_User             $user    User.
	 * @return array<string, bool>
	 */
	public static function grant_implied( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );
		$allcaps = (array) $allcaps;

		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::MANAGE ] = true;
			$allcaps[ self::VIEW ]   = true;
		} elseif ( ! empty( $allcaps[ self::MANAGE ] ) ) {
			$allcaps[ self::VIEW ] = true;
		}

		return $allcaps;
	}

	/**
	 * Grant caps on activate / upgrade. Administrator always; editor both by default.
	 */
	public static function install() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::all() as $cap ) {
				if ( ! $administrator->has_cap( $cap ) ) {
					$administrator->add_cap( $cap );
				}
			}
		}

		$settings = get_option( Thimbleform_Settings::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		if ( empty( $settings['role_caps'] ) || ! is_array( $settings['role_caps'] ) ) {
			$settings['role_caps'] = array(
				'editor' => self::all(),
			);
			$merged = array_merge( Thimbleform_Settings::defaults(), $settings );
			update_option( Thimbleform_Settings::OPTION, $merged, false );
		}

		self::sync_roles();
	}

	/**
	 * @return array<string, string>
	 */
	public static function assignable_roles() {
		$roles = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			$roles[ $slug ] = translate_user_role( $name );
		}
		return $roles;
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function role_settings() {
		$stored = array();
		if ( class_exists( 'Thimbleform_Settings' ) ) {
			$all    = Thimbleform_Settings::get();
			$stored = isset( $all['role_caps'] ) ? $all['role_caps'] : array();
		}
		$clean = array();
		foreach ( (array) $stored as $role => $caps ) {
			$caps = array_values( array_intersect( (array) $caps, self::all() ) );
			if ( $caps ) {
				$clean[ (string) $role ] = $caps;
			}
		}
		return $clean;
	}

	public static function sync_roles() {
		$granted = self::role_settings();

		foreach ( array_keys( self::assignable_roles() ) as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			$wanted = isset( $granted[ $slug ] ) ? $granted[ $slug ] : array();
			foreach ( self::all() as $cap ) {
				$has = $role->has_cap( $cap );
				if ( in_array( $cap, $wanted, true ) ) {
					if ( ! $has ) {
						$role->add_cap( $cap );
					}
				} elseif ( $has ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	public static function remove_all() {
		foreach ( array_keys( wp_roles()->get_names() ) as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all() as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
		delete_option( self::ROLE_VERSION_OPTION );
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::MANAGE ) || current_user_can( 'edit_posts' );
	}

	/**
	 * @return bool
	 */
	public static function can_view_entries() {
		return current_user_can( self::VIEW ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize role => caps map from settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, array<int, string>>
	 */
	public static function sanitize_role_caps( $input ) {
		$roles = self::assignable_roles();
		$clean = array();

		foreach ( (array) $input as $role => $caps ) {
			$role = sanitize_key( (string) $role );
			if ( ! isset( $roles[ $role ] ) ) {
				continue;
			}
			$keys = array_map( 'sanitize_key', array_keys( (array) $caps ) );
			$caps = array_values( array_intersect( $keys, self::all() ) );
			if ( $caps ) {
				$clean[ $role ] = $caps;
			}
		}

		return $clean;
	}
}
