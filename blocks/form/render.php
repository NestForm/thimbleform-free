<?php
/**
 * Dynamic render for thimbleform/form block.
 *
 * @package Thimbleform
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$thimbleform_form_id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
if ( $thimbleform_form_id <= 0 || ! class_exists( 'Thimbleform_Renderer' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="thimbleform-block thimbleform-block--empty">' . esc_html__( 'Select a Thimbleform in the block settings.', 'thimbleform' ) . '</p>';
	}
	return;
}

$thimbleform_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'thimbleform-block',
	)
);

$thimbleform_html = Thimbleform_Renderer::render( $thimbleform_form_id );
if ( $thimbleform_html === '' ) {
	return;
}

echo wp_kses( '<div ' . $thimbleform_wrapper . '>' . $thimbleform_html . '</div>', thimbleform_form_allowed_html() );
