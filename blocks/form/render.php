<?php
/**
 * Dynamic render for nestform/form block.
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

$nestform_form_id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
if ( $nestform_form_id <= 0 || ! class_exists( 'Nestform_Renderer' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="nestform-block nestform-block--empty">' . esc_html__( 'Select a Thimbleform in the block settings.', 'nestform' ) . '</p>';
	}
	return;
}

$nestform_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'nestform-block',
	)
);

$nestform_html = Nestform_Renderer::render( $nestform_form_id );
if ( $nestform_html === '' ) {
	return;
}

echo '<div ' . $nestform_wrapper . '>' . $nestform_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer returns escaped HTML.
