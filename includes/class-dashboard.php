<?php
/**
 * Forms analytics dashboard.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Dashboard {

	const PAGE_SLUG = 'nestform-dashboard';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 15 );
		add_action( 'admin_menu', array( __CLASS__, 'reorder_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Dashboard', 'nestform' ),
			__( 'Dashboard', 'nestform' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Keep Dashboard as the first Forms submenu item.
	 */
	public static function reorder_menu() {
		global $submenu;
		$parent = 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}
		$dash = null;
		$rest = array();
		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && self::PAGE_SLUG === $item[2] ) {
				$dash = $item;
				continue;
			}
			$rest[] = $item;
		}
		if ( null === $dash ) {
			return;
		}
		$submenu[ $parent ] = array_merge( array( $dash ), $rest );
	}

	/**
	 * @param string $submenu_file Submenu.
	 * @return string
	 */
	public static function submenu_file( $submenu_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'nestform_page_' . self::PAGE_SLUG === $screen->id ) {
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
		if ( self::PAGE_SLUG !== $page ) {
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
		$ver_chart = (string) filemtime( NESTFORM_PATH . 'assets/vendor/chart.umd.min.js' );
		wp_enqueue_script(
			'nestform-chartjs',
			NESTFORM_URL . 'assets/vendor/chart.umd.min.js',
			array(),
			$ver_chart ? $ver_chart : '4.5.1',
			true
		);
		$ver_js = (string) filemtime( nestform_admin_js_path( 'admin-dashboard-chart.js' ) );
		wp_enqueue_script(
			'nestform-dashboard-chart',
			nestform_admin_js_url( 'admin-dashboard-chart.js' ),
			array( 'nestform-chartjs' ),
			$ver_js ? $ver_js : NESTFORM_VERSION,
			true
		);
		wp_localize_script(
			'nestform-dashboard-chart',
			'nestformDashChart',
			array(
				'i18n' => array(
					'submissions' => __( 'Submissions', 'nestform' ),
					'views'       => __( 'Views', 'nestform' ),
					'conversion'  => __( 'Conversion', 'nestform' ),
				),
			)
		);
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

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the dashboard.', 'nestform' ) );
		}

		$range   = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30d'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $range, array( '7d', '30d', '90d', 'all' ), true ) ) {
			$range = '30d';
		}

		$forms    = self::get_forms();
		$form_ids = array();
		foreach ( $forms as $form ) {
			$form_ids[ (int) $form->ID ] = $form;
		}
		if ( $form_id > 0 && ! isset( $form_ids[ $form_id ] ) ) {
			$form_id = 0;
		}

		$bounds       = self::range_bounds( $range );
		$after        = $bounds['after'];
		$before       = $bounds['before'];
		$days         = max( 1, (int) $bounds['days'] );
		$prev_bounds  = self::previous_bounds( $after, $days );
		$chart_after  = ( 'all' === $range )
			? wp_date( 'Y-m-d', strtotime( '-89 days' ) ) . ' 00:00:00'
			: $after;

		$count_args = array(
			'after'        => $after,
			'before'       => $before,
			'exclude_spam' => true,
		);
		$prev_args  = array(
			'after'        => $prev_bounds['after'],
			'before'       => $prev_bounds['before'],
			'exclude_spam' => true,
		);
		if ( $form_id > 0 ) {
			$count_args['form_id'] = $form_id;
			$prev_args['form_id']  = $form_id;
		}

		$total      = Nestform_Submissions::count_entries( $count_args );
		$prev_total = ( 'all' === $range ) ? 0 : Nestform_Submissions::count_entries( $prev_args );
		$daily      = Nestform_Submissions::daily_counts(
			$chart_after,
			$before,
			$form_id,
			array( 'exclude_spam' => true )
		);
		$top        = $form_id > 0
			? array(
				array(
					'form_id' => $form_id,
					'count'   => $total,
				),
			)
			: Nestform_Submissions::top_forms( $after, $before, 5 );

		$status_new  = Nestform_Submissions::count_entries( array_merge( $count_args, array( 'status' => Nestform_Submissions::STATUS_NEW ) ) );
		$status_read = Nestform_Submissions::count_entries( array_merge( $count_args, array( 'status' => Nestform_Submissions::STATUS_READ ) ) );
		$status_spam = Nestform_Submissions::count_entries( array_merge( $count_args, array( 'status' => Nestform_Submissions::STATUS_SPAM ) ) );
		$recent      = Nestform_Submissions::recent_entries(
			6,
			$form_id,
			array(
				'after'  => $after,
				'before' => $before,
			)
		);

		$forms_published = 0;
		$forms_draft     = 0;
		foreach ( $forms as $form ) {
			if ( 'publish' === $form->post_status ) {
				++$forms_published;
			} else {
				++$forms_draft;
			}
		}
		if ( $form_id > 0 && isset( $form_ids[ $form_id ] ) ) {
			$forms_published = ( 'publish' === $form_ids[ $form_id ]->post_status ) ? 1 : 0;
			$forms_draft     = ( 'publish' === $form_ids[ $form_id ]->post_status ) ? 0 : 1;
		}

		$avg_day = round( $total / $days, 1 );
		$delta   = $total - $prev_total;
		$delta_pct = null;
		if ( 'all' !== $range && $prev_total > 0 ) {
			$delta_pct = round( ( $delta / $prev_total ) * 100 );
		}
		// Skip flashy "+100%" when the previous period was empty.

		$peak_day   = '';
		$peak_count = 0;
		foreach ( $daily as $day => $count ) {
			if ( (int) $count > $peak_count ) {
				$peak_count = (int) $count;
				$peak_day   = (string) $day;
			}
		}

		$active_forms = 0;
		$forms_total  = count( $forms );
		if ( $form_id > 0 ) {
			$active_forms = $total > 0 ? 1 : 0;
		} else {
			$top_all = Nestform_Submissions::top_forms( $after, $before, 200 );
			foreach ( $top_all as $row ) {
				$fid = isset( $row['form_id'] ) ? (int) $row['form_id'] : 0;
				if ( $fid > 0 && isset( $form_ids[ $fid ] ) ) {
					++$active_forms;
				}
			}
		}

		$max_top = 0;
		foreach ( $top as $row ) {
			$max_top = max( $max_top, (int) $row['count'] );
		}

		$range_labels = array(
			'7d'  => __( '7 days', 'nestform' ),
			'30d' => __( '30 days', 'nestform' ),
			'90d' => __( '90 days', 'nestform' ),
			'all' => __( 'All time', 'nestform' ),
		);

		$selected_form_title = __( 'All forms', 'nestform' );
		if ( $form_id > 0 && isset( $form_ids[ $form_id ] ) ) {
			$selected_form_title = $form_ids[ $form_id ]->post_title !== ''
				? $form_ids[ $form_id ]->post_title
				: __( '(no title)', 'nestform' );
		}
		?>
		<div class="wrap nestform-dash">
			<div class="nestform-dash__top">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Dashboard', 'nestform' ),
					'description' => sprintf(
						/* translators: 1: form scope, 2: period label */
						__( '%1$s · %2$s', 'nestform' ),
						$selected_form_title,
						$range_labels[ $range ]
					),
				)
			);
			?>
			<header class="nestform-dash__hero">
				<form class="nestform-dash__controls" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" data-nestform-dash-filters>
					<input type="hidden" name="post_type" value="<?php echo esc_attr( Nestform_Post_Type::POST_TYPE ); ?>" />
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
					<input type="hidden" name="range" value="<?php echo esc_attr( $range ); ?>" data-nestform-dash-range />
					<nav class="nestform-dash__ranges" aria-label="<?php esc_attr_e( 'Period', 'nestform' ); ?>">
						<?php foreach ( $range_labels as $key => $label ) : ?>
							<a
								class="nestform-dash__range<?php echo $range === $key ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( self::url( array( 'range' => $key, 'form_id' => $form_id ) ) ); ?>"
							><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
					<label class="nestform-dash__form-pick">
						<span class="screen-reader-text"><?php esc_html_e( 'Form', 'nestform' ); ?></span>
						<select class="nestform-admin__input" name="form_id" onchange="this.form.submit()">
							<option value="0"><?php esc_html_e( 'All forms', 'nestform' ); ?></option>
							<?php foreach ( $forms as $form ) : ?>
								<?php
								$title = $form->post_title !== '' ? $form->post_title : __( '(no title)', 'nestform' );
								?>
								<option value="<?php echo (int) $form->ID; ?>" <?php selected( $form_id, (int) $form->ID ); ?>>
									<?php echo esc_html( $title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</form>
			</header>
			</div>

			<section class="nestform-dash__pulse" aria-label="<?php esc_attr_e( 'Work pulse', 'nestform' ); ?>">
				<div class="nestform-dash__pulse-bar">
					<span class="nestform-dash__pulse-title"><?php esc_html_e( 'Inbox', 'nestform' ); ?></span>
					<?php
					$qa_new_url = admin_url( 'post-new.php?post_type=' . Nestform_Post_Type::POST_TYPE );
					$qa_export  = ( $form_id > 0 && class_exists( 'Nestform_Form_IO' ) )
						? Nestform_Form_IO::export_url( $form_id )
						: admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE );
					$qa_integ   = class_exists( 'Nestform_Integrations' )
						? admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE . '&page=' . Nestform_Integrations::PAGE_SLUG )
						: '';
					?>
					<nav class="nestform-dash__quick nestform-dash__quick--secondary" aria-label="<?php esc_attr_e( 'Quick actions', 'nestform' ); ?>">
						<a class="nestform-dash__quick-item" href="<?php echo esc_url( $qa_new_url ); ?>">
							<?php nestform_admin_icon( 'plus' ); ?>
							<span><?php esc_html_e( 'New form', 'nestform' ); ?></span>
						</a>
						<a class="nestform-dash__quick-item" href="<?php echo esc_url( $qa_export ); ?>">
							<?php nestform_admin_icon( $form_id > 0 ? 'download' : 'forms' ); ?>
							<span><?php echo esc_html( $form_id > 0 ? __( 'Export', 'nestform' ) : __( 'Forms', 'nestform' ) ); ?></span>
						</a>
						<?php if ( $qa_integ !== '' ) : ?>
							<a class="nestform-dash__quick-item" href="<?php echo esc_url( $qa_integ ); ?>">
								<?php nestform_admin_icon( 'integrations' ); ?>
								<span><?php esc_html_e( 'Integrations', 'nestform' ); ?></span>
							</a>
						<?php endif; ?>
					</nav>
				</div>
				<nav class="nestform-dash__pulse-list" aria-label="<?php esc_attr_e( 'Inbox by status', 'nestform' ); ?>">
					<?php
					$pulse_new_url  = $form_id > 0
						? Nestform_Submissions::list_url( $form_id, Nestform_Submissions::STATUS_NEW )
						: Nestform_Submissions::hub_url( array( 'nestform_status' => Nestform_Submissions::STATUS_NEW ) );
					$pulse_read_url = $form_id > 0
						? Nestform_Submissions::list_url( $form_id, Nestform_Submissions::STATUS_READ )
						: Nestform_Submissions::hub_url( array( 'nestform_status' => Nestform_Submissions::STATUS_READ ) );
					$pulse_spam_url = $form_id > 0
						? Nestform_Submissions::list_url( $form_id, Nestform_Submissions::STATUS_SPAM )
						: Nestform_Submissions::hub_url( array( 'nestform_status' => Nestform_Submissions::STATUS_SPAM ) );
					?>
					<a class="nestform-dash__pulse-item nestform-dash__pulse-item--new" href="<?php echo esc_url( $pulse_new_url ); ?>">
						<span class="nestform-dash__pulse-label"><?php esc_html_e( 'New', 'nestform' ); ?></span>
						<span class="nestform-dash__pulse-value"><?php echo esc_html( number_format_i18n( $status_new ) ); ?></span>
					</a>
					<a class="nestform-dash__pulse-item nestform-dash__pulse-item--read" href="<?php echo esc_url( $pulse_read_url ); ?>">
						<span class="nestform-dash__pulse-label"><?php esc_html_e( 'Read', 'nestform' ); ?></span>
						<span class="nestform-dash__pulse-value"><?php echo esc_html( number_format_i18n( $status_read ) ); ?></span>
					</a>
					<a class="nestform-dash__pulse-item nestform-dash__pulse-item--spam" href="<?php echo esc_url( $pulse_spam_url ); ?>">
						<span class="nestform-dash__pulse-label"><?php esc_html_e( 'Spam', 'nestform' ); ?></span>
						<span class="nestform-dash__pulse-value"><?php echo esc_html( number_format_i18n( $status_spam ) ); ?></span>
					</a>
					<?php
					$pulse_ctx = array(
						'form_id'     => $form_id,
						'after'       => $after,
						'before'      => $before,
						'range'       => $range,
						'status_new'  => $status_new,
						'status_read' => $status_read,
						'status_spam' => $status_spam,
					);
					/**
					 * Extra work-pulse chips (e.g. Pro Hot leads).
					 *
					 * @param string $html Empty by default.
					 * @param array  $ctx  Dashboard context.
					 */
					$pulse_extra = (string) apply_filters( 'nestform_dashboard_work_pulse_extra', '', $pulse_ctx );
					echo $pulse_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted Pro markup.
					?>
				</nav>
			</section>

			<section class="nestform-dash__kpis" aria-label="<?php esc_attr_e( 'Key metrics', 'nestform' ); ?>">
				<a class="nestform-dash__kpi nestform-dash__kpi--link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE ) ); ?>">
					<span class="nestform-dash__kpi-label"><?php esc_html_e( 'Forms', 'nestform' ); ?></span>
					<span class="nestform-dash__kpi-value"><?php echo esc_html( number_format_i18n( $forms_published ) ); ?></span>
					<span class="nestform-dash__kpi-meta">
						<?php
						if ( $form_id > 0 ) {
							echo esc_html( $forms_published ? __( 'Published', 'nestform' ) : __( 'Draft', 'nestform' ) );
						} else {
							echo esc_html(
								sprintf(
									/* translators: %d: draft forms count */
									_n( '%d draft', '%d drafts', $forms_draft, 'nestform' ),
									$forms_draft
								)
							);
						}
						?>
					</span>
				</a>
				<article class="nestform-dash__kpi nestform-dash__kpi--primary">
					<span class="nestform-dash__kpi-label"><?php esc_html_e( 'Submissions', 'nestform' ); ?></span>
					<span class="nestform-dash__kpi-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					<?php self::render_delta( $delta, $delta_pct, $range ); ?>
				</article>
				<?php
				$has_conversion = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS );
				if ( $has_conversion ) {
					$conversion_kpi = (string) apply_filters(
						'nestform_dashboard_conversion_kpi',
						'',
						array(
							'form_id'     => $form_id,
							'after'       => $after,
							'before'      => $before,
							'days'        => $days,
							'total'       => $total,
							'prev_total'  => $prev_total,
							'range'       => $range,
							'daily'       => $daily,
							'status_spam' => $status_spam,
						)
					);
					if ( $conversion_kpi !== '' ) {
						echo $conversion_kpi; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by trusted Pro addon.
					}
				} else {
					?>
				<article class="nestform-dash__kpi">
					<span class="nestform-dash__kpi-label"><?php esc_html_e( 'Active forms', 'nestform' ); ?></span>
					<span class="nestform-dash__kpi-value"><?php echo esc_html( number_format_i18n( $active_forms ) ); ?></span>
					<span class="nestform-dash__kpi-meta">
						<?php
						if ( $form_id > 0 ) {
							echo esc_html__( 'this form', 'nestform' );
						} else {
							echo esc_html(
								sprintf(
									/* translators: %d: total forms on the site */
									__( '%d total', 'nestform' ),
									$forms_total
								)
							);
						}
						?>
					</span>
				</article>
					<?php
				}
				?>
			</section>

			<div class="nestform-dash__layout">
				<div class="nestform-dash__main">
				<section class="nestform-dash__panel nestform-dash__panel--chart">
					<div class="nestform-dash__panel-head">
						<h2 class="nestform-dash__panel-title"><?php esc_html_e( 'Activity', 'nestform' ); ?></h2>
						<?php if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS ) ) : ?>
							<?php
							/**
							 * Chart metrics nav HTML (Pro views/conversion tabs).
							 *
							 * @param string $html Empty by default.
							 * @param array  $ctx  Context.
							 */
							$metrics_nav = (string) apply_filters(
								'nestform_dashboard_chart_metrics',
								'',
								array(
									'form_id' => $form_id,
									'after'   => $chart_after,
									'before'  => $before,
									'daily'   => $daily,
									'range'   => $range,
								)
							);
							echo $metrics_nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						<?php elseif ( class_exists( 'Nestform_Features' ) && ! Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS ) ) : ?>
							<nav class="nestform-dash__metrics" aria-label="<?php esc_attr_e( 'Chart metric', 'nestform' ); ?>">
								<span class="nestform-dash__metric is-active"><?php esc_html_e( 'Submissions', 'nestform' ); ?></span>
							</nav>
						<?php elseif ( 'all' === $range ) : ?>
							<span class="nestform-dash__panel-hint"><?php esc_html_e( 'Chart shows last 90 days', 'nestform' ); ?></span>
						<?php endif; ?>
					</div>
					<?php
					$chart_empty = ( 0 === array_sum( $daily ) );
					/**
					 * Whether the activity chart should show the empty state.
					 * Only applied when Advanced Analytics is active.
					 *
					 * @param bool  $empty Empty.
					 * @param array $ctx   Context.
					 */
					if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS ) ) {
						$chart_empty = (bool) apply_filters(
							'nestform_dashboard_chart_empty',
							$chart_empty,
							array(
								'form_id' => $form_id,
								'after'   => $chart_after,
								'before'  => $before,
								'daily'   => $daily,
								'range'   => $range,
							)
						);
					}
					?>
					<?php if ( $chart_empty ) : ?>
						<div class="nestform-dash__empty">
							<strong><?php esc_html_e( 'No activity yet', 'nestform' ); ?></strong>
							<span>
								<?php
								echo esc_html(
									class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS )
										? __( 'When forms start receiving views or entries, the trend will appear here.', 'nestform' )
										: __( 'When forms start receiving entries, the trend will appear here.', 'nestform' )
								);
								?>
							</span>
						</div>
					<?php else : ?>
						<?php
						$free_chart = array(
							'active' => 'submissions',
							'series' => array(
								'submissions' => array(
									'label'    => __( 'Submissions', 'nestform' ),
									'unit'     => 'count',
									'labels'   => array_keys( $daily ),
									'values'   => array_map( 'floatval', array_values( $daily ) ),
									'peak'     => number_format_i18n( $peak_count ),
									'peak_day' => $peak_day,
									'avg'      => number_format_i18n( $avg_day, 1 ),
									'total'    => (int) array_sum( $daily ),
								),
							),
						);
						?>
						<div class="nestform-dash__chart-wrap" data-nestform-chart-wrap>
							<script type="application/json" data-nestform-chart><?php echo wp_json_encode( $free_chart ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
							<?php
							/**
							 * Extra markup / data for Pro chart series switching.
							 * Only applied when Advanced Analytics is active.
							 *
							 * @param string $html Empty.
							 * @param array  $ctx  Context.
							 */
							if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS ) ) {
								$chart_ctx = array(
									'form_id'    => $form_id,
									'after'      => $chart_after,
									'before'     => $before,
									'daily'      => $daily,
									'range'      => $range,
									'peak_count' => $peak_count,
									'avg_day'    => $avg_day,
									'peak_day'   => $peak_day,
								);
								/**
								 * Extra chart datasets when Advanced Analytics is active.
								 *
								 * @param string $html Empty.
								 * @param array  $ctx  Context.
								 */
								$chart_extra = apply_filters( 'nestform_dashboard_chart_data', '', $chart_ctx );
								self::echo_filter_html( $chart_extra );
							}
							?>
							<div class="nestform-dash__legend">
								<span class="nestform-dash__legend-item">
									<span class="nestform-dash__legend-swatch" aria-hidden="true"></span>
									<span data-nestform-chart-legend-label><?php esc_html_e( 'Submissions', 'nestform' ); ?></span>
								</span>
								<span class="nestform-dash__legend-stat" data-nestform-chart-peak>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: peak count, 2: peak date */
											__( 'Peak %1$s · %2$s', 'nestform' ),
											number_format_i18n( $peak_count ),
											$peak_day !== '' ? wp_date( 'j M', strtotime( $peak_day . ' 12:00:00' ) ) : '—'
										)
									);
									?>
								</span>
								<span class="nestform-dash__legend-stat" data-nestform-chart-avg>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: average per day */
											__( 'Avg %s / day', 'nestform' ),
											number_format_i18n( $avg_day, 1 )
										)
									);
									?>
								</span>
							</div>
							<div class="nestform-dash__chart-plot" data-nestform-chart-plot>
								<canvas
									class="nestform-dash__chart-canvas"
									data-nestform-chart-canvas
									role="img"
									aria-label="<?php esc_attr_e( 'Submissions trend', 'nestform' ); ?>"
								></canvas>
							</div>
						</div>
					<?php endif; ?>
				</section>

				<aside class="nestform-dash__aside">
					<section class="nestform-dash__panel nestform-dash__panel--rail">
						<div class="nestform-dash__panel-head">
							<h2 class="nestform-dash__panel-title"><?php esc_html_e( 'Top forms', 'nestform' ); ?></h2>
							<span class="nestform-dash__panel-hint"><?php echo esc_html( $range_labels[ $range ] ); ?></span>
						</div>

						<div class="nestform-dash__rail">
							<div class="nestform-dash__rail-block nestform-dash__rail-block--forms">
								<?php if ( array() === $top || 0 === $total ) : ?>
									<p class="nestform-dash__rail-empty"><?php esc_html_e( 'Forms with the most submissions will show up here.', 'nestform' ); ?></p>
								<?php else : ?>
									<ol class="nestform-dash__rank">
										<?php foreach ( $top as $index => $row ) : ?>
											<?php
											$fid   = (int) $row['form_id'];
											$count = (int) $row['count'];
											$post  = isset( $form_ids[ $fid ] ) ? $form_ids[ $fid ] : get_post( $fid );
											$title = ( $post && $post->post_title !== '' ) ? $post->post_title : sprintf(
												/* translators: %d: form id */
												__( 'Form #%d', 'nestform' ),
												$fid
											);
											$share = $total > 0 ? (int) round( ( $count / $total ) * 100 ) : 0;
											$pct   = $max_top > 0 ? (int) round( ( $count / $max_top ) * 100 ) : 0;
											?>
											<li class="nestform-dash__rank-item<?php echo 0 === $index ? ' nestform-dash__rank-item--lead' : ''; ?>">
												<div class="nestform-dash__rank-row">
													<div class="nestform-dash__rank-name">
														<span class="nestform-dash__rank-index"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
														<a class="nestform-dash__rank-title" href="<?php echo esc_url( Nestform_Submissions::list_url( $fid ) ); ?>">
															<?php echo esc_html( $title ); ?>
														</a>
													</div>
													<span class="nestform-dash__rank-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
												</div>
												<div class="nestform-dash__rank-track" aria-hidden="true">
													<span class="nestform-dash__rank-fill" style="width: <?php echo esc_attr( (string) $pct ); ?>%"></span>
												</div>
												<span class="nestform-dash__rank-share"><?php echo esc_html( $share . '%' ); ?></span>
											</li>
										<?php endforeach; ?>
									</ol>
								<?php endif; ?>
							</div>
						</div>
					</section>
				</aside>
				</div>

				<?php
				$insights_html = '';
				if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::LEAD_INSIGHTS ) ) {
					/**
					 * Lead insights panel HTML (Pro).
					 *
					 * @param string $html Empty by default.
					 * @param array  $ctx  Dashboard context.
					 */
					$insights_html = (string) apply_filters(
						'nestform_dashboard_lead_insights',
						'',
						array(
							'form_id'      => $form_id,
							'after'        => $after,
							'before'       => $before,
							'range'        => $range,
							'total'        => $total,
							'status_new'   => $status_new,
							'status_read'  => $status_read,
							'status_spam'  => $status_spam,
						)
					);
				}

				$responses_ctx = array(
					'form_id' => $form_id,
					'after'   => $after,
					'before'  => $before,
					'range'   => $range,
					'top'     => $top,
				);
				$can_responses = class_exists( 'Nestform_Features' )
					&& ( Nestform_Features::can( Nestform_Features::QUIZ_SURVEY ) || Nestform_Features::can( Nestform_Features::ADVANCED_ANALYTICS ) );
				/**
				 * Response breakdown panel HTML (Pro survey charts).
				 * Only applied when Quiz/Survey or Advanced Analytics is active.
				 *
				 * @param string $html Empty by default.
				 * @param array  $ctx  Context.
				 */
				$responses_html = '';
				if ( $can_responses ) {
					$responses_html = (string) apply_filters( 'nestform_dashboard_responses_panel', '', $responses_ctx );
				}

				if ( '' !== $insights_html || '' !== $responses_html ) {
					echo '<div class="nestform-dash__deep">';
					self::echo_filter_html( $insights_html );
					self::echo_filter_html( $responses_html );
					echo '</div>';
				}
				?>

				<section class="nestform-dash__panel nestform-dash__panel--wide nestform-dash__panel--activity">
					<div class="nestform-dash__panel-head">
						<h2 class="nestform-dash__panel-title"><?php esc_html_e( 'Recent activity', 'nestform' ); ?></h2>
						<span class="nestform-dash__panel-hint"><?php echo esc_html( $range_labels[ $range ] ); ?></span>
						<a class="nestform-btn nestform-btn--ghost" href="<?php echo esc_url( $form_id > 0 ? Nestform_Submissions::list_url( $form_id ) : Nestform_Submissions::hub_url() ); ?>">
							<?php echo esc_html( $form_id > 0 ? __( 'Form inbox', 'nestform' ) : __( 'All entries', 'nestform' ) ); ?>
							<?php echo nestform_admin_icon_html( 'forward' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
						</a>
					</div>
					<?php if ( array() === $recent ) : ?>
						<div class="nestform-dash__empty">
							<strong><?php esc_html_e( 'No activity in this period', 'nestform' ); ?></strong>
							<span><?php esc_html_e( 'Try another range, or wait for new submissions.', 'nestform' ); ?></span>
						</div>
					<?php else : ?>
						<div class="nestform-dash__feed">
							<?php foreach ( $recent as $entry ) : ?>
								<?php
								$eid      = (int) $entry->ID;
								$efid     = (int) get_post_meta( $eid, Nestform_Submissions::META_FORM, true );
								$payload  = get_post_meta( $eid, Nestform_Submissions::META_DATA, true );
								$payload  = is_array( $payload ) ? $payload : array();
								$eform    = isset( $form_ids[ $efid ] ) ? $form_ids[ $efid ] : get_post( $efid );
								$ftitle   = ( $eform && $eform->post_title !== '' ) ? $eform->post_title : ( $efid ? '#' . $efid : '—' );
								$edit_url = get_edit_post_link( $eid, 'raw' );
								$when     = human_time_diff( get_post_time( 'U', true, $entry ), current_time( 'timestamp', true ) );
								$who      = Nestform_Submissions::payload_name( $payload, (string) $entry->post_title, $ftitle, $efid );
								$email    = Nestform_Submissions::payload_email( $payload );
								$estatus  = Nestform_Submissions::get_status( $eid );
								$badge    = Nestform_Submissions::badge_modifier( $estatus );
								$card_tag = $edit_url ? 'a' : 'div';
								$card_href = $edit_url ? ' href="' . esc_url( $edit_url ) . '"' : '';
								?>
								<<?php echo $card_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nestform-dash__card"<?php echo $card_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<div class="nestform-dash__card-top">
										<span class="nestform-badge nestform-badge--<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( strtoupper( $estatus ) ); ?></span>
										<span class="nestform-dash__card-ago">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: relative time */
													__( '%s ago', 'nestform' ),
													$when
												)
											);
											?>
										</span>
									</div>
									<div class="nestform-dash__card-who"><?php echo esc_html( $who ); ?></div>
									<div class="nestform-dash__card-form"><?php echo esc_html( $ftitle ); ?></div>
									<?php if ( $email !== '' ) : ?>
										<div class="nestform-dash__card-email"><?php echo esc_html( $email ); ?></div>
									<?php endif; ?>
								</<?php echo $card_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int         $delta     Absolute delta.
	 * @param int|null    $delta_pct Percent or null.
	 * @param string      $range     Range key.
	 */
	private static function render_delta( $delta, $delta_pct, $range ) {
		if ( 'all' === $range ) {
			echo '<span class="nestform-dash__kpi-meta">' . esc_html__( 'Lifetime across all entries', 'nestform' ) . '</span>';
			return;
		}
		$class = 'nestform-dash__delta';
		$icon  = 'minus';
		if ( $delta > 0 ) {
			$class .= ' is-up';
			$icon   = 'arrow-up-alt';
		} elseif ( $delta < 0 ) {
			$class .= ' is-down';
			$icon   = 'arrow-down-alt';
		} else {
			$class .= ' is-flat';
		}
		$sign = $delta > 0 ? '+' : '';
		$text = $sign . number_format_i18n( $delta );
		if ( null !== $delta_pct ) {
			$text .= ' · ' . ( $delta_pct > 0 ? '+' : '' ) . number_format_i18n( (int) $delta_pct ) . '%';
		}
		echo '<span class="' . esc_attr( $class ) . '">';
		echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo esc_html( $text );
		echo ' <span>' . esc_html__( 'vs previous period', 'nestform' ) . '</span>';
		echo '</span>';
	}

	/**
	 * @param array<string, float|int> $daily Daily map.
	 * @param float|int                $max   Max value.
	 * @return array{line:string,area:string,dots:array<int, array{date:string,value:float,x:float,y:float}>}
	 */
	public static function chart_points( array $daily, $max ) {
		$count = count( $daily );
		if ( $count < 1 ) {
			return array(
				'line' => '',
				'area' => '',
				'dots' => array(),
			);
		}
		$max   = max( 0.0001, (float) $max );
		$i     = 0;
		$pts   = array();
		$dots  = array();
		foreach ( $daily as $day => $value ) {
			$value = (float) $value;
			$x     = $count === 1 ? 50.0 : ( $i / ( $count - 1 ) ) * 100;
			$y     = 36 - ( ( $value / $max ) * 32 );
			$x     = round( $x, 2 );
			$y     = round( $y, 2 );
			$pts[] = $x . ',' . $y;
			$dots[] = array(
				'date'  => (string) $day,
				'value' => $value,
				'x'     => $x,
				'y'     => $y,
			);
			++$i;
		}
		$line  = implode( ' ', $pts );
		$first = explode( ',', $pts[0] );
		$last  = explode( ',', $pts[ count( $pts ) - 1 ] );
		$area  = 'M ' . $first[0] . ',40 L ' . implode( ' L ', $pts ) . ' L ' . $last[0] . ',40 Z';
		return array(
			'line' => $line,
			'area' => $area,
			'dots' => $dots,
		);
	}

	/**
	 * @param string $range Range key.
	 * @return array{after:string,before:string,days:int}
	 */
	private static function range_bounds( $range ) {
		$before = wp_date( 'Y-m-d' ) . ' 23:59:59';
		switch ( $range ) {
			case '7d':
				$days  = 7;
				$after = wp_date( 'Y-m-d', strtotime( '-6 days' ) ) . ' 00:00:00';
				break;
			case '90d':
				$days  = 90;
				$after = wp_date( 'Y-m-d', strtotime( '-89 days' ) ) . ' 00:00:00';
				break;
			case 'all':
				$days  = max( 1, (int) floor( ( time() - strtotime( '2020-01-01' ) ) / DAY_IN_SECONDS ) + 1 );
				$after = '1970-01-01 00:00:00';
				break;
			case '30d':
			default:
				$days  = 30;
				$after = wp_date( 'Y-m-d', strtotime( '-29 days' ) ) . ' 00:00:00';
				break;
		}
		if ( 'all' === $range ) {
			// Prefer real span for avg/day when possible.
			$oldest = self::oldest_entry_date();
			if ( $oldest !== '' ) {
				$span = max( 1, (int) floor( ( strtotime( wp_date( 'Y-m-d' ) ) - strtotime( $oldest ) ) / DAY_IN_SECONDS ) + 1 );
				$days = $span;
			}
		}
		return array(
			'after'  => $after,
			'before' => $before,
			'days'   => $days,
		);
	}

	/**
	 * @param string $after Current period start.
	 * @param int    $days  Length of current period.
	 * @return array{after:string,before:string}
	 */
	private static function previous_bounds( $after, $days ) {
		$start_ts = strtotime( substr( (string) $after, 0, 10 ) . ' 00:00:00' );
		if ( ! $start_ts ) {
			$start_ts = time();
		}
		$prev_end_ts   = strtotime( '-1 day', $start_ts );
		$prev_start_ts = strtotime( '-' . max( 1, (int) $days ) . ' days', $start_ts );
		return array(
			'after'  => wp_date( 'Y-m-d', $prev_start_ts ) . ' 00:00:00',
			'before' => wp_date( 'Y-m-d', $prev_end_ts ) . ' 23:59:59',
		);
	}

	/**
	 * @return string Y-m-d or empty.
	 */
	private static function oldest_entry_date() {
		$posts = get_posts(
			array(
				'post_type'              => Nestform_Submissions::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( empty( $posts[0] ) ) {
			return '';
		}
		return get_the_date( 'Y-m-d', (int) $posts[0] );
	}

	/**
	 * @return array<int, WP_Post>
	 */
	private static function get_forms() {
		$forms = get_posts(
			array(
				'post_type'      => Nestform_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		if ( ! is_array( $forms ) ) {
			return array();
		}
		$allowed = Nestform_Submissions::accessible_form_ids();
		if ( null === $allowed ) {
			return $forms;
		}
		if ( array() === $allowed ) {
			return array();
		}
		$allow = array_fill_keys( $allowed, true );
		return array_values(
			array_filter(
				$forms,
				static function ( $form ) use ( $allow ) {
					return isset( $allow[ (int) $form->ID ] );
				}
			)
		);
	}

	/**
	 * Print filtered dashboard HTML. JSON data islands stay hidden in script tags.
	 *
	 * @param mixed $html Filter output.
	 */
	private static function echo_filter_html( $html ) {
		$html    = (string) $html;
		$islands = array();
		$stripped = preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			static function ( $matches ) use ( &$islands ) {
				$attrs = $matches[1];
				if ( ! preg_match( '/\btype\s*=\s*([\'"])application\/json\1/i', $attrs ) ) {
					return '';
				}
				if ( ! preg_match( '/\b(data-nestform-[a-z0-9-]+)/', $attrs, $name ) ) {
					return '';
				}
				$data = json_decode( $matches[2], true );
				if ( ! is_array( $data ) ) {
					return '';
				}
				$islands[] = '<script type="application/json" ' . $name[1] . '>' . wp_json_encode( $data ) . '</script>';
				return '';
			},
			$html
		);
		if ( ! is_string( $stripped ) ) {
			$stripped = '';
		}
		echo wp_kses_post( $stripped );
		foreach ( $islands as $island ) {
			echo $island; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- re-encoded JSON, attribute name is whitelisted.
		}
	}
}
