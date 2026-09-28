<?php
/**
 * Thimbleform Pro upgrade surface (upsell UI; capabilities come from Thimbleform Pro).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Upgrade {

	const LEGACY_PAGE_SLUG = 'nestform-upgrade';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_pages' ) );
	}

	/**
	 * Old Upgrade / Account slugs → current Pro / License screens.
	 *
	 * Do not redirect Freemius `nestform-account` — the SDK needs that slug
	 * for activate / sync / billing. Only remap our legacy Forms Account slug.
	 */
	public static function redirect_legacy_pages() {
		if ( ! is_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::LEGACY_PAGE_SLUG === $page && class_exists( 'Nestform_Promotion' ) ) {
			wp_safe_redirect( Nestform_Promotion::url() );
			exit;
		}
		if ( 'nestform-forms-account' === $page && class_exists( 'Nestform_Pro_License' ) ) {
			wp_safe_redirect( Nestform_Pro_License::url() );
			exit;
		}
	}

	/**
	 * Whether this site has an active Pro license.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		if ( class_exists( 'Nestform_Pro_License' ) && Nestform_Pro_License::is_valid() ) {
			return true;
		}
		return (bool) apply_filters( 'nestform_is_pro', false );
	}

	/**
	 * Plan catalog (Free, Pro, Agency) — shared by Upgrade and upsells.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function plan_catalog() {
		$free_features = array(
			__( 'Unlimited forms', 'nestform' ),
			__( 'Lead & contact forms with templates', 'nestform' ),
			__( 'Conditional logic & file uploads', 'nestform' ),
			__( 'Entries inbox, CSV export & captcha', 'nestform' ),
			__( 'Email notifications & spam protection', 'nestform' ),
			__( 'Outbound webhooks (up to 5 endpoints per form)', 'nestform' ),
			__( 'Basic analytics', 'nestform' ),
		);

		$pro_features = array(
			__( 'Multi-step forms & branch rules', 'nestform' ),
			__( 'Quizzes & surveys with scoring, result bands, timers, and charts', 'nestform' ),
			__( 'HTML email designer & PDF attachments', 'nestform' ),
			__( 'Automations, calculated fields & repeaters', 'nestform' ),
			__( 'Stripe payment fields', 'nestform' ),
			__( 'HubSpot contact sync', 'nestform' ),
			__( 'Rating, signature, NPS, and other advanced fields', 'nestform' ),
			__( 'Conversion metrics & lead insights', 'nestform' ),
			__( 'Optional Recruiting: jobs, recruiter inbox, and pipeline', 'nestform' ),
		);

		$agency_features = array(
			__( 'Everything in Pro', 'nestform' ),
			__( 'Same features on up to 5 WordPress sites', 'nestform' ),
		);

		/**
		 * Filter the Thimbleform plan catalog (Upgrade, upsells).
		 *
		 * @param array<string, array<string, mixed>> $plans Plan definitions.
		 */
		return (array) apply_filters(
			'nestform_plan_catalog',
			array(
				'free'   => array(
					'id'                 => 'free',
					'name'               => __( 'Free', 'nestform' ),
					'tagline'            => __( 'Lead forms that ship', 'nestform' ),
					'price_monthly'      => '$0',
					'price_yearly'       => '$0',
					'price_unit_monthly' => __( '/ month', 'nestform' ),
					'price_unit_yearly'  => __( '/ year', 'nestform' ),
					'billed_yearly'      => '',
					'features'           => $free_features,
					'foot'               => __( 'Included with Thimbleform', 'nestform' ),
					'popular'            => false,
					'checkout_plan'      => '',
				),
				'pro'    => array(
					'id'                 => 'pro',
					'name'               => __( 'Pro', 'nestform' ),
					'tagline'            => __( 'Interactive forms & growth', 'nestform' ),
					'price_monthly'      => '$9.99',
					'price_yearly'       => '$89.99',
					'price_unit_monthly' => __( '/ month', 'nestform' ),
					'price_unit_yearly'  => __( '/ year', 'nestform' ),
					'billed_yearly'      => '',
					'features'           => $pro_features,
					'foot'               => __( 'Single WordPress site', 'nestform' ),
					'popular'            => true,
					'checkout_plan'      => 'pro',
				),
				'agency' => array(
					'id'                 => 'agency',
					'name'               => __( 'Agency', 'nestform' ),
					'tagline'            => __( 'Same features, up to 5 sites', 'nestform' ),
					'price_monthly'      => '$29.99',
					'price_yearly'       => '$269.99',
					'price_unit_monthly' => __( '/ month', 'nestform' ),
					'price_unit_yearly'  => __( '/ year', 'nestform' ),
					'billed_yearly'      => '',
					'features'           => $agency_features,
					'foot'               => __( 'Up to 5 client sites', 'nestform' ),
					'popular'            => false,
					'checkout_plan'      => 'agency',
				),
			)
		);
	}

	/**
	 * Human-readable price line for docs and tooltips.
	 *
	 * @param string $plan_key free|pro|agency.
	 * @return string
	 */
	public static function plan_price_summary( $plan_key ) {
		$catalog = self::plan_catalog();
		$key     = sanitize_key( (string) $plan_key );
		if ( ! isset( $catalog[ $key ] ) ) {
			return '';
		}
		$plan = $catalog[ $key ];
		if ( 'free' === $key ) {
			return (string) $plan['price_monthly'] . (string) $plan['price_unit_monthly'];
		}
		return sprintf(
			/* translators: 1: monthly price with unit, 2: yearly price with unit */
			__( '%1$s or %2$s', 'nestform' ),
			(string) $plan['price_monthly'] . (string) $plan['price_unit_monthly'],
			(string) $plan['price_yearly'] . (string) $plan['price_unit_yearly']
		);
	}

	/**
	 * Parse a display price string into a float (e.g. "$9.99" → 9.99).
	 *
	 * @param string $price Price label.
	 * @return float
	 */
	public static function parse_price_amount( $price ) {
		$price = (string) $price;
		if ( preg_match( '/[\d.,]+/', $price, $matches ) ) {
			return (float) str_replace( ',', '', $matches[0] );
		}
		return 0.0;
	}

	/**
	 * Yearly savings vs paying monthly for 12 months.
	 *
	 * @param string $plan_key free|pro|agency.
	 * @return int 0–100
	 */
	public static function plan_yearly_savings_percent( $plan_key ) {
		$catalog = self::plan_catalog();
		$key     = sanitize_key( (string) $plan_key );
		if ( ! isset( $catalog[ $key ] ) || empty( $catalog[ $key ]['checkout_plan'] ) ) {
			return 0;
		}

		$plan    = $catalog[ $key ];
		$monthly = self::parse_price_amount( (string) $plan['price_monthly'] );
		$yearly  = self::parse_price_amount( (string) $plan['price_yearly'] );
		if ( $monthly <= 0 || $yearly <= 0 ) {
			return 0;
		}

		$annual_from_monthly = $monthly * 12;
		if ( $annual_from_monthly <= $yearly ) {
			return 0;
		}

		return (int) round( ( ( $annual_from_monthly - $yearly ) / $annual_from_monthly ) * 100 );
	}

	/**
	 * Badge copy for the yearly billing toggle.
	 *
	 * @return string Empty when no paid yearly savings.
	 */
	public static function yearly_savings_badge_text() {
		$max     = 0;
		$amounts = array();

		foreach ( self::plan_catalog() as $plan_key => $plan ) {
			if ( empty( $plan['checkout_plan'] ) ) {
				continue;
			}
			$pct = self::plan_yearly_savings_percent( (string) $plan_key );
			if ( $pct <= 0 ) {
				continue;
			}
			$max       = max( $max, $pct );
			$amounts[] = $pct;
		}

		if ( $max <= 0 ) {
			return '';
		}

		$amounts = array_values( array_unique( $amounts ) );
		if ( count( $amounts ) > 1 ) {
			return sprintf(
				/* translators: %d: maximum yearly savings percent */
				__( 'Save up to %d%%', 'nestform' ),
				$max
			);
		}

		return sprintf(
			/* translators: %d: yearly savings percent */
			__( 'Save %d%%', 'nestform' ),
			$max
		);
	}

	/**
	 * Monthly equivalent when billed yearly (derived from catalog prices).
	 *
	 * @param string               $plan_key pro|agency.
	 * @param array<string, mixed> $plan     Plan row.
	 * @return string
	 */
	public static function plan_billed_yearly_label( $plan_key, $plan ) {
		$key = sanitize_key( (string) $plan_key );
		if ( empty( $plan['checkout_plan'] ) ) {
			return '';
		}

		$yearly = self::parse_price_amount( (string) $plan['price_yearly'] );
		if ( $yearly <= 0 ) {
			return isset( $plan['billed_yearly'] ) ? (string) $plan['billed_yearly'] : '';
		}

		$monthly_equiv = $yearly / 12;
		$symbol        = '$';
		if ( preg_match( '/^\s*([^\d\s.,]+)/', (string) $plan['price_yearly'], $matches ) ) {
			$symbol = (string) $matches[1];
		}

		$formatted = $symbol . number_format_i18n( $monthly_equiv, 2 );

		if ( 'agency' === $key ) {
			return sprintf(
				/* translators: %s: monthly equivalent price */
				__( '≈ %s / month, billed yearly · 5 sites', 'nestform' ),
				$formatted
			);
		}

		return sprintf(
			/* translators: %s: monthly equivalent price */
			__( '≈ %s / month, billed yearly', 'nestform' ),
			$formatted
		);
	}

	/**
	 * Public checkout / buy URL for Thimbleform Pro.
	 *
	 * @param string $plan Optional plan key (pro|agency).
	 * @return string
	 */
	public static function checkout_url( $plan = 'pro', $billing = 'monthly' ) {
		$plan    = sanitize_key( (string) $plan );
		$billing = sanitize_key( (string) $billing );
		if ( ! in_array( $plan, array( 'pro', 'agency' ), true ) ) {
			$plan = 'pro';
		}
		if ( ! in_array( $billing, array( 'monthly', 'yearly' ), true ) ) {
			$billing = 'monthly';
		}

		// Freemius plan IDs (not pricing IDs). Pricing packs: Pro 84761 / Agency 84776.
		$plan_id    = ( 'agency' === $plan ) ? 63298 : 63282;
		$pricing_id = ( 'agency' === $plan ) ? 84776 : 84761;
		$licenses   = ( 'agency' === $plan ) ? 5 : 1;
		$cycle      = ( 'yearly' === $billing ) ? 'annual' : 'monthly';
		$url        = add_query_arg(
			array(
				'billing_cycle' => $cycle,
				'pricing_id'    => $pricing_id,
			),
			sprintf(
				'https://checkout.freemius.com/product/%d/plan/%d/licenses/%d/',
				37981,
				$plan_id,
				$licenses
			)
		);

		if ( 'agency' === $plan ) {
			$url = apply_filters( 'nestform_agency_checkout_url', $url, $plan, $billing );
		} else {
			$url = apply_filters( 'nestform_pro_checkout_url', $url, $plan, $billing );
		}

		return esc_url_raw( (string) $url );
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		if ( class_exists( 'Nestform_Promotion' ) ) {
			return Nestform_Promotion::url( $args );
		}
		return add_query_arg(
			array_merge(
				array(
					'post_type' => Nestform_Post_Type::POST_TYPE,
					'page'      => self::LEGACY_PAGE_SLUG,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Compact PRO pill.
	 *
	 * @return string
	 */
	public static function pill_html() {
		return '<span class="nestform-pro-pill">'
			. nestform_admin_icon_html( 'pro' )
			. esc_html__( 'PRO', 'nestform' )
			. '</span>';
	}

	/**
	 * Primary Pro CTA label with arrow.
	 *
	 * @return string
	 */
	public static function cta_label_html() {
		return esc_html__( 'Upgrade to Pro', 'nestform' );
	}

	/**
	 * Sidebar Pro card (forms count lives in nav / inbox stats).
	 *
	 * @param int $forms_n Form count (unused; kept for callers).
	 */
	public static function render_sidebar( $forms_n = 0 ) {
		unset( $forms_n );
		if ( self::is_pro() || ! class_exists( 'Nestform_Promotion' ) || ! Nestform_Promotion::should_promote() ) {
			return;
		}
		?>
		<a class="nestform-app__pro" href="<?php echo esc_url( Nestform_Promotion::url() ); ?>">
			<span class="nestform-app__pro-kicker"><?php echo nestform_admin_icon_html( 'pro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?> <?php esc_html_e( 'Thimbleform Pro', 'nestform' ); ?></span>
			<span class="nestform-app__pro-copy"><?php esc_html_e( 'Quizzes, multi-step flows, PDF, Stripe, HubSpot, and optional Recruiting.', 'nestform' ); ?></span>
			<span class="nestform-pro-cta nestform-app__pro-cta"><?php esc_html_e( 'Learn more', 'nestform' ); ?></span>
		</a>
		<?php
	}

	/**
	 * @param array<string, array<string, mixed>> $plans Plan catalog.
	 */
	public static function render_plan_cards( array $plans ) {
		?>
		<div class="nestform-upgrade__grid">
			<?php foreach ( $plans as $plan_key => $plan ) : ?>
				<?php
				$article_class = 'nestform-upgrade__plan';
				if ( 'pro' === $plan_key ) {
					$article_class .= ' nestform-upgrade__plan--pro';
				} elseif ( 'agency' === $plan_key ) {
					$article_class .= ' nestform-upgrade__plan--agency';
				}
				$is_paid = ! empty( $plan['checkout_plan'] );
				?>
				<article class="<?php echo esc_attr( $article_class ); ?>">
					<?php if ( ! empty( $plan['popular'] ) ) : ?>
						<span class="nestform-upgrade__popular"><?php esc_html_e( 'Most popular', 'nestform' ); ?></span>
					<?php endif; ?>
					<div class="nestform-upgrade__plan-top">
						<h2 class="nestform-upgrade__plan-name">
							<?php echo esc_html( (string) $plan['name'] ); ?>
							<?php
							if ( 'pro' === $plan_key ) {
								echo self::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</h2>
						<p class="nestform-upgrade__plan-tag"><?php echo esc_html( (string) $plan['tagline'] ); ?></p>
					</div>
					<?php if ( $is_paid ) : ?>
						<p class="nestform-upgrade__price" data-nestform-price-monthly<?php echo 'agency' === $plan_key ? ' data-nestform-agency-monthly' : ''; ?>>
							<?php echo esc_html( (string) $plan['price_monthly'] ); ?><span class="nestform-upgrade__price-unit"><?php echo esc_html( (string) $plan['price_unit_monthly'] ); ?></span>
						</p>
						<p class="nestform-upgrade__price" data-nestform-price-yearly<?php echo 'agency' === $plan_key ? ' data-nestform-agency-yearly' : ''; ?> hidden>
							<?php echo esc_html( (string) $plan['price_yearly'] ); ?><span class="nestform-upgrade__price-unit"><?php echo esc_html( (string) $plan['price_unit_yearly'] ); ?></span>
						</p>
						<?php
						$billed_yearly = self::plan_billed_yearly_label( (string) $plan_key, $plan );
						if ( $billed_yearly ) :
							?>
							<p class="nestform-upgrade__billed" data-nestform-billed-yearly hidden><?php echo esc_html( $billed_yearly ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p class="nestform-upgrade__price">
							<?php echo esc_html( (string) $plan['price_monthly'] ); ?><span class="nestform-upgrade__price-unit"><?php echo esc_html( (string) $plan['price_unit_monthly'] ); ?></span>
						</p>
					<?php endif; ?>
					<ul class="nestform-upgrade__list">
						<?php foreach ( (array) $plan['features'] as $item ) : ?>
							<li><?php echo esc_html( (string) $item ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $is_paid ) : ?>
						<?php
						$checkout_plan = sanitize_key( (string) $plan['checkout_plan'] );
						$cta_class     = 'pro' === $checkout_plan
							? 'nestform-pro-cta nestform-upgrade__cta'
							: 'nestform-btn nestform-btn--outline nestform-upgrade__cta nestform-upgrade__cta--agency';
						?>
						<a
							class="<?php echo esc_attr( $cta_class ); ?>"
							href="<?php echo esc_url( self::checkout_url( $checkout_plan, 'monthly' ) ); ?>"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php
							if ( 'pro' === $checkout_plan ) {
								esc_html_e( 'Buy Pro', 'nestform' );
							} else {
								esc_html_e( 'Get Agency', 'nestform' );
							}
							?>
						</a>
					<?php elseif ( ! empty( $plan['foot'] ) ) : ?>
						<span class="nestform-upgrade__plan-foot"><?php echo esc_html( (string) $plan['foot'] ); ?></span>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<p class="nestform-upgrade__trust">
			<span class="nestform-upgrade__trust-item"><?php esc_html_e( '30-day money-back guarantee', 'nestform' ); ?></span>
			<span class="nestform-upgrade__trust-item"><?php esc_html_e( 'Cancel anytime', 'nestform' ); ?></span>
			<span class="nestform-upgrade__trust-item"><?php esc_html_e( 'Install Thimbleform Pro after purchase', 'nestform' ); ?></span>
		</p>
		<?php
	}
}
