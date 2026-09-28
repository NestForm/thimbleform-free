<?php
/**
 * Aggregates form entries into per-field summaries.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Response_Summary {

	const CACHE_PREFIX     = 'nestform_summary_v2_';
	const MAX_ENTRIES      = 10000;
	const TEXT_SAMPLE_SIZE = 5;

	public static function init() {
		add_action( 'nestform_submitted', array( __CLASS__, 'on_submitted' ), 10, 3 );
	}

	/**
	 * Invalidate summary cache after a new entry.
	 *
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $data     Entry payload.
	 * @param int                  $entry_id Entry ID.
	 */
	public static function on_submitted( $form_id, $data, $entry_id ) {
		unset( $data, $entry_id );
		self::invalidate( (int) $form_id );
	}

	/**
	 * @param int $form_id Form ID.
	 */
	public static function invalidate( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return;
		}
		delete_transient( self::CACHE_PREFIX . $form_id );
	}

	/**
	 * Summary screen URL for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function url( $form_id ) {
		return Nestform_Submissions::hub_url(
			array(
				'form_id'  => (int) $form_id,
				'summary'  => '1',
			)
		);
	}

	/**
	 * Whether the current user may view the summary for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function user_can_view( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return false;
		}
		if ( class_exists( 'Nestform_Capabilities' ) && Nestform_Capabilities::can_view_entries() ) {
			return Nestform_Submissions::user_can_manage_form_entries( $form_id )
				|| current_user_can( 'edit_post', $form_id );
		}
		return Nestform_Submissions::user_can_manage_form_entries( $form_id );
	}

	/**
	 * Build or return cached summary for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array{total:int,capped:bool,entry_count:int,fields:array<int,array<string,mixed>>}
	 */
	public static function summarize( $form_id ) {
		$form_id = (int) $form_id;
		$count   = class_exists( 'Nestform_Submissions' )
			? (int) Nestform_Submissions::count_entries(
				array(
					'form_id'           => $form_id,
					'skip_access_check' => true,
				)
			)
			: 0;

		$cache_key = self::CACHE_PREFIX . $form_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached )
			&& isset( $cached['entry_count'], $cached['fields'], $cached['total'] )
			&& (int) $cached['entry_count'] === $count
		) {
			return $cached;
		}

		$summary                 = self::build_summary( $form_id );
		$summary['entry_count']  = $count;
		set_transient( $cache_key, $summary, HOUR_IN_SECONDS );

		return $summary;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array{total:int,capped:bool,fields:array<int,array<string,mixed>>}
	 */
	private static function build_summary( $form_id ) {
		$fields   = Nestform_Form_Config::get_fields( $form_id );
		$payloads = array();
		$paged    = 1;
		$pages    = 1;

		do {
			$result = Nestform_Submissions::query_entries(
				array(
					'form_id'           => $form_id,
					'limit'             => 100,
					'paged'             => $paged,
					'skip_access_check' => true,
				)
			);
			$posts = isset( $result['posts'] ) && is_array( $result['posts'] ) ? $result['posts'] : array();
			$pages = isset( $result['pages'] ) ? max( 1, (int) $result['pages'] ) : 1;

			foreach ( $posts as $post ) {
				$data       = get_post_meta( (int) $post->ID, Nestform_Submissions::META_DATA, true );
				$payloads[] = is_array( $data ) ? $data : array();
				if ( count( $payloads ) >= self::MAX_ENTRIES ) {
					break 2;
				}
			}
			++$paged;
		} while ( $paged <= $pages );

		$total = count( $payloads );

		$out_fields = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			$name = (string) ( $field['name'] ?? '' );
			if ( $name === '' || Nestform_Form_Config::is_layout_field( $type ) ) {
				continue;
			}
			if ( in_array( $type, array( 'hidden', 'password', 'file', 'html', 'submit', 'signature' ), true ) ) {
				continue;
			}
			$out_fields[] = self::summarize_field( $field, $payloads );
		}

		return array(
			'total'  => $total,
			'capped' => $total >= self::MAX_ENTRIES,
			'fields' => $out_fields,
		);
	}

	/**
	 * @param array<string, mixed>           $field    Field definition.
	 * @param array<int, array<string,mixed>> $payloads Entry payloads, newest first.
	 * @return array<string, mixed>
	 */
	private static function summarize_field( array $field, array $payloads ) {
		$name   = (string) ( $field['name'] ?? '' );
		$type   = (string) ( $field['type'] ?? 'text' );
		$label  = (string) ( $field['label'] ?? '' );
		$values = array();

		foreach ( $payloads as $payload ) {
			if ( ! array_key_exists( $name, $payload ) ) {
				continue;
			}
			$value = $payload[ $name ];
			if ( '' === $value || null === $value || array() === $value ) {
				continue;
			}
			$values[] = $value;
		}

		$base = array(
			'label'    => $label !== '' ? wp_strip_all_tags( $label ) : $name,
			'type'     => $type,
			'answered' => count( $values ),
			'name'     => $name,
		);

		switch ( $type ) {
			case 'select':
			case 'radio':
			case 'checkboxes':
			case 'checkbox':
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				return array_merge(
					$base,
					array(
						'kind'   => 'choice',
						'counts' => self::count_choices( $values, $options ),
					)
				);

			case 'number':
			case 'range':
			case 'rating':
			case 'nps':
			case 'scale':
			case 'calculated':
				return array_merge(
					$base,
					array( 'kind' => 'number' ),
					self::number_stats( $values )
				);

			case 'text':
			case 'email':
			case 'textarea':
			case 'tel':
			case 'url':
			default:
				return array_merge(
					$base,
					array(
						'kind'    => 'text',
						'samples' => self::text_samples( $values ),
						'unique'  => self::count_unique( $values ),
					)
				);
		}
	}

	/**
	 * @param array<int, mixed> $values  Submitted values.
	 * @param array<int, mixed> $options Configured options.
	 * @return array<string, int>
	 */
	private static function count_choices( array $values, array $options ) {
		$labels = array();
		foreach ( $options as $opt ) {
			if ( is_array( $opt ) ) {
				$labels[] = (string) ( $opt['label'] ?? $opt['value'] ?? '' );
			} else {
				$labels[] = (string) $opt;
			}
		}
		$labels = array_values( array_filter( array_map( 'strval', $labels ), static function ( $v ) {
			return $v !== '';
		} ) );

		$counts = array();
		foreach ( $labels as $label ) {
			$counts[ $label ] = 0;
		}
		$extra = array();

		foreach ( $values as $value ) {
			foreach ( (array) $value as $choice ) {
				if ( is_array( $choice ) ) {
					$choice = (string) ( $choice['label'] ?? $choice['value'] ?? '' );
				} else {
					$choice = (string) $choice;
				}
				if ( $choice === '' ) {
					continue;
				}
				if ( array_key_exists( $choice, $counts ) ) {
					++$counts[ $choice ];
				} else {
					if ( ! isset( $extra[ $choice ] ) ) {
						$extra[ $choice ] = 0;
					}
					++$extra[ $choice ];
				}
			}
		}

		arsort( $extra );

		return $counts + $extra;
	}

	/**
	 * @param array<int, mixed> $values Submitted values.
	 * @return array{min?:float,max?:float,avg?:float}
	 */
	private static function number_stats( array $values ) {
		$numbers = array();
		foreach ( $values as $value ) {
			if ( is_numeric( $value ) ) {
				$numbers[] = (float) $value;
			}
		}
		if ( array() === $numbers ) {
			return array();
		}
		return array(
			'min' => min( $numbers ),
			'max' => max( $numbers ),
			'avg' => array_sum( $numbers ) / count( $numbers ),
		);
	}

	/**
	 * @param array<int, mixed> $values Submitted values, newest first.
	 * @return array<int, string>
	 */
	private static function text_samples( array $values ) {
		$samples = array();
		foreach ( $values as $value ) {
			$samples[] = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			if ( count( $samples ) >= self::TEXT_SAMPLE_SIZE ) {
				break;
			}
		}
		return $samples;
	}

	/**
	 * @param array<int, mixed> $values Submitted values.
	 * @return int
	 */
	private static function count_unique( array $values ) {
		$seen = array();
		foreach ( $values as $value ) {
			$key = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
			$seen[ $key ] = true;
		}
		return count( $seen );
	}

	/**
	 * Human label for a field type.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	private static function type_label( $type ) {
		$type   = (string) $type;
		$labels = class_exists( 'Nestform_Form_Config' ) ? Nestform_Form_Config::field_type_labels() : array();
		if ( isset( $labels[ $type ] ) && (string) $labels[ $type ] !== '' ) {
			return (string) $labels[ $type ];
		}
		return $type !== '' ? ucfirst( $type ) : __( 'Field', 'nestform' );
	}

	/**
	 * Render the summary admin screen (called from hub when summary=1).
	 *
	 * @return bool True if rendered.
	 */
	public static function render_summary_screen() {
		$form_id = 0;
		if ( isset( $_GET['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$form_id = (int) $_GET['form_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['nestform_form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$form_id = (int) $_GET['nestform_form_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $form_id <= 0 || Nestform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			return false;
		}

		$can = false;
		if ( class_exists( 'Nestform_Capabilities' ) ) {
			$can = Nestform_Capabilities::can_view_entries() || current_user_can( 'edit_posts' );
		} else {
			$can = current_user_can( 'edit_posts' );
		}
		if ( ! $can || ! self::user_can_view( $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this summary.', 'nestform' ), '', array( 'response' => 403 ) );
		}

		$form    = get_post( $form_id );
		$summary = self::summarize( $form_id );
		$title   = ( $form && $form->post_title !== '' ) ? $form->post_title : __( 'Form', 'nestform' );
		$total   = (int) $summary['total'];
		$fields  = isset( $summary['fields'] ) && is_array( $summary['fields'] ) ? $summary['fields'] : array();
		$new_n   = (int) Nestform_Submissions::count_new_for_form( $form_id );
		$last_ts = 0;
		if ( method_exists( 'Nestform_Submissions', 'latest_entry_times' ) ) {
			$times   = Nestform_Submissions::latest_entry_times( array( $form_id ) );
			$last_ts = isset( $times[ $form_id ] ) ? (int) $times[ $form_id ] : 0;
		}

		$answered_sum = 0;
		$field_n      = count( $fields );
		foreach ( $fields as $row ) {
			$answered_sum += (int) ( $row['answered'] ?? 0 );
		}
		$completion = ( $total > 0 && $field_n > 0 )
			? (int) round( ( $answered_sum / ( $total * $field_n ) ) * 100 )
			: 0;

		$edit_url = get_edit_post_link( $form_id, 'raw' );
		$inbox_url = Nestform_Submissions::list_url( $form_id );
		$new_url   = $new_n > 0
			? Nestform_Submissions::list_url( $form_id, Nestform_Submissions::STATUS_NEW )
			: $inbox_url;

		$actions  = '<a class="nestform-btn nestform-btn--outline" href="' . esc_url( $inbox_url ) . '">';
		$actions .= nestform_admin_icon_html( 'entries' ) . ' ' . esc_html__( 'Inbox', 'nestform' );
		$actions .= '</a>';
		if ( $edit_url ) {
			$actions .= ' <a class="nestform-btn nestform-btn--outline" href="' . esc_url( $edit_url ) . '">';
			$actions .= nestform_admin_icon_html( 'forms' ) . ' ' . esc_html__( 'Edit form', 'nestform' );
			$actions .= '</a>';
		}
		if ( class_exists( 'Nestform_Export' ) ) {
			$actions .= ' ' . Nestform_Export::dropdown_html(
				$form_id,
				array(
					'variant' => 'outline',
				)
			);
		}

		$insights = self::insights( $fields, $total );

		?>
		<div class="wrap nestform-hub nestform-summary">
			<?php
			nestform_render_page_head(
				array(
					'title'        => sprintf(
						/* translators: %s: form title */
						__( 'Summary — %s', 'nestform' ),
						$title
					),
					'description'  => __( 'How people answered this form — choices, ranges, and recent text.', 'nestform' ),
					'actions_html' => $actions,
					'icon'         => 'analytics',
				)
			);
			?>

			<div class="nestform-hub__stats nestform-summary__kpis" aria-label="<?php esc_attr_e( 'Response overview', 'nestform' ); ?>">
				<a class="nestform-hub__stat" href="<?php echo esc_url( $inbox_url ); ?>">
					<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					<span class="nestform-hub__stat-label"><?php echo esc_html( _n( 'Entry', 'Entries', $total, 'nestform' ) ); ?></span>
				</a>
				<a class="nestform-hub__stat<?php echo $new_n > 0 ? ' nestform-hub__stat--new' : ''; ?>" href="<?php echo esc_url( $new_url ); ?>">
					<span class="nestform-hub__stat-value"><?php echo esc_html( number_format_i18n( $new_n ) ); ?></span>
					<span class="nestform-hub__stat-label"><?php esc_html_e( 'New', 'nestform' ); ?></span>
				</a>
				<div class="nestform-hub__stat">
					<span class="nestform-hub__stat-value"><?php echo $field_n > 0 ? esc_html( (string) $completion . '%' ) : '—'; ?></span>
					<span class="nestform-hub__stat-label"><?php esc_html_e( 'Answered', 'nestform' ); ?></span>
				</div>
				<div class="nestform-hub__stat">
					<span class="nestform-hub__stat-value"><?php echo esc_html( (string) number_format_i18n( $field_n ) ); ?></span>
					<span class="nestform-hub__stat-label"><?php echo esc_html( _n( 'Field', 'Fields', $field_n, 'nestform' ) ); ?></span>
				</div>
				<div class="nestform-hub__stat">
					<span class="nestform-hub__stat-value"><?php echo esc_html( self::format_when( $last_ts ) ); ?></span>
					<span class="nestform-hub__stat-label"><?php esc_html_e( 'Last entry', 'nestform' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $summary['capped'] ) ) : ?>
				<p class="nestform-summary__note"><?php esc_html_e( 'Showing the newest 10,000 entries only.', 'nestform' ); ?></p>
			<?php endif; ?>

			<?php if ( $total > 0 && ( ! empty( $insights['top'] ) || ! empty( $insights['skipped'] ) ) ) : ?>
				<div class="nestform-summary__insights">
					<?php if ( ! empty( $insights['top'] ) ) : ?>
						<div class="nestform-summary__insight">
							<span class="nestform-summary__insight-kicker"><?php esc_html_e( 'Most chosen', 'nestform' ); ?></span>
							<strong class="nestform-summary__insight-value"><?php echo esc_html( (string) $insights['top']['option'] ); ?></strong>
							<span class="nestform-summary__insight-hint">
								<?php
								printf(
									/* translators: 1: field label, 2: percentage */
									esc_html__( '%1$s · %2$s%% of answers', 'nestform' ),
									esc_html( (string) $insights['top']['field'] ),
									esc_html( (string) $insights['top']['pct'] )
								);
								?>
							</span>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $insights['skipped'] ) ) : ?>
						<div class="nestform-summary__insight">
							<span class="nestform-summary__insight-kicker"><?php esc_html_e( 'Most skipped', 'nestform' ); ?></span>
							<strong class="nestform-summary__insight-value"><?php echo esc_html( (string) $insights['skipped']['field'] ); ?></strong>
							<span class="nestform-summary__insight-hint">
								<?php
								printf(
									/* translators: %s: percentage left blank */
									esc_html__( '%s%% left blank', 'nestform' ),
									esc_html( (string) $insights['skipped']['pct'] )
								);
								?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( 0 === $total ) : ?>
				<div class="nestform-hub__empty-state">
					<p class="nestform-hub__empty-state-title"><?php esc_html_e( 'No entries yet', 'nestform' ); ?></p>
					<p class="nestform-hub__empty-state-text"><?php esc_html_e( 'Once people submit this form, answers will roll up here — choices as bars, numbers as ranges, text as recent samples.', 'nestform' ); ?></p>
					<div class="nestform-hub__empty-actions">
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( $inbox_url ); ?>">
							<?php nestform_admin_icon( 'entries' ); ?>
							<?php esc_html_e( 'Open inbox', 'nestform' ); ?>
						</a>
					</div>
				</div>
			<?php elseif ( array() === $fields ) : ?>
				<div class="nestform-hub__empty-state">
					<p class="nestform-hub__empty-state-title"><?php esc_html_e( 'Nothing to summarize', 'nestform' ); ?></p>
					<p class="nestform-hub__empty-state-text"><?php esc_html_e( 'This form has no questions that can be aggregated yet. Add choice, number, or text fields, then come back.', 'nestform' ); ?></p>
					<?php if ( $edit_url ) : ?>
						<div class="nestform-hub__empty-actions">
							<a class="nestform-btn nestform-btn--primary" href="<?php echo esc_url( $edit_url ); ?>">
								<?php nestform_admin_icon( 'forms' ); ?>
								<?php esc_html_e( 'Edit form', 'nestform' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="nestform-summary__grid">
					<?php foreach ( $fields as $field_summary ) : ?>
						<?php self::render_field_card( $field_summary, $total ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return true;
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Field rows.
	 * @param int                              $total  Entry count.
	 * @return array{top?:array{field:string,option:string,pct:int},skipped?:array{field:string,pct:int}}
	 */
	private static function insights( array $fields, $total ) {
		$out = array();
		$total = (int) $total;

		$best_count  = 0;
		$best_option = '';
		$best_field  = '';
		$best_pct    = 0;
		$skip_pct    = -1;
		$skip_field  = '';

		foreach ( $fields as $field ) {
			$label    = (string) ( $field['label'] ?? '' );
			$answered = (int) ( $field['answered'] ?? 0 );
			if ( $total > 0 ) {
				$blank = (int) round( ( ( $total - $answered ) / $total ) * 100 );
				if ( $blank > $skip_pct ) {
					$skip_pct   = $blank;
					$skip_field = $label;
				}
			}
			if ( 'choice' !== ( $field['kind'] ?? '' ) || empty( $field['counts'] ) || ! is_array( $field['counts'] ) ) {
				continue;
			}
			$sum = array_sum( array_map( 'intval', $field['counts'] ) );
			foreach ( $field['counts'] as $option => $count ) {
				$count = (int) $count;
				if ( $count > $best_count ) {
					$best_count  = $count;
					$best_option = (string) $option;
					$best_field  = $label;
					$best_pct    = $sum > 0 ? (int) round( ( $count / $sum ) * 100 ) : 0;
				}
			}
		}

		if ( $best_option !== '' ) {
			$out['top'] = array(
				'field'  => $best_field,
				'option' => $best_option,
				'pct'    => $best_pct,
			);
		}
		if ( $skip_field !== '' && $skip_pct > 0 ) {
			$out['skipped'] = array(
				'field' => $skip_field,
				'pct'   => $skip_pct,
			);
		}
		return $out;
	}

	/**
	 * @param int $gmt_ts GMT unix timestamp.
	 * @return string
	 */
	private static function format_when( $gmt_ts ) {
		$gmt_ts = (int) $gmt_ts;
		if ( $gmt_ts <= 0 ) {
			return '—';
		}
		$local = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $gmt_ts ), 'U' );
		$local = $local ? (int) $local : $gmt_ts;
		$diff  = human_time_diff( $local, current_time( 'timestamp' ) );
		/* translators: %s: relative time, e.g. 3 hours */
		return sprintf( __( '%s ago', 'nestform' ), $diff );
	}

	/**
	 * @param array<string, mixed> $field Field summary row.
	 * @param int                  $total Entry count.
	 */
	private static function render_field_card( array $field, $total = 0 ) {
		$kind     = (string) ( $field['kind'] ?? 'text' );
		$label    = (string) ( $field['label'] ?? '' );
		$answered = (int) ( $field['answered'] ?? 0 );
		$type     = (string) ( $field['type'] ?? '' );
		$total    = (int) $total;
		$rate     = $total > 0 ? (int) round( ( $answered / $total ) * 100 ) : 0;
		?>
		<article class="nestform-summary__card">
			<header class="nestform-summary__card-head">
				<div class="nestform-summary__card-copy">
					<span class="nestform-badge nestform-badge--draft"><?php echo esc_html( self::type_label( $type ) ); ?></span>
					<h3 class="nestform-summary__card-title"><?php echo esc_html( $label ); ?></h3>
					<p class="nestform-summary__card-meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: answered count */
								_n( '%d answer', '%d answers', $answered, 'nestform' ),
								(int) $answered
							)
						);
						?>
					</p>
				</div>
				<div class="nestform-summary__rate" title="<?php esc_attr_e( 'Share of entries that filled this field', 'nestform' ); ?>">
					<span class="nestform-summary__rate-value"><?php echo esc_html( (string) $rate ); ?>%</span>
					<span class="nestform-summary__rate-label"><?php esc_html_e( 'filled', 'nestform' ); ?></span>
				</div>
			</header>
			<div class="nestform-summary__rate-track" aria-hidden="true">
				<span class="nestform-summary__rate-fill" style="width:<?php echo esc_attr( (string) $rate ); ?>%"></span>
			</div>
			<div class="nestform-summary__card-body">
				<?php if ( 0 === $answered ) : ?>
					<p class="nestform-summary__empty"><?php esc_html_e( 'No answers yet.', 'nestform' ); ?></p>
				<?php elseif ( 'choice' === $kind ) : ?>
					<?php
					$counts = isset( $field['counts'] ) && is_array( $field['counts'] ) ? $field['counts'] : array();
					$sum    = (int) array_sum( array_map( 'intval', $counts ) );
					$leader = '';
					$lead_n = 0;
					foreach ( $counts as $option => $count ) {
						if ( (int) $count > $lead_n ) {
							$lead_n = (int) $count;
							$leader = (string) $option;
						}
					}
					?>
					<ul class="nestform-summary__bars">
						<?php foreach ( $counts as $option => $count ) : ?>
							<?php
							$count = (int) $count;
							$pct   = $sum > 0 ? (int) round( ( $count / $sum ) * 100 ) : 0;
							$is_on = ( (string) $option === $leader && $lead_n > 0 );
							?>
							<li class="nestform-summary__bar<?php echo $is_on ? ' nestform-summary__bar--lead' : ''; ?>">
								<span class="nestform-summary__bar-label"><?php echo esc_html( (string) $option ); ?></span>
								<span class="nestform-summary__bar-track" aria-hidden="true">
									<span class="nestform-summary__bar-fill" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span>
								</span>
								<span class="nestform-summary__bar-count"><?php echo esc_html( (string) $pct ); ?>%</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php elseif ( 'number' === $kind ) : ?>
					<?php if ( isset( $field['min'], $field['max'], $field['avg'] ) ) : ?>
						<?php
						$min = (float) $field['min'];
						$max = (float) $field['max'];
						$avg = (float) $field['avg'];
						$span = $max - $min;
						$avg_pct = $span > 0 ? (int) round( ( ( $avg - $min ) / $span ) * 100 ) : 50;
						?>
						<dl class="nestform-summary__nums">
							<div>
								<dt><?php esc_html_e( 'Min', 'nestform' ); ?></dt>
								<dd><?php echo esc_html( self::format_number( $min ) ); ?></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Avg', 'nestform' ); ?></dt>
								<dd><?php echo esc_html( self::format_number( $avg ) ); ?></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Max', 'nestform' ); ?></dt>
								<dd><?php echo esc_html( self::format_number( $max ) ); ?></dd>
							</div>
						</dl>
						<div class="nestform-summary__range" aria-hidden="true">
							<span class="nestform-summary__range-fill" style="width:<?php echo esc_attr( (string) $avg_pct ); ?>%"></span>
							<span class="nestform-summary__range-mark" style="left:<?php echo esc_attr( (string) $avg_pct ); ?>%"></span>
						</div>
					<?php else : ?>
						<p class="nestform-summary__empty"><?php esc_html_e( 'No numeric answers yet.', 'nestform' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<?php
					$samples = isset( $field['samples'] ) && is_array( $field['samples'] ) ? $field['samples'] : array();
					$unique  = (int) ( $field['unique'] ?? 0 );
					?>
					<?php if ( $unique > 0 ) : ?>
						<p class="nestform-summary__unique">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: distinct answers */
									_n( '%d unique answer', '%d unique answers', $unique, 'nestform' ),
									(int) $unique
								)
							);
							?>
						</p>
					<?php endif; ?>
					<ul class="nestform-summary__samples">
						<?php foreach ( $samples as $sample ) : ?>
							<li><?php echo esc_html( (string) $sample ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/**
	 * @param float $n Number.
	 * @return string
	 */
	private static function format_number( $n ) {
		if ( abs( $n - round( $n ) ) < 0.0001 ) {
			return number_format_i18n( (int) round( $n ) );
		}
		return number_format_i18n( $n, 2 );
	}
}
