<?php
/**
 * Starter form templates.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Templates {

	const ACTION = 'thimbleform_apply_template';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * @return array<string, array{label:string,description:string,config:array}>
	 */
	public static function all() {
		$admin = get_option( 'admin_email' );
		$admin = is_string( $admin ) ? $admin : '';

		$templates = array(
			'contact'    => array(
				'label'       => __( 'Contact', 'thimbleform' ),
				'description' => __( 'Name, email, phone, message.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'tel',
							'name'     => 'phone',
							'label'    => 'Phone',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'message',
							'label'    => 'Message',
							'required' => true,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send message',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'New contact: {form_title}',
						'reply_to_field'=> 'email',
						'body_template' => "New contact from {form_title}\n\n{all_fields}\n",
					),
				),
			),
			'lead'       => array(
				'label'       => __( 'Lead', 'thimbleform' ),
				'description' => __( 'Company lead capture with service select.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Full name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Work email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'company',
							'label'    => 'Company',
							'required' => false,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'service',
							'label'    => 'Interested in',
							'required' => true,
							'width'    => 'half',
							'options'  => "Consulting\nDesign\nDevelopment\nOther",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'details',
							'label'    => 'Project details',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Request callback',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Lead: {service} — {form_title}',
						'reply_to_field' => 'email',
						'body_template'  => "New lead\n\n{all_fields}\n",
					),
				),
			),
			'quiz'       => array(
				'label'       => __( 'Quiz', 'thimbleform' ),
				'description' => __( 'Scored multi-step quiz with result bands (Pro).', 'thimbleform' ),
				'category'    => 'quiz',
				'requires'    => array( 'quiz_survey', 'multi_step' ),
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'heading',
							'name'     => 'h_about',
							'label'    => 'Quick quiz',
							'options'  => 'h2',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'radio',
							'name'     => 'q1',
							'label'    => 'WordPress is…',
							'required' => true,
							'options'  => "A CMS|10\nA database|0\nA browser|0",
							'step'     => 2,
						),
						array(
							'type'     => 'radio',
							'name'     => 'q2',
							'label'    => 'Best place for forms?',
							'required' => true,
							'options'  => "Thimbleform|10\nSpreadsheets|0\nSticky notes|0",
							'step'     => 2,
						),
						array(
							'type'     => 'radio',
							'name'     => 'q3',
							'label'    => 'You want…',
							'required' => true,
							'options'  => "Leads|5\nQuizzes|10\nBoth|10",
							'step'     => 3,
						),
					),
					'settings' => array(
						'submit_label'       => 'See my score',
						'enable_steps'       => '1',
						'step_labels'        => "Start\nQuestions\nFinish",
						'form_mode'          => 'quiz',
						'quiz_show_score'    => '1',
						'quiz_show_answers'  => '1',
						'share_results'      => '1',
						'quiz_results'       => "0|40|Keep practicing|Review the answers and try again.\n41|70|Nice work|Solid score — share it!\n71|100|Quiz master|You nailed it.",
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Quiz completed',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'newsletter' => array(
				'label'       => __( 'Newsletter', 'thimbleform' ),
				'description' => __( 'Minimal email signup + acceptance.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'acceptance',
							'name'     => 'consent',
							'label'    => 'I agree to receive updates.',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Subscribe',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Newsletter signup',
						'reply_to_field' => 'email',
						'body_template'  => "New subscriber: {email}\n",
					),
				),
			),
			'feedback'   => array(
				'label'       => __( 'Feedback', 'thimbleform' ),
				'description' => __( 'Satisfaction radio, comment, optional email.', 'thimbleform' ),
				'category'    => 'survey',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'radio',
							'name'     => 'rating',
							'label'    => 'How was your experience?',
							'required' => true,
							'options'  => "Great\nOkay\nPoor",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'comment',
							'label'    => 'Tell us more',
							'required' => false,
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email (optional)',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send feedback',
						'enable_steps' => '0',
						'store_ip'     => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Feedback: {rating}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'support'    => array(
				'label'       => __( 'Support', 'thimbleform' ),
				'description' => __( 'Topic select with Other, message, email.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'        => 'select',
							'name'        => 'topic',
							'label'       => 'Topic',
							'required'    => true,
							'options'     => "Billing\nTechnical\nAccount",
							'allow_other' => true,
							'step'        => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'message',
							'label'    => 'How can we help?',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit ticket',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Support: {topic}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'rsvp'       => array(
				'label'       => __( 'Event RSVP', 'thimbleform' ),
				'description' => __( 'Name, guests, dietary preference with Other.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'number',
							'name'     => 'guests',
							'label'    => 'Guests',
							'required' => true,
							'default'  => '1',
							'step'     => 1,
						),
						array(
							'type'        => 'radio',
							'name'        => 'diet',
							'label'       => 'Dietary preference',
							'required'    => false,
							'options'     => "None\nVegetarian\nVegan",
							'allow_other' => true,
							'step'        => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Confirm RSVP',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'RSVP: {name}',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'survey_nps' => array(
				'label'       => __( 'NPS survey', 'thimbleform' ),
				'description' => __( 'NPS + matrix + open comment (Pro).', 'thimbleform' ),
				'category'    => 'survey',
				'requires'    => array( 'quiz_survey', 'advanced_fields' ),
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'nps',
							'name'     => 'nps',
							'label'    => 'How likely are you to recommend us?',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'matrix',
							'name'     => 'experience',
							'label'    => 'Rate your experience',
							'required' => true,
							'options'  => "Support\nProduct\nValue\n---\nPoor\nFair\nGood\nGreat\nExcellent",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'comment',
							'label'    => 'Anything else?',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit survey',
						'form_mode'    => 'survey',
						'store_ip'     => '0',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'Survey response',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'csat'       => array(
				'label'       => __( 'CSAT', 'thimbleform' ),
				'description' => __( 'Customer satisfaction scale + follow-up (Pro).', 'thimbleform' ),
				'category'    => 'survey',
				'requires'    => array( 'quiz_survey', 'advanced_fields' ),
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'scale',
							'name'     => 'satisfaction',
							'label'    => 'Overall satisfaction',
							'required' => true,
							'options'  => "1\n5\nVery dissatisfied\nVery satisfied",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'improve',
							'label'    => 'What could we improve?',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send',
						'form_mode'    => 'survey',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'CSAT response',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'contact_minimal' => array(
				'label'       => __( 'Minimal contact', 'thimbleform' ),
				'description' => __( 'Email and message only.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'message',
							'label'    => 'Message',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Message: {form_title}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'quote_request'   => array(
				'label'       => __( 'Quote request', 'thimbleform' ),
				'description' => __( 'Budget, timeline, project scope.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Full name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'budget',
							'label'    => 'Budget range',
							'required' => true,
							'width'    => 'half',
							'options'  => "Under \$1k\n\$1k–\$5k\n\$5k–\$15k\n\$15k+",
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'timeline',
							'label'    => 'Timeline',
							'required' => true,
							'width'    => 'half',
							'options'  => "ASAP\n1–2 weeks\n1 month\nFlexible",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'details',
							'label'    => 'Project details',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Request quote',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Quote request: {budget}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'request_demo'    => array(
				'label'       => __( 'Request demo', 'thimbleform' ),
				'description' => __( 'Sales demo booking with company size.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Work email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'company',
							'label'    => 'Company',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'team_size',
							'label'    => 'Team size',
							'required' => false,
							'width'    => 'half',
							'options'  => "1–10\n11–50\n51–200\n200+",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'goals',
							'label'    => 'What are you looking to solve?',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Book demo',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Demo request: {company}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'callback'        => array(
				'label'       => __( 'Callback', 'thimbleform' ),
				'description' => __( 'Phone callback with preferred time.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'tel',
							'name'     => 'phone',
							'label'    => 'Phone',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'time',
							'label'    => 'Best time to call',
							'required' => true,
							'options'  => "Morning\nAfternoon\nEvening",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'note',
							'label'    => 'Note (optional)',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Request callback',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'Callback: {name}',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'appointment'     => array(
				'label'       => __( 'Appointment', 'thimbleform' ),
				'description' => __( 'Book a visit with date and service.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'tel',
							'name'     => 'phone',
							'label'    => 'Phone',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'service',
							'label'    => 'Service',
							'required' => true,
							'width'    => 'half',
							'options'  => "Consultation\nFollow-up\nNew client",
							'step'     => 1,
						),
						array(
							'type'     => 'date',
							'name'     => 'preferred_date',
							'label'    => 'Preferred date',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'time',
							'name'     => 'preferred_time',
							'label'    => 'Preferred time',
							'required' => false,
							'width'    => 'half',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Book appointment',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Appointment: {preferred_date}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'job_application' => array(
				'label'       => __( 'Job application', 'thimbleform' ),
				'description' => __( 'Role, experience, resume upload.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Full name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'role',
							'label'    => 'Role applying for',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'url',
							'name'     => 'portfolio',
							'label'    => 'Portfolio or LinkedIn',
							'required' => false,
							'step'     => 1,
						),
						array(
							'type'     => 'file',
							'name'     => 'resume',
							'label'    => 'Resume (PDF)',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'cover',
							'label'    => 'Cover letter',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit application',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Application: {role}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'registration'    => array(
				'label'       => __( 'Event registration', 'thimbleform' ),
				'description' => __( 'Sign up with ticket type and dietary needs.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'ticket',
							'label'    => 'Ticket type',
							'required' => true,
							'options'  => "General\nVIP\nStudent",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'dietary',
							'label'    => 'Dietary requirements',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Register',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Registration: {ticket}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'waitlist'        => array(
				'label'       => __( 'Waitlist', 'thimbleform' ),
				'description' => __( 'Join waitlist with email and interest.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'interest',
							'label'    => 'Interested in',
							'required' => true,
							'options'  => "Early access\nBeta\nLaunch notify",
							'step'     => 1,
						),
						array(
							'type'     => 'acceptance',
							'name'     => 'consent',
							'label'    => 'Notify me when spots open.',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Join waitlist',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Waitlist signup',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'donation'        => array(
				'label'       => __( 'Donation pledge', 'thimbleform' ),
				'description' => __( 'Amount intent and optional message.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'amount',
							'label'    => 'Amount',
							'required' => true,
							'options'  => "\$25\n\$50\n\$100\n\$250\nOther",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'message',
							'label'    => 'Message (optional)',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Pledge support',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Donation pledge: {amount}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'volunteer'       => array(
				'label'       => __( 'Volunteer', 'thimbleform' ),
				'description' => __( 'Availability and skills for volunteers.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'checkboxes',
							'name'     => 'skills',
							'label'    => 'Skills',
							'required' => true,
							'options'  => "Events\nMarketing\nTech\nLogistics",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'availability',
							'label'    => 'Availability',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Sign up',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Volunteer: {name}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'partnership'     => array(
				'label'       => __( 'Partnership', 'thimbleform' ),
				'description' => __( 'B2B partnership inquiry.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'company',
							'label'    => 'Company',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'contact',
							'label'    => 'Contact name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'url',
							'name'     => 'website',
							'label'    => 'Website',
							'required' => false,
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'proposal',
							'label'    => 'Partnership idea',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send inquiry',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Partnership: {company}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'product_inquiry' => array(
				'label'       => __( 'Product inquiry', 'thimbleform' ),
				'description' => __( 'Ask about a product with SKU or link.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'product',
							'label'    => 'Product name or SKU',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'number',
							'name'     => 'quantity',
							'label'    => 'Quantity',
							'required' => false,
							'default'  => '1',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'question',
							'label'    => 'Question',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send inquiry',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Product inquiry: {product}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'bug_report'      => array(
				'label'       => __( 'Bug report', 'thimbleform' ),
				'description' => __( 'Steps to reproduce and severity.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'summary',
							'label'    => 'Summary',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'severity',
							'label'    => 'Severity',
							'required' => true,
							'options'  => "Low\nMedium\nHigh\nCritical",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'steps',
							'label'    => 'Steps to reproduce',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'url',
							'name'     => 'page_url',
							'label'    => 'Page URL',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Report bug',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Bug: {severity} — {summary}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'maintenance'     => array(
				'label'       => __( 'Maintenance request', 'thimbleform' ),
				'description' => __( 'Facility issue with urgency level.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Your name',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'location',
							'label'    => 'Location / unit',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'urgency',
							'label'    => 'Urgency',
							'required' => true,
							'options'  => "Low\nNormal\nUrgent",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'issue',
							'label'    => 'Describe the issue',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit request',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'Maintenance [{urgency}]: {location}',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'vendor_inquiry'  => array(
				'label'       => __( 'Vendor inquiry', 'thimbleform' ),
				'description' => __( 'Supplier onboarding questionnaire.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'company',
							'label'    => 'Company name',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'contact',
							'label'    => 'Contact person',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'category',
							'label'    => 'Category',
							'required' => true,
							'options'  => "Software\nHardware\nServices\nOther",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'offer',
							'label'    => 'What do you offer?',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Vendor: {company}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'referral'        => array(
				'label'       => __( 'Referral', 'thimbleform' ),
				'description' => __( 'Refer someone with contact details.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'your_name',
							'label'    => 'Your name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'your_email',
							'label'    => 'Your email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'referral_name',
							'label'    => 'Referral name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'referral_email',
							'label'    => 'Referral email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'note',
							'label'    => 'Why are you referring them?',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send referral',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Referral from {your_name}',
						'reply_to_field' => 'your_email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'employee_feedback' => array(
				'label'       => __( 'Employee feedback', 'thimbleform' ),
				'description' => __( 'Anonymous-style team pulse check.', 'thimbleform' ),
				'category'    => 'survey',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'radio',
							'name'     => 'morale',
							'label'    => 'Team morale this week',
							'required' => true,
							'options'  => "Great\nGood\nNeutral\nLow",
							'step'     => 1,
						),
						array(
							'type'     => 'radio',
							'name'     => 'workload',
							'label'    => 'Workload feels',
							'required' => true,
							'options'  => "Light\nBalanced\nHeavy\nOverloaded",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'comments',
							'label'    => 'Comments (optional)',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit',
						'enable_steps' => '0',
						'store_ip'     => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'Employee feedback',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'order_form'      => array(
				'label'       => __( 'Simple order', 'thimbleform' ),
				'description' => __( 'Product pick, qty, shipping address.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'product',
							'label'    => 'Product',
							'required' => true,
							'options'  => "Starter kit\nPro bundle\nCustom",
							'step'     => 1,
						),
						array(
							'type'     => 'number',
							'name'     => 'quantity',
							'label'    => 'Quantity',
							'required' => true,
							'default'  => '1',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'address',
							'label'    => 'Shipping address',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Place order',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Order: {product}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'onboarding'      => array(
				'label'       => __( 'Client onboarding', 'thimbleform' ),
				'description' => __( 'New client intake with brand assets.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'heading',
							'name'     => 'h_welcome',
							'label'    => 'Welcome aboard',
							'options'  => 'h2',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'company',
							'label'    => 'Company',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'contact',
							'label'    => 'Primary contact',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'url',
							'name'     => 'website',
							'label'    => 'Website',
							'required' => false,
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'goals',
							'label'    => 'Project goals',
							'required' => true,
							'step'     => 1,
						),
						array(
							'type'     => 'file',
							'name'     => 'assets',
							'label'    => 'Brand assets (optional)',
							'required' => false,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit intake',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Onboarding: {company}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'survey_quick'    => array(
				'label'       => __( 'Quick survey', 'thimbleform' ),
				'description' => __( 'Three multiple-choice questions.', 'thimbleform' ),
				'category'    => 'survey',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'radio',
							'name'     => 'q1',
							'label'    => 'How did you hear about us?',
							'required' => true,
							'options'  => "Search\nSocial\nReferral\nOther",
							'step'     => 1,
						),
						array(
							'type'     => 'radio',
							'name'     => 'q2',
							'label'    => 'How often do you visit?',
							'required' => true,
							'options'  => "First time\nMonthly\nWeekly\nDaily",
							'step'     => 1,
						),
						array(
							'type'     => 'radio',
							'name'     => 'q3',
							'label'    => 'Would you recommend us?',
							'required' => true,
							'options'  => "Yes\nMaybe\nNo",
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit',
						'form_mode'    => 'survey',
						'store_ip'     => '0',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'            => $admin,
						'subject'       => 'Survey response',
						'body_template' => "{all_fields}\n",
					),
				),
			),
			'restaurant_reservation' => array(
				'label'       => __( 'Restaurant reservation', 'thimbleform' ),
				'description' => __( 'Table booking with date, time, and party size.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'tel',
							'name'     => 'phone',
							'label'    => 'Phone',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'number',
							'name'     => 'guests',
							'label'    => 'Guests',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'date',
							'name'     => 'date',
							'label'    => 'Date',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'time',
							'name'     => 'time',
							'label'    => 'Time',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'notes',
							'label'    => 'Special requests (optional)',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Reserve table',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Reservation: {date} · {guests} guests',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'product_review' => array(
				'label'       => __( 'Product review', 'thimbleform' ),
				'description' => __( 'Star rating, product name, and written review.', 'thimbleform' ),
				'category'    => 'survey',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'product',
							'label'    => 'Product name',
							'required' => true,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'radio',
							'name'     => 'rating',
							'label'    => 'Your rating',
							'required' => true,
							'options'  => "5 — Excellent\n4 — Good\n3 — Average\n2 — Poor\n1 — Terrible",
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'title',
							'label'    => 'Review title',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'review',
							'label'    => 'Your review',
							'required' => true,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email (optional)',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit review',
						'form_mode'    => 'survey',
						'store_ip'     => '0',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Review: {product} ({rating})',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'giveaway' => array(
				'label'       => __( 'Giveaway / contest', 'thimbleform' ),
				'description' => __( 'Contest entry with email and consent.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'handle',
							'label'    => 'Social handle (optional)',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'heard_from',
							'label'    => 'How did you find this giveaway?',
							'required' => false,
							'width'    => 'full',
							'options'  => "Instagram\nFacebook\nEmail\nFriend\nOther",
							'step'     => 1,
						),
						array(
							'type'     => 'acceptance',
							'name'     => 'rules',
							'label'    => 'I agree to the contest rules and privacy policy.',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Enter giveaway',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Giveaway entry: {email}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'gdpr_request' => array(
				'label'       => __( 'GDPR data request', 'thimbleform' ),
				'description' => __( 'Access, export, or delete personal data.', 'thimbleform' ),
				'category'    => 'contact',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email on file',
							'required' => true,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'request_type',
							'label'    => 'Request type',
							'required' => true,
							'width'    => 'full',
							'options'  => "Access my data\nExport my data\nCorrect my data\nDelete my data\nOther",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'details',
							'label'    => 'Additional details',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'acceptance',
							'name'     => 'identity',
							'label'    => 'I confirm I am the account holder for this email.',
							'required' => true,
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit request',
						'enable_steps' => '0',
						'store_ip'     => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'GDPR: {request_type}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'multi_step_intake' => array(
				'label'       => __( 'Multi-step intake', 'thimbleform' ),
				'description' => __( 'Contact, project scope, then files (Pro).', 'thimbleform' ),
				'category'    => 'lead',
				'requires'    => array( 'multi_step' ),
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'heading',
							'name'     => 'h_about',
							'label'    => 'About you',
							'options'  => 'h2',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Full name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'company',
							'label'    => 'Company',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'heading',
							'name'     => 'h_project',
							'label'    => 'Project',
							'options'  => 'h2',
							'step'     => 2,
						),
						array(
							'type'     => 'select',
							'name'     => 'service',
							'label'    => 'What do you need?',
							'required' => true,
							'width'    => 'half',
							'options'  => "Website\nBrand\nApp\nOther",
							'step'     => 2,
						),
						array(
							'type'     => 'select',
							'name'     => 'budget',
							'label'    => 'Budget range',
							'required' => false,
							'width'    => 'half',
							'options'  => "Under $5k\n$5k–$15k\n$15k–$50k\n$50k+",
							'step'     => 2,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'goals',
							'label'    => 'Goals and context',
							'required' => true,
							'width'    => 'full',
							'step'     => 2,
						),
						array(
							'type'     => 'heading',
							'name'     => 'h_files',
							'label'    => 'Files',
							'options'  => 'h2',
							'step'     => 3,
						),
						array(
							'type'     => 'file',
							'name'     => 'brief',
							'label'    => 'Brief or references (optional)',
							'required' => false,
							'width'    => 'full',
							'step'     => 3,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'notes',
							'label'    => 'Anything else?',
							'required' => false,
							'width'    => 'full',
							'step'     => 3,
						),
					),
					'settings' => array(
						'submit_label' => 'Submit intake',
						'enable_steps' => '1',
						'step_labels'  => "About you\nProject\nFiles",
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Intake: {name} — {service}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'real_estate' => array(
				'label'       => __( 'Real estate inquiry', 'thimbleform' ),
				'description' => __( 'Property type, budget, and contact details.', 'thimbleform' ),
				'category'    => 'lead',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Full name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'tel',
							'name'     => 'phone',
							'label'    => 'Phone',
							'required' => false,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'looking_for',
							'label'    => 'Looking to',
							'required' => true,
							'width'    => 'half',
							'options'  => "Buy\nRent\nSell\nInvest",
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'property_type',
							'label'    => 'Property type',
							'required' => true,
							'width'    => 'half',
							'options'  => "Apartment\nHouse\nCondo\nCommercial\nLand",
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'budget',
							'label'    => 'Budget',
							'required' => false,
							'width'    => 'half',
							'options'  => "Under $250k\n$250k–$500k\n$500k–$1M\n$1M+",
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'location',
							'label'    => 'Preferred area',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'notes',
							'label'    => 'Notes',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send inquiry',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Real estate: {looking_for} · {property_type}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'course_enrollment' => array(
				'label'       => __( 'Course enrollment', 'thimbleform' ),
				'description' => __( 'Sign up for a class with experience level.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Full name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'course',
							'label'    => 'Course',
							'required' => true,
							'width'    => 'half',
							'options'  => "Intro\nIntermediate\nAdvanced\nWorkshop",
							'step'     => 1,
						),
						array(
							'type'     => 'select',
							'name'     => 'experience',
							'label'    => 'Experience level',
							'required' => true,
							'width'    => 'half',
							'options'  => "Beginner\nSome experience\nAdvanced",
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'goals',
							'label'    => 'What do you want to learn?',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Enroll',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Enrollment: {course} — {name}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
			'content_pitch' => array(
				'label'       => __( 'Content tip / pitch', 'thimbleform' ),
				'description' => __( 'Guest post or story pitch with link.', 'thimbleform' ),
				'category'    => 'other',
				'config'      => array(
					'fields'   => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'Your name',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'email',
							'name'     => 'email',
							'label'    => 'Email',
							'required' => true,
							'width'    => 'half',
							'step'     => 1,
						),
						array(
							'type'     => 'text',
							'name'     => 'title',
							'label'    => 'Proposed title',
							'required' => true,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'url',
							'name'     => 'link',
							'label'    => 'Portfolio or draft URL',
							'required' => false,
							'width'    => 'full',
							'step'     => 1,
						),
						array(
							'type'     => 'textarea',
							'name'     => 'pitch',
							'label'    => 'Pitch / summary',
							'required' => true,
							'width'    => 'full',
							'step'     => 1,
						),
					),
					'settings' => array(
						'submit_label' => 'Send pitch',
						'enable_steps' => '0',
					),
					'mail'     => array(
						'to'             => $admin,
						'subject'        => 'Pitch: {title}',
						'reply_to_field' => 'email',
						'body_template'  => "{all_fields}\n",
					),
				),
			),
		);

		/**
		 * Filter starter form templates (addons may register job application, etc.).
		 *
		 * @param array<string, array{label:string,description:string,config:array}> $templates Templates.
		 */
		return apply_filters( 'thimbleform_templates', $templates );
	}

	/**
	 * @param int    $form_id Form ID.
	 * @param string $key     Template key.
	 * @return bool
	 */
	public static function apply( $form_id, $key ) {
		$form_id = (int) $form_id;
		$all     = self::all();
		if ( $form_id <= 0 || ! isset( $all[ $key ] ) ) {
			return false;
		}
		if ( ! self::template_allowed( $key ) ) {
			return false;
		}
		$tpl     = $all[ $key ]['config'];
		$current = Thimbleform_Form_Config::get( $form_id );
		$config  = array(
			'fields'   => $tpl['fields'],
			'messages' => $current['messages'],
			'mail'     => array_merge( $current['mail'], $tpl['mail'] ),
			'settings' => array_merge( $current['settings'], $tpl['settings'] ),
		);
		Thimbleform_Form_Config::save( $form_id, $config );
		/**
		 * After a starter template is applied to a form.
		 *
		 * @param int    $form_id Form ID.
		 * @param string $key     Template key.
		 */
		do_action( 'thimbleform_template_applied', $form_id, $key );
		return true;
	}

	/**
	 * Whether a template may be applied with current capabilities.
	 *
	 * Free never lists or applies Pro templates (explicit requires + inferred from config).
	 *
	 * @param string $key Template key.
	 * @return bool
	 */
	public static function template_allowed( $key ) {
		$all = self::all();
		if ( ! isset( $all[ $key ] ) || ! is_array( $all[ $key ] ) ) {
			return false;
		}
		foreach ( self::required_features_for_template( $all[ $key ] ) as $feature ) {
			if ( ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::can( (string) $feature ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Feature keys a template needs (declared + inferred from fields/settings).
	 *
	 * @param array<string, mixed> $tpl Template row.
	 * @return array<int, string>
	 */
	public static function required_features_for_template( array $tpl ) {
		$features = array();
		if ( isset( $tpl['requires'] ) && is_array( $tpl['requires'] ) ) {
			foreach ( $tpl['requires'] as $feature ) {
				$feature = sanitize_key( (string) $feature );
				if ( '' !== $feature ) {
					$features[] = $feature;
				}
			}
		}

		$config   = isset( $tpl['config'] ) && is_array( $tpl['config'] ) ? $tpl['config'] : array();
		$settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();
		$fields   = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : array();

		if ( ! empty( $settings['enable_steps'] ) && '1' === (string) $settings['enable_steps'] ) {
			$features[] = Thimbleform_Features::MULTI_STEP;
		}

		$form_mode = isset( $settings['form_mode'] ) ? sanitize_key( (string) $settings['form_mode'] ) : 'form';
		if ( in_array( $form_mode, array( 'quiz', 'survey' ), true ) ) {
			// Survey mode is free for simple templates; quiz scoring needs Pro.
			if ( 'quiz' === $form_mode ) {
				$features[] = Thimbleform_Features::QUIZ_SURVEY;
			}
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
			if ( '' === $type || ! class_exists( 'Thimbleform_Features' ) || ! Thimbleform_Features::is_pro_field_type( $type ) ) {
				continue;
			}
			$cap = Thimbleform_Features::capability_for_field_type( $type );
			if ( '' !== $cap ) {
				$features[] = $cap;
			}
		}

		return array_values( array_unique( $features ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @param string $key Template key.
	 * @return string
	 */
	public static function url( $form_id, $key ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'form_id' => (int) $form_id,
					'template'=> sanitize_key( $key ),
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . (int) $form_id
		);
	}

	public static function handle() {
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
		$key     = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		if ( $form_id <= 0 || Thimbleform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'thimbleform' ), 400 );
		}
		check_admin_referer( self::ACTION . '_' . $form_id );
		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this form.', 'thimbleform' ), 403 );
		}
		if ( ! self::apply( $form_id, $key ) ) {
			wp_die( esc_html__( 'Unknown template.', 'thimbleform' ), 400 );
		}
		wp_safe_redirect( get_edit_post_link( $form_id, 'raw' ) );
		exit;
	}
}
