<?php
/**
 * Elementor widget integration.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Elementor {

	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor manager.
	 * @return void
	 */
	public static function register_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$widget = new class() extends \Elementor\Widget_Base {

			public function get_name() {
				return 'nestform';
			}

			public function get_title() {
				return __( 'Thimbleform', 'nestform' );
			}

			public function get_icon() {
				return 'eicon-form-horizontal';
			}

			public function get_categories() {
				return array( 'general' );
			}

			public function get_keywords() {
				return array( 'form', 'contact', 'lead', 'nestform' );
			}

			protected function register_controls() {
				$this->start_controls_section(
					'section_form',
					array(
						'label' => __( 'Form', 'nestform' ),
					)
				);

				$this->add_control(
					'form_id',
					array(
						'label'       => __( 'Select form', 'nestform' ),
						'type'        => \Elementor\Controls_Manager::SELECT,
						'options'     => Nestform_Elementor::form_options(),
						'default'     => '0',
						'label_block' => true,
					)
				);

				$this->end_controls_section();
			}

			protected function render() {
				$settings = $this->get_settings_for_display();
				$form_id  = isset( $settings['form_id'] ) ? (int) $settings['form_id'] : 0;

				if ( $form_id <= 0 || ! class_exists( 'Nestform_Renderer' ) ) {
					if ( current_user_can( 'edit_posts' ) ) {
						echo '<div class="nestform-elementor-placeholder">' . esc_html__( 'Select a Thimbleform in the widget settings.', 'nestform' ) . '</div>';
					}
					return;
				}

				echo Nestform_Renderer::render( $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		};

		$widgets_manager->register( $widget );
	}

	/**
	 * @return array<string, string>
	 */
	public static function form_options() {
		$options = array(
			'0' => __( 'Select a form...', 'nestform' ),
		);

		if ( ! class_exists( 'Nestform_Post_Type' ) ) {
			return $options;
		}

		$forms = get_posts(
			array(
				'post_type'              => Nestform_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $forms as $form ) {
			$title = $form->post_title !== '' ? $form->post_title : sprintf(
				/* translators: %d: form id */
				__( 'Form #%d', 'nestform' ),
				(int) $form->ID
			);
			if ( 'publish' !== $form->post_status ) {
				$title .= ' (' . $form->post_status . ')';
			}
			$options[ (string) (int) $form->ID ] = $title;
		}

		return $options;
	}
}
