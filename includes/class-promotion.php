<?php
/**
 * Pro promotion surfaces (free plugin only — does not gate features).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Promotion {

	const PAGE_SLUG = 'thimbleform-pro';

	const STORE_URL = 'https://checkout.freemius.com/product/37981/plan/63282/licenses/1/?billing_cycle=monthly&pricing_id=84761';

	const DOCS_URL = 'https://thimbleform.app/docs';

	const DISMISSED_META = 'thimbleform_pro_notice_dismissed';

	const EXPORT_COUNT_OPTION = 'nestform_export_count';

	const FORMS_BEFORE_NOTICE = 2;

	const FIELDS_BEFORE_NOTICE = 8;

	const EXPORTS_BEFORE_NOTICE = 3;

	const ENTRIES_BEFORE_INSIGHT = 25;

	const FORMS_SCANNED_FOR_SIGNAL = 25;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_hidden_page' ) );
		add_action( 'admin_post_nestform_dismiss_pro_notice', array( __CLASS__, 'dismiss_notice' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_pro_installed() {
		return defined( 'THIMBLEFORM_PRO_VERSION' );
	}

	/**
	 * @return bool
	 */
	public static function should_promote() {
		return (bool) apply_filters( 'nestform_show_promotions', ! self::is_pro_installed() );
	}

	/**
	 * @return string
	 */
	public static function store_url() {
		return (string) apply_filters( 'nestform_store_url', self::STORE_URL );
	}

	/**
	 * One-line summary shared by footer and settings card.
	 *
	 * @return string
	 */
	public static function summary() {
		return __( 'Multi-step flows, quizzes, HTML email, PDF, Stripe, HubSpot, automations, lead insights, and optional Recruiting.', 'nestform' );
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
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
	 * Job-by-job comparison (free vs Pro).
	 *
	 * @return array<int, array{job:string,free:string,pro:string}>
	 */
	private static function comparison() {
		return array(
			array(
				'job'  => __( 'Lead & contact forms', 'nestform' ),
				'free' => __( 'Unlimited forms, conditional logic, file uploads, spam protection, email notifications, entries inbox, and CSV export.', 'nestform' ),
				'pro'  => __( 'Multi-step flows with branch rules, PDF attachments, calculated fields, and repeaters.', 'nestform' ),
			),
			array(
				'job'  => __( 'Quizzes & surveys', 'nestform' ),
				'free' => __( 'Standard field types, templates, and entries you can read in the admin.', 'nestform' ),
				'pro'  => __( 'Scoring, result bands, timers, attempt limits, shareable results, and survey charts.', 'nestform' ),
			),
			array(
				'job'  => __( 'Getting answers where you need them', 'nestform' ),
				'free' => __( 'Per-form mail, CC/BCC, autoreply, CSV export, and outbound webhooks (up to 5 HTTPS endpoints per form).', 'nestform' ),
				'pro'  => __( 'Automations, HTML email designer, HubSpot sync, and Stripe payment fields.', 'nestform' ),
			),
			array(
				'job'  => __( 'Understanding performance', 'nestform' ),
				'free' => __( 'Dashboard with submission counts and a basic activity view.', 'nestform' ),
				'pro'  => __( 'Conversion metrics, lead insights, and deeper analytics charts.', 'nestform' ),
			),
			array(
				'job'  => __( 'Recruiting', 'nestform' ),
				'free' => __( 'Not included.', 'nestform' ),
				'pro'  => __( 'Optional module for job vacancies, a recruiter inbox, and a pipeline. Turn it on under Integrations.', 'nestform' ),
			),
		);
	}

	/**
	 * Pre-purchase FAQ.
	 *
	 * @return array<string, string>
	 */
	private static function questions() {
		return array(
			__( 'Does anything I have now change?', 'nestform' ) => __( 'No. Pro is a separate add-on: it extends the screens you already use. Your forms, entries, and settings stay as they are. Thimbleform (free) must stay active — Pro builds on it.', 'nestform' ),
			__( 'Do I need a license key for the free plugin?', 'nestform' ) => __( 'No. There is no sign-up and no key in the free plugin. Licensing belongs only to Thimbleform Pro (Freemius) after purchase.', 'nestform' ),
			__( 'What happens if I stop paying?', 'nestform' ) => __( 'Your data is never deleted. Pro features stop applying until you activate again — then they return without rebuilding forms.', 'nestform' ),
			__( 'How do I activate Pro?', 'nestform' ) => __( 'Buy via Freemius checkout, install Thimbleform Pro, then activate under Thimbleform → Account (or Thimbleform → License).', 'nestform' ),
			__( 'Is Agency a different product?', 'nestform' ) => __( 'No. Agency includes the same Pro features, including optional Recruiting. The license covers up to 5 sites instead of 1.', 'nestform' ),
		);
	}

	/**
	 * Old Pro links must not land on an unregistered admin screen.
	 */
	public static function redirect_hidden_page() {
		if ( ! is_admin() || self::should_promote() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		$post_type = class_exists( 'Nestform_Post_Type' ) ? Nestform_Post_Type::POST_TYPE : 'nestform';
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . $post_type ) );
		exit;
	}

	public static function register_menu() {
		if ( ! self::should_promote() ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Thimbleform Pro', 'nestform' ),
			__( 'Pro', 'nestform' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nestform' ) );
		}

		$store      = self::store_url();
		$plans      = class_exists( 'Nestform_Upgrade' ) ? Nestform_Upgrade::plan_catalog() : array();
		$save_badge = class_exists( 'Nestform_Upgrade' ) ? Nestform_Upgrade::yearly_savings_badge_text() : '';
		?>
		<div class="wrap nestform-upgrade">
			<?php
			nestform_render_page_head(
				array(
					'title'        => __( 'Thimbleform Pro', 'nestform' ),
					'description'  => __( 'Everything in Thimbleform is free and unlimited. Pro adds multi-step forms, quizzes, payments, and optional Recruiting. Agency is that same set on up to 5 sites.', 'nestform' ),
					'icon'         => 'pro',
					'actions_html' => '<a class="nestform-btn nestform-btn--primary" href="' . esc_url( $store ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View plans (Freemius)', 'nestform' ) . '</a>',
				)
			);
			?>
			<div class="nestform-upgrade__shell">
				<header class="nestform-upgrade__head">
					<span class="nestform-upgrade__badge">
						<?php echo nestform_admin_icon_html( 'pro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Thimbleform Pro', 'nestform' ); ?>
					</span>
					<h2 class="nestform-upgrade__title"><?php esc_html_e( 'Forms, quizzes & surveys that convert', 'nestform' ); ?></h2>
					<p class="nestform-upgrade__lead"><?php esc_html_e( 'Purchase via Freemius checkout, install Thimbleform Pro, then activate under Thimbleform → Account. Agency is the same feature set on up to 5 sites.', 'nestform' ); ?></p>
					<div class="nestform-upgrade__billing" data-nestform-billing>
						<button type="button" class="nestform-upgrade__bill is-active" data-plan="monthly"><?php esc_html_e( 'Monthly', 'nestform' ); ?></button>
						<button type="button" class="nestform-upgrade__bill" data-plan="yearly"><?php esc_html_e( 'Yearly', 'nestform' ); ?></button>
						<?php if ( $save_badge ) : ?>
							<span class="nestform-upgrade__save"><?php echo esc_html( $save_badge ); ?></span>
						<?php endif; ?>
					</div>
				</header>
				<?php if ( class_exists( 'Nestform_Upgrade' ) && array() !== $plans ) : ?>
					<?php Nestform_Upgrade::render_plan_cards( $plans ); ?>
				<?php endif; ?>

				<div class="nestform-admin__surface thimbleform-pro-compare-card">
					<h3 class="thimbleform-pro-compare-card__title"><?php esc_html_e( 'Where the line falls', 'nestform' ); ?></h3>
					<p class="thimbleform-pro-compare-card__lead"><?php esc_html_e( 'The same jobs, side by side. If the free column already covers what you do, you do not need Pro.', 'nestform' ); ?></p>
					<table class="thimbleform-pro-compare widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'The job', 'nestform' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Thimbleform, free', 'nestform' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What Pro adds', 'nestform' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( self::comparison() as $row ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $row['job'] ); ?></th>
									<td data-label="<?php esc_attr_e( 'Thimbleform, free', 'nestform' ); ?>"><?php echo esc_html( $row['free'] ); ?></td>
									<td data-label="<?php esc_attr_e( 'What Pro adds', 'nestform' ); ?>"><?php echo esc_html( $row['pro'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="nestform-admin__surface thimbleform-pro-faq-card">
					<h3 class="thimbleform-pro-faq-card__title"><?php esc_html_e( 'Before you decide', 'nestform' ); ?></h3>
					<dl class="thimbleform-pro-faq">
						<?php foreach ( self::questions() as $question => $answer ) : ?>
							<dt><?php echo esc_html( $question ); ?></dt>
							<dd><?php echo esc_html( $answer ); ?></dd>
						<?php endforeach; ?>
					</dl>
					<p class="thimbleform-pro-faq-card__more">
						<a href="<?php echo esc_url( self::DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Pro documentation', 'nestform' ); ?></a>
					</p>
				</div>

				<div class="nestform-admin__surface thimbleform-pro-bought-card">
					<h3 class="thimbleform-pro-bought-card__title"><?php esc_html_e( 'Already bought it?', 'nestform' ); ?></h3>
					<p><?php esc_html_e( 'Install Thimbleform Pro under Plugins → Add Plugin → Upload Plugin, activate it, then open Thimbleform → Account (or Thimbleform → License) to activate your Freemius license. Keep Thimbleform (free) active — Pro is an add-on, not a replacement.', 'nestform' ); ?></p>
				</div>

				<p class="nestform-upgrade__trust thimbleform-pro-note">
					<?php esc_html_e( 'Thimbleform Pro is sold via Freemius checkout. Links open in a new tab; the free plugin does not phone home.', 'nestform' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Pro card at the foot of Settings.
	 */
	public static function render_settings_card() {
		if ( ! self::should_promote() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="nestform-admin__surface nestform-settings__pro-card" aria-label="<?php esc_attr_e( 'Thimbleform Pro', 'nestform' ); ?>">
			<p class="nestform-settings__pro-kicker">
				<?php echo nestform_admin_icon_html( 'pro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Thimbleform Pro', 'nestform' ); ?>
			</p>
			<p class="nestform-settings__pro-copy"><?php echo esc_html( self::summary() ); ?></p>
			<a class="nestform-btn nestform-btn--outline nestform-settings__pro-link" href="<?php echo esc_url( self::url() ); ?>">
				<?php esc_html_e( 'See what Pro adds', 'nestform' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Honest Pro upsell (no interactive controls — wp.org guideline 5).
	 *
	 * @param array{title?:string,copy?:string,cta?:string,url?:string,compact?:bool} $args Teaser copy.
	 */
	public static function render_feature_teaser( array $args = array() ) {
		if ( ! self::should_promote() ) {
			return;
		}
		$title = isset( $args['title'] ) ? (string) $args['title'] : __( 'Thimbleform Pro', 'nestform' );
		$copy  = array_key_exists( 'copy', $args ) ? (string) $args['copy'] : self::summary();
		$cta   = isset( $args['cta'] ) ? (string) $args['cta'] : __( 'See Thimbleform Pro', 'nestform' );
		$url   = isset( $args['url'] ) && is_string( $args['url'] ) && '' !== $args['url']
			? (string) $args['url']
			: self::url();
		// Compact banner is default for contextual upsells (Integrations / Fields / Mail).
		$compact = ! array_key_exists( 'compact', $args ) || ! empty( $args['compact'] );
		$class   = $compact ? 'thimbleform-pro-teaser thimbleform-pro-teaser--compact' : 'thimbleform-pro-teaser';
		?>
		<div class="<?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
			<div class="thimbleform-pro-teaser__body">
				<p class="nestform-app__pro-kicker">
					<?php echo nestform_admin_icon_html( 'pro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Thimbleform Pro', 'nestform' ); ?>
				</p>
				<p class="thimbleform-pro-teaser__title"><?php echo esc_html( $title ); ?></p>
				<?php if ( '' !== trim( $copy ) ) : ?>
					<p class="thimbleform-pro-teaser__copy"><?php echo esc_html( $copy ); ?></p>
				<?php endif; ?>
				<?php if ( ! $compact ) : ?>
					<p class="thimbleform-pro-teaser__note"><?php esc_html_e( 'This is a separate add-on. It is not included or locked inside the free plugin.', 'nestform' ); ?></p>
				<?php endif; ?>
			</div>
			<a class="thimbleform-pro-cta thimbleform-pro-teaser__cta" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html( $cta ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * What this site has built that Pro answers.
	 *
	 * @return string long_form|quiz|exports|analytics|''
	 */
	public static function intent_signal() {
		if ( self::export_count() >= self::EXPORTS_BEFORE_NOTICE ) {
			return 'exports';
		}

		$stats = function_exists( 'nestform_admin_stats' ) ? nestform_admin_stats() : array();
		if ( (int) ( $stats['entries'] ?? 0 ) >= self::ENTRIES_BEFORE_INSIGHT ) {
			return 'analytics';
		}

		$form_ids = get_posts(
			array(
				'post_type'              => Nestform_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => self::FORMS_SCANNED_FOR_SIGNAL,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$long_form = false;

		foreach ( $form_ids as $form_id ) {
			if ( ! class_exists( 'Nestform_Form_Config' ) ) {
				break;
			}
			$fields    = Nestform_Form_Config::get_fields( (int) $form_id );
			$questions = 0;
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$type = (string) ( $field['type'] ?? '' );
				if ( Nestform_Form_Config::is_layout_field( $type ) ) {
					continue;
				}
				if ( self::field_suggests_quiz( $field ) ) {
					return 'quiz';
				}
				++$questions;
			}
			if ( $questions >= self::FIELDS_BEFORE_NOTICE ) {
				$long_form = true;
			}
		}

		return $long_form ? 'long_form' : '';
	}

	/**
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private static function field_suggests_quiz( array $field ) {
		$haystack = strtolower(
			implode(
				' ',
				array(
					(string) ( $field['label'] ?? '' ),
					(string) ( $field['name'] ?? '' ),
					(string) ( $field['description'] ?? '' ),
				)
			)
		);
		return (bool) preg_match( '/\b(quiz|survey|score|nps|assessment|poll)\b/u', $haystack );
	}

	/**
	 * @return int
	 */
	private static function export_count() {
		return (int) get_option( self::EXPORT_COUNT_OPTION, 0 );
	}

	/**
	 * Track manual CSV exports for contextual notices.
	 */
	public static function record_export() {
		if ( ! self::should_promote() ) {
			return;
		}
		$count = self::export_count();
		if ( $count >= self::EXPORTS_BEFORE_NOTICE ) {
			return;
		}
		update_option( self::EXPORT_COUNT_OPTION, $count + 1, false );
	}

	/**
	 * @param string $signal Intent key.
	 * @return string
	 */
	private static function notice_message( $signal ) {
		switch ( $signal ) {
			case 'long_form':
				return __( 'One of your forms is getting long. Thimbleform Pro adds multi-step flows and branch rules so visitors only see what applies to them. Everything you have now stays free.', 'nestform' );
			case 'quiz':
				return __( 'Your forms look like quizzes or surveys. Thimbleform Pro adds scoring, result bands, timers, and shareable outcomes. Everything you have now stays free.', 'nestform' );
			case 'exports':
				return __( 'You have exported entries by hand a few times. Thimbleform Pro adds automations and HubSpot sync so answers can flow to your stack without CSV downloads. Everything you have now stays free.', 'nestform' );
			case 'analytics':
				return __( 'You are collecting a steady stream of entries. Thimbleform Pro adds conversion metrics and lead insights on top of your dashboard. Everything you have now stays free.', 'nestform' );
			default:
				return __( 'Need multi-step forms or quizzes? Thimbleform Pro is a separate add-on sold via Freemius. Everything you have now stays free.', 'nestform' );
		}
	}

	/**
	 * @return bool
	 */
	public static function notice_is_due() {
		if ( ! self::should_promote() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( $user_id && get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}

		$on_forms = Nestform_Post_Type::POST_TYPE === ( $screen->post_type ?? '' ) && in_array( $screen->base, array( 'edit', 'post' ), true );
		$on_entries = isset( $_GET['page'] ) && 'nestform-entries' === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $on_forms && ! $on_entries ) {
			return false;
		}

		if ( '' !== self::intent_signal() ) {
			return true;
		}

		$stats = function_exists( 'nestform_admin_stats' ) ? nestform_admin_stats() : array();
		return (int) ( $stats['forms'] ?? 0 ) >= self::FORMS_BEFORE_NOTICE;
	}

	public static function maybe_render_notice() {
		if ( ! self::notice_is_due() ) {
			return;
		}

		$signal  = self::intent_signal();
		$dismiss = wp_nonce_url(
			admin_url( 'admin-post.php?action=nestform_dismiss_pro_notice' ),
			'nestform_dismiss_pro_notice'
		);
		?>
		<div class="notice notice-info thimbleform-pro-notice">
			<p><?php echo esc_html( self::notice_message( $signal ) ); ?></p>
			<p class="thimbleform-pro-notice__actions">
				<a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'See what Pro adds', 'nestform' ); ?></a>
				<a class="thimbleform-pro-notice__dismiss" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Don\'t show this again', 'nestform' ); ?></a>
			</p>
		</div>
		<?php
	}

	public static function dismiss_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'nestform' ) );
		}
		check_admin_referer( 'nestform_dismiss_pro_notice' );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, '1' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE ) );
		exit;
	}

	/**
	 * @param string $text Footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! self::should_promote() || '' === nestform_admin_current_view() ) {
			return $text;
		}
		$view = nestform_admin_current_view();
		if ( in_array( $view, array( 'pro', 'license' ), true ) ) {
			return $text;
		}
		return sprintf(
			'<span class="nestform-footer-note">%s</span>',
			sprintf(
				/* translators: 1: one line naming Pro features. 2: link to Pro page. */
				esc_html__( 'Thimbleform is free and unlimited. Pro is a paid add-on: %1$s %2$s', 'nestform' ),
				esc_html( self::summary() ),
				'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'See what Pro adds', 'nestform' ) . '</a>'
			)
		);
	}

	/**
	 * @param array<string, string> $links Plugin links.
	 * @return array<string, string>
	 */
	public static function plugin_action_links( $links ) {
		$new = array();
		if ( current_user_can( 'edit_posts' ) && class_exists( 'Nestform_Post_Type' ) ) {
			$new['nestform-create'] = '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . Nestform_Post_Type::POST_TYPE ) ) . '">' . esc_html__( 'Create a form', 'nestform' ) . '</a>';
		}
		if ( self::should_promote() ) {
			$new['thimbleform-pro'] = '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Get Pro', 'nestform' ) . '</a>';
		}
		return array_merge( $new, $links );
	}
}
