<?php
/**
 * Feature capability registry (Free vs Pro).
 *
 * Free ships core forms. Pro registers capabilities after a valid license in Thimbleform Pro.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Features {

	const MULTI_STEP         = 'multi_step';
	const WEBHOOK            = 'webhook';
	const ADVANCED_FIELDS    = 'advanced_fields';
	const ADVANCED_ANALYTICS = 'advanced_analytics';
	const UNLIMITED_FORMS    = 'unlimited_forms';
	const LEAD_INSIGHTS      = 'lead_insights';
	const QUIZ_SURVEY        = 'quiz_survey';
	const EMAIL_DESIGNER     = 'email_designer';
	const CALCULATED_FIELDS  = 'calculated_fields';
	const REPEATERS          = 'repeaters';
	const PDF_EXPORT         = 'pdf_export';
	const AUTOMATIONS        = 'automations';
	const PAYMENTS           = 'payments';
	const HUBSPOT            = 'hubspot';
	const RECRUITING         = 'recruiting';

	/**
	 * Registered Pro capabilities.
	 *
	 * @var array<string, true>
	 */
	private static $capabilities = array();

	/**
	 * Known feature keys (documentation + validation).
	 *
	 * @return array<int, string>
	 */
	public static function all_keys() {
		return array(
			self::MULTI_STEP,
			self::WEBHOOK,
			self::ADVANCED_FIELDS,
			self::ADVANCED_ANALYTICS,
			self::UNLIMITED_FORMS,
			self::LEAD_INSIGHTS,
			self::QUIZ_SURVEY,
			self::EMAIL_DESIGNER,
			self::CALCULATED_FIELDS,
			self::REPEATERS,
			self::PDF_EXPORT,
			self::AUTOMATIONS,
			self::PAYMENTS,
			self::HUBSPOT,
			self::RECRUITING,
		);
	}

	/**
	 * Register a capability (called from Thimbleform Pro after license validation).
	 *
	 * @param string $feature Feature key.
	 */
	public static function register( $feature ) {
		if ( ! self::registration_allowed() ) {
			return;
		}
		$feature = sanitize_key( (string) $feature );
		if ( '' === $feature || ! in_array( $feature, self::all_keys(), true ) ) {
			return;
		}
		self::$capabilities[ $feature ] = true;
	}

	/**
	 * Whether capability registration is allowed (licensed Pro add-on).
	 *
	 * @return bool
	 */
	private static function registration_allowed() {
		return defined( 'THIMBLEFORM_PRO_VERSION' )
			|| class_exists( 'Thimbleform_Pro_License' )
			|| class_exists( 'Thimbleform_Pro' );
	}

	/**
	 * Register several capabilities at once.
	 *
	 * @param array<int, string> $features Feature keys.
	 */
	public static function register_many( array $features ) {
		foreach ( $features as $feature ) {
			self::register( (string) $feature );
		}
	}

	/**
	 * Whether a capability is active.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function can( $feature ) {
		$feature = sanitize_key( (string) $feature );
		if ( self::WEBHOOK === $feature ) {
			return (bool) apply_filters( 'nestform_feature_allowed', true, $feature );
		}
		$allowed = ! empty( self::$capabilities[ $feature ] );

		if ( ! $allowed ) {
			return false;
		}
		return (bool) apply_filters( 'nestform_feature_allowed', true, $feature );
	}

	/**
	 * Form limit. Free is unlimited; kept for backward compatibility.
	 *
	 * @return int Always 0 (unlimited).
	 */
	public static function form_limit() {
		return 0;
	}

	/**
	 * Whether another form may be created or duplicated.
	 *
	 * @return bool
	 */
	public static function can_create_form() {
		return true;
	}

	/**
	 * Pro-only field type labels (used when Pro registers capabilities).
	 *
	 * @return array<string, string>
	 */
	public static function advanced_field_teasers() {
		$labels = array(
			'rating'    => __( 'Rating', 'nestform' ),
			'signature' => __( 'Signature', 'nestform' ),
			'nps'       => __( 'NPS', 'nestform' ),
			'scale'     => __( 'Scale', 'nestform' ),
			'ranking'   => __( 'Ranking', 'nestform' ),
			'matrix'    => __( 'Matrix', 'nestform' ),
		);

		/**
		 * Filter Pro field type labels.
		 *
		 * @param array<string, string> $labels Type => label.
		 */
		return (array) apply_filters( 'nestform_advanced_field_teasers', $labels );
	}

	/**
	 * Specialty Pro field labels (calculated, repeater).
	 *
	 * @return array<string, string>
	 */
	public static function specialty_field_teasers() {
		return array(
			'calculated' => __( 'Calculated', 'nestform' ),
			'repeater'   => __( 'Repeater', 'nestform' ),
			'payment'    => __( 'Payment', 'nestform' ),
		);
	}

	/**
	 * Field types that require a Pro capability.
	 *
	 * @return array<int, string>
	 */
	public static function pro_field_types() {
		return array_merge(
			array_keys( self::advanced_field_teasers() ),
			array_keys( self::specialty_field_teasers() )
		);
	}

	/**
	 * Capability required for a Pro field type.
	 *
	 * @param string $type Field type.
	 * @return string Feature key or empty.
	 */
	public static function capability_for_field_type( $type ) {
		$type = sanitize_key( (string) $type );
		if ( isset( self::advanced_field_teasers()[ $type ] ) ) {
			return self::ADVANCED_FIELDS;
		}
		if ( 'calculated' === $type ) {
			return self::CALCULATED_FIELDS;
		}
		if ( 'repeater' === $type ) {
			return self::REPEATERS;
		}
		if ( 'payment' === $type ) {
			return self::PAYMENTS;
		}
		return '';
	}

	/**
	 * Whether a field type is Pro-only.
	 *
	 * @param string $type Field type.
	 * @return bool
	 */
	public static function is_pro_field_type( $type ) {
		return in_array( sanitize_key( (string) $type ), self::pro_field_types(), true );
	}

	/**
	 * Whether a Pro field type is currently unlocked.
	 *
	 * @param string $type Field type.
	 * @return bool
	 */
	public static function can_use_field_type( $type ) {
		$cap = self::capability_for_field_type( $type );
		if ( '' === $cap ) {
			return true;
		}
		return self::can( $cap );
	}
}
