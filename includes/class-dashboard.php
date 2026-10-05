<?php
/**
 * Forms analytics dashboard.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Dashboard {

	const PAGE_SLUG = 'thimbleform-dashboard';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 15 );
		add_action( 'admin_menu', array( __CLASS__, 'reorder_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE,
			__( 'Dashboard', 'thimbleform' ),
			__( 'Dashboard', 'thimbleform' ),
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
		$parent = 'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE;
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
		if ( $screen && 'thimbleform_page_' . self::PAGE_SLUG === $screen->id ) {
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
		$classes .= ' thimbleform-admin-screen';
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
		$ver_chart = (string) filemtime( THIMBLEFORM_PATH . 'assets/vendor/chart.umd.min.js' );
		wp_enqueue_script(
			'thimbleform-chartjs',
			THIMBLEFORM_URL . 'assets/vendor/chart.umd.min.js',
			array(),
			$ver_chart ? $ver_chart : '4.5.1',
			true
		);
		$ver_js = (string) filemtime( thimbleform_admin_js_path( 'admin-dashboard-chart.js' ) );
		wp_enqueue_script(
			'thimbleform-dashboard-chart',
			thimbleform_admin_js_url( 'admin-dashboard-chart.js' ),
			array( 'thimbleform-chartjs' ),
			$ver_js ? $ver_js : THIMBLEFORM_VERSION,
			true
		);
		wp_localize_script(
			'thimbleform-dashboard-chart',
			'thimbleformDashChart',
			array(
				'i18n' => array(
					'submissions' => __( 'Submissions', 'thimbleform' ),
					'views'       => __( 'Views', 'thimbleform' ),
					'conversion'  => __( 'Conversion', 'thimbleform' ),
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
					'post_type' => Thimbleform_Post_Type::POST_TYPE,
					'page'      => self::PAGE_SLUG,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the dashboard.', 'thimbleform' ) );
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

		$total      = Thimbleform_Submissions::count_entries( $count_args );
		$prev_total = ( 'all' === $range ) ? 0 : Thimbleform_Submissions::count_entries( $prev_args );
		$daily      = Thimbleform_Submissions::daily_counts(
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
			: Thimbleform_Submissions::top_forms( $after, $before, 5 );

		$status_new  = Thimbleform_Submissions::count_entries( array_merge( $count_args, array( 'status' => Thimbleform_Submissions::STATUS_NEW ) ) );
		$status_read = Thimbleform_Submissions::count_entries( array_merge( $count_args, array( 'status' => Thimbleform_Submissions::STATUS_READ ) ) );
		$status_spam = Thimbleform_Submissions::count_entries( array_merge( $count_args, array( 'status' => Thimbleform_Submissions::STATUS_SPAM ) ) );
		$recent      = Thimbleform_Submissions::recent_entries(
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
			$top_all = Thimbleform_Submissions::top_forms( $after, $before, 200 );
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
			'7d'  => __( '7 days', 'thimbleform' ),
			'30d' => __( '30 days', 'thimbleform' ),
			'90d' => __( '90 days', 'thimbleform' ),
			'all' => __( 'All time', 'thimbleform' ),
		);

		$selected_form_title = __( 'All forms', 'thimbleform' );
		if ( $form_id > 0 && isset( $form_ids[ $form_id ] ) ) {
			$selected_form_title = $form_ids[ $form_id ]->post_title !== ''
				? $form_ids[ $form_id ]->post_title
				: __( '(no title)', 'thimbleform' );
		}
		?>
		<div class="wrap thimbleform-dash">
			<div class="thimbleform-dash__top">
			<?php
			thimbleform_render_page_head(
				array(
					'title'       => __( 'Dashboard', 'thimbleform' ),
					'description' => sprintf(
						/* translators: 1: form scope, 2: period label */
						__( '%1$s · %2$s', 'thimbleform' ),
						$selected_form_title,
						$range_labels[ $range ]
					),
				)
			);
			?>
			<header class="thimbleform-dash__hero">
				<form class="thimbleform-dash__controls" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" data-thimbleform-dash-filters>
					<input type="hidden" name="post_type" value="<?php echo esc_attr( Thimbleform_Post_Type::POST_TYPE ); ?>" />
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
					<input type="hidden" name="range" value="<?php echo esc_attr( $range ); ?>" data-thimbleform-dash-range />
					<nav class="thimbleform-dash__ranges" aria-label="<?php esc_attr_e( 'Period', 'thimbleform' ); ?>">
						<?php foreach ( $range_labels as $key => $label ) : ?>
							<a
								class="thimbleform-dash__range<?php echo $range === $key ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( self::url( array( 'range' => $key, 'form_id' => $form_id ) ) ); ?>"
							><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
					<label class="thimbleform-dash__form-pick">
						<span class="screen-reader-text"><?php esc_html_e( 'Form', 'thimbleform' ); ?></span>
						<select class="thimbleform-admin__input" name="form_id" onchange="this.form.submit()">
							<option value="0"><?php esc_html_e( 'All forms', 'thimbleform' ); ?></option>
							<?php foreach ( $forms as $form ) : ?>
								<?php
								$title = $form->post_title !== '' ? $form->post_title : __( '(no title)', 'thimbleform' );
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

			<section class="thimbleform-dash__pulse" aria-label="<?php esc_attr_e( 'Work pulse', 'thimbleform' ); ?>">
				<div class="thimbleform-dash__pulse-bar">
					<span class="thimbleform-dash__pulse-title"><?php esc_html_e( 'Inbox', 'thimbleform' ); ?></span>
					<?php
					$qa_new_url = admin_url( 'post-new.php?post_type=' . Thimbleform_Post_Type::POST_TYPE );
					$qa_export  = ( $form_id > 0 && class_exists( 'Thimbleform_Form_IO' ) )
						? Thimbleform_Form_IO::export_url( $form_id )
						: admin_url( 'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE );
					$qa_integ   = class_exists( 'Thimbleform_Integrations' )
						? admin_url( 'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE . '&page=' . Thimbleform_Integrations::PAGE_SLUG )
						: '';
					?>
					<nav class="thimbleform-dash__quick thimbleform-dash__quick--secondary" aria-label="<?php esc_attr_e( 'Quick actions', 'thimbleform' ); ?>">
						<a class="thimbleform-dash__quick-item" href="<?php echo esc_url( $qa_new_url ); ?>">
							<?php thimbleform_admin_icon( 'plus' ); ?>
							<span><?php esc_html_e( 'New form', 'thimbleform' ); ?></span>
						</a>
						<a class="thimbleform-dash__quick-item" href="<?php echo esc_url( $qa_export ); ?>">
							<?php thimbleform_admin_icon( $form_id > 0 ? 'download' : 'forms' ); ?>
							<span><?php echo esc_html( $form_id > 0 ? __( 'Export', 'thimbleform' ) : __( 'Forms', 'thimbleform' ) ); ?></span>
						</a>
						<?php if ( $qa_integ !== '' ) : ?>
							<a class="thimbleform-dash__quick-item" href="<?php echo esc_url( $qa_integ ); ?>">
								<?php thimbleform_admin_icon( 'integrations' ); ?>
								<span><?php esc_html_e( 'Integrations', 'thimbleform' ); ?></span>
							</a>
						<?php endif; ?>
					</nav>
				</div>
				<nav class="thimbleform-dash__pulse-list" aria-label="<?php esc_attr_e( 'Inbox by status', 'thimbleform' ); ?>">
					<?php
					$pulse_new_url  = $form_id > 0
						? Thimbleform_Submissions::list_url( $form_id, Thimbleform_Submissions::STATUS_NEW )
						: Thimbleform_Submissions::hub_url( array( 'thimbleform_status' => Thimbleform_Submissions::STATUS_NEW ) );
					$pulse_read_url = $form_id > 0
						? Thimbleform_Submissions::list_url( $form_id, Thimbleform_Submissions::STATUS_READ )
						: Thimbleform_Submissions::hub_url( array( 'thimbleform_status' => Thimbleform_Submissions::STATUS_READ ) );
					$pulse_spam_url = $form_id > 0
						? Thimbleform_Submissions::list_url( $form_id, Thimbleform_Submissions::STATUS_SPAM )
						: Thimbleform_Submissions::hub_url( array( 'thimbleform_status' => Thimbleform_Submissions::STATUS_SPAM ) );
					?>
					<a class="thimbleform-dash__pulse-item thimbleform-dash__pulse-item--new" href="<?php echo esc_url( $pulse_new_url ); ?>">
						<span class="thimbleform-dash__pulse-label"><?php esc_html_e( 'New', 'thimbleform' ); ?></span>
						<span class="thimbleform-dash__pulse-value"><?php echo esc_html( number_format_i18n( $status_new ) ); ?></span>
					</a>
					<a class="thimbleform-dash__pulse-item thimbleform-dash__pulse-item--read" href="<?php echo esc_url( $pulse_read_url ); ?>">
						<span class="thimbleform-dash__pulse-label"><?php esc_html_e( 'Read', 'thimbleform' ); ?></span>
						<span class="thimbleform-dash__pulse-value"><?php echo esc_html( number_format_i18n( $status_read ) ); ?></span>
					</a>
					<a class="thimbleform-dash__pulse-item thimbleform-dash__pulse-item--spam" href="<?php echo esc_url( $pulse_spam_url ); ?>">
						<span class="thimbleform-dash__pulse-label"><?php esc_html_e( 'Spam', 'thimbleform' ); ?></span>
						<span class="thimbleform-dash__pulse-value"><?php echo esc_html( number_format_i18n( $status_spam ) ); ?></span>
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
					$pulse_extra = (string) apply_filters( 'thimbleform_dashboard_work_pulse_extra', '', $pulse_ctx );
					echo wp_kses( $pulse_extra, thimbleform_admin_allowed_html() );
					?>
				</nav>
			</section>

			<section class="thimbleform-dash__kpis" aria-label="<?php esc_attr_e( 'Key metrics', 'thimbleform' ); ?>">
				<a class="thimbleform-dash__kpi thimbleform-dash__kpi--link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Thimbleform_Post_Type::POST_TYPE ) ); ?>">
					<span class="thimbleform-dash__kpi-label"><?php esc_html_e( 'Forms', 'thimbleform' ); ?></span>
					<span class="thimbleform-dash__kpi-value"><?php echo esc_html( number_format_i18n( $forms_published ) ); ?></span>
					<span class="thimbleform-dash__kpi-meta">
						<?php
						if ( $form_id > 0 ) {
							echo esc_html( $forms_published ? __( 'Published', 'thimbleform' ) : __( 'Draft', 'thimbleform' ) );
						} else {
							echo esc_html(
								sprintf(
									/* translators: %d: draft forms count */
									_n( '%d draft', '%d drafts', $forms_draft, 'thimbleform' ),
									$forms_draft
								)
							);
						}
						?>
					</span>
				</a>
				<article class="thimbleform-dash__kpi thimbleform-dash__kpi--primary">
					<span class="thimbleform-dash__kpi-label"><?php esc_html_e( 'Submissions', 'thimbleform' ); ?></span>
					<span class="thimbleform-dash__kpi-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					<?php self::render_delta( $delta, $delta_pct, $range ); ?>
				</article>
				<?php
				$has_conversion = class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS );
				if ( $has_conversion ) {
					$conversion_kpi = (string) apply_filters(
						'thimbleform_dashboard_conversion_kpi',
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
						echo wp_kses( $conversion_kpi, thimbleform_admin_allowed_html() );
					}
				} else {
					?>
				<article class="thimbleform-dash__kpi">
					<span class="thimbleform-dash__kpi-label"><?php esc_html_e( 'Active forms', 'thimbleform' ); ?></span>
					<span class="thimbleform-dash__kpi-value"><?php echo esc_html( number_format_i18n( $active_forms ) ); ?></span>
					<span class="thimbleform-dash__kpi-meta">
						<?php
						if ( $form_id > 0 ) {
							echo esc_html__( 'this form', 'thimbleform' );
						} else {
							echo esc_html(
								sprintf(
									/* translators: %d: total forms on the site */
									__( '%d total', 'thimbleform' ),
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

			<div class="thimbleform-dash__layout">
				<div class="thimbleform-dash__main">
				<section class="thimbleform-dash__panel thimbleform-dash__panel--chart">
					<div class="thimbleform-dash__panel-head">
						<h2 class="thimbleform-dash__panel-title"><?php esc_html_e( 'Activity', 'thimbleform' ); ?></h2>
						<?php if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS ) ) : ?>
							<?php
							/**
							 * Chart metrics nav HTML (Pro views/conversion tabs).
							 *
							 * @param string $html Empty by default.
							 * @param array  $ctx  Context.
							 */
							$metrics_nav = (string) apply_filters(
								'thimbleform_dashboard_chart_metrics',
								'',
								array(
									'form_id' => $form_id,
									'after'   => $chart_after,
									'before'  => $before,
									'daily'   => $daily,
									'range'   => $range,
								)
							);
							echo wp_kses( $metrics_nav, thimbleform_admin_allowed_html() );
							?>
						<?php elseif ( class_exists( 'Thimbleform_Features' ) && ! Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS ) ) : ?>
							<nav class="thimbleform-dash__metrics" aria-label="<?php esc_attr_e( 'Chart metric', 'thimbleform' ); ?>">
								<span class="thimbleform-dash__metric is-active"><?php esc_html_e( 'Submissions', 'thimbleform' ); ?></span>
							</nav>
						<?php elseif ( 'all' === $range ) : ?>
							<span class="thimbleform-dash__panel-hint"><?php esc_html_e( 'Chart shows last 90 days', 'thimbleform' ); ?></span>
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
					if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS ) ) {
						$chart_empty = (bool) apply_filters(
							'thimbleform_dashboard_chart_empty',
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
						<div class="thimbleform-dash__empty">
							<strong><?php esc_html_e( 'No activity yet', 'thimbleform' ); ?></strong>
							<span>
								<?php
								echo esc_html(
									class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS )
										? __( 'When forms start receiving views or entries, the trend will appear here.', 'thimbleform' )
										: __( 'When forms start receiving entries, the trend will appear here.', 'thimbleform' )
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
									'label'    => __( 'Submissions', 'thimbleform' ),
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
						<div class="thimbleform-dash__chart-wrap" data-thimbleform-chart-wrap>
							<?php
							echo wp_kses(
								'<script type="application/json" data-thimbleform-chart>' . wp_json_encode( $free_chart ) . '</script>',
								thimbleform_admin_allowed_html()
							);
							?>
							<?php
							/**
							 * Extra markup / data for Pro chart series switching.
							 * Only applied when Advanced Analytics is active.
							 *
							 * @param string $html Empty.
							 * @param array  $ctx  Context.
							 */
							if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS ) ) {
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
								$chart_extra = apply_filters( 'thimbleform_dashboard_chart_data', '', $chart_ctx );
								self::echo_filter_html( $chart_extra );
							}
							?>
							<div class="thimbleform-dash__legend">
								<span class="thimbleform-dash__legend-item">
									<span class="thimbleform-dash__legend-swatch" aria-hidden="true"></span>
									<span data-thimbleform-chart-legend-label><?php esc_html_e( 'Submissions', 'thimbleform' ); ?></span>
								</span>
								<span class="thimbleform-dash__legend-stat" data-thimbleform-chart-peak>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: peak count, 2: peak date */
											__( 'Peak %1$s · %2$s', 'thimbleform' ),
											number_format_i18n( $peak_count ),
											$peak_day !== '' ? wp_date( 'j M', strtotime( $peak_day . ' 12:00:00' ) ) : '—'
										)
									);
									?>
								</span>
								<span class="thimbleform-dash__legend-stat" data-thimbleform-chart-avg>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: average per day */
											__( 'Avg %s / day', 'thimbleform' ),
											number_format_i18n( $avg_day, 1 )
										)
									);
									?>
								</span>
							</div>
							<div class="thimbleform-dash__chart-plot" data-thimbleform-chart-plot>
								<canvas
									class="thimbleform-dash__chart-canvas"
									data-thimbleform-chart-canvas
									role="img"
									aria-label="<?php esc_attr_e( 'Submissions trend', 'thimbleform' ); ?>"
								></canvas>
							</div>
						</div>
					<?php endif; ?>
				</section>

				<aside class="thimbleform-dash__aside">
					<section class="thimbleform-dash__panel thimbleform-dash__panel--rail">
						<div class="thimbleform-dash__panel-head">
							<h2 class="thimbleform-dash__panel-title"><?php esc_html_e( 'Top forms', 'thimbleform' ); ?></h2>
							<span class="thimbleform-dash__panel-hint"><?php echo esc_html( $range_labels[ $range ] ); ?></span>
						</div>

						<div class="thimbleform-dash__rail">
							<div class="thimbleform-dash__rail-block thimbleform-dash__rail-block--forms">
								<?php if ( array() === $top || 0 === $total ) : ?>
									<p class="thimbleform-dash__rail-empty"><?php esc_html_e( 'Forms with the most submissions will show up here.', 'thimbleform' ); ?></p>
								<?php else : ?>
									<ol class="thimbleform-dash__rank">
										<?php foreach ( $top as $index => $row ) : ?>
											<?php
											$fid   = (int) $row['form_id'];
											$count = (int) $row['count'];
											$post  = isset( $form_ids[ $fid ] ) ? $form_ids[ $fid ] : get_post( $fid );
											$title = ( $post && $post->post_title !== '' ) ? $post->post_title : sprintf(
												/* translators: %d: form id */
												__( 'Form #%d', 'thimbleform' ),
												$fid
											);
											$share = $total > 0 ? (int) round( ( $count / $total ) * 100 ) : 0;
											$pct   = $max_top > 0 ? (int) round( ( $count / $max_top ) * 100 ) : 0;
											?>
											<li class="thimbleform-dash__rank-item<?php echo 0 === $index ? ' thimbleform-dash__rank-item--lead' : ''; ?>">
												<div class="thimbleform-dash__rank-row">
													<div class="thimbleform-dash__rank-name">
														<span class="thimbleform-dash__rank-index"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
														<a class="thimbleform-dash__rank-title" href="<?php echo esc_url( Thimbleform_Submissions::list_url( $fid ) ); ?>">
															<?php echo esc_html( $title ); ?>
														</a>
													</div>
													<span class="thimbleform-dash__rank-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
												</div>
												<div class="thimbleform-dash__rank-track" aria-hidden="true">
													<span class="thimbleform-dash__rank-fill" style="width: <?php echo esc_attr( (string) $pct ); ?>%"></span>
												</div>
												<span class="thimbleform-dash__rank-share"><?php echo esc_html( $share . '%' ); ?></span>
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
				if ( class_exists( 'Thimbleform_Features' ) && Thimbleform_Features::can( Thimbleform_Features::LEAD_INSIGHTS ) ) {
					/**
					 * Lead insights panel HTML (Pro).
					 *
					 * @param string $html Empty by default.
					 * @param array  $ctx  Dashboard context.
					 */
					$insights_html = (string) apply_filters(
						'thimbleform_dashboard_lead_insights',
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
				$can_responses = class_exists( 'Thimbleform_Features' )
					&& ( Thimbleform_Features::can( Thimbleform_Features::QUIZ_SURVEY ) || Thimbleform_Features::can( Thimbleform_Features::ADVANCED_ANALYTICS ) );
				/**
				 * Response breakdown panel HTML (Pro survey charts).
				 * Only applied when Quiz/Survey or Advanced Analytics is active.
				 *
				 * @param string $html Empty by default.
				 * @param array  $ctx  Context.
				 */
				$responses_html = '';
				if ( $can_responses ) {
					$responses_html = (string) apply_filters( 'thimbleform_dashboard_responses_panel', '', $responses_ctx );
				}

				if ( '' !== $insights_html || '' !== $responses_html ) {
					echo '<div class="thimbleform-dash__deep">';
					self::echo_filter_html( $insights_html );
					self::echo_filter_html( $responses_html );
					echo '</div>';
				}
				?>

				<section class="thimbleform-dash__panel thimbleform-dash__panel--wide thimbleform-dash__panel--activity">
					<div class="thimbleform-dash__panel-head">
						<h2 class="thimbleform-dash__panel-title"><?php esc_html_e( 'Recent activity', 'thimbleform' ); ?></h2>
						<span class="thimbleform-dash__panel-hint"><?php echo esc_html( $range_labels[ $range ] ); ?></span>
						<a class="thimbleform-btn thimbleform-btn--ghost" href="<?php echo esc_url( $form_id > 0 ? Thimbleform_Submissions::list_url( $form_id ) : Thimbleform_Submissions::hub_url() ); ?>">
							<?php echo esc_html( $form_id > 0 ? __( 'Form inbox', 'thimbleform' ) : __( 'All entries', 'thimbleform' ) ); ?>
							<?php echo wp_kses( thimbleform_admin_icon_html( 'forward' ), thimbleform_svg_allowed_html() ); ?>
						</a>
					</div>
					<?php if ( array() === $recent ) : ?>
						<div class="thimbleform-dash__empty">
							<strong><?php esc_html_e( 'No activity in this period', 'thimbleform' ); ?></strong>
							<span><?php esc_html_e( 'Try another range, or wait for new submissions.', 'thimbleform' ); ?></span>
						</div>
					<?php else : ?>
						<div class="thimbleform-dash__feed">
							<?php foreach ( $recent as $entry ) : ?>
								<?php
								$eid      = (int) $entry->ID;
								$efid     = (int) get_post_meta( $eid, Thimbleform_Submissions::META_FORM, true );
								$payload  = get_post_meta( $eid, Thimbleform_Submissions::META_DATA, true );
								$payload  = is_array( $payload ) ? $payload : array();
								$eform    = isset( $form_ids[ $efid ] ) ? $form_ids[ $efid ] : get_post( $efid );
								$ftitle   = ( $eform && $eform->post_title !== '' ) ? $eform->post_title : ( $efid ? '#' . $efid : '—' );
								$edit_url = get_edit_post_link( $eid, 'raw' );
								$when     = human_time_diff( get_post_time( 'U', true, $entry ), current_time( 'timestamp', true ) );
								$who      = Thimbleform_Submissions::payload_name( $payload, (string) $entry->post_title, $ftitle, $efid );
								$email    = Thimbleform_Submissions::payload_email( $payload );
								$estatus  = Thimbleform_Submissions::get_status( $eid );
								$badge    = Thimbleform_Submissions::badge_modifier( $estatus );
								$card_tag = $edit_url ? 'a' : 'div';
								?>
								<<?php echo esc_html( $card_tag ); ?> class="thimbleform-dash__card"<?php echo $edit_url ? ' href="' . esc_url( $edit_url ) . '"' : ''; ?>>
									<div class="thimbleform-dash__card-top">
										<span class="thimbleform-badge thimbleform-badge--<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( strtoupper( $estatus ) ); ?></span>
										<span class="thimbleform-dash__card-ago">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: relative time */
													__( '%s ago', 'thimbleform' ),
													$when
												)
											);
											?>
										</span>
									</div>
									<div class="thimbleform-dash__card-who"><?php echo esc_html( $who ); ?></div>
									<div class="thimbleform-dash__card-form"><?php echo esc_html( $ftitle ); ?></div>
									<?php if ( $email !== '' ) : ?>
										<div class="thimbleform-dash__card-email"><?php echo esc_html( $email ); ?></div>
									<?php endif; ?>
								</<?php echo esc_html( $card_tag ); ?>>
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
			echo '<span class="thimbleform-dash__kpi-meta">' . esc_html__( 'Lifetime across all entries', 'thimbleform' ) . '</span>';
			return;
		}
		$class = 'thimbleform-dash__delta';
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
		echo ' <span>' . esc_html__( 'vs previous period', 'thimbleform' ) . '</span>';
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
				'post_type'              => Thimbleform_Submissions::POST_TYPE,
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
				'post_type'      => Thimbleform_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		if ( ! is_array( $forms ) ) {
			return array();
		}
		$allowed = Thimbleform_Submissions::accessible_form_ids();
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
				if ( ! preg_match( '/\b(data-thimbleform-[a-z0-9-]+)/', $attrs, $name ) ) {
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
			echo wp_kses( $island, thimbleform_admin_allowed_html() );
		}
	}
}
