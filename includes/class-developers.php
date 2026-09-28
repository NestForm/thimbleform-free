<?php
/**
 * Developer hooks reference (Forms → Developers).
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Developers {

	const PAGE_SLUG = 'thimbleform-developers';

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
				'post_type' => Thimbleform_Post_Type::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE,
			__( 'Developers', 'thimbleform' ),
			__( 'Developers', 'thimbleform' ),
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
			$classes .= ' thimbleform-admin-screen thimbleform-developers-screen';
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
		$ver = (string) filemtime( thimbleform_admin_css_path() );
		wp_enqueue_style(
			'thimbleform-admin',
			thimbleform_admin_css_url(),
			thimbleform_admin_style_deps(),
			$ver ? $ver : THIMBLEFORM_VERSION
		);
	}

	/**
	 * Front-end CustomEvents (dispatched on the form element, bubble).
	 *
	 * @return array<string, string>
	 */
	public static function js_events() {
		return array(
			'thimbleform:ready'               => __( 'Form bootstrapped (selects + multi-step). detail.steps when steps exist.', 'thimbleform' ),
			'thimbleform:before-submit'       => __( 'Submit started — before client validation. Cancelable. Sync custom widgets into inputs here.', 'thimbleform' ),
			'thimbleform:validation-error'    => __( 'Client-side validation failed. detail.errors, detail.message, detail.source.', 'thimbleform' ),
			'thimbleform:submit'              => __( 'After validation + captcha, before fetch. Cancelable. detail.formData is the request body (mutable).', 'thimbleform' ),
			'thimbleform:success'             => __( 'Server accepted the entry. Fired before reset. detail.message, detail.entryId, detail.values.', 'thimbleform' ),
			'thimbleform:error'               => __( 'Server rejected the submit. detail.message, detail.errors.', 'thimbleform' ),
			'thimbleform:network-error'       => __( 'Fetch/network failure. detail.message, detail.error.', 'thimbleform' ),
			'thimbleform:redirect'            => __( 'About to change location after success. Cancelable. detail.url, detail.values.', 'thimbleform' ),
			'thimbleform:before-step-change'  => __( 'Before multi-step index changes. Cancelable — preventDefault() keeps the current step.', 'thimbleform' ),
			'thimbleform:step-change'         => __( 'Multi-step index changed. detail.index, detail.previousIndex, detail.reason.', 'thimbleform' ),
			'thimbleform:select-change'       => __( 'Custom select value changed. detail.name, detail.value, detail.previous.', 'thimbleform' ),
			'thimbleform:reset'               => __( 'Form reset after success (or native reset).', 'thimbleform' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function actions() {
		return array(
			'thimbleform_before_validate' => __( 'Before field validation (after captcha).', 'thimbleform' ),
			'thimbleform_submitted'       => __( 'After entry is stored. Args: form_id, data, entry_id.', 'thimbleform' ),
			'thimbleform_mail_sent'       => __( 'After admin notification mail.', 'thimbleform' ),
			'thimbleform_extra_mail_sent' => __( 'After conditional extra mail (only if sent).', 'thimbleform' ),
			'thimbleform_user_mail_sent'  => __( 'After visitor autoreply (only if sent).', 'thimbleform' ),
			'thimbleform_webhook_sent'    => __( 'After each outbound webhook HTTP request.', 'thimbleform' ),
			'thimbleform_entry_meta_after_triage' => __( 'After entry status triage (addons: recruiting stage).', 'thimbleform' ),
			'thimbleform_entry_meta_after'=> __( 'After entry meta box rows.', 'thimbleform' ),
			'thimbleform_template_applied'=> __( 'After a starter template is applied. Args: form_id, key.', 'thimbleform' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function filters() {
		return array(
			'thimbleform_verify_captcha'      => __( 'Captcha pass/fail (custom captcha integrations).', 'thimbleform' ),
			'thimbleform_entry_data'          => __( 'Sanitized entry payload before save.', 'thimbleform' ),
			'thimbleform_validate_field'      => __( 'Per-field value or WP_Error.', 'thimbleform' ),
			'thimbleform_captcha_html'        => __( 'Captcha markup output.', 'thimbleform' ),
			'thimbleform_render_html'         => __( 'Full form HTML.', 'thimbleform' ),
			'thimbleform_field_html'          => __( 'Single field HTML.', 'thimbleform' ),
			'thimbleform_field_classes'       => __( 'Field wrapper classes.', 'thimbleform' ),
			'thimbleform_success_message'     => __( 'Thank-you message text before JSON response.', 'thimbleform' ),
			'thimbleform_submit_success_data' => __( 'AJAX success payload (message, redirect, entry_id).', 'thimbleform' ),
			'thimbleform_redirect_url'        => __( 'Redirect URL after merge tags (before final sanitize).', 'thimbleform' ),
			'thimbleform_sanitize_redirect_url' => __( 'Sanitized redirect URL (empty = blocked).', 'thimbleform' ),
			'thimbleform_mail_args'           => __( 'Admin wp_mail() args.', 'thimbleform' ),
			'thimbleform_extra_mail_args'     => __( 'Extra notification wp_mail() args.', 'thimbleform' ),
			'thimbleform_user_mail_args'      => __( 'Visitor autoreply wp_mail() args.', 'thimbleform' ),
			'thimbleform_accessible_form_ids' => __( 'Form IDs the user may inspect entries for (null = all).', 'thimbleform' ),
			'thimbleform_user_can_manage_form_entries' => __( 'Whether a user may manage entries for a form.', 'thimbleform' ),
			'thimbleform_entries_kind_filters' => __( 'Entries hub kind tabs (e.g. All / Forms / Jobs).', 'thimbleform' ),
			'thimbleform_entries_hub_query_args' => __( 'Entries hub query args (form_ids / exclude_form_ids by kind).', 'thimbleform' ),
			'thimbleform_templates'           => __( 'Starter form templates registry.', 'thimbleform' ),
			'thimbleform_settings'            => __( 'Plugin-wide settings array.', 'thimbleform' ),
			'thimbleform_webhook_payload'     => __( 'Webhook JSON body (per endpoint URL).', 'thimbleform' ),
			'thimbleform_webhook_request_args'=> __( 'Webhook wp_remote_post() args (per endpoint URL).', 'thimbleform' ),
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
			'thimbleform_pre_validate_field'        => __( 'Early per-field validation for advanced field types.', 'thimbleform' ),
			'thimbleform_render_field'              => __( 'Short-circuit single field HTML for advanced widgets.', 'thimbleform' ),
			'thimbleform_mail_attachments'          => __( 'Admin mail attachment paths (e.g. PDF export).', 'thimbleform' ),
			'thimbleform_dashboard_conversion_kpi'  => __( 'Dashboard conversion KPI card HTML.', 'thimbleform' ),
			'thimbleform_dashboard_chart_metrics'   => __( 'Dashboard chart metrics nav HTML.', 'thimbleform' ),
			'thimbleform_dashboard_chart_empty'     => __( 'Whether the activity chart shows empty state.', 'thimbleform' ),
			'thimbleform_dashboard_chart_data'      => __( 'Extra chart series markup/data.', 'thimbleform' ),
			'thimbleform_dashboard_work_pulse_extra' => __( 'Extra Work pulse chips (e.g. Hot leads).', 'thimbleform' ),
			'thimbleform_dashboard_lead_insights'   => __( 'Lead insights panel HTML.', 'thimbleform' ),
			'thimbleform_dashboard_responses_panel' => __( 'Response breakdown panel HTML (survey/quiz).', 'thimbleform' ),
		);
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'thimbleform' ) );
		}
		?>
		<div class="wrap thimbleform-admin thimbleform-developers">
			<?php
			thimbleform_render_page_head(
				array(
					'title'       => __( 'Developers', 'thimbleform' ),
					'description' => __( 'Public PHP actions/filters and front-end CustomEvents for themes and small plugins.', 'thimbleform' ),
					'icon'        => 'developers',
				)
			);
			?>

			<section class="thimbleform-admin__surface thimbleform-developers__card thimbleform-developers__card--js">
				<div class="thimbleform-admin__panel-head">
					<div>
						<h2 class="thimbleform-admin__panel-title"><?php esc_html_e( 'JavaScript events', 'thimbleform' ); ?></h2>
						<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'CustomEvents bubble from the <form data-nest-form> element. detail always includes form and formId. List also exposed as window.thimbleformEvents.', 'thimbleform' ); ?></p>
					</div>
				</div>
				<ul class="thimbleform-developers__list thimbleform-developers__list--js">
					<?php
					$cancelable = array( 'thimbleform:before-submit', 'thimbleform:submit', 'thimbleform:redirect', 'thimbleform:before-step-change' );
					foreach ( self::js_events() as $hook => $desc ) :
						?>
						<li class="thimbleform-developers__item">
							<div class="thimbleform-developers__hook-row">
								<code class="thimbleform-developers__hook thimbleform-developers__hook--js"><?php echo esc_html( $hook ); ?></code>
								<?php if ( in_array( $hook, $cancelable, true ) ) : ?>
									<span class="thimbleform-developers__badge"><?php esc_html_e( 'cancelable', 'thimbleform' ); ?></span>
								<?php endif; ?>
							</div>
							<span class="thimbleform-developers__desc"><?php echo esc_html( $desc ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<div class="thimbleform-developers__grid">
				<section class="thimbleform-admin__surface thimbleform-developers__card">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h2 class="thimbleform-admin__panel-title"><?php esc_html_e( 'PHP actions', 'thimbleform' ); ?></h2>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Hook with add_action(). Fired during submit and mail.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<ul class="thimbleform-developers__list">
						<?php foreach ( self::actions() as $hook => $desc ) : ?>
							<li class="thimbleform-developers__item">
								<code class="thimbleform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="thimbleform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="thimbleform-admin__surface thimbleform-developers__card">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h2 class="thimbleform-admin__panel-title"><?php esc_html_e( 'PHP filters', 'thimbleform' ); ?></h2>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Hook with add_filter(). Public extension points only — change data, markup, or mail.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<ul class="thimbleform-developers__list">
						<?php foreach ( self::filters() as $hook => $desc ) : ?>
							<li class="thimbleform-developers__item">
								<code class="thimbleform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="thimbleform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>

			<?php if ( class_exists( 'Thimbleform_Upgrade' ) && Thimbleform_Upgrade::is_pro() && array() !== self::pro_actions() ) : ?>
			<div class="thimbleform-developers__grid">
				<section class="thimbleform-admin__surface thimbleform-developers__card thimbleform-developers__card--pro">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h2 class="thimbleform-admin__panel-title">
								<?php esc_html_e( 'Pro actions', 'thimbleform' ); ?>
								<span class="thimbleform-developers__badge thimbleform-developers__badge--pro"><?php esc_html_e( 'Pro', 'thimbleform' ); ?></span>
							</h2>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Available with licensed Thimbleform Pro. Without Pro these actions do not fire.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<ul class="thimbleform-developers__list">
						<?php foreach ( self::pro_actions() as $hook => $desc ) : ?>
							<li class="thimbleform-developers__item">
								<code class="thimbleform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="thimbleform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="thimbleform-admin__surface thimbleform-developers__card thimbleform-developers__card--pro">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h2 class="thimbleform-admin__panel-title">
								<?php esc_html_e( 'Pro filters', 'thimbleform' ); ?>
								<span class="thimbleform-developers__badge thimbleform-developers__badge--pro"><?php esc_html_e( 'Pro', 'thimbleform' ); ?></span>
							</h2>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Available with licensed Thimbleform Pro. Without Pro these filters are not applied — callbacks never run.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<ul class="thimbleform-developers__list">
						<?php foreach ( self::pro_filters() as $hook => $desc ) : ?>
							<li class="thimbleform-developers__item">
								<code class="thimbleform-developers__hook"><?php echo esc_html( $hook ); ?></code>
								<span class="thimbleform-developers__desc"><?php echo esc_html( $desc ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>
			<?php endif; ?>

			<div class="thimbleform-developers__grid">
				<section class="thimbleform-admin__surface thimbleform-developers__card thimbleform-developers__card--example">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h2 class="thimbleform-admin__panel-title"><?php esc_html_e( 'PHP example', 'thimbleform' ); ?></h2>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Log every successful submission from functions.php or a small mu-plugin.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<pre class="thimbleform-developers__code" tabindex="0"><code>add_action( 'thimbleform_submitted', function( $form_id, $data, $entry_id ) {
	error_log( sprintf( 'Thimbleform #%d entry #%d', $form_id, $entry_id ) );
}, 10, 3 );</code></pre>
				</section>

				<section class="thimbleform-admin__surface thimbleform-developers__card thimbleform-developers__card--example">
					<div class="thimbleform-admin__panel-head">
						<div>
							<h2 class="thimbleform-admin__panel-title"><?php esc_html_e( 'JS example', 'thimbleform' ); ?></h2>
							<p class="thimbleform-admin__panel-desc"><?php esc_html_e( 'Listen on document — events bubble from the form.', 'thimbleform' ); ?></p>
						</div>
					</div>
					<pre class="thimbleform-developers__code" tabindex="0"><code>document.addEventListener( 'thimbleform:success', function( event ) {
	console.log( 'Entry', event.detail.entryId, event.detail.values );
} );

document.addEventListener( 'thimbleform:before-submit', function( event ) {
	// Sync custom widgets into inputs, or event.preventDefault() to abort.
} );

document.addEventListener( 'thimbleform:submit', function( event ) {
	// event.detail.formData.append( 'utm', '…' );
	// event.preventDefault(); // cancel AJAX
} );</code></pre>
				</section>
			</div>
		</div>
		<?php
	}
}
