<?php
/**
 * Shared kses allowlists for late escaping.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep field-width custom properties when markup is passed through wp_kses().
 *
 * @param array<int, string> $styles Allowed CSS properties.
 * @return array<int, string>
 */
function thimbleform_safe_style_css( $styles ) {
	$styles[] = '--thimbleform-field-basis';
	$styles[] = '--thimbleform-field-ratio';
	return $styles;
}
add_filter( 'safe_style_css', 'thimbleform_safe_style_css' );

/**
 * Attributes shared by form and admin markup.
 *
 * @return array<string, bool>
 */
function thimbleform_kses_attrs() {
	$attrs = array(
		'class'            => true,
		'id'               => true,
		'style'            => true,
		'title'            => true,
		'role'             => true,
		'hidden'           => true,
		'tabindex'         => true,
		'dir'              => true,
		'lang'             => true,
		'type'             => true,
		'name'             => true,
		'value'            => true,
		'placeholder'      => true,
		'for'              => true,
		'action'           => true,
		'method'           => true,
		'enctype'          => true,
		'novalidate'       => true,
		'href'             => true,
		'src'              => true,
		'alt'              => true,
		'rel'              => true,
		'target'           => true,
		'width'            => true,
		'height'           => true,
		'loading'          => true,
		'decoding'         => true,
		'srcset'           => true,
		'sizes'            => true,
		'required'         => true,
		'checked'          => true,
		'disabled'         => true,
		'selected'         => true,
		'readonly'         => true,
		'multiple'         => true,
		'autocomplete'     => true,
		'inputmode'        => true,
		'accept'           => true,
		'min'              => true,
		'max'              => true,
		'step'             => true,
		'pattern'          => true,
		'maxlength'        => true,
		'minlength'        => true,
		'rows'             => true,
		'cols'             => true,
		'size'             => true,
		'colspan'          => true,
		'rowspan'          => true,
		'scope'            => true,
		'aria-hidden'      => true,
		'aria-label'       => true,
		'aria-live'        => true,
		'aria-atomic'      => true,
		'aria-expanded'    => true,
		'aria-haspopup'    => true,
		'aria-controls'    => true,
		'aria-describedby' => true,
		'aria-invalid'     => true,
		'aria-required'    => true,
		'aria-selected'    => true,
		'aria-current'     => true,
		'xmlns'            => true,
		'viewbox'          => true,
		'fill'             => true,
		'stroke'           => true,
		'stroke-width'     => true,
		'stroke-linecap'   => true,
		'stroke-linejoin'  => true,
		'd'                => true,
		'points'           => true,
		'x'                => true,
		'y'                => true,
		'rx'               => true,
		'ry'               => true,
		'cx'               => true,
		'cy'               => true,
		'r'                => true,
		'shape-rendering'  => true,
		'focusable'        => true,
	);

	$data = array(
		'data-condition-field',
		'data-condition-op',
		'data-condition-value',
		'data-dial',
		'data-error-generic',
		'data-field-name',
		'data-field-step',
		'data-flag',
		'data-form-id',
		'data-iso',
		'data-max-files',
		'data-max-mb',
		'data-msg-file-too-large',
		'data-msg-invalid-date',
		'data-msg-invalid-email',
		'data-msg-invalid-file',
		'data-msg-invalid-number',
		'data-msg-invalid-tel',
		'data-msg-invalid-time',
		'data-msg-invalid-url',
		'data-msg-required',
		'data-msg-too-many-files',
		'data-placeholder',
		'data-redirect',
		'data-required',
		'data-search',
		'data-style-skin',
		'data-success-display',
		'data-thimbleform',
		'data-thimbleform-allow-other',
		'data-thimbleform-captcha',
		'data-thimbleform-captcha-wrap',
		'data-thimbleform-chart',
		'data-thimbleform-choices',
		'data-thimbleform-error',
		'data-thimbleform-layout',
		'data-thimbleform-other',
		'data-thimbleform-other-trigger',
		'data-thimbleform-phone',
		'data-thimbleform-phone-dial',
		'data-thimbleform-phone-flag',
		'data-thimbleform-phone-iso',
		'data-thimbleform-phone-national',
		'data-thimbleform-phone-panel',
		'data-thimbleform-phone-search',
		'data-thimbleform-phone-toggle',
		'data-thimbleform-phone-value',
		'data-thimbleform-preview',
		'data-thimbleform-result',
		'data-thimbleform-select',
		'data-thimbleform-select-list',
		'data-thimbleform-select-native',
		'data-thimbleform-select-trigger',
		'data-thimbleform-select-value',
		'data-thimbleform-status',
		'data-value',
		'data-thimbleform-add-step',
		'data-thimbleform-answer-fill',
		'data-thimbleform-answer-swatch',
		'data-thimbleform-branch-add',
		'data-thimbleform-branch-field',
		'data-thimbleform-branch-from',
		'data-thimbleform-branch-hint',
		'data-thimbleform-branch-list',
		'data-thimbleform-branch-op',
		'data-thimbleform-branch-rules',
		'data-thimbleform-branch-to',
		'data-thimbleform-branch-ui',
		'data-thimbleform-branch-value',
		'data-thimbleform-calculated',
		'data-thimbleform-can-multi-step',
		'data-thimbleform-enable-steps',
		'data-thimbleform-form-id',
		'data-thimbleform-formula',
		'data-thimbleform-hub-import',
		'data-thimbleform-lead-insights',
		'data-thimbleform-mode',
		'data-thimbleform-next',
		'data-thimbleform-partial',
		'data-thimbleform-payment',
		'data-thimbleform-payment-intent',
		'data-thimbleform-payment-mount',
		'data-thimbleform-payment-nonce',
		'data-thimbleform-prev',
		'data-thimbleform-pro-chart',
		'data-thimbleform-pro-clear',
		'data-thimbleform-progress',
		'data-thimbleform-progress-fill',
		'data-thimbleform-progress-step',
		'data-thimbleform-pro-matrix',
		'data-thimbleform-pro-metric',
		'data-thimbleform-pro-metrics',
		'data-thimbleform-pro-nps',
		'data-thimbleform-pro-ranking',
		'data-thimbleform-pro-rating',
		'data-thimbleform-pro-scale',
		'data-thimbleform-pro-scale-value',
		'data-thimbleform-pro-signature',
		'data-thimbleform-quiz-started',
		'data-thimbleform-repeater',
		'data-thimbleform-repeater-add',
		'data-thimbleform-repeater-name',
		'data-thimbleform-repeater-remove',
		'data-thimbleform-repeater-required',
		'data-thimbleform-repeater-row',
		'data-thimbleform-repeater-rows',
		'data-thimbleform-repeater-template',
		'data-thimbleform-response-hub-label',
		'data-thimbleform-response-hub-value',
		'data-thimbleform-responses-form',
		'data-thimbleform-step-labels',
		'data-thimbleform-step-panel',
		'data-thimbleform-steps',
		'data-thimbleform-steps-branch',
		'data-thimbleform-steps-extra',
		'data-thimbleform-steps-setup',
		'data-thimbleform-submit',
		'data-thimbleform-survey-chart',
		'data-thimbleform-survey-charts',
		'data-thimbleform-survey-charts-root',
		'data-thimbleform-timer',
		'data-thimbleform-visited-steps',
		'data-thimbleform-export-menu',
		'data-thimbleform-image-id',
		'data-thimbleform-options-input',
		'data-thimbleform-payment-amount',
		'data-thimbleform-payment-currency',
		'data-thimbleform-phone-picker',
		'data-thimbleform-placeholder',
		'data-thimbleform-description',
		'data-thimbleform-description-label',
		'data-thimbleform-allow-other',
		'data-thimbleform-show',
		'data-thimbleform-subfield-name',
		'data-thimbleform-subfield-options',
		'data-thimbleform-subfield-options-input',
		'data-thimbleform-subfield-options-label',
		'data-thimbleform-subfield-options-hint',
		'data-thimbleform-subfield-remove',
	);
	foreach ( $data as $name ) {
		$attrs[ $name ] = true;
	}

	return $attrs;
}

