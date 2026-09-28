(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var cfg = window.thimbleformBlock || {};
	var forms = Array.isArray(cfg.forms) ? cfg.forms : [];
	var i18n = cfg.i18n || {};

	function formOptions() {
		var opts = [
			{
				label: i18n.selectForm || 'Select a form...',
				value: '0',
			},
		];
		forms.forEach(function (form) {
			opts.push({
				label: form.title || '#' + form.id,
				value: String(form.id),
			});
		});
		return opts;
	}

	function findForm(formId) {
		for (var i = 0; i < forms.length; i++) {
			if (Number(forms[i].id) === Number(formId)) {
				return forms[i];
			}
		}
		return null;
	}

	function FormSelect(formId, setAttributes) {
		return el(SelectControl, {
			label: i18n.formLabel || 'Form',
			value: String(formId || 0),
			options: formOptions(),
			onChange: function (value) {
				setAttributes({ formId: parseInt(value, 10) || 0 });
			},
		});
	}

	registerBlockType('thimbleform/form', {
		edit: function (props) {
			var formId = Number(props.attributes.formId) || 0;
			var selected = findForm(formId);
			var blockProps = useBlockProps({
				className:
					'thimbleform-block-editor' +
					(formId ? ' thimbleform-block-editor--selected' : ''),
			});

			var body = formId
				? el(
						Fragment,
						null,
						el(
							'span',
							{ className: 'thimbleform-block-editor__title' },
							selected
								? selected.title
								: (i18n.formFallback || 'Form #%d').replace('%d', String(formId))
						),
						el(
							'p',
							{ className: 'thimbleform-block-editor__meta' },
							i18n.previewHint || 'The live form renders on the front end.'
						),
						el(
							'div',
							{ className: 'thimbleform-block-editor__picker' },
							FormSelect(formId, props.setAttributes)
						),
						el(
							'code',
							{ className: 'thimbleform-block-editor__code' },
							'[thimbleform id="' + formId + '"]'
						)
					)
				: el(
						Placeholder,
						{
							icon: 'feedback',
							label: i18n.placeholderLabel || 'Thimbleform',
							instructions: i18n.placeholderHelp || 'Choose which form to insert.',
						},
						FormSelect(formId, props.setAttributes)
					);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: i18n.panelTitle || 'Thimbleform',
							initialOpen: true,
						},
						FormSelect(formId, props.setAttributes),
						forms.length === 0
							? el(
									'p',
									{ className: 'description' },
									i18n.noForms ||
										'No forms yet. Create one under Forms in the admin menu.'
								)
							: null
					)
				),
				el('div', blockProps, body)
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp);
