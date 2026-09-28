<?php
/**
 * Developer hooks reference (Forms → Developers).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Developers {

	const PAGE_SLUG = 'nestform-developers';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_page' ) );
	}

	/**
	 * Old in-admin reference now lives on the public docs site.
	 */
	public static function redirect_legacy_page() {
		if ( ! is_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		wp_safe_redirect( 'https://thimbleform.app/docs' );
		exit;
	}

	/**
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				'post_type' => Nestform_Post_Type::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Developers', 'nestform' ),
			__( 'Developers', 'nestform' ),
			'edit_posts',
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
			$classes .= ' nestform-admin-screen nestform-developers-screen';
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
		$ver = (string) filemtime( nestform_admin_css_path() );
		wp_enqueue_style(
			'nestform-admin',
			nestform_admin_css_url(),
			nestform_admin_style_deps(),
			$ver ? $ver : NESTFORM_VERSION
		);
	}

	/**
	 * Front-end CustomEvents (dispatched on the form element, bubble).
	 *
	 * @return array<string, string>
	 */
	public static function js_events() {
		return array(
			'nestform:ready'               => __( 'Form bootstrapped (selects + multi-step). detail.steps when steps exist.', 'nestform' ),
			'nestform:before-submit'       => __( 'Submit started — before client validation. Cancelable. Sync custom widgets into inputs here.', 'nestform' ),
			'nestform:validation-error'    => __( 'Client-side validation failed. detail.errors, detail.message, detail.source.', 'nestform' ),
			'nestform:submit'              => __( 'After validation + captcha, before fetch. Cancelable. detail.formData is the request body (mutable).', 'nestform' ),
			'nestform:success'             => __( 'Server accepted the entry. Fired before reset. detail.message, detail.entryId, detail.values.', 'nestform' ),
			'nestform:error'               => __( 'Server rejected the submit. detail.message, detail.errors.', 'nestform' ),
			'nestform:network-error'       => __( 'Fetch/network failure. detail.message, detail.error.', 'nestform' ),
			'nestform:redirect'            => __( 'About to change location after success. Cancelable. detail.url, detail.values.', 'nestform' ),
			'nestform:before-step-change'  => __( 'Before multi-step index changes. Cancelable — preventDefault() keeps the current step.', 'nestform' ),
			'nestform:step-change'         => __( 'Multi-step index changed. detail.index, detail.previousIndex, detail.reason.', 'nestform' ),
			'nestform:select-change'       => __( 'Custom select value changed. detail.name, detail.value, detail.previous.', 'nestform' ),
			'nestform:reset'               => __( 'Form reset after success (or native reset).', 'nestform' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function actions() {
		return array(
			'nestform_before_validate' => __( 'Before field validation (after captcha).', 'nestform' ),
			'nestform_submitted'       => __( 'After entry is stored. Args: form_id, data, entry_id.', 'nestform' ),
			'nestform_mail_sent'       => __( 'After admin notification mail.', 'nestform' ),
			'nestform_extra_mail_sent' => __( 'After conditional extra mail (only if sent).', 'nestform' ),
			'nestform_user_mail_sent'  => __( 'After visitor autoreply (only if sent).', 'nestform' ),
			'nestform_webhook_sent'    => __( 'After each outbound webhook HTTP request.', 'nestform' ),
			'nestform_entry_meta_after_triage' => __( 'After entry status triage (addons: recruiting stage).', 'nestform' ),
			'nestform_entry_meta_after'=> __( 'After entry meta box rows.', 'nestform' ),
			'nestform_template_applied'=> __( 'After a starter template is applied. Args: form_id, key.', 'nestform' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function filters() {
		return array(
			'nestform_verify_captcha'      => __( 'Captcha pass/fail (custom captcha integrations).', 'nestform' ),
			'nestform_entry_data'          => __( 'Sanitized entry payload before save.', 'nestform' ),
			'nestform_validate_field'      => __( 'Per-field value or WP_Error.', 'nestform' ),
			'nestform_captcha_html'        => __( 'Captcha markup output.', 'nestform' ),
			'nestform_render_html'         => __( 'Full form HTML.', 'nestform' ),
			'nestform_field_html'          => __( 'Single field HTML.', 'nestform' ),
			'nestform_field_classes'       => __( 'Field wrapper classes.', 'nestform' ),
			'nestform_success_message'     => __( 'Thank-you message text before JSON response.', 'nestform' ),
			'nestform_submit_success_data' => __( 'AJAX success payload (message, redirect, entry_id).', 'nestform' ),
			'nestform_redirect_url'        => __( 'Redirect URL after merge tags (before final sanitize).', 'nestform' ),
			'nestform_sanitize_redirect_url' => __( 'Sanitized redirect URL (empty = blocked).', 'nestform' ),
			'nestform_mail_args'           => __( 'Admin wp_mail() args.', 'nestform' ),
			'nestform_extra_mail_args'     => __( 'Extra notification wp_mail() args.', 'nestform' ),
			'nestform_user_mail_args'      => __( 'Visitor autoreply wp_mail() args.', 'nestform' ),
			'nestform_accessible_form_ids' => __( 'Form IDs the user may inspect entries for (null = all).', 'nestform' ),
			'nestform_user_can_manage_form_entries' => __( 'Whether a user may manage entries for a form.', 'nestform' ),
			'nestform_entries_kind_filters' => __( 'Entries hub kind tabs (e.g. All / Forms / Jobs).', 'nestform' ),
			'nestform_entries_hub_query_args' => __( 'Entries hub query args (form_ids / exclude_form_ids by kind).', 'nestform' ),
			'nestform_templates'           => __( 'Starter form templates registry.', 'nestform' ),
			'nestform_settings'            => __( 'Plugin-wide settings array.', 'nestform' ),
			'nestform_webhook_payload'     => __( 'Webhook JSON body (per endpoint URL).', 'nestform' ),
			'nestform_webhook_request_args'=> __( 'Webhook wp_remote_post() args (per endpoint URL).', 'nestform' ),
		);
	}

	/**
	 * Pro-only actions (fire only when the related Pro capability is active).
	 *
	 * @return array<string, string>
	 */
	public static function pro_actions() {
		return array();
	}

	/**
	 * Pro-only filters (apply_filters runs only with licensed Thimbleform Pro).
	 *
	 * @return array<string, string>
	 */
	public static function pro_filters() {
		return array(
			'nestform_pre_validate_field'        => __( 'Early per-field validation for advanced field types.', 'nestform' ),
			'nestform_render_field'              => __( 'Short-circuit single field HTML for advanced widgets.', 'nestform' ),
			'nestform_mail_attachments'          => __( 'Admin mail attachment paths (e.g. PDF export).', 'nestform' ),
			'nestform_dashboard_conversion_kpi'  => __( 'Dashboard conversion KPI card HTML.', 'nestform' ),
			'nestform_dashboard_chart_metrics'   => __( 'Dashboard chart metrics nav HTML.', 'nestform' ),
			'nestform_dashboard_chart_empty'     => __( 'Whether the activity chart shows empty state.', 'nestform' ),
			'nestform_dashboard_chart_data'      => __( 'Extra chart series markup/data.', 'nestform' ),
			'nestform_dashboard_work_pulse_extra' => __( 'Extra Work pulse chips (e.g. Hot leads).', 'nestform' ),
			'nestform_dashboard_lead_insights'   => __( 'Lead insights panel HTML.', 'nestform' ),
			'nestform_dashboard_responses_panel' => __( 'Response breakdown panel HTML (survey/quiz).', 'nestform' ),
		);
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'nestform' ) );
		}
		?>
		<div class="wrap nestform-admin nestform-developers">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Developers', 'nestform' ),
					'description' => __( 'Public PHP actions/filters and front-end CustomEvents for themes and small plugins.', 'nestform' ),
					'icon'        => 'developers',
				)
			);
			?>

			<section class="nestform-admin__surface nestform-developers__card nestform-developers__card--js">
				<div class="nestform-admin__panel-head">
					<div>
						<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'JavaScript events', 'nestform' ); ?></h2>
						<p class="nestform-admin__panel-desc"><?php esc_html_e( 'CustomEvents bubble from the <form data-nest-form> element. detail always includes form and formId. List also exposed as window.nestformEvents.', 'nestform' ); ?></p>
					</div>
				</div>
				<ul class="nestform-developers__list nestform-developers__list--js">
					<?php
					$cancelable = array( 'nestform:before-submit', 'nestform:submit', 'nestform:redirect', 'nestform:before-step-change' );
					foreach ( self::js_events() as $hook => $desc ) :
						?>
						<li class="nestform-developers__item">
							<div class="nestform-developers__hook-row">
								<code class="nestform-developers__hook nestform-developers__hook--js"><?php echo esc_html( $hook ); ?></code>
								<?php if ( in_array( $hook, $cancelable, true ) ) : ?>
									<span class="nestform-developers__badge"><?php esc_html_e( 'cancelable', 'nestform' ); ?></span>
								<?php endif; ?>
							</div>
							<span class="nestform-developers__desc"><?php echo esc_html( $desc ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<div class="nestform-developers__grid">
				<section class="nestform-admin__surface nestform-developers__card">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'PHP actions', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Hook with add_action(). Fired during submit and mail.', 'nestform' ); ?></p>
						</div>
					</div>
					<ul class="nestform-developers__list">
						<?php foreach ( self::actions() as $hook => $desc ) : ?>
							<li class="nestform-developers__item">
								<code class="nestform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="nestform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="nestform-admin__surface nestform-developers__card">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'PHP filters', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Hook with add_filter(). Public extension points only — change data, markup, or mail.', 'nestform' ); ?></p>
						</div>
					</div>
					<ul class="nestform-developers__list">
						<?php foreach ( self::filters() as $hook => $desc ) : ?>
							<li class="nestform-developers__item">
								<code class="nestform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="nestform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>

			<?php if ( class_exists( 'Nestform_Upgrade' ) && Nestform_Upgrade::is_pro() && array() !== self::pro_actions() ) : ?>
			<div class="nestform-developers__grid">
				<section class="nestform-admin__surface nestform-developers__card nestform-developers__card--pro">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title">
								<?php esc_html_e( 'Pro actions', 'nestform' ); ?>
								<span class="nestform-developers__badge nestform-developers__badge--pro"><?php esc_html_e( 'Pro', 'nestform' ); ?></span>
							</h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Available with licensed Thimbleform Pro. Without Pro these actions do not fire.', 'nestform' ); ?></p>
						</div>
					</div>
					<ul class="nestform-developers__list">
						<?php foreach ( self::pro_actions() as $hook => $desc ) : ?>
							<li class="nestform-developers__item">
								<code class="nestform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="nestform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="nestform-admin__surface nestform-developers__card nestform-developers__card--pro">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title">
								<?php esc_html_e( 'Pro filters', 'nestform' ); ?>
								<span class="nestform-developers__badge nestform-developers__badge--pro"><?php esc_html_e( 'Pro', 'nestform' ); ?></span>
							</h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Available with licensed Thimbleform Pro. Without Pro these filters are not applied — callbacks never run.', 'nestform' ); ?></p>
						</div>
					</div>
					<ul class="nestform-developers__list">
						<?php foreach ( self::pro_filters() as $hook => $desc ) : ?>
							<li class="nestform-developers__item">
								<code class="nestform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="nestform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>
			<?php endif; ?>

			<div class="nestform-developers__grid">
				<section class="nestform-admin__surface nestform-developers__card nestform-developers__card--example">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'PHP example', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Log every successful submission from functions.php or a small mu-plugin.', 'nestform' ); ?></p>
						</div>
					</div>
					<pre class="nestform-developers__code" tabindex="0"><code>add_action( 'nestform_submitted', function( $form_id, $data, $entry_id ) {
	error_log( sprintf( 'Thimbleform #%d entry #%d', $form_id, $entry_id ) );
}, 10, 3 );</code></pre>
				</section>

				<section class="nestform-admin__surface nestform-developers__card nestform-developers__card--example">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'JS example', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Listen on document — events bubble from the form.', 'nestform' ); ?></p>
						</div>
					</div>
					<pre class="nestform-developers__code" tabindex="0"><code>document.addEventListener( 'nestform:success', function( event ) {
	console.log( 'Entry', event.detail.entryId, event.detail.values );
} );

document.addEventListener( 'nestform:before-submit', function( event ) {
	// Sync custom widgets into inputs, or event.preventDefault() to abort.
} );

document.addEventListener( 'nestform:submit', function( event ) {
	// event.detail.formData.append( 'utm', '…' );
	// event.preventDefault(); // cancel AJAX
} );</code></pre>
				</section>
			</div>
		</div>
		<?php
	}
}
