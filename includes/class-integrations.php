<?php
/**
 * Integrations (Forms → Integrations). Captcha, Stripe, HubSpot, and future connectors.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Integrations {

	const PAGE_SLUG = 'thimbleform-integrations';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 41 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * @param array<string, string> $args Query args (e.g. section).
	 * @return string
	 */
	public static function url( $args = array() ) {
		$query = array_merge(
			array(
				'post_type' => Thimbleform_Post_Type::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			is_array( $args ) ? $args : array()
		);
		return add_query_arg( $query, admin_url( 'edit.php' ) );
	}

	/**
	 * Top-level Integrations tabs.
	 *
	 * @return array<string, array{label:string,desc:string}>
	 */
	public static function sections() {
		$sections = array(
			'captcha' => array(
				'label' => __( 'Captcha', 'thimbleform' ),
				'desc'  => __( 'One site-wide captcha provider. Forms opt in under Spam & privacy.', 'thimbleform' ),
			),
			'stripe'  => array(
				'label' => __( 'Stripe', 'thimbleform' ),
				'desc'  => __( 'Card payments via the Thimbleform Pro add-on. Setup appears when Pro is licensed.', 'thimbleform' ),
			),
			'hubspot' => array(
				'label' => __( 'HubSpot', 'thimbleform' ),
				'desc'  => __( 'CRM contact sync via the Thimbleform Pro add-on. Setup appears when Pro is licensed.', 'thimbleform' ),
			),
			'more'    => array(
				'label' => __( 'More', 'thimbleform' ),
				'desc'  => __( 'Webhooks and planned native connectors.', 'thimbleform' ),
			),
		);

		$promote = class_exists( 'Thimbleform_Promotion' ) && Thimbleform_Promotion::should_promote();
		if ( ! $promote && ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::PAYMENTS ) ) ) {
			unset( $sections['stripe'] );
		}
		if ( ! $promote && ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( Thimbleform_Features::HUBSPOT ) ) ) {
			unset( $sections['hubspot'] );
		}

		/**
		 * Filter Integrations sidebar sections (Pro modules, etc.).
		 *
		 * @param array<string, array{label:string,desc:string}> $sections Sections.
		 */
		return apply_filters( 'thimbleform_integrations_sections', $sections );
	}

	/**
	 * @return string
	 */
	public static function current_section() {
		$sections = self::sections();
		$section  = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'captcha'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $sections[ $section ] ) ) {
			$section = 'captcha';
		}
		return $section;
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE,
			__( 'Integrations', 'thimbleform' ),
			__( 'Integrations', 'thimbleform' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			$classes .= ' thimbleform-admin-screen thimbleform-integrations-screen thimbleform-settings-screen';
		}
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver_css = (string) filemtime( thimbleform_admin_css_path() );
		wp_enqueue_style(
			'thimbleform-admin',
			thimbleform_admin_css_url(),
			thimbleform_admin_style_deps(),
			$ver_css ? $ver_css : THIMBLEFORM_VERSION
		);

		if ( 'captcha' === self::current_section() ) {
			$js = thimbleform_admin_js_path( 'integrations-captcha.js' );
			if ( is_readable( $js ) ) {
				$ver_js = (string) filemtime( $js );
				wp_enqueue_script(
					'thimbleform-integrations-captcha',
					thimbleform_admin_js_url( 'integrations-captcha.js' ),
					array(),
					$ver_js ? $ver_js : THIMBLEFORM_VERSION,
					true
				);
			}
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit these settings.', 'thimbleform' ) );
		}

		$sections = self::sections();
		$section  = self::current_section();
		$s        = Thimbleform_Settings::get();
		$opt      = Thimbleform_Settings::OPTION;
		?>
		<div class="wrap thimbleform-admin thimbleform-settings thimbleform-integrations">
			<?php
			thimbleform_render_page_head(
				array(
					'title'       => __( 'Integrations', 'thimbleform' ),
					'description' => (string) $sections[ $section ]['desc'],
					'icon'        => 'integrations',
				)
			);
			settings_errors();
			?>

			<div class="thimbleform-settings__layout">
				<nav class="thimbleform-settings__nav" aria-label="<?php esc_attr_e( 'Integrations sections', 'thimbleform' ); ?>">
					<?php foreach ( $sections as $id => $meta ) : ?>
						<a
							class="thimbleform-settings__nav-item<?php echo $section === $id ? ' thimbleform-settings__nav-item--active' : ''; ?>"
							href="<?php echo esc_url( self::url( array( 'section' => $id ) ) ); ?>"
						>
							<?php echo esc_html( (string) $meta['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="thimbleform-settings__main">
					<h2 class="thimbleform-settings__section-title screen-reader-text"><?php echo esc_html( (string) $sections[ $section ]['label'] ); ?></h2>

					<?php if ( 'captcha' === $section ) : ?>
						<?php self::render_captcha_section( $s, $opt ); ?>
					<?php elseif ( 'stripe' === $section ) : ?>
						<?php self::render_stripe_section( $s, $opt ); ?>
					<?php elseif ( 'hubspot' === $section ) : ?>
						<?php self::render_hubspot_section( $s, $opt ); ?>
					<?php elseif ( 'more' === $section ) : ?>
						<?php self::render_more_section(); ?>
					<?php else : ?>
						<?php
						/**
						 * Render a custom Integrations section (e.g. Recruiting).
						 *
						 * @param string               $section Section id.
						 * @param array<string, mixed> $s       Settings.
						 * @param string               $opt     Option name.
						 */
						do_action( 'thimbleform_integrations_section', $section, $s, $opt );
						?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $s   Settings.
	 * @param string               $opt Option name.
	 */
	private static function render_captcha_section( array $s, $opt ) {
		$provider  = (string) $s['captcha_provider'];
		$providers = class_exists( 'Thimbleform_Captcha' ) ? Thimbleform_Captcha::providers() : array();
		$keys      = isset( $s['captcha_keys'] ) && is_array( $s['captcha_keys'] ) ? $s['captcha_keys'] : Thimbleform_Settings::empty_captcha_keys();
		$enabled   = '1' === (string) $s['captcha_enabled'];
		?>
		<form method="post" action="options.php" class="thimbleform-settings__form">
			<?php settings_fields( 'thimbleform_settings' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="integrations_captcha" />

			<div class="thimbleform-admin__surface thimbleform-settings__card thimbleform-captcha-integ" data-thimbleform-captcha-tabs>
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Captcha', 'thimbleform' ); ?></h3>
					</div>
				</div>

				<table class="form-table thimbleform-settings__table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable captcha', 'thimbleform' ); ?></th>
						<td>
							<fieldset>
								<label class="thimbleform-admin__check" for="thimbleform_captcha_enabled">
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[captcha_enabled]" value="0" />
									<input
										type="checkbox"
										id="thimbleform_captcha_enabled"
										name="<?php echo esc_attr( $opt ); ?>[captcha_enabled]"
										value="1"
										<?php checked( $enabled ); ?>
									/>
									<span><?php esc_html_e( 'Allow captcha on Thimbleform forms', 'thimbleform' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Each form still needs “Enable captcha on this form” under Spam & privacy.', 'thimbleform' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>

				<input
					type="hidden"
					name="<?php echo esc_attr( $opt ); ?>[captcha_provider]"
					value="<?php echo esc_attr( $provider ); ?>"
					data-thimbleform-captcha-provider
				/>

				<nav class="thimbleform-settings__subnav thimbleform-captcha-integ__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Captcha provider', 'thimbleform' ); ?>">
					<?php foreach ( $providers as $slug => $meta ) : ?>
						<?php
						$is_active = ( $provider === $slug );
						$tab_label = ! empty( $meta['short'] ) ? (string) $meta['short'] : (string) $meta['label'];
						$has_keys  = ! empty( $keys[ $slug ]['site'] ) && ! empty( $keys[ $slug ]['secret'] );
						?>
						<button
							type="button"
							class="thimbleform-settings__subnav-item<?php echo $is_active ? ' thimbleform-settings__subnav-item--active' : ''; ?>"
							role="tab"
							id="thimbleform-captcha-tab-<?php echo esc_attr( $slug ); ?>"
							aria-controls="thimbleform-captcha-panel-<?php echo esc_attr( $slug ); ?>"
							aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
							tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
							data-thimbleform-captcha-tab="<?php echo esc_attr( $slug ); ?>"
						>
							<span class="thimbleform-captcha-integ__tab-label"><?php echo esc_html( $tab_label ); ?></span>
							<?php if ( $is_active && $enabled && $has_keys ) : ?>
								<span class="thimbleform-badge thimbleform-badge--ok thimbleform-captcha-integ__tab-badge"><?php esc_html_e( 'Active', 'thimbleform' ); ?></span>
							<?php elseif ( $has_keys ) : ?>
								<span class="thimbleform-badge thimbleform-badge--draft thimbleform-captcha-integ__tab-badge"><?php esc_html_e( 'Saved', 'thimbleform' ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</nav>
				<p class="description thimbleform-captcha-integ__tabs-hint"><?php esc_html_e( 'Choose a provider, then Save to apply. Only one can be active site-wide.', 'thimbleform' ); ?></p>

				<?php foreach ( $providers as $slug => $meta ) : ?>
					<?php
					$is_active  = ( $provider === $slug );
					$site_val   = isset( $keys[ $slug ]['site'] ) ? (string) $keys[ $slug ]['site'] : '';
					$secret_val = isset( $keys[ $slug ]['secret'] ) ? (string) $keys[ $slug ]['secret'] : '';
					$site_id    = 'thimbleform_captcha_site_' . $slug;
					$secret_id  = 'thimbleform_captcha_secret_' . $slug;
					?>
					<div
						class="thimbleform-captcha-integ__panel<?php echo $is_active ? ' is-active' : ''; ?>"
						id="thimbleform-captcha-panel-<?php echo esc_attr( $slug ); ?>"
						role="tabpanel"
						aria-labelledby="thimbleform-captcha-tab-<?php echo esc_attr( $slug ); ?>"
						data-thimbleform-captcha-panel="<?php echo esc_attr( $slug ); ?>"
						<?php echo $is_active ? '' : ' hidden'; ?>
					>
						<p class="thimbleform-captcha-integ__panel-lead">
							<?php echo esc_html( (string) $meta['label'] ); ?>
							<?php if ( ! empty( $meta['keys_url'] ) ) : ?>
								—
								<a href="<?php echo esc_url( (string) $meta['keys_url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Get API keys', 'thimbleform' ); ?>
								</a>
							<?php endif; ?>
						</p>
						<table class="form-table thimbleform-settings__table" role="presentation">
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $site_id ); ?>"><?php esc_html_e( 'Site key', 'thimbleform' ); ?></label></th>
								<td>
									<input
										type="text"
										class="regular-text thimbleform-admin__input"
										id="<?php echo esc_attr( $site_id ); ?>"
										name="<?php echo esc_attr( $opt ); ?>[captcha_keys][<?php echo esc_attr( $slug ); ?>][site]"
										value="<?php echo esc_attr( $site_val ); ?>"
										autocomplete="off"
										spellcheck="false"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $secret_id ); ?>"><?php esc_html_e( 'Secret key', 'thimbleform' ); ?></label></th>
								<td>
									<input
										type="password"
										class="regular-text thimbleform-admin__input"
										id="<?php echo esc_attr( $secret_id ); ?>"
										name="<?php echo esc_attr( $opt ); ?>[captcha_keys][<?php echo esc_attr( $slug ); ?>][secret]"
										value="<?php echo esc_attr( $secret_val ); ?>"
										autocomplete="new-password"
										spellcheck="false"
									/>
								</td>
							</tr>
							<?php if ( 'recaptcha_v3' === $slug ) : ?>
								<tr>
									<th scope="row"><label for="thimbleform_captcha_v3_score"><?php esc_html_e( 'Min score', 'thimbleform' ); ?></label></th>
									<td>
										<input
											type="number"
											class="small-text thimbleform-admin__input"
											id="thimbleform_captcha_v3_score"
											name="<?php echo esc_attr( $opt ); ?>[captcha_v3_score]"
											min="0"
											max="1"
											step="0.1"
											value="<?php echo esc_attr( (string) $s['captcha_v3_score'] ); ?>"
										/>
										<p class="description"><?php esc_html_e( '0.0–1.0 (default 0.5). Lower accepts more traffic; higher is stricter.', 'thimbleform' ); ?></p>
									</td>
								</tr>
							<?php endif; ?>
						</table>
					</div>
				<?php endforeach; ?>

				<div class="thimbleform-settings__card-foot">
					<button type="submit" class="thimbleform-btn thimbleform-btn--primary" name="submit" value="1">
						<?php thimbleform_admin_icon( 'save' ); ?>
						<?php esc_html_e( 'Save captcha', 'thimbleform' ); ?>
					</button>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * @param array<string, mixed> $s   Settings.
	 * @param string               $opt Option name.
	 */
	private static function render_stripe_section( array $s, $opt ) {
		$can_payments = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::PAYMENTS );
		if ( ! $can_payments ) {
			if ( class_exists( 'Thimbleform_Promotion' ) ) {
				Thimbleform_Promotion::render_feature_teaser(
					array(
						'title' => __( 'Stripe payments', 'thimbleform' ),
						'copy'  => __( 'Card payments and Payment fields ship with the Thimbleform Pro add-on.', 'thimbleform' ),
						'cta'   => __( 'See Thimbleform Pro', 'thimbleform' ),
					)
				);
			}
			return;
		}

		$stripe_enabled = '1' === (string) ( $s['stripe_enabled'] ?? '0' );
		$stripe_mode    = in_array( (string) ( $s['stripe_mode'] ?? 'test' ), array( 'test', 'live' ), true )
			? (string) $s['stripe_mode']
			: 'test';
		$stripe_keys    = isset( $s['stripe_keys'] ) && is_array( $s['stripe_keys'] )
			? $s['stripe_keys']
			: Thimbleform_Settings::empty_stripe_keys();
		$stripe_ready   = Thimbleform_Settings::stripe_ready();
		?>
		<form method="post" action="options.php" class="thimbleform-settings__form">
			<?php settings_fields( 'thimbleform_settings' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="integrations_stripe" />

			<div class="thimbleform-admin__surface thimbleform-settings__card thimbleform-stripe-integ">
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'Stripe', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc">
							<?php esc_html_e( 'Accept card payments on Thimbleform Pro payment fields. Keys stay on this site — Thimbleform never sees card numbers.', 'thimbleform' ); ?>
						</p>
					</div>
					<?php if ( $stripe_ready ) : ?>
						<span class="thimbleform-badge thimbleform-badge--ok"><?php esc_html_e( 'Ready', 'thimbleform' ); ?></span>
					<?php else : ?>
						<span class="thimbleform-badge thimbleform-badge--draft"><?php esc_html_e( 'Not configured', 'thimbleform' ); ?></span>
					<?php endif; ?>
				</div>
				<table class="form-table thimbleform-settings__table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Stripe', 'thimbleform' ); ?></th>
						<td>
							<label class="thimbleform-admin__check" for="thimbleform_stripe_enabled">
								<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[stripe_enabled]" value="0" />
								<input
									type="checkbox"
									id="thimbleform_stripe_enabled"
									name="<?php echo esc_attr( $opt ); ?>[stripe_enabled]"
									value="1"
									<?php checked( $stripe_enabled ); ?>
								/>
								<span><?php esc_html_e( 'Allow Stripe payments on Thimbleform forms', 'thimbleform' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'Master switch. Each form still needs “Enable Stripe payments”, and a Payment field on the form.', 'thimbleform' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="thimbleform_stripe_mode"><?php esc_html_e( 'Mode', 'thimbleform' ); ?></label></th>
						<td>
							<select
								class="thimbleform-admin__input"
								id="thimbleform_stripe_mode"
								name="<?php echo esc_attr( $opt ); ?>[stripe_mode]"
							>
								<option value="test" <?php selected( $stripe_mode, 'test' ); ?>><?php esc_html_e( 'Test', 'thimbleform' ); ?></option>
								<option value="live" <?php selected( $stripe_mode, 'live' ); ?>><?php esc_html_e( 'Live', 'thimbleform' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Use Test keys while building; switch to Live only when ready to charge real cards.', 'thimbleform' ); ?></p>
						</td>
					</tr>
					<?php foreach ( array( 'test' => __( 'Test keys', 'thimbleform' ), 'live' => __( 'Live keys', 'thimbleform' ) ) as $mode_slug => $mode_label ) : ?>
						<?php
						$row = isset( $stripe_keys[ $mode_slug ] ) && is_array( $stripe_keys[ $mode_slug ] )
							? $stripe_keys[ $mode_slug ]
							: array( 'publishable' => '', 'secret' => '' );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $mode_label ); ?></th>
							<td>
								<div class="thimbleform-integ-keys">
									<div class="thimbleform-integ-keys__field">
										<label for="thimbleform_stripe_pub_<?php echo esc_attr( $mode_slug ); ?>">
											<?php esc_html_e( 'Publishable key', 'thimbleform' ); ?>
										</label>
										<input
											type="text"
											class="regular-text thimbleform-admin__input"
											id="thimbleform_stripe_pub_<?php echo esc_attr( $mode_slug ); ?>"
											name="<?php echo esc_attr( $opt ); ?>[stripe_keys][<?php echo esc_attr( $mode_slug ); ?>][publishable]"
											value="<?php echo esc_attr( (string) ( $row['publishable'] ?? '' ) ); ?>"
											autocomplete="off"
											spellcheck="false"
										/>
									</div>
									<div class="thimbleform-integ-keys__field">
										<label for="thimbleform_stripe_sec_<?php echo esc_attr( $mode_slug ); ?>">
											<?php esc_html_e( 'Secret key', 'thimbleform' ); ?>
										</label>
										<input
											type="password"
											class="regular-text thimbleform-admin__input"
											id="thimbleform_stripe_sec_<?php echo esc_attr( $mode_slug ); ?>"
											name="<?php echo esc_attr( $opt ); ?>[stripe_keys][<?php echo esc_attr( $mode_slug ); ?>][secret]"
											value="<?php echo esc_attr( (string) ( $row['secret'] ?? '' ) ); ?>"
											autocomplete="off"
											spellcheck="false"
										/>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description">
					<a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Stripe API keys', 'thimbleform' ); ?></a>
				</p>
				<button type="submit" class="thimbleform-btn thimbleform-btn--primary" name="submit" value="1">
					<?php thimbleform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Save Stripe', 'thimbleform' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * @param array<string, mixed> $s   Settings.
	 * @param string               $opt Option name.
	 */
	private static function render_hubspot_section( array $s, $opt ) {
		$can_hubspot = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::HUBSPOT );
		if ( ! $can_hubspot ) {
			if ( class_exists( 'Thimbleform_Promotion' ) ) {
				Thimbleform_Promotion::render_feature_teaser(
					array(
						'title' => __( 'HubSpot contact sync', 'thimbleform' ),
						'copy'  => __( 'Create or update HubSpot contacts from submissions with Thimbleform Pro.', 'thimbleform' ),
						'cta'   => __( 'See Thimbleform Pro', 'thimbleform' ),
					)
				);
			}
			return;
		}

		$hubspot_enabled = '1' === (string) ( $s['hubspot_enabled'] ?? '0' );
		$hubspot_ready   = Thimbleform_Settings::hubspot_ready();
		$token           = (string) ( $s['hubspot_access_token'] ?? '' );
		?>
		<form method="post" action="options.php" class="thimbleform-settings__form">
			<?php settings_fields( 'thimbleform_settings' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="integrations_hubspot" />

			<div class="thimbleform-admin__surface thimbleform-settings__card thimbleform-hubspot-integ">
				<div class="thimbleform-admin__panel-head">
					<div>
						<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'HubSpot', 'thimbleform' ); ?></h3>
						<p class="thimbleform-admin__panel-desc">
							<?php esc_html_e( 'Sync successful submissions to HubSpot CRM contacts. Use a Private App access token with crm.objects.contacts write scope.', 'thimbleform' ); ?>
						</p>
					</div>
					<?php if ( $hubspot_ready ) : ?>
						<span class="thimbleform-badge thimbleform-badge--ok"><?php esc_html_e( 'Ready', 'thimbleform' ); ?></span>
					<?php else : ?>
						<span class="thimbleform-badge thimbleform-badge--draft"><?php esc_html_e( 'Not configured', 'thimbleform' ); ?></span>
					<?php endif; ?>
				</div>
				<table class="form-table thimbleform-settings__table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable HubSpot', 'thimbleform' ); ?></th>
						<td>
							<label class="thimbleform-admin__check" for="thimbleform_hubspot_enabled">
								<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[hubspot_enabled]" value="0" />
								<input
									type="checkbox"
									id="thimbleform_hubspot_enabled"
									name="<?php echo esc_attr( $opt ); ?>[hubspot_enabled]"
									value="1"
									<?php checked( $hubspot_enabled ); ?>
								/>
								<span><?php esc_html_e( 'Allow HubSpot contact sync on Thimbleform forms', 'thimbleform' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'Master switch. Each form still needs “Enable HubSpot” and field mapping.', 'thimbleform' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="thimbleform_hubspot_access_token"><?php esc_html_e( 'Private App token', 'thimbleform' ); ?></label></th>
						<td>
							<input
								type="password"
								class="regular-text thimbleform-admin__input"
								id="thimbleform_hubspot_access_token"
								name="<?php echo esc_attr( $opt ); ?>[hubspot_access_token]"
								value="<?php echo esc_attr( $token ); ?>"
								autocomplete="off"
								spellcheck="false"
							/>
							<p class="description"><?php esc_html_e( 'Create a Private App in HubSpot → Settings → Integrations → Private Apps. Required scopes: crm.objects.contacts read and write.', 'thimbleform' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="description">
					<a href="https://developers.hubspot.com/docs/api/private-apps" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'HubSpot Private Apps docs', 'thimbleform' ); ?></a>
				</p>
				<button type="submit" class="thimbleform-btn thimbleform-btn--primary" name="submit" value="1">
					<?php thimbleform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Save HubSpot', 'thimbleform' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	private static function render_more_section() {
		$webhook_url = class_exists( 'Thimbleform_Post_Type' )
			? admin_url( 'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE )
			: admin_url();
		$cards       = array(
			array(
				'title'  => __( 'Outbound webhooks', 'thimbleform' ),
				'desc'   => __( 'POST JSON on every successful submission to one or more HTTPS endpoints. Configure per form under Settings → Webhooks.', 'thimbleform' ),
				'status' => __( 'Built-in', 'thimbleform' ),
				'action' => array(
					'label' => __( 'Open forms', 'thimbleform' ),
					'url'   => $webhook_url,
				),
			),
			array(
				'title'  => __( 'Telegram', 'thimbleform' ),
				'desc'   => __( 'Push lead alerts to a Telegram chat or channel.', 'thimbleform' ),
				'status' => __( 'Coming soon', 'thimbleform' ),
				'action' => null,
			),
			array(
				'title'  => __( 'Slack', 'thimbleform' ),
				'desc'   => __( 'Notify a Slack channel when a form converts.', 'thimbleform' ),
				'status' => __( 'Coming soon', 'thimbleform' ),
				'action' => null,
			),
			array(
				'title'  => __( 'Google Sheets', 'thimbleform' ),
				'desc'   => __( 'Append rows to a spreadsheet automatically.', 'thimbleform' ),
				'status' => __( 'Coming soon', 'thimbleform' ),
				'action' => null,
			),
		);
		?>
		<div class="thimbleform-admin__surface thimbleform-settings__card thimbleform-integ-cards">
			<div class="thimbleform-admin__panel-head">
				<div>
					<h3 class="thimbleform-admin__panel-title"><?php esc_html_e( 'More connectors', 'thimbleform' ); ?></h3>
					<p class="thimbleform-admin__panel-desc">
						<?php esc_html_e( 'Webhooks are built into Free. Telegram, Slack, and Sheets are planned.', 'thimbleform' ); ?>
					</p>
				</div>
			</div>
			<div class="thimbleform-integ-cards__grid">
				<?php foreach ( $cards as $card ) : ?>
					<article class="thimbleform-integ-card">
						<div class="thimbleform-integ-card__top">
							<h3 class="thimbleform-integ-card__title"><?php echo esc_html( $card['title'] ); ?></h3>
							<span class="thimbleform-integ-card__status"><?php echo esc_html( $card['status'] ); ?></span>
						</div>
						<p class="thimbleform-integ-card__desc"><?php echo esc_html( $card['desc'] ); ?></p>
						<?php if ( ! empty( $card['action']['url'] ) ) : ?>
							<a class="thimbleform-btn thimbleform-btn--outline thimbleform-integ-card__action" href="<?php echo esc_url( $card['action']['url'] ); ?>">
								<?php echo esc_html( (string) $card['action']['label'] ); ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