/**
 * @param array<int, string> $tags Tag names.
 * @return array<string, array<string, bool>>
 */
function thimbleform_kses_tags( array $tags ) {
	$attrs   = thimbleform_kses_attrs();
	$allowed = array();
	foreach ( $tags as $tag ) {
		$allowed[ $tag ] = $attrs;
	}
	return $allowed;
}

/**
 * Markup a public form is allowed to print.
 *
 * @return array<string, array<string, bool>>
 */
function thimbleform_form_allowed_html() {
	$allowed = thimbleform_kses_tags(
		array(
			'form',
			'div',
			'span',
			'p',
			'a',
			'label',
			'input',
			'textarea',
			'select',
			'option',
			'optgroup',
			'button',
			'ul',
			'ol',
			'li',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'img',
			'br',
			'hr',
			'strong',
			'em',
			'b',
			'i',
			'u',
			'small',
			'fieldset',
			'legend',
			'figure',
			'figcaption',
			'table',
			'thead',
			'tbody',
			'tr',
			'th',
			'td',
			'blockquote',
			'svg',
			'path',
			'rect',
			'circle',
			'polyline',
			'polygon',
			'line',
			'g',
		)
	);

	/**
	 * Filter the public form kses allowlist.
	 *
	 * @param array<string, array<string, bool>> $allowed Allowed tags.
	 */
	return (array) apply_filters( 'thimbleform_form_allowed_html', $allowed );
}

/**
 * Markup trusted admin screens may print (icons, filters, charts).
 *
 * @return array<string, array<string, bool>>
 */
function thimbleform_admin_allowed_html() {
	$allowed = thimbleform_form_allowed_html();
	$extra   = thimbleform_kses_tags(
		array(
			'nav',
			'article',
			'section',
			'header',
			'footer',
			'script',
		)
	);
	$allowed = array_merge( $allowed, $extra );

	/**
	 * Filter the admin kses allowlist.
	 *
	 * @param array<string, array<string, bool>> $allowed Allowed tags.
	 */
	return (array) apply_filters( 'thimbleform_admin_allowed_html', $allowed );
}

/**
 * Static admin SVG icons.
 *
 * @return array<string, array<string, bool>>
 */
function thimbleform_svg_allowed_html() {
	return thimbleform_kses_tags(
		array(
			'svg',
			'path',
			'rect',
			'circle',
			'polyline',
			'polygon',
			'line',
			'g',
		)
	);
}
