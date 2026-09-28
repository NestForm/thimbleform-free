/**
 * Thimbleform front: custom select + AJAX submit + CustomEvents.
 *
 * Events (bubble from <form data-thimbleform>, cancelable where noted):
 * - thimbleform:ready
 * - thimbleform:before-submit       (cancelable  --  before client validation)
 * - thimbleform:validation-error
 * - thimbleform:submit              (cancelable  --  after validation + captcha, before fetch; detail.formData is the payload)
 * - thimbleform:success             (detail.values = submitted fields; fired before form reset)
 * - thimbleform:error
 * - thimbleform:network-error
 * - thimbleform:redirect            (cancelable  --  stop location change)
 * - thimbleform:before-step-change  (cancelable  --  before multi-step index changes)
 * - thimbleform:step-change
 * - thimbleform:select-change
 * - thimbleform:reset
 */
(function () {
	'use strict';

	function formIdOf(form) {
		return parseInt(form.getAttribute('data-form-id') || '0', 10) || 0;
	}

	/**
	 * Toggle submit loader UI (spinner + aria-busy + disabled actions).
	 *
	 * @param {HTMLFormElement} form
	 * @param {boolean} on
	 */
	function setSubmitting(form, on) {
		var busy = !!on;
		form.classList.toggle('is-submitting', busy);
		form.setAttribute('aria-busy', busy ? 'true' : 'false');

		var i18n = (window.thimbleform && window.thimbleform.i18n) || {};
		var submit = form.querySelector('.thimbleform__submit');
		if (submit) {
			submit.disabled = busy;
			submit.setAttribute('aria-busy', busy ? 'true' : 'false');
			if (busy) {
				if (!submit.getAttribute('data-thimbleform-label-idle')) {
					submit.setAttribute(
						'data-thimbleform-label-idle',
						submit.getAttribute('aria-label') || ''
					);
				}
				submit.setAttribute('aria-label', i18n.submitting || 'Sending…');
			} else {
				var idle = submit.getAttribute('data-thimbleform-label-idle');
				if (idle) {
					submit.setAttribute('aria-label', idle);
				} else {
					submit.removeAttribute('aria-label');
				}
				submit.removeAttribute('data-thimbleform-label-idle');
			}
		}

		form.querySelectorAll('.thimbleform__next, .thimbleform__prev').forEach(function (btn) {
			btn.disabled = busy;
		});
	}

	/**
	 * Plain object snapshot of FormData (multi-value keys become arrays).
	 *
	 * @param {HTMLFormElement} form
	 * @return {Object<string, *>}
	 */
	function formValuesOf(form) {
		var data = new FormData(form);
		var out = {};
		data.forEach(function (value, key) {
			if (Object.prototype.hasOwnProperty.call(out, key)) {
				if (!Array.isArray(out[key])) {
					out[key] = [out[key]];
				}
				out[key].push(value);
			} else {
				out[key] = value;
			}
		});
		return out;
	}

	/**
	 * Client-side guard before location change (server already sanitizes).
	 * Allows relative paths and http(s) only  --  blocks javascript:/data:.
	 *
	 * @param {string} url
	 * @return {boolean}
	 */
	function isSafeRedirectUrl(url) {
		url = String(url || '').trim();
		if (!url) {
			return false;
		}
		if (url.charAt(0) === '/' && url.charAt(1) !== '/') {
			return true;
		}
		try {
			var parsed = new URL(url, window.location.href);
			return parsed.protocol === 'http:' || parsed.protocol === 'https:';
		} catch (err) {
			return false;
		}
	}

	/**
	 * @param {HTMLFormElement} form
	 * @param {string} name
	 * @param {object} [detail]
	 * @param {boolean} [cancelable]
	 * @return {boolean} false if canceled
	 */
	function emit(form, name, detail, cancelable) {
		if (!form) {
			return true;
		}
		detail = detail || {};
		if (detail.formId == null) {
			detail.formId = formIdOf(form);
		}
		detail.form = form;
		return form.dispatchEvent(
			new CustomEvent(name, {
				bubbles: true,
				cancelable: !!cancelable,
				detail: detail,
			})
		);
	}

	function setFieldInvalid(wrap, on) {
		wrap.classList.toggle('thimbleform__field--invalid', on);
		wrap.querySelectorAll('.input, .textarea, .select, .thimbleform__input, .thimbleform-select__trigger').forEach(function (el) {
			el.setAttribute('aria-invalid', on ? 'true' : 'false');
			if (el.classList.contains('textarea') || el.classList.contains('thimbleform__textarea')) {
				el.classList.toggle('textarea--error', on);
				el.classList.toggle('input--error', on);
			} else if (el.classList.contains('select') || el.classList.contains('thimbleform-select__trigger')) {
				el.classList.toggle('select--error', on);
				el.classList.toggle('input--error', on);
			} else {
				el.classList.toggle('input--error', on);
			}
		});
	}

	function clearErrors(form) {
		form.querySelectorAll('[data-field-name]').forEach(function (wrap) {
			setFieldInvalid(wrap, false);
			var err = wrap.querySelector('[data-thimbleform-error]');
			var errId = err ? err.id : '';
			if (err) {
				err.hidden = true;
				err.textContent = '';
			}
			if (errId) {
				wrap.querySelectorAll('[aria-describedby="' + errId + '"]').forEach(function (el) {
					el.removeAttribute('aria-describedby');
				});
			}
		});
	}

	function showFieldErrors(form, errors) {
		var first = null;
		Object.keys(errors || {}).forEach(function (name) {
			var wrap = form.querySelector('[data-field-name="' + name + '"]');
			if (!wrap) {
				return;
			}
			setFieldInvalid(wrap, true);
			var err = wrap.querySelector('[data-thimbleform-error]');
			if (err) {
				err.hidden = false;
				err.textContent = errors[name];
				wrap.querySelectorAll('.thimbleform__input, .thimbleform__textarea, .thimbleform__checkbox, .thimbleform-select__trigger').forEach(function (el) {
					if (err.id) {
						el.setAttribute('aria-describedby', err.id);
					}
				});
			}
			if (!first) {
				first =
					wrap.querySelector('[data-thimbleform-select-trigger]') ||
					wrap.querySelector(
						'.thimbleform__input, .thimbleform__textarea, .thimbleform-select__native, .thimbleform__checkbox'
					);
			}
		});
		if (first && typeof first.focus === 'function') {
			first.focus({ preventScroll: false });
		}
	}

	function setStatus(form, message, type) {
		var status = form.querySelector('[data-thimbleform-status]');
		if (!status) {
			return;
		}
		status.hidden = !message;
		status.textContent = message || '';
		status.classList.remove('thimbleform__status--success', 'thimbleform__status--error');
		if (type) {
			status.classList.add('thimbleform__status--' + type);
		}
		if (type === 'error') {
			status.setAttribute('role', 'alert');
		} else {
			status.setAttribute('role', 'status');
		}
	}

	function successDisplay(form) {
		var mode = (form.getAttribute('data-success-display') || 'inline').toLowerCase();
		if (mode === 'replace' || mode === 'popup') {
			return mode;
		}
		return 'inline';
	}

	function clearSuccessUi(form) {
		form.classList.remove('thimbleform--success-replace');
		var result = form.querySelector('[data-thimbleform-result]');
		if (result) {
			result.hidden = true;
			result.textContent = '';
			result.classList.remove('thimbleform__result--success');
		}
		var modal = form.querySelector('[data-thimbleform-success-modal]');
		if (modal) {
			modal.remove();
		}
	}

	function showSuccessMessage(form, message) {
		var mode = successDisplay(form);
		clearSuccessUi(form);

		if (mode === 'replace') {
			setStatus(form, '', '');
			form.classList.add('thimbleform--success-replace');
			var result = form.querySelector('[data-thimbleform-result]');
			if (result) {
				result.hidden = false;
				result.textContent = message || '';
				result.classList.add('thimbleform__result--success');
				result.setAttribute('role', 'status');
				result.setAttribute('tabindex', '-1');
				if (typeof result.focus === 'function') {
					result.focus({ preventScroll: false });
				}
			} else {
				setStatus(form, message, 'success');
			}
			return;
		}

		if (mode === 'popup') {
			setStatus(form, '', '');
			var i18n = (window.thimbleform && window.thimbleform.i18n) || {};
			var overlay = document.createElement('div');
			overlay.className = 'thimbleform-success-modal';
			overlay.setAttribute('data-thimbleform-success-modal', '');
			overlay.setAttribute('role', 'dialog');
			overlay.setAttribute('aria-modal', 'true');
			overlay.setAttribute('aria-label', i18n.successTitle || 'Thank you');

			var dialog = document.createElement('div');
			dialog.className = 'thimbleform-success-modal__dialog';

			var title = document.createElement('p');
			title.className = 'thimbleform-success-modal__title';
			title.textContent = i18n.successTitle || 'Thank you';

			var body = document.createElement('p');
			body.className = 'thimbleform-success-modal__body';
			body.textContent = message || '';

			var closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.className = 'button button--primary thimbleform-success-modal__close';
			closeBtn.textContent = i18n.close || 'Close';

			function closeModal() {
				document.removeEventListener('keydown', onKey);
				if (overlay.parentNode) {
					overlay.parentNode.removeChild(overlay);
				}
			}

			function onKey(event) {
				if (event.key === 'Escape') {
					closeModal();
				}
			}

			closeBtn.addEventListener('click', closeModal);
			overlay.addEventListener('click', function (event) {
				if (event.target === overlay) {
					closeModal();
				}
			});
			document.addEventListener('keydown', onKey);

			dialog.appendChild(title);
			dialog.appendChild(body);
			dialog.appendChild(closeBtn);
			overlay.appendChild(dialog);
			form.appendChild(overlay);
			closeBtn.focus({ preventScroll: false });
			return;
		}

		setStatus(form, message, 'success');
	}

	function formMsg(form, key, fallback) {
		if (!form) {
			return fallback || '';
		}
		var map = {
			required: 'data-msg-required',
			invalid_email: 'data-msg-invalid-email',
			invalid_tel: 'data-msg-invalid-tel',
			invalid_url: 'data-msg-invalid-url',
			invalid_number: 'data-msg-invalid-number',
			invalid_date: 'data-msg-invalid-date',
			invalid_time: 'data-msg-invalid-time',
			invalid_file: 'data-msg-invalid-file',
			file_too_large: 'data-msg-file-too-large',
			too_many_files: 'data-msg-too-many-files',
			error_generic: 'data-error-generic',
		};
		var attr = map[key];
		var val = attr ? form.getAttribute(attr) : '';
		return (val && String(val).trim()) || fallback || '';
	}

	function clientHints(form, opts) {
		opts = opts || {};
		var stepOnly = opts.step != null ? String(opts.step) : null;
		var errors = {};
		form.querySelectorAll('[data-field-name]').forEach(function (wrap) {
			if (wrap.hasAttribute('data-thimbleform-layout')) {
				return;
			}
			if (wrap.classList.contains('thimbleform__field--condition-hidden')) {
				return;
			}
			/* Stripe Payment Element fills the intent after clientHints — validate in thimbleformEnsurePayments. */
			if (wrap.hasAttribute('data-thimbleform-payment') || wrap.getAttribute('data-field-type') === 'payment') {
				return;
			}
			var name = wrap.getAttribute('data-field-name');
			if (!name) {
				return;
			}
			if (stepOnly !== null) {
				var fieldStep = wrap.getAttribute('data-field-step') || '1';
				if (fieldStep !== stepOnly) {
					return;
				}
			}

			var custom = wrap.querySelector('[data-thimbleform-select]');
			if (custom) {
				var native = custom.querySelector('[data-thimbleform-select-native]');
				if (native && native.required && !String(native.value || '').trim()) {
					errors[name] = formMsg(form, 'required', 'This field is required.');
				}
				return;
			}

			var choices = wrap.querySelector('[data-thimbleform-choices]');
			if (choices) {
				if (choices.getAttribute('data-required') === '1') {
					var anyChecked = !!choices.querySelector('input:checked');
					if (!anyChecked) {
						errors[name] = formMsg(form, 'required', 'This field is required.');
					}
				}
				return;
			}

			var input = wrap.querySelector(
				'.thimbleform__input, .thimbleform__textarea, .thimbleform__checkbox, .thimbleform__file, .thimbleform__range'
			);
			if (!input || input.tagName === 'BUTTON') {
				return;
			}

			var msg = validateControl(form, input);
			if (msg) {
				errors[name] = msg;
			}
		});
		return errors;
	}

	/* ---------- Conditional show/hide ---------- */

	function readWatchValue(form, name) {
		var wrap = form.querySelector('[data-field-name="' + name + '"]');
		if (!wrap) {
			var loose = form.elements[name];
			if (!loose) {
				return '';
			}
			if (loose instanceof RadioNodeList || (loose.length && loose[0] && loose[0].name === name)) {
				var picked = '';
				Array.prototype.forEach.call(loose, function (el) {
					if (el.checked) {
						picked = el.value;
					}
				});
				return String(picked || '');
			}
			if (loose.type === 'checkbox') {
				return loose.checked ? '1' : '';
			}
			return String(loose.value || '').trim();
		}

		var checks = wrap.querySelectorAll('input[type="checkbox"]');
		if (checks.length > 1) {
			var parts = [];
			checks.forEach(function (el) {
				if (el.checked) {
					parts.push(el.value || '1');
				}
			});
			return parts.join(', ');
		}
		if (checks.length === 1) {
			return checks[0].checked ? '1' : '';
		}

		var radios = wrap.querySelectorAll('input[type="radio"]');
		if (radios.length) {
			var radioVal = '';
			radios.forEach(function (el) {
				if (el.checked) {
					radioVal = el.value;
				}
			});
			return String(radioVal || '');
		}

		var phoneVal = wrap.querySelector('[data-thimbleform-phone-value]');
		if (phoneVal) {
			return String(phoneVal.value || '').trim();
		}

		var select = wrap.querySelector('select');
		if (select) {
			return String(select.value || '').trim();
		}

		var input = wrap.querySelector(
			'.thimbleform__input, .thimbleform__textarea, .thimbleform__range, input, textarea'
		);
		if (!input) {
			return '';
		}
		return String(input.value || '').trim();
	}

	function conditionPasses(wrap, form) {
		var watch = wrap.getAttribute('data-condition-field');
		if (!watch) {
			return true;
		}
		var op = wrap.getAttribute('data-condition-op') || 'equals';
		var want = wrap.getAttribute('data-condition-value') || '';
		var value = readWatchValue(form, watch);

		if (op === 'empty') {
			return value === '';
		}
		if (op === 'not_empty') {
			return value !== '';
		}
		if (op === 'not_equals') {
			return value.toLowerCase() !== String(want).toLowerCase();
		}
		if (op === 'contains') {
			if (!want) {
				return true;
			}
			return value.toLowerCase().indexOf(String(want).toLowerCase()) !== -1;
		}
		return value.toLowerCase() === String(want).toLowerCase();
	}

	function setConditionRequired(wrap, enabled) {
		wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
			if (!el.hasAttribute('data-thimbleform-was-required') && el.required) {
				el.setAttribute('data-thimbleform-was-required', '1');
			}
			if (el.hasAttribute('data-thimbleform-was-required')) {
				el.required = !!enabled;
			}
		});
		var choices = wrap.querySelector('[data-thimbleform-choices]');
		if (choices && choices.hasAttribute('data-required')) {
			if (!choices.hasAttribute('data-thimbleform-was-req-flag')) {
				choices.setAttribute('data-thimbleform-was-req-flag', choices.getAttribute('data-required') || '0');
			}
			choices.setAttribute('data-required', enabled ? choices.getAttribute('data-thimbleform-was-req-flag') || '0' : '0');
		}
	}

	function applyConditions(form) {
		form.querySelectorAll('[data-condition-field]').forEach(function (wrap) {
			var show = conditionPasses(wrap, form);
			wrap.classList.toggle('thimbleform__field--condition-hidden', !show);
			wrap.setAttribute('aria-hidden', show ? 'false' : 'true');
			setConditionRequired(wrap, show);
		});
	}

	function initConditions(form) {
		if (!form.querySelector('[data-condition-field]')) {
			return;
		}
		applyConditions(form);
		form.addEventListener('change', function () {
			applyConditions(form);
		});
		form.addEventListener('input', function () {
			applyConditions(form);
		});
		form.addEventListener('thimbleform:select-change', function () {
			applyConditions(form);
		});
		form.addEventListener('thimbleform:step-change', function () {
			applyConditions(form);
		});
	}

	/**
	 * Manual validation  --  do not rely on checkValidity() alone:
	 * fields inside [hidden] step panels are barred from constraint validation.
	 * Messages come from form settings (not browser locale).
	 */
	function validateControl(form, input) {
		var type = String(input.type || input.tagName || '').toLowerCase();
		var required = !!input.required;
		var raw = input.value == null ? '' : String(input.value);
		var val = raw.trim();

		if (type === 'checkbox') {
			if (required && !input.checked) {
				return formMsg(form, 'required', 'This field is required.');
			}
			return '';
		}

		if (required && val === '') {
			return formMsg(form, 'required', 'This field is required.');
		}
		if (val === '') {
			return '';
		}

		if (type === 'email') {
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || /\s/.test(val)) {
				return formMsg(form, 'invalid_email', 'Please enter a valid email address.');
			}
		} else if (type === 'url') {
			if (!/^https?:\/\/\S+/i.test(val)) {
				return formMsg(form, 'invalid_url', 'Please enter a valid URL.');
			}
		} else if (type === 'number') {
			if (isNaN(Number(val))) {
				return formMsg(form, 'invalid_number', 'Please enter a valid number.');
			}
		} else if (type === 'date') {
			if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) {
				return formMsg(form, 'invalid_date', 'Please enter a valid date.');
			}
		} else if (type === 'time') {
			if (!/^\d{2}:\d{2}(:\d{2})?$/.test(val)) {
				return formMsg(form, 'invalid_time', 'Please enter a valid time.');
			}
		} else if (type === 'tel') {
			var digits = val.replace(/\D+/g, '');
			if (digits.length < 7 || digits.length > 15) {
				return formMsg(form, 'invalid_tel', 'Please enter a valid phone number.');
			}
		} else if (type === 'file') {
			var files = input.files;
			if (required && (!files || !files.length)) {
				return formMsg(form, 'required', 'This field is required.');
			}
			if (files && files.length) {
				var maxFiles = parseInt(input.getAttribute('data-max-files') || '1', 10) || 1;
				if (files.length > maxFiles) {
					return formMsg(form, 'too_many_files', 'Too many files selected.');
				}
				var maxMb = parseFloat(input.getAttribute('data-max-mb') || '10', 10);
				for (var fi = 0; fi < files.length; fi++) {
					if (maxMb > 0 && files[fi].size > maxMb * 1024 * 1024) {
						return formMsg(form, 'file_too_large', 'File is too large.');
					}
				}
			}
		} else if (typeof input.checkValidity === 'function' && input.willValidate && !input.checkValidity()) {
			return formMsg(form, 'error_generic', 'Something went wrong. Please try again.');
		}

		return '';
	}


	/* ---------- Custom select ---------- */

	function closeSelect(root) {
		if (!root) {
			return;
		}
		root.classList.remove('is-open');
		var trigger = root.querySelector('[data-thimbleform-select-trigger]');
		var list = root.querySelector('[data-thimbleform-select-list]');
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'false');
			trigger.removeAttribute('aria-activedescendant');
		}
		if (list) {
			list.hidden = true;
		}
		root.querySelectorAll('.thimbleform-select__option.is-active').forEach(function (opt) {
			opt.classList.remove('is-active');
		});
	}

	function closeAll(except) {
		document.querySelectorAll('[data-thimbleform-select].is-open').forEach(function (root) {
			if (root !== except) {
				closeSelect(root);
			}
		});
	}

	function syncFromNative(root) {
		var native = root.querySelector('[data-thimbleform-select-native]');
		var valueEl = root.querySelector('[data-thimbleform-select-value]');
		var list = root.querySelector('[data-thimbleform-select-list]');
		if (!native || !valueEl) {
			return;
		}
		var value = native.value;
		var placeholder = valueEl.getAttribute('data-placeholder') || '';
		if (!value) {
			valueEl.textContent = placeholder;
			valueEl.classList.add('is-placeholder');
		} else {
			var label = value;
			var selectedNative = native.options[native.selectedIndex];
			if (selectedNative && selectedNative.textContent) {
				label = selectedNative.textContent;
			} else if (list) {
				list.querySelectorAll('[role="option"]').forEach(function (opt) {
					if (opt.getAttribute('data-value') === value && opt.textContent) {
						label = opt.textContent;
					}
				});
			}
			valueEl.textContent = label;
			valueEl.classList.remove('is-placeholder');
		}
		if (list) {
			list.querySelectorAll('[role="option"]').forEach(function (opt) {
				var selected = opt.getAttribute('data-value') === value;
				opt.setAttribute('aria-selected', selected ? 'true' : 'false');
			});
		}
	}

	function setValue(root, value, focusTrigger) {
		var native = root.querySelector('[data-thimbleform-select-native]');
		if (!native) {
			return;
		}
		var previous = native.value;
		native.value = value;
		native.dispatchEvent(new Event('change', { bubbles: true }));
		syncFromNative(root);
		closeSelect(root);
		if (focusTrigger) {
			var trigger = root.querySelector('[data-thimbleform-select-trigger]');
			if (trigger) {
				trigger.focus();
			}
		}
		var form = root.closest('[data-thimbleform]');
		if (form && previous !== value) {
			var wrap = root.closest('[data-field-name]');
			emit(form, 'thimbleform:select-change', {
				name: wrap ? wrap.getAttribute('data-field-name') || '' : '',
				value: value,
				previous: previous,
				select: root,
			});
		}
	}

	function openSelect(root) {
		closeAll(root);
		var trigger = root.querySelector('[data-thimbleform-select-trigger]');
		var list = root.querySelector('[data-thimbleform-select-list]');
		if (!trigger || !list) {
			return;
		}
		root.classList.add('is-open');
		trigger.setAttribute('aria-expanded', 'true');
		list.hidden = false;

		var selected = list.querySelector('[aria-selected="true"]');
		var first = list.querySelector('[role="option"]');
		var active = selected || first;
		if (active) {
			list.querySelectorAll('.is-active').forEach(function (el) {
				el.classList.remove('is-active');
			});
			active.classList.add('is-active');
			trigger.setAttribute('aria-activedescendant', active.id || '');
			active.focus();
		}
	}

	function moveActive(root, delta) {
		var list = root.querySelector('[data-thimbleform-select-list]');
		var trigger = root.querySelector('[data-thimbleform-select-trigger]');
		if (!list) {
			return;
		}
		var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
		if (!options.length) {
			return;
		}
		var index = options.findIndex(function (opt) {
			return opt.classList.contains('is-active');
		});
		if (index < 0) {
			index = options.findIndex(function (opt) {
				return opt.getAttribute('aria-selected') === 'true';
			});
		}
		var next = options[(index + delta + options.length) % options.length];
		options.forEach(function (opt) {
			opt.classList.remove('is-active');
		});
		next.classList.add('is-active');
		if (trigger) {
			trigger.setAttribute('aria-activedescendant', next.id || '');
		}
		next.focus();
	}

	function initSelect(root) {
		if (root.dataset.liteFormSelectReady) {
			return;
		}
		root.dataset.liteFormSelectReady = '1';
		syncFromNative(root);

		var trigger = root.querySelector('[data-thimbleform-select-trigger]');
		var list = root.querySelector('[data-thimbleform-select-list]');
		var native = root.querySelector('[data-thimbleform-select-native]');
		if (!trigger || !list || !native) {
			return;
		}

		trigger.addEventListener('click', function () {
			if (root.classList.contains('is-open')) {
				closeSelect(root);
			} else {
				openSelect(root);
			}
		});

		trigger.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				if (!root.classList.contains('is-open')) {
					openSelect(root);
				} else if (event.key === 'ArrowDown') {
					moveActive(root, 1);
				}
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				if (!root.classList.contains('is-open')) {
					openSelect(root);
				} else {
					moveActive(root, -1);
				}
			} else if (event.key === 'Escape') {
				closeSelect(root);
			}
		});

		list.addEventListener('click', function (event) {
			var opt = event.target.closest('[role="option"]');
			if (!opt || !list.contains(opt)) {
				return;
			}
			setValue(root, opt.getAttribute('data-value') || '', true);
		});

		list.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown') {
				event.preventDefault();
				moveActive(root, 1);
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				moveActive(root, -1);
			} else if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				var active = list.querySelector('.is-active') || list.querySelector('[aria-selected="true"]');
				if (active) {
					setValue(root, active.getAttribute('data-value') || '', true);
				}
			} else if (event.key === 'Escape') {
				event.preventDefault();
				closeSelect(root);
				trigger.focus();
			} else if (event.key === 'Tab') {
				closeSelect(root);
			}
		});

		native.addEventListener('change', function () {
			syncFromNative(root);
		});
	}

	function initAllSelects(scope) {
		(scope || document).querySelectorAll('[data-thimbleform-select]').forEach(initSelect);
	}

	function closePhonePanels(except) {
		document.querySelectorAll('[data-thimbleform-phone]').forEach(function (root) {
			if (except && root === except) {
				return;
			}
			var panel = root.querySelector('[data-thimbleform-phone-panel]');
			var toggle = root.querySelector('[data-thimbleform-phone-toggle]');
			if (panel) {
				panel.hidden = true;
			}
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
			root.classList.remove('is-open');
		});
	}

	function syncPhone(root) {
		if (!root) {
			return;
		}
		var iso = root.getAttribute('data-iso') || '';
		var dial = root.getAttribute('data-dial') || '';
		var nationalEl = root.querySelector('[data-thimbleform-phone-national]');
		var valueEl = root.querySelector('[data-thimbleform-phone-value]');
		var isoEl = root.querySelector('[data-thimbleform-phone-iso]');
		var digits = nationalEl ? String(nationalEl.value || '').replace(/\D+/g, '') : '';
		var e164 = '';
		if (digits) {
			if (dial && digits.indexOf(dial) === 0) {
				e164 = '+' + digits;
			} else if (dial === '7' && digits.length >= 10 && (digits.charAt(0) === '8' || digits.charAt(0) === '7')) {
				e164 = '+7' + digits.slice(1);
			} else {
				e164 = '+' + dial + digits;
			}
		}
		if (valueEl) {
			valueEl.value = e164;
		}
		if (isoEl) {
			isoEl.value = iso;
		}
	}

	function initPhone(root) {
		if (!root || root.dataset.phoneBound === '1') {
			return;
		}
		root.dataset.phoneBound = '1';
		var toggle = root.querySelector('[data-thimbleform-phone-toggle]');
		var panel = root.querySelector('[data-thimbleform-phone-panel]');
		var search = root.querySelector('[data-thimbleform-phone-search]');
		var national = root.querySelector('[data-thimbleform-phone-national]');
		var flagEl = root.querySelector('[data-thimbleform-phone-flag]');
		var dialEl = root.querySelector('[data-thimbleform-phone-dial]');

		function setCountry(iso, dial, flagUrl) {
			root.setAttribute('data-iso', iso);
			root.setAttribute('data-dial', dial);
			if (flagEl) {
				var img = flagEl.querySelector('.thimbleform-phone__flag-img');
				if (!img && flagUrl) {
					img = document.createElement('img');
					img.className = 'thimbleform-phone__flag-img';
					img.width = 20;
					img.height = 15;
					img.alt = '';
					flagEl.textContent = '';
					flagEl.appendChild(img);
				}
				if (img && flagUrl) {
					img.src = flagUrl;
				}
			}
			if (dialEl) {
				dialEl.textContent = '+' + dial;
			}
			root.querySelectorAll('.thimbleform-phone__opt').forEach(function (btn) {
				btn.classList.toggle('is-active', btn.getAttribute('data-iso') === iso);
			});
			syncPhone(root);
		}

		if (toggle && panel) {
			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				var open = panel.hidden;
				closePhonePanels(open ? root : null);
				panel.hidden = !open;
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				root.classList.toggle('is-open', open);
				if (open && search) {
					search.value = '';
					root.querySelectorAll('.thimbleform-phone__opt').forEach(function (btn) {
						btn.parentElement.hidden = false;
					});
					search.focus();
				}
			});
		}

		root.querySelectorAll('.thimbleform-phone__opt').forEach(function (btn) {
			btn.addEventListener('click', function () {
				setCountry(
					btn.getAttribute('data-iso') || '',
					btn.getAttribute('data-dial') || '',
					btn.getAttribute('data-flag') || ''
				);
				closePhonePanels();
				if (national) {
					national.focus();
				}
			});
		});

		if (search) {
			search.addEventListener('keydown', function (event) {
				if (event.key === 'Enter') {
					event.preventDefault();
				}
			});
			search.addEventListener('input', function () {
				var q = String(search.value || '').toLowerCase().trim();
				root.querySelectorAll('.thimbleform-phone__opt').forEach(function (btn) {
					var hay = btn.getAttribute('data-search') || '';
					var li = btn.parentElement;
					if (li) {
						li.hidden = q !== '' && hay.indexOf(q) === -1;
					}
				});
			});
		}

		if (national) {
			national.addEventListener('input', function () {
				syncPhone(root);
			});
			national.addEventListener('change', function () {
				syncPhone(root);
			});
		}

		syncPhone(root);
	}

	function initAllPhones(scope) {
		(scope || document).querySelectorAll('[data-thimbleform-phone]').forEach(initPhone);
	}

	function ensureCaptchaToken(form) {
		return new Promise(function (resolve, reject) {
			var box = form.querySelector('[data-thimbleform-captcha]');
			if (!box) {
				resolve();
				return;
			}

			var provider =
				(box.getAttribute('data-thimbleform-captcha') || '') ||
				(window.thimbleformCaptcha && window.thimbleformCaptcha.provider) ||
				'';

			if (provider === 'recaptcha_v3') {
				var input = form.querySelector('[data-thimbleform-captcha-token]');
				var cfg = window.thimbleformCaptcha || {};
				if (!input) {
					reject(new Error('Captcha token field missing'));
					return;
				}
				if (typeof grecaptcha === 'undefined' || !cfg.siteKey) {
					reject(new Error('reCAPTCHA is not loaded'));
					return;
				}
				grecaptcha.ready(function () {
					grecaptcha
						.execute(cfg.siteKey, { action: cfg.action || 'thimbleform' })
						.then(function (token) {
							input.value = token;
							resolve();
						})
						.catch(reject);
				});
				return;
			}

			// v2 / Turnstile / hCaptcha — token is injected into the provider field.
			var fieldName =
				(window.thimbleformCaptcha && window.thimbleformCaptcha.field) ||
				(provider === 'turnstile'
					? 'cf-turnstile-response'
					: provider === 'hcaptcha'
						? 'h-captcha-response'
						: 'g-recaptcha-response');
			var tokenField = form.querySelector('[name="' + fieldName + '"]');
			if (tokenField && !tokenField.value) {
				reject(new Error('Please complete the captcha'));
				return;
			}
			resolve();
		});
	}

	function onSubmit(event) {
		var form = event.target.closest('[data-thimbleform]');
		if (!form) {
			return;
		}
		event.preventDefault();
		form.querySelectorAll('[data-thimbleform-phone]').forEach(syncPhone);

		if (!emit(form, 'thimbleform:before-submit', {}, true)) {
			return;
		}

		clearErrors(form);
		clearSuccessUi(form);
		setStatus(form, '', '');

		// On multi-step, Enter / implicit submit acts like Next until the last step (Pro).
		if (form.hasAttribute('data-thimbleform-steps') && window.thimbleformSteps) {
			var steps = window.thimbleformSteps.getSteps(form);
			var index = window.thimbleformSteps.getStepIndex(form);
			if (index < steps.length - 1) {
				var nextBtn = form.querySelector('[data-thimbleform-next]');
				if (nextBtn && !nextBtn.hidden) {
					nextBtn.click();
				}
				return;
			}
			var lastHints = clientHints(form, { step: steps[index] });
			if (Object.keys(lastHints).length) {
				showFieldErrors(form, lastHints);
				var lastMsg =
					form.getAttribute('data-error-generic') || 'Please check the highlighted fields.';
				setStatus(form, lastMsg, 'error');
				emit(form, 'thimbleform:validation-error', {
					errors: lastHints,
					message: lastMsg,
					source: 'submit-step',
					step: steps[index],
					stepIndex: index,
				});
				return;
			}
		}

		var hints = clientHints(form);
		if (Object.keys(hints).length) {
			showFieldErrors(form, hints);
			// Jump to first errored step if multi-step (Pro).
			if (form.hasAttribute('data-thimbleform-steps') && window.thimbleformSteps) {
				var firstName = Object.keys(hints)[0];
				var wrap = form.querySelector('[data-field-name="' + firstName + '"]');
				if (wrap) {
					var errStep = parseInt(wrap.getAttribute('data-field-step') || '1', 10);
					var allSteps = window.thimbleformSteps.getSteps(form);
					var jump = allSteps.indexOf(errStep);
					if (jump >= 0) {
						window.thimbleformSteps.setStepIndex(form, jump, { reason: 'validation' });
					}
				}
			}
			var hintMsg =
				form.getAttribute('data-error-generic') || 'Please check the highlighted fields.';
			setStatus(form, hintMsg, 'error');
			emit(form, 'thimbleform:validation-error', {
				errors: hints,
				message: hintMsg,
				source: 'submit',
			});
			return;
		}

		setSubmitting(form, true);

		ensureCaptchaToken(form)
			.then(function () {
				if (typeof window.thimbleformEnsurePayments === 'function') {
					return window.thimbleformEnsurePayments(form);
				}
				return null;
			})
			.then(function () {
				var body = new FormData(form);
				if (
					!emit(
						form,
						'thimbleform:submit',
						{
							formData: body,
						},
						true
					)
				) {
					setSubmitting(form, false);
					return null;
				}

				var ajaxUrl =
					(window.thimbleform && window.thimbleform.ajaxUrl) || form.getAttribute('action');

				return fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body,
				}).then(function (res) {
					return res.json().then(function (json) {
						return { ok: res.ok, json: json };
					});
				});
			})
			.then(function (result) {
				if (!result) {
					return;
				}
				setSubmitting(form, false);
				var json = result.json || {};
				var data = json.data || {};

				if (json.success) {
					var values = formValuesOf(form);
					var redirect = data.redirect || '';
					emit(form, 'thimbleform:success', {
						message: data.message || '',
						redirect: redirect,
						entryId: data.entry_id || 0,
						display: successDisplay(form),
						values: values,
						data: data,
					});
					form.reset();
					form.querySelectorAll('[data-thimbleform-select]').forEach(syncFromNative);
					var token = form.querySelector('[data-thimbleform-captcha-token]');
					if (token) {
						token.value = '';
					}
					if (form.hasAttribute('data-thimbleform-steps') && window.thimbleformSteps) {
						window.thimbleformSteps.setStepIndex(form, 0, { reason: 'success', force: true });
					}
					// Quiz/pro may render a rich result panel; otherwise show thank-you UI.
					if (!data.result) {
						showSuccessMessage(form, data.message || '');
					}
					if (redirect) {
						var go = function () {
							if (!isSafeRedirectUrl(redirect)) {
								return;
							}
							if (
								emit(
									form,
									'thimbleform:redirect',
									{
										url: redirect,
										message: data.message || '',
										entryId: data.entry_id || 0,
										values: values,
										data: data,
									},
									true
								)
							) {
								window.location.href = redirect;
							}
						};
						if (data.result) {
							window.setTimeout(go, 2800);
						} else if (successDisplay(form) === 'popup' || successDisplay(form) === 'replace') {
							window.setTimeout(go, 1600);
						} else {
							go();
						}
					}
					return;
				}

				showFieldErrors(form, data.errors || {});
				if (form.hasAttribute('data-thimbleform-steps') && data.errors && window.thimbleformSteps) {
					var errName = Object.keys(data.errors)[0];
					var errWrap = form.querySelector('[data-field-name="' + errName + '"]');
					if (errWrap) {
						var s = parseInt(errWrap.getAttribute('data-field-step') || '1', 10);
						var idx = window.thimbleformSteps.getSteps(form).indexOf(s);
						if (idx >= 0) {
							window.thimbleformSteps.setStepIndex(form, idx, { reason: 'server-error' });
						}
					}
				}
				setStatus(form, data.message || 'Error', 'error');
				emit(form, 'thimbleform:error', {
					message: data.message || '',
					errors: data.errors || {},
					data: data,
				});
			})
			.catch(function (err) {
				setSubmitting(form, false);
				if (err && err.thimbleformPayment) {
					setStatus(form, (err && err.message) || 'Payment failed', 'error');
					return;
				}
				var netMsg = (err && err.message) || 'Network error. Please try again.';
				setStatus(form, netMsg, 'error');
				emit(form, 'thimbleform:network-error', {
					message: netMsg,
					error: err || null,
				});
			});
	}

	document.addEventListener('submit', onSubmit, true);

	document.addEventListener(
		'click',
		function (event) {
			var link = event.target.closest('.thimbleform__check a');
			if (link) {
				// Prevent label from toggling checkbox when opening a policy link.
				event.stopPropagation();
			}
		},
		true
	);

	document.addEventListener('click', function (event) {
		if (!event.target.closest('[data-thimbleform-select]')) {
			closeAll();
		}
		if (!event.target.closest('[data-thimbleform-phone]')) {
			closePhonePanels();
		}
	});

	document.addEventListener('reset', function (event) {
		var form = event.target;
		if (!form || !form.matches || !form.matches('[data-thimbleform]')) {
			return;
		}
		window.setTimeout(function () {
			form.querySelectorAll('[data-thimbleform-select]').forEach(syncFromNative);
			form.querySelectorAll('[data-thimbleform-phone]').forEach(syncPhone);
			if (form.hasAttribute('data-thimbleform-steps') && window.thimbleformSteps) {
				window.thimbleformSteps.setStepIndex(form, 0, { reason: 'reset', force: true });
			}
			emit(form, 'thimbleform:reset', {});
		}, 0);
	});

	function syncOtherField(wrap) {
		if (!wrap) {
			return;
		}
		var other = wrap.querySelector('[data-thimbleform-other]');
		if (!other) {
			return;
		}
		var on = false;
		var native = wrap.querySelector('[data-thimbleform-select-native]');
		if (native) {
			on = native.value === '__other';
		} else {
			var checked = wrap.querySelectorAll('input[data-thimbleform-other-trigger]:checked, input[value="__other"]:checked');
			on = checked.length > 0;
		}
		other.hidden = !on;
		if (!on) {
			other.value = '';
		}
	}

	function initOtherFields(root) {
		(root || document).querySelectorAll('[data-thimbleform-allow-other]').forEach(function (wrap) {
			var scope = wrap.closest('[data-field-name]') || wrap;
			syncOtherField(scope);
			wrap.addEventListener('change', function () {
				syncOtherField(scope);
			});
		});
		document.addEventListener('thimbleform:select-change', function (event) {
			var form = event.target && event.target.closest ? event.target.closest('[data-thimbleform]') : null;
			if (!form) {
				return;
			}
			form.querySelectorAll('[data-field-name]').forEach(syncOtherField);
		});
	}

	function boot() {
		initAllSelects(document);
		initAllPhones(document);
		initOtherFields(document);
		document.querySelectorAll('[data-thimbleform]').forEach(function (form) {
			initConditions(form);
			if (typeof window.thimbleformInitProFront === 'function') {
				window.thimbleformInitProFront(form);
			}
			emit(form, 'thimbleform:ready', {
				steps: window.thimbleformSteps ? window.thimbleformSteps.getSteps(form) : [],
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.thimbleformEmit = emit;
	window.thimbleformFormMsg = formMsg;
	window.thimbleformClientHints = clientHints;
	window.thimbleformClearErrors = clearErrors;
	window.thimbleformSetStatus = setStatus;
	window.thimbleformConditionPasses = conditionPasses;

	window.thimbleformSelect = {
		init: initAllSelects,
		sync: syncFromNative,
		closeAll: closeAll,
	};

	window.thimbleformEvents = [
		'thimbleform:ready',
		'thimbleform:before-submit',
		'thimbleform:validation-error',
		'thimbleform:submit',
		'thimbleform:success',
		'thimbleform:error',
		'thimbleform:network-error',
		'thimbleform:redirect',
		'thimbleform:before-step-change',
		'thimbleform:step-change',
		'thimbleform:select-change',
		'thimbleform:reset',
	];
})();
