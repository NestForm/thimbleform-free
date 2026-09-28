/**
 * Thimbleform admin: step groups, DnD, quick-add, duplicate.
 */
(function () {
	'use strict';

	var cfg = window.nestformAdmin || {};
	var i18n = cfg.i18n || {};
	var typeLabels = cfg.typeLabels || {};
	var dragState = { card: null };

	function slugify(value) {
		return String(value || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.replace(/_+/g, '_');
	}

	function stripTags(value) {
		return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function uniqueFieldName(root, base, exceptCard) {
		var used = {};
		allFieldCards(root).forEach(function (card) {
			if (exceptCard && card === exceptCard) {
				return;
			}
			var el = card.querySelector('[data-nestform-name]');
			var v = el && String(el.value || '').trim();
			if (v) {
				used[v] = true;
			}
		});
		var slug = slugify(base) || 'field';
		if (!used[slug]) {
			return slug;
		}
		var n = 2;
		while (used[slug + '_' + n]) {
			n += 1;
		}
		return slug + '_' + n;
	}

	function filterAddMenu(panel, query) {
		if (!panel) {
			return;
		}
		var q = String(query || '')
			.trim()
			.toLowerCase();
		panel.querySelectorAll('.nestform-add-menu__group').forEach(function (group) {
			var visible = 0;
			group.querySelectorAll('.nestform-add-menu__item').forEach(function (item) {
				var label = (item.textContent || '').trim().toLowerCase();
				var show = !q || label.indexOf(q) !== -1;
				item.hidden = !show;
				if (show) {
					visible += 1;
				}
			});
			group.hidden = visible === 0;
		});
	}

	function openAddMenu(root, menu) {
		if (!menu) {
			return;
		}
		var toggle = menu.querySelector('[data-nestform-add-menu-toggle]');
		var panel = menu.querySelector('[data-nestform-add-menu-panel]');
		closeAllAddMenus(root);
		menu.classList.add('is-open');
		if (toggle) {
			toggle.setAttribute('aria-expanded', 'true');
		}
		if (panel) {
			panel.hidden = false;
			var search = panel.querySelector('[data-nestform-add-search]');
			if (search) {
				search.value = '';
				filterAddMenu(panel, '');
				window.setTimeout(function () {
					search.focus();
				}, 0);
			}
		}
		var browse = root.querySelector('[data-nestform-add-browse]');
		if (browse) {
			browse.setAttribute(
				'aria-expanded',
				menu.getAttribute('data-nestform-add-menu') === 'more' ? 'true' : 'false'
			);
		}
	}

	function closeAllAddMenus(root) {
		root.querySelectorAll('[data-nestform-add-menu]').forEach(function (menu) {
			menu.classList.remove('is-open');
			var toggle = menu.querySelector('[data-nestform-add-menu-toggle]');
			var panel = menu.querySelector('[data-nestform-add-menu-panel]');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
			if (panel) {
				panel.hidden = true;
			}
		});
		var browse = root.querySelector('[data-nestform-add-browse]');
		if (browse) {
			browse.setAttribute('aria-expanded', 'false');
		}
	}

	function initAddMenus(root) {
		root.querySelectorAll('[data-nestform-add-menu-toggle]').forEach(function (toggle) {
			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				var menu = toggle.closest('[data-nestform-add-menu]');
				if (!menu) {
					return;
				}
				var willOpen = !menu.classList.contains('is-open');
				closeAllAddMenus(root);
				if (!willOpen) {
					return;
				}
				openAddMenu(root, menu);
			});
		});

		var browseButtons = root.querySelectorAll('[data-nestform-add-browse]');
		browseButtons.forEach(function (browse) {
			browse.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				var more = root.querySelector('[data-nestform-add-menu="more"]');
				if (!more) {
					return;
				}
				if (more.classList.contains('is-open')) {
					closeAllAddMenus(root);
					return;
				}
				openAddMenu(root, more);
			});
		});

		document.addEventListener('click', function (event) {
			if (event.target.closest('[data-nestform-add-menu], [data-nestform-add-browse]')) {
				return;
			}
			closeAllAddMenus(root);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeAllAddMenus(root);
			}
		});

		root.querySelectorAll('[data-nestform-add-search]').forEach(function (input) {
			input.addEventListener('input', function () {
				var panel = input.closest('[data-nestform-add-menu-panel]');
				filterAddMenu(panel, input.value);
			});
			input.addEventListener('click', function (event) {
				event.stopPropagation();
			});
		});
	}

	function initAppearanceUi(root) {
		var colorFallbacks = {
			style_accent: '#2563eb',
			style_accent_text: '#ffffff',
			style_text: '#01123e',
			style_muted: '#6b6b80',
			style_surface: '#ffffff',
			style_input_bg: '#ffffff',
			style_border: '#e8e8ec',
		};
		var fontMap = {
			sm: { base: '0.875rem', label: '0.8125rem', help: '0.75rem' },
			md: { base: '1rem', label: '0.875rem', help: '0.8125rem' },
			lg: { base: '1.0625rem', label: '0.9375rem', help: '0.875rem' },
			xl: { base: '1.125rem', label: '1rem', help: '0.9375rem' },
		};
		var gapMap = { sm: '0.65rem', md: '1rem', lg: '1.35rem' };
		var radiusMap = { sm: '0.25rem', md: '0.5rem', lg: '0.875rem', pill: '999px' };
		var densityMap = {
			sm: { pad: '0.5rem 0.75rem', min_h: '2.35rem', btn: '0.5rem 1rem' },
			md: { pad: '0.75rem 1rem', min_h: '2.75rem', btn: '0.65rem 1.25rem' },
			lg: { pad: '0.9rem 1.15rem', min_h: '3.1rem', btn: '0.8rem 1.4rem' },
		};

		function settingValue(name) {
			var el = root.querySelector('[name="nestform[settings][' + name + ']"]');
			if (!el) {
				return '';
			}
			if (el.type === 'radio') {
				var checked = root.querySelector(
					'[name="nestform[settings][' + name + ']"]:checked'
				);
				return checked ? String(checked.value || '') : '';
			}
			return String(el.value || '');
		}

		function colorValue(key) {
			var hex = root.querySelector('[data-nestform-style-hex][data-color-key="' + key + '"]');
			var raw = hex ? String(hex.value || '').trim() : '';
			var normalized = normalizeHex(raw);
			if (normalized) {
				return normalized;
			}
			return colorFallbacks[key] || '';
		}

		function syncSkinChrome() {
			var checked = root.querySelector('.nestform-style-skins__item input[type="radio"]:checked');
			var skin = checked ? String(checked.value || 'theme') : 'theme';
			var chrome = root.querySelector('[data-nestform-style-chrome]');
			var note = root.querySelector('[data-nestform-style-chrome-note]');
			var liveNote = root.querySelector('[data-nestform-live-theme-note]');
			if (chrome) {
				chrome.classList.toggle('is-disabled', skin === 'theme');
			}
			if (note) {
				note.hidden = skin !== 'theme';
			}
			if (liveNote) {
				liveNote.hidden = skin !== 'theme';
			}
		}

		function normalizeHex(value) {
			var v = String(value || '').trim();
			if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v)) {
				return '';
			}
			if (v.length === 4) {
				v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
			}
			return v.toLowerCase();
		}

		function syncColorPreview() {
			root.querySelectorAll('[data-nestform-color-preview]').forEach(function (chip) {
				var key = chip.getAttribute('data-nestform-color-preview');
				var hex = root.querySelector('[data-nestform-style-hex][data-color-key="' + key + '"]');
				var wrap = hex ? hex.closest('[data-nestform-style-color-wrap]') : null;
				var swatch = wrap ? wrap.querySelector('[data-nestform-style-color]') : null;
				var color = hex && hex.value ? normalizeHex(hex.value) : '';
				if (!color && swatch) {
					color = swatch.getAttribute('data-fallback') || swatch.value;
				}
				if (color) {
					chip.style.background = color;
				}
			});
		}

		function syncLivePreview() {
			var form = root.querySelector('[data-nestform-live-form]');
			var fieldStages = root.querySelectorAll(
				'[data-nestform-field-preview-stage]'
			);

			var skin = settingValue('style_skin') || 'theme';
			var button = settingValue('style_button') || 'solid';
			var radius = settingValue('style_radius') || 'md';
			var font = settingValue('style_font_size') || 'md';
			var gap = settingValue('style_gap') || 'md';
			var density = settingValue('style_density') || 'md';

			var vars = {
				'--nest-form-accent': colorValue('style_accent'),
				'--nest-form-accent-text': colorValue('style_accent_text'),
				'--nest-form-text': colorValue('style_text'),
				'--nest-form-muted': colorValue('style_muted'),
				'--nest-form-surface': colorValue('style_surface'),
				'--nest-form-input-bg': colorValue('style_input_bg'),
				'--nest-form-border': colorValue('style_border'),
			};

			if (!fontMap[font]) {
				font = 'md';
			}
			vars['--nest-form-font-size'] = fontMap[font].base;
			vars['--nest-form-label-size'] = fontMap[font].label;
			vars['--nest-form-help-size'] = fontMap[font].help;

			if (!gapMap[gap]) {
				gap = 'md';
			}
			vars['--nest-form-gap'] = gapMap[gap];

			if (!radiusMap[radius]) {
				radius = 'md';
			}
			vars['--nest-form-radius'] = radiusMap[radius];

			if (!densityMap[density]) {
				density = 'md';
			}
			vars['--nest-form-control-pad'] = densityMap[density].pad;
			vars['--nest-form-control-min-h'] = densityMap[density].min_h;
			vars['--nest-form-btn-pad'] = densityMap[density].btn;

			var styleParts = [];
			Object.keys(vars).forEach(function (key) {
				if (vars[key]) {
					styleParts.push(key + ':' + vars[key]);
				}
			});
			var styleAttr = styleParts.join(';');

			if (form) {
				var classes = [
					'nestform-live-preview',
					'nestform-live-preview--skin-' + skin,
				];
				if (skin !== 'theme') {
					classes.push('nestform-live-preview--btn-' + button);
				}
				form.className = classes.join(' ');
				form.setAttribute('style', styleAttr);

				var submitBtn = form.querySelector('[data-nestform-live-submit]');
				var submitLabel = settingValue('submit_label');
				if (submitBtn && submitLabel) {
					submitBtn.textContent = submitLabel;
				}
			}

			/* Field card stages share form appearance tokens (keep light canvas in dark admin). */
			fieldStages.forEach(function (stage) {
				stage.setAttribute('style', styleAttr);
			});
		}

		function syncColorWrap(wrap) {
			var hex = wrap.querySelector('[data-nestform-style-hex]');
			var clear = wrap.querySelector('[data-nestform-style-clear]');
			var set = !!(hex && String(hex.value || '').trim());
			wrap.classList.toggle('is-set', set);
			if (clear) {
				clear.hidden = !set;
			}
		}

		function refreshAppearance() {
			syncSkinChrome();
			syncColorPreview();
			syncLivePreview();
		}

		root.querySelectorAll('.nestform-style-skins__item input[type="radio"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				root.querySelectorAll('.nestform-style-skins__item').forEach(function (item) {
					var input = item.querySelector('input[type="radio"]');
					item.classList.toggle('is-active', !!(input && input.checked));
				});
				refreshAppearance();
			});
		});

		root.querySelectorAll('[data-nestform-style-color-wrap]').forEach(function (wrap) {
			var swatch = wrap.querySelector('[data-nestform-style-color]');
			var hex = wrap.querySelector('[data-nestform-style-hex]');
			var clear = wrap.querySelector('[data-nestform-style-clear]');
			if (!swatch || !hex) {
				return;
			}
			swatch.addEventListener('input', function () {
				hex.value = String(swatch.value || '').toLowerCase();
				syncColorWrap(wrap);
				refreshAppearance();
			});
			hex.addEventListener('input', function () {
				syncColorWrap(wrap);
				refreshAppearance();
			});
			hex.addEventListener('change', function () {
				var v = normalizeHex(hex.value);
				if (v) {
					hex.value = v;
					swatch.value = v;
				}
				syncColorWrap(wrap);
				refreshAppearance();
			});
			if (clear) {
				clear.addEventListener('click', function (event) {
					event.preventDefault();
					hex.value = '';
					swatch.value = swatch.getAttribute('data-fallback') || '#0d9488';
					syncColorWrap(wrap);
					refreshAppearance();
				});
			}
			syncColorWrap(wrap);
		});

		root
			.querySelectorAll(
				'[name="nestform[settings][style_font_size]"], [name="nestform[settings][style_gap]"], [name="nestform[settings][style_density]"], [name="nestform[settings][style_radius]"], [name="nestform[settings][style_button]"], [name="nestform[settings][submit_label]"]'
			)
			.forEach(function (el) {
				el.addEventListener('change', refreshAppearance);
				el.addEventListener('input', refreshAppearance);
			});

		refreshAppearance();
	}

	function initAutomationsUi(root) {
		var wrap = root.querySelector('[data-nestform-auto]');
		if (!wrap) {
			return;
		}
		var rulesEl = wrap.querySelector('[data-nestform-auto-rules]');
		var tpl = wrap.querySelector('[data-nestform-auto-rule-tpl]');
		var addBtn = wrap.querySelector('[data-nestform-auto-add]');
		var warn = wrap.querySelector('[data-nestform-auto-warn]');
		var enabled = wrap.querySelector('[data-nestform-auto-enabled]');
		var maxRules = 3;

		function reindexRules() {
			if (!rulesEl) {
				return;
			}
			var rows = rulesEl.querySelectorAll('[data-nestform-auto-rule]');
			rows.forEach(function (row, i) {
				row.querySelectorAll('[name]').forEach(function (el) {
					var name = String(el.getAttribute('name') || '');
					el.setAttribute(
						'name',
						name.replace(/automation_rules\]\[\d+\]|automation_rules\]\[__i__\]/, 'automation_rules][' + i + ']')
					);
				});
			});
			if (addBtn) {
				addBtn.hidden = rows.length >= maxRules;
			}
		}

		function syncOpValue(row) {
			var op = row.querySelector('[data-nestform-auto-op]');
			var valueWrap = row.querySelector('[data-nestform-auto-value-wrap]');
			if (!op || !valueWrap) {
				return;
			}
			var hide = op.value === 'empty' || op.value === 'not_empty';
			valueWrap.hidden = hide;
			if (hide) {
				var input = valueWrap.querySelector('[data-nestform-auto-value]');
				if (input) {
					input.value = '';
				}
			}
		}

		function syncWarn() {
			if (!warn) {
				return;
			}
			var on = enabled && enabled.checked;
			var status = wrap.querySelector('[name="nestform[settings][automation_then_status]"]');
			var email = wrap.querySelector('[name="nestform[settings][automation_then_email]"]');
			var webhook = wrap.querySelector('[name="nestform[settings][automation_then_webhook]"]');
			var hasThen =
				(status && String(status.value || '') !== '') ||
				(email && String(email.value || '').trim() !== '') ||
				(webhook && String(webhook.value || '').trim() !== '');
			warn.hidden = !(on && !hasThen);
		}

		function bindRow(row) {
			var op = row.querySelector('[data-nestform-auto-op]');
			if (op) {
				op.addEventListener('change', function () {
					syncOpValue(row);
				});
			}
			var remove = row.querySelector('[data-nestform-auto-remove]');
			if (remove) {
				remove.addEventListener('click', function (event) {
					event.preventDefault();
					var rows = rulesEl ? rulesEl.querySelectorAll('[data-nestform-auto-rule]') : [];
					if (rows.length <= 1) {
						var field = row.querySelector('[data-nestform-auto-field]');
						var value = row.querySelector('[data-nestform-auto-value]');
						if (field) {
							field.value = '';
						}
						if (value) {
							value.value = '';
						}
						if (op) {
							op.value = 'equals';
						}
						syncOpValue(row);
						return;
					}
					row.parentNode.removeChild(row);
					reindexRules();
				});
			}
			syncOpValue(row);
		}

		if (rulesEl) {
			rulesEl.querySelectorAll('[data-nestform-auto-rule]').forEach(bindRow);
		}

		if (addBtn && tpl && rulesEl) {
			addBtn.addEventListener('click', function (event) {
				event.preventDefault();
				var count = rulesEl.querySelectorAll('[data-nestform-auto-rule]').length;
				if (count >= maxRules) {
					return;
				}
				var html = tpl.innerHTML.replace(/__i__/g, String(count));
				var holder = document.createElement('div');
				holder.innerHTML = html.trim();
				var row = holder.firstElementChild;
				if (!row) {
					return;
				}
				rulesEl.appendChild(row);
				bindRow(row);
				reindexRules();
			});
		}

		if (enabled) {
			enabled.addEventListener('change', syncWarn);
		}
		wrap.querySelectorAll('[data-nestform-auto-then]').forEach(function (el) {
			el.addEventListener('input', syncWarn);
			el.addEventListener('change', syncWarn);
		});

		reindexRules();
		syncWarn();
	}

	function initQuizBandsUi(root) {
		var wrap = root.querySelector('[data-nestform-bands]');
		if (!wrap) {
			return;
		}
		var listEl = wrap.querySelector('[data-nestform-bands-list]');
		var tpl = wrap.querySelector('[data-nestform-bands-tpl]');
		var addBtn = wrap.querySelector('[data-nestform-bands-add]');
		var maxBands = 8;

		function reindexBands() {
			if (!listEl) {
				return;
			}
			var rows = listEl.querySelectorAll('[data-nestform-bands-row]');
			rows.forEach(function (row, i) {
				row.querySelectorAll('[name]').forEach(function (el) {
					var name = String(el.getAttribute('name') || '');
					el.setAttribute(
						'name',
						name.replace(/quiz_bands\]\[\d+\]|quiz_bands\]\[__i__\]/, 'quiz_bands][' + i + ']')
					);
				});
			});
			if (addBtn) {
				addBtn.hidden = rows.length >= maxBands;
			}
		}

		function clearRow(row) {
			var min = row.querySelector('[data-nestform-bands-min]');
			var max = row.querySelector('[data-nestform-bands-max]');
			var title = row.querySelector('[data-nestform-bands-title]');
			var message = row.querySelector('[data-nestform-bands-message]');
			var redirect = row.querySelector('[data-nestform-bands-redirect]');
			if (min) {
				min.value = '0';
			}
			if (max) {
				max.value = '100';
			}
			if (title) {
				title.value = '';
			}
			if (message) {
				message.value = '';
			}
			if (redirect) {
				redirect.value = '';
			}
		}

		function bindRow(row) {
			var remove = row.querySelector('[data-nestform-bands-remove]');
			if (!remove) {
				return;
			}
			remove.addEventListener('click', function (event) {
				event.preventDefault();
				var rows = listEl ? listEl.querySelectorAll('[data-nestform-bands-row]') : [];
				if (rows.length <= 1) {
					clearRow(row);
					return;
				}
				row.parentNode.removeChild(row);
				reindexBands();
			});
		}

		if (listEl) {
			listEl.querySelectorAll('[data-nestform-bands-row]').forEach(bindRow);
		}

		if (addBtn && tpl && listEl) {
			addBtn.addEventListener('click', function (event) {
				event.preventDefault();
				var count = listEl.querySelectorAll('[data-nestform-bands-row]').length;
				if (count >= maxBands) {
					return;
				}
				var html = tpl.innerHTML.replace(/__i__/g, String(count));
				var holder = document.createElement('div');
				holder.innerHTML = html.trim();
				var row = holder.firstElementChild;
				if (!row) {
					return;
				}
				listEl.appendChild(row);
				bindRow(row);
				reindexBands();
			});
		}

		reindexBands();
	}

	function initWebhooksUi(root) {
		var wrap = root.querySelector('[data-nestform-webhooks]');
		if (!wrap) {
			return;
		}
		var listEl = wrap.querySelector('[data-nestform-webhooks-list]');
		var tpl = wrap.querySelector('[data-nestform-webhooks-tpl]');
		var addBtn = wrap.querySelector('[data-nestform-webhooks-add]');
		var maxEndpoints = 5;

		function reindexEndpoints() {
			if (!listEl) {
				return;
			}
			var rows = listEl.querySelectorAll('[data-nestform-webhooks-row]');
			rows.forEach(function (row, i) {
				row.querySelectorAll('[name]').forEach(function (el) {
					var name = String(el.getAttribute('name') || '');
					el.setAttribute(
						'name',
						name.replace(
							/webhook_endpoints\]\[\d+\]|webhook_endpoints\]\[__i__\]/,
							'webhook_endpoints][' + i + ']'
						)
					);
				});
			});
			if (addBtn) {
				addBtn.hidden = rows.length >= maxEndpoints;
			}
		}

		function clearRow(row) {
			var url = row.querySelector('[data-nestform-webhooks-url]');
			var secret = row.querySelector('[data-nestform-webhooks-secret]');
			if (url) {
				url.value = '';
			}
			if (secret) {
				secret.value = '';
			}
		}

		function bindRow(row) {
			var remove = row.querySelector('[data-nestform-webhooks-remove]');
			if (!remove) {
				return;
			}
			remove.addEventListener('click', function (event) {
				event.preventDefault();
				var rows = listEl ? listEl.querySelectorAll('[data-nestform-webhooks-row]') : [];
				if (rows.length <= 1) {
					clearRow(row);
					return;
				}
				row.parentNode.removeChild(row);
				reindexEndpoints();
			});
		}

		if (listEl) {
			listEl.querySelectorAll('[data-nestform-webhooks-row]').forEach(bindRow);
		}

		if (addBtn && tpl && listEl) {
			addBtn.addEventListener('click', function (event) {
				event.preventDefault();
				var count = listEl.querySelectorAll('[data-nestform-webhooks-row]').length;
				if (count >= maxEndpoints) {
					return;
				}
				var html = tpl.innerHTML.replace(/__i__/g, String(count));
				var holder = document.createElement('div');
				holder.innerHTML = html.trim();
				var row = holder.firstElementChild;
				if (!row) {
					return;
				}
				listEl.appendChild(row);
				bindRow(row);
				reindexEndpoints();
			});
		}

		reindexEndpoints();
	}

	function getWorkspace(root) {
		return root.querySelector('[data-nestform-workspace]');
	}

	function isStepsMode(root) {
		var ws = getWorkspace(root);
		return ws && ws.getAttribute('data-mode') === 'steps';
	}

	function getActiveStep(root) {
		var active = root.querySelector('[data-nestform-step-group].is-active');
		if (active) {
			return parseInt(active.getAttribute('data-step') || '1', 10) || 1;
		}
		return 1;
	}

	function getActiveList(root) {
		if (isStepsMode(root)) {
			var group = root.querySelector('[data-nestform-step-group].is-active');
			return group ? group.querySelector('[data-nestform-fields]') : null;
		}
		var first = root.querySelector('[data-nestform-step-group][data-step="1"] [data-nestform-fields]');
		return first || root.querySelector('[data-nestform-fields]');
	}

	function allFieldCards(root) {
		return Array.prototype.slice.call(root.querySelectorAll('[data-nestform-field]'));
	}

	function reindex(root) {
		allFieldCards(root).forEach(function (row, index) {
			row.querySelectorAll('[name]').forEach(function (input) {
				input.name = input.name.replace(
					/nestform\[fields\]\[[^\]]+\]/,
					'nestform[fields][' + index + ']'
				);
			});
		});
	}

	function setCollapsed(card, collapsed) {
		card.classList.toggle('is-collapsed', collapsed);
		var body = card.querySelector('[data-nestform-card-body]');
		if (body) {
			body.hidden = collapsed;
		}
		card.querySelectorAll('[data-nestform-toggle]').forEach(function (btn) {
			btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		});
		if (!collapsed) {
			syncFieldPreview(card);
		}
	}

	function isLayoutType(type) {
		return (window.nestformAdmin && window.nestformAdmin.layoutTypes || []).indexOf(type) >= 0;
	}

	/**
	 * Map data-nestform-show → field types that own that control.
	 * Used to disable inactive inputs so shared name keys (options/default/placeholder)
	 * do not overwrite each other on save. `name` is never disabled (always submitted).
	 */
	var showForTypes = {
		'heading-level': ['heading'],
		'image-picker': ['image'],
		'html-content': ['html'],
		'paragraph-text': ['paragraph'],
		'spacer-size': ['spacer'],
		'phone-country': ['tel'],
		'file-limits': ['file'],
		'file-max': ['file'],
		options: ['select', 'radio', 'checkboxes', 'range', 'rating', 'scale', 'ranking', 'matrix'],
		'payment-setup': ['payment'],
		formula: ['calculated'],
		subfields: ['repeater'],
		'choice-other': ['select', 'radio', 'checkboxes'],
		placeholder: ['text', 'email', 'tel', 'url', 'password', 'number', 'textarea', 'select'],
		default: [
			'text',
			'email',
			'tel',
			'url',
			'password',
			'number',
			'range',
			'textarea',
			'hidden',
			'select',
			'radio',
			'checkboxes',
			'date',
			'time',
			'checkbox',
			'rating',
			'nps',
			'scale',
		],
		description: [
			'text',
			'email',
			'tel',
			'url',
			'password',
			'number',
			'range',
			'textarea',
			'select',
			'radio',
			'checkboxes',
			'checkbox',
			'acceptance',
			'file',
			'image',
			'date',
			'time',
			'hidden',
			'rating',
			'signature',
			'nps',
			'scale',
			'ranking',
			'paragraph',
			'calculated',
			'repeater',
			'payment',
		],
		'acceptance-html': ['acceptance'],
		condition: [
			'text',
			'email',
			'tel',
			'url',
			'password',
			'number',
			'range',
			'textarea',
			'select',
			'radio',
			'checkboxes',
			'checkbox',
			'acceptance',
			'file',
			'date',
			'time',
			'hidden',
			'rating',
			'signature',
			'nps',
			'scale',
			'ranking',
			'calculated',
			'repeater',
			'payment',
		],
	};

	function setControlsDisabled(root, disabled) {
		if (!root) {
			return;
		}
		root.querySelectorAll('input, select, textarea, button').forEach(function (el) {
			if (el.hasAttribute('data-nestform-type') || el.hasAttribute('data-nestform-move-step')) {
				return;
			}
			el.disabled = !!disabled;
		});
	}

	function syncShowControls(card, type) {
		card.querySelectorAll('[data-nestform-show]').forEach(function (el) {
			var key = el.getAttribute('data-nestform-show');
			if (key === 'name') {
				// Always submit the slug; only hide the control for layout blocks.
				var showName = !isLayoutType(type);
				el.hidden = !showName;
				return;
			}
			var allowed = showForTypes[key];
			if (!allowed) {
				return;
			}
			var active = allowed.indexOf(type) >= 0;
			el.hidden = !active;
			setControlsDisabled(el, !active);
		});
		syncOtherLabel(card);
		syncConditionSection(card, type);
		syncTypeSectionTitle(card, type);
		syncDescriptionLabel(card, type);
		syncSections(card);
		var subWrap = card.querySelector('[data-nestform-subfields]');
		if (subWrap && !subWrap.hidden) {
			syncSubfieldsUi(subWrap);
		}
	}

	function syncTypeSectionTitle(card, type) {
		var wrap = card.querySelector('[data-nestform-section="type"]');
		var titleEl = card.querySelector('[data-nestform-type-section-title]');
		if (!wrap) {
			return;
		}
		var titles = (i18n.typeSectionTitles || {});
		var title = titles[type] || '';
		if (titleEl) {
			titleEl.textContent = title;
		}
		// Visibility is finalized in syncSections after show/hide of controls.
		wrap.setAttribute('data-has-type-title', title ? '1' : '0');
	}

	function syncDescriptionLabel(card, type) {
		var label = card.querySelector('[data-nestform-description-label]');
		var input = card.querySelector('[data-nestform-description]');
		if (label) {
			label.textContent =
				type === 'image'
					? i18n.altText || 'Alt text'
					: i18n.helpText || 'Help text';
		}
		if (input) {
			input.placeholder =
				type === 'image'
					? i18n.describeImage || 'Describe the image'
					: i18n.shownUnderField || 'Shown under the field';
		}
	}

	function syncOptionsHelp(card, type) {
		var labelEl = card.querySelector('[data-nestform-options-label]');
		var hintEl = card.querySelector('[data-nestform-options-hint]');
		var inputEl = card.querySelector('[data-nestform-options-input]');
		var tipEl = card.querySelector('[data-nestform-options-tip]');
		if (!labelEl && !hintEl && !inputEl) {
			return;
		}

		var label = i18n.optionsLabelChoices || 'Choices';
		var hint =
			i18n.optionsHintChoices ||
			'One choice per line — the text visitors see. For quizzes, add points after | : Correct answer|10';
		var tip =
			i18n.optionsTipChoices ||
			'One choice per line. Quizzes: Correct answer|10. Optional advanced: Label|saved_value|points';
		var placeholder = i18n.optionsPhChoices || "Yes\nNo\nMaybe";

		if (type === 'range') {
			label = i18n.optionsLabelRange || 'Min / max / step';
			hint = i18n.optionsHintRange || 'Three lines: lowest value, highest value, and step size.';
			tip = i18n.optionsTipRange || 'Line 1 = min, line 2 = max, line 3 = step. Example: 0 / 100 / 1';
			placeholder = i18n.optionsPhRange || "0\n100\n1";
		} else if (type === 'rating') {
			label = i18n.optionsLabelRating || 'Number of stars';
			hint = i18n.optionsHintRating || 'Enter one number for how many stars to show (1–10), e.g. 5.';
			tip = i18n.optionsTipRating || 'A single number sets max stars (1–10). Or list one label per star.';
			placeholder = i18n.optionsPhRating || '5';
		} else if (type === 'scale') {
			label = i18n.optionsLabelScale || 'Scale setup';
			hint = i18n.optionsHintScale || 'Four lines: lowest number, highest number, left label, right label.';
			tip = i18n.optionsTipScale || 'Line 1–2 = number range, line 3–4 = labels under the ends of the scale.';
			placeholder = i18n.optionsPhScale || "1\n5\nVery dissatisfied\nVery satisfied";
		} else if (type === 'matrix') {
			label = i18n.optionsLabelMatrix || 'Rows and columns';
			hint = i18n.optionsHintMatrix || 'List row labels, then a line with only ---, then column labels.';
			tip = i18n.optionsTipMatrix || 'Rows above ---, columns below. Each line is one label.';
			placeholder = i18n.optionsPhMatrix || "Support\nProduct\n---\nPoor\nFair\nGood";
		}

		if (labelEl) {
			labelEl.textContent = label;
		}
		if (hintEl) {
			hintEl.textContent = hint;
		}
		if (inputEl) {
			inputEl.placeholder = placeholder;
		}
		if (tipEl) {
			tipEl.setAttribute('title', tip);
			tipEl.setAttribute('aria-label', tip);
		}
	}

	/**
	 * Show Other label only when Allow “Other” is checked (and options UI is active).
	 */
	function syncOtherLabel(card) {
		var wrap = card.querySelector('[data-nestform-other-label]');
		var toggle = card.querySelector('[data-nestform-allow-other]');
		if (!wrap || !toggle) {
			return;
		}
		if (toggle.disabled) {
			wrap.hidden = true;
			return;
		}
		wrap.hidden = !toggle.checked;
	}

	function isControlVisible(el, boundary) {
		if (!el || el.hidden) {
			return false;
		}
		var ancestor = el.parentElement;
		while (ancestor && ancestor !== boundary) {
			if (ancestor.hidden) {
				return false;
			}
			ancestor = ancestor.parentElement;
		}
		var inputs = el.querySelectorAll('input, select, textarea');
		if (inputs.length) {
			var enabled = false;
			inputs.forEach(function (inp) {
				if (!inp.disabled) {
					enabled = true;
				}
			});
			if (!enabled) {
				return false;
			}
		}
		var style = window.getComputedStyle(el);
		return style.display !== 'none' && style.visibility !== 'hidden';
	}

	function syncSections(card) {
		if (!card) {
			return;
		}
		card.querySelectorAll('[data-nestform-section]').forEach(function (section) {
			var key = section.getAttribute('data-nestform-section');
			// Primary Field block always stays visible (Type + Label).
			if (key === 'field' || key === 'advanced') {
				section.hidden = false;
				return;
			}
			// Logic handled separately for layout types.
			if (key === 'condition') {
				return;
			}
			section.hidden = false;
			var controls = section.querySelectorAll(
				'.nestform-admin__field-control, .nestform-admin__field-control--full'
			);
			var anyVisible = false;
			controls.forEach(function (el) {
				if (isControlVisible(el, section)) {
					anyVisible = true;
				}
			});
			section.hidden = !anyVisible;
		});
		syncConditionValue(card);
	}

	function clearCondition(card) {
		var field = card.querySelector('[data-nestform-condition-field]');
		var op = card.querySelector('[data-nestform-condition-op]');
		var val = card.querySelector('[data-nestform-condition-value]');
		if (field) {
			field.value = '';
		}
		if (op) {
			op.value = 'equals';
		}
		if (val) {
			val.value = '';
		}
		var details = card.querySelector('[data-nestform-section="condition"]');
		if (details && details.tagName === 'DETAILS') {
			details.open = false;
		}
	}

	function syncConditionSection(card, type) {
		var section = card.querySelector('[data-nestform-section="condition"]');
		if (!section) {
			return;
		}
		var allow = !isLayoutType(type);
		if (!allow) {
			clearCondition(card);
		}
		section.hidden = !allow;
		setControlsDisabled(section, !allow);
	}

	function syncConditionValue(card) {
		var op = card.querySelector('[data-nestform-condition-op]');
		var wrap = card.querySelector('[data-nestform-condition-value-wrap]');
		if (!op || !wrap) {
			return;
		}
		var needsValue = op.value !== 'empty' && op.value !== 'not_empty';
		wrap.hidden = !needsValue;
	}

	function syncTypeUi(card) {
		var select = card.querySelector('[data-nestform-type]');
		if (!select) {
			return;
		}
		var type = select.value;
		card.setAttribute('data-field-type', type);
		var badge = card.querySelector('[data-nestform-type-badge]');
		if (badge) {
			badge.textContent =
				(select.options[select.selectedIndex] && select.options[select.selectedIndex].text) ||
				typeLabels[type] ||
				type;
		}
		syncShowControls(card, type);
		syncPhonePicker(card);
		syncOptionsHelp(card, type);
		if (type === 'payment') {
			var payAmount = card.querySelector('[data-nestform-payment-amount]');
			var payCurrency = card.querySelector('[data-nestform-payment-currency]');
			if (payAmount && !String(payAmount.value || '').trim()) {
				payAmount.value = '9.99';
			}
			if (payCurrency && !String(payCurrency.value || '').trim()) {
				payCurrency.value = 'USD';
			}
			syncCardSummary(card);
		}
		var phInput = card.querySelector('[data-nestform-placeholder]');
		if (phInput) {
			phInput.placeholder =
				type === 'select'
					? i18n.selectPlaceholder || 'Select...'
					: i18n.fieldPlaceholder || 'Optional hint';
		}
		var reqWrap = card.querySelector('[data-nestform-required-wrap]');
		if (reqWrap) {
			var hideReq = isLayoutType(type);
			reqWrap.hidden = hideReq;
			if (hideReq) {
				reqWrap.removeAttribute('title');
				var req = card.querySelector('[data-nestform-required]');
				if (req) {
					req.checked = false;
				}
				syncRequiredPill(card);
			} else if (!reqWrap.getAttribute('title')) {
				reqWrap.setAttribute('title', i18n.toggleRequired || 'Toggle required');
			}
		}
		scheduleFieldPreview(card);
	}

	function syncTitle(card) {
		var label = card.querySelector('[data-nestform-label]');
		var name = card.querySelector('[data-nestform-name]');
		var title = card.querySelector('[data-nestform-card-title]');
		var meta = card.querySelector('[data-nestform-card-meta]');
		if (title) {
			title.textContent =
				(label && stripTags(label.value)) ||
				(name && name.value.trim()) ||
				i18n.untitled ||
				'Untitled field';
		}
		if (meta) {
			var type = card.getAttribute('data-field-type') || '';
			if (isLayoutType(type)) {
				meta.textContent = i18n.layout || 'Layout';
			} else {
				meta.textContent = name && name.value.trim() ? '{' + name.value.trim() + '}' : '';
			}
		}
		syncCardSummary(card);
	}

	function operatorLabel(op) {
		var ops = (window.nestformAdmin && window.nestformAdmin.operators) || {};
		return ops[op] || op;
	}

	function syncCardSummary(card) {
		var summary = card.querySelector('[data-nestform-card-summary]');
		if (!summary) {
			return;
		}
		var type = card.getAttribute('data-field-type') || '';
		var chips = [];
		var enabledInput = card.querySelector('[data-nestform-enabled]');
		if (enabledInput && !enabledInput.checked) {
			chips.push({
				text: i18n.fieldHiddenChip || 'Hidden',
				mod: 'hidden',
			});
		}
		if (type === 'payment') {
			var sumAmount = card.querySelector('[data-nestform-payment-amount]');
			var sumCurrency = card.querySelector('[data-nestform-payment-currency]');
			var sumA = sumAmount ? String(sumAmount.value || '').trim() : '';
			var sumC = sumCurrency ? String(sumCurrency.value || '').trim().toUpperCase() : '';
			if (sumA || sumC) {
				chips.push({ text: (sumA || '9.99') + ' ' + (sumC || 'USD') });
			}
		}
		var width = card.querySelector('[data-nestform-width], select[name*="[width]"]');
		var widthVal = width ? String(width.value || 'full') : 'full';
		if (widthVal === 'half') {
			chips.push({ text: '½' });
		} else if (widthVal === 'third') {
			chips.push({ text: '⅓' });
		} else if (widthVal === 'two_thirds') {
			chips.push({ text: '⅔' });
		} else if (widthVal === 'quarter') {
			chips.push({ text: '¼' });
		} else if (widthVal === 'custom') {
			var customInput = card.querySelector('[data-nestform-width-custom]');
			var pct = customInput ? parseInt(String(customInput.value || '50'), 10) : 50;
			if (!isFinite(pct) || pct < 1 || pct > 100) {
				pct = 50;
			}
			chips.push({ text: pct + '%' });
		}
		if (!isLayoutType(type)) {
			var condField = card.querySelector('[data-nestform-condition-field]');
			var condOp = card.querySelector('[data-nestform-condition-op]');
			var condVal = card.querySelector('[data-nestform-condition-value]');
			var watch = condField ? condField.value : '';
			if (watch) {
				var op = condOp ? condOp.value : 'equals';
				var val = condVal ? String(condVal.value || '').trim() : '';
				var text = (i18n.ifPrefix || 'if') + ' {' + watch + '} ' + operatorLabel(op);
				if (op !== 'empty' && op !== 'not_empty' && val) {
					text += ' “' + val + '”';
				}
				chips.push({ text: text });
			}
		}
		summary.textContent = '';
		if (!chips.length) {
			summary.hidden = true;
			return;
		}
		summary.hidden = false;
		chips.forEach(function (chip) {
			var item = typeof chip === 'string' ? { text: chip } : chip || {};
			var span = document.createElement('span');
			span.className =
				'nestform-card__chip' + (item.mod ? ' nestform-card__chip--' + item.mod : '');
			span.textContent = item.text || '';
			summary.appendChild(span);
		});
	}

	var fieldPreviewTimers = typeof WeakMap === 'function' ? new WeakMap() : null;

	function scheduleFieldPreview(card) {
		if (!card || card.classList.contains('is-collapsed')) {
			return;
		}
		if (!fieldPreviewTimers) {
			syncFieldPreview(card);
			return;
		}
		var prev = fieldPreviewTimers.get(card);
		if (prev) {
			window.clearTimeout(prev);
		}
		fieldPreviewTimers.set(
			card,
			window.setTimeout(function () {
				syncFieldPreview(card);
			}, 120)
		);
	}

	function readVisibleControlValue(card, selector) {
		var el = card.querySelector(selector);
		if (!el || el.disabled || el.hidden) {
			return '';
		}
		var wrap = el.closest('[data-nestform-show], [hidden]');
		if (wrap && wrap.hidden) {
			return '';
		}
		return String(el.value || '');
	}

	function parsePreviewOptions(raw) {
		return String(raw || '')
			.split(/\r?\n/)
			.map(function (line) {
				return String(line || '').trim();
			})
			.filter(Boolean)
			.map(function (line) {
				var parts = line.split('|');
				return String(parts[0] || line).trim() || line;
			});
	}

	function previewLabelNode(label, required) {
		var el = document.createElement('span');
		el.className = 'nestform-live-preview__label';
		el.textContent = label || (i18n.untitled || 'Untitled field');
		if (required) {
			var req = document.createElement('em');
			req.style.fontStyle = 'normal';
			req.style.color = 'var(--nestform-danger, #b32d2e)';
			req.style.marginLeft = '4px';
			req.textContent = '*';
			req.title = i18n.fieldPreviewRequired || 'required';
			el.appendChild(req);
		}
		return el;
	}

	function previewControlNode(text, area) {
		var el = document.createElement('span');
		el.className =
			'nestform-live-preview__control' + (area ? ' nestform-live-preview__control--area' : '');
		el.textContent = text || '';
		return el;
	}

	function previewHintNode(text) {
		if (!text) {
			return null;
		}
		var el = document.createElement('span');
		el.className = 'nestform-live-preview__hint';
		el.textContent = text;
		return el;
	}

	function previewChoicesNode(options, mode) {
		var list = document.createElement('ul');
		list.className = 'nestform-card__preview-choices';
		var items = options.length ? options.slice(0, 6) : ['Yes', 'No'];
		items.forEach(function (opt) {
			var li = document.createElement('li');
			li.className = 'nestform-card__preview-choice';
			var mark = document.createElement('span');
			mark.className =
				'nestform-card__preview-choice-mark nestform-card__preview-choice-mark--' +
				(mode === 'check' ? 'check' : 'radio');
			mark.setAttribute('aria-hidden', 'true');
			var label = document.createElement('span');
			label.textContent = opt;
			li.appendChild(mark);
			li.appendChild(label);
			list.appendChild(li);
		});
		if (options.length > 6) {
			var more = document.createElement('li');
			more.className = 'nestform-card__preview-choice';
			more.textContent = '…';
			list.appendChild(more);
		}
		return list;
	}

	function previewStarsNode(count, active) {
		var wrap = document.createElement('div');
		wrap.className = 'nestform-card__preview-stars';
		var n = Math.max(1, Math.min(10, count || 5));
		var on = Math.max(0, Math.min(n, active == null ? 0 : active));
		for (var i = 1; i <= n; i++) {
			var star = document.createElement('span');
			star.className =
				'nestform-card__preview-star' + (i <= on ? ' is-on' : '');
			star.textContent = '★';
			star.setAttribute('aria-hidden', 'true');
			wrap.appendChild(star);
		}
		return wrap;
	}

	function previewScaleNode(min, max, minLabel, maxLabel, value) {
		var wrap = document.createElement('div');
		wrap.className = 'nestform-card__preview-scale';
		var row = document.createElement('div');
		row.className = 'nestform-card__preview-scale-row';
		var a = Math.min(min, max);
		var b = Math.max(min, max);
		var active = value != null && value !== '' ? Number(value) : NaN;
		for (var i = a; i <= b; i++) {
			var btn = document.createElement('span');
			btn.className =
				'nestform-card__preview-scale-btn' +
				(!isNaN(active) && active === i ? ' is-on' : '');
			btn.textContent = String(i);
			row.appendChild(btn);
		}
		wrap.appendChild(row);
		if (minLabel || maxLabel) {
			var caps = document.createElement('div');
			caps.className = 'nestform-card__preview-scale-caps';
			var left = document.createElement('span');
			left.textContent = minLabel || '';
			var right = document.createElement('span');
			right.textContent = maxLabel || '';
			caps.appendChild(left);
			caps.appendChild(right);
			wrap.appendChild(caps);
		}
		return wrap;
	}

	function previewRangeNode(min, max, value) {
		var wrap = document.createElement('div');
		wrap.className = 'nestform-card__preview-range';
		var track = document.createElement('span');
		track.className = 'nestform-card__preview-range-track';
		var fill = document.createElement('span');
		fill.className = 'nestform-card__preview-range-fill';
		var a = Number(min);
		var b = Number(max);
		if (!isFinite(a)) a = 0;
		if (!isFinite(b)) b = 100;
		if (b === a) b = a + 1;
		var v = value !== '' && isFinite(Number(value)) ? Number(value) : a;
		var pct = Math.max(0, Math.min(100, ((v - a) / (b - a)) * 100));
		fill.style.width = pct + '%';
		track.appendChild(fill);
		var thumb = document.createElement('span');
		thumb.className = 'nestform-card__preview-range-thumb';
		thumb.style.left = pct + '%';
		track.appendChild(thumb);
		wrap.appendChild(track);
		var meta = document.createElement('div');
		meta.className = 'nestform-card__preview-scale-caps';
		meta.appendChild(document.createTextNode(String(a)));
		var val = document.createElement('strong');
		val.textContent = String(v);
		meta.appendChild(val);
		meta.appendChild(document.createTextNode(String(b)));
		wrap.appendChild(meta);
		return wrap;
	}

	function previewSignatureNode() {
		var pad = document.createElement('div');
		pad.className = 'nestform-card__preview-signature';
		pad.textContent = i18n.fieldPreviewSignature || 'Sign here';
		return pad;
	}

	function previewRankingNode(options) {
		var list = document.createElement('ol');
		list.className = 'nestform-card__preview-ranking';
		var items = options.length ? options.slice(0, 6) : ['First', 'Second', 'Third'];
		items.forEach(function (opt, index) {
			var li = document.createElement('li');
			li.className = 'nestform-card__preview-ranking-item';
			var num = document.createElement('span');
			num.className = 'nestform-card__preview-ranking-num';
			num.textContent = String(index + 1);
			var text = document.createElement('span');
			text.textContent = opt;
			li.appendChild(num);
			li.appendChild(text);
			list.appendChild(li);
		});
		return list;
	}

	function previewMatrixNode(raw) {
		var wrap = document.createElement('div');
		wrap.className = 'nestform-card__preview-matrix';
		var parts = String(raw || '').split(/\n---\n|\n---\r?\n|\r?\n---\r?\n/);
		var rows = parsePreviewOptions(parts[0] || '');
		var cols = parsePreviewOptions(parts[1] || '');
		if (!rows.length) {
			rows = ['Row 1', 'Row 2'];
		}
		if (!cols.length) {
			cols = ['Poor', 'Fair', 'Good'];
		}
		rows = rows.slice(0, 4);
		cols = cols.slice(0, 5);
		var table = document.createElement('div');
		table.className = 'nestform-card__preview-matrix-table';
		var colsTemplate = 'minmax(72px, 1.4fr) repeat(' + cols.length + ', minmax(28px, 1fr))';
		var head = document.createElement('div');
		head.className = 'nestform-card__preview-matrix-row nestform-card__preview-matrix-row--head';
		head.style.gridTemplateColumns = colsTemplate;
		head.appendChild(document.createElement('span'));
		cols.forEach(function (col) {
			var cell = document.createElement('span');
			cell.className = 'nestform-card__preview-matrix-col';
			cell.textContent = col;
			head.appendChild(cell);
		});
		table.appendChild(head);
		rows.forEach(function (rowLabel) {
			var row = document.createElement('div');
			row.className = 'nestform-card__preview-matrix-row';
			row.style.gridTemplateColumns = colsTemplate;
			var label = document.createElement('span');
			label.className = 'nestform-card__preview-matrix-label';
			label.textContent = rowLabel;
			row.appendChild(label);
			cols.forEach(function () {
				var mark = document.createElement('span');
				mark.className =
					'nestform-card__preview-choice-mark nestform-card__preview-choice-mark--radio';
				mark.setAttribute('aria-hidden', 'true');
				row.appendChild(mark);
			});
			table.appendChild(row);
		});
		wrap.appendChild(table);
		return wrap;
	}

	function parseRatingMax(options) {
		var lines = parsePreviewOptions(options);
		if (lines.length === 1 && /^\d+$/.test(lines[0])) {
			return Math.max(1, Math.min(10, parseInt(lines[0], 10)));
		}
		if (lines.length > 1) {
			return Math.max(1, Math.min(10, lines.length));
		}
		return 5;
	}

	function parseScaleConfig(options) {
		var lines = parsePreviewOptions(options);
		var min = 1;
		var max = 5;
		var minLabel = '';
		var maxLabel = '';
		if (lines.length >= 2 && isFinite(Number(lines[0])) && isFinite(Number(lines[1]))) {
			min = Number(lines[0]);
			max = Number(lines[1]);
			minLabel = lines[2] || '';
			maxLabel = lines[3] || '';
		} else if (lines.length >= 1 && isFinite(Number(lines[0]))) {
			max = Number(lines[0]);
		}
		if (max < min) {
			var tmp = min;
			min = max;
			max = tmp;
		}
		if (max - min > 12) {
			max = min + 12;
		}
		return { min: min, max: max, minLabel: minLabel, maxLabel: maxLabel };
	}

	function parseRangeConfig(options) {
		var lines = parsePreviewOptions(options);
		var min = lines[0] != null && isFinite(Number(lines[0])) ? Number(lines[0]) : 0;
		var max = lines[1] != null && isFinite(Number(lines[1])) ? Number(lines[1]) : 100;
		if (max < min) {
			var tmp = min;
			min = max;
			max = tmp;
		}
		return { min: min, max: max };
	}

	function syncFieldPreview(card) {
		if (!card) {
			return;
		}
		var stage = card.querySelector('[data-nestform-field-preview-stage]');
		if (!stage) {
			return;
		}
		stage.textContent = '';
		var type = card.getAttribute('data-field-type') || 'text';
		var labelInput = card.querySelector('[data-nestform-label]');
		var label = labelInput ? stripTags(labelInput.value) : '';
		var requiredEl = card.querySelector('[data-nestform-required]');
		var required = !!(requiredEl && requiredEl.checked && !isLayoutType(type));
		var placeholder = readVisibleControlValue(card, '[data-nestform-placeholder]');
		var help = '';
		var helpInput = card.querySelector('[data-nestform-description]');
		if (helpInput && !helpInput.disabled) {
			help = String(helpInput.value || '').trim();
		}
		var defaultVal = '';
		var defaultInput = card.querySelector(
			'[data-nestform-show="default"] input, input[name*="[default]"]:not([data-nestform-image-id]):not([data-nestform-file-max])'
		);
		if (defaultInput && !defaultInput.disabled && defaultInput.type !== 'hidden') {
			defaultVal = String(defaultInput.value || '').trim();
		}
		var width = card.querySelector('[data-nestform-width], select[name*="[width]"]');
		var widthVal = width ? String(width.value || 'full') : 'full';
		var widthClass = 'full';
		var widthStyle = '';
		if (widthVal === 'half') {
			widthClass = 'half';
			widthStyle = '--nest-form-field-basis:50%;--nest-form-field-ratio:0.5';
		} else if (widthVal === 'third') {
			widthClass = 'third';
			widthStyle = '--nest-form-field-basis:33.333%;--nest-form-field-ratio:0.33333';
		} else if (widthVal === 'two_thirds') {
			widthClass = 'two-thirds';
			widthStyle =
				'--nest-form-field-basis:66.667%;--nest-form-field-ratio:0.66667';
		} else if (widthVal === 'quarter') {
			widthClass = 'quarter';
			widthStyle = '--nest-form-field-basis:25%;--nest-form-field-ratio:0.25';
		} else if (widthVal === 'custom') {
			widthClass = 'custom';
			var customW = card.querySelector('[data-nestform-width-custom]');
			var customPct = customW ? parseInt(String(customW.value || '50'), 10) : 50;
			if (!isFinite(customPct) || customPct < 1 || customPct > 100) {
				customPct = 50;
			}
			widthStyle =
				'--nest-form-field-basis:' +
				customPct +
				'%;--nest-form-field-ratio:' +
				String(Math.round((customPct / 100) * 1e5) / 1e5);
		}
		var field = document.createElement('div');
		field.className =
			'nestform-live-preview__field' +
			(widthClass !== 'full' ? ' nestform-live-preview__field--' + widthClass : '');
		if (widthStyle) {
			field.setAttribute('style', widthStyle);
		}

		if (type === 'hidden') {
			var hiddenNote = document.createElement('p');
			hiddenNote.className = 'nestform-card__preview-empty';
			hiddenNote.textContent = i18n.fieldPreviewHidden || 'Hidden field — not shown on the form';
			stage.appendChild(hiddenNote);
			return;
		}

		if (type === 'divider') {
			var hr = document.createElement('hr');
			hr.className = 'nestform-card__preview-divider';
			hr.setAttribute('aria-label', i18n.fieldPreviewDivider || 'Divider');
			stage.appendChild(hr);
			return;
		}

		if (type === 'spacer') {
			var spacerSize = readVisibleControlValue(card, '[data-nestform-show="spacer-size"] select') || 'm';
			var spacer = document.createElement('span');
			spacer.className = 'nestform-card__preview-spacer nestform-card__preview-spacer--' + spacerSize;
			spacer.title = i18n.fieldPreviewSpacer || 'Spacer';
			stage.appendChild(spacer);
			return;
		}

		if (type === 'heading') {
			var level = readVisibleControlValue(card, '[data-nestform-show="heading-level"] select') || 'h2';
			var heading = document.createElement('p');
			heading.className =
				'nestform-card__preview-heading nestform-card__preview-heading--' + level;
			heading.textContent = label || (i18n.untitled || 'Untitled field');
			stage.appendChild(heading);
			return;
		}

		if (type === 'paragraph') {
			var paraRaw = readVisibleControlValue(card, '[data-nestform-show="paragraph-text"] textarea');
			var para = document.createElement('p');
			para.className = 'nestform-card__preview-paragraph';
			para.textContent = paraRaw || label || (i18n.fieldPreviewEmpty || '');
			stage.appendChild(para);
			return;
		}

		if (type === 'html') {
			var htmlNote = document.createElement('p');
			htmlNote.className = 'nestform-card__preview-empty';
			htmlNote.textContent =
				(label ? label + ' — ' : '') + (i18n.fieldPreviewHtml || 'HTML block');
			stage.appendChild(htmlNote);
			return;
		}

		if (type === 'image') {
			var imgWrap = document.createElement('div');
			imgWrap.className = 'nestform-card__preview-image';
			var previewImg = card.querySelector('[data-nestform-image-preview] img');
			if (previewImg && previewImg.getAttribute('src')) {
				var clone = document.createElement('img');
				clone.src = previewImg.getAttribute('src');
				clone.alt = help || label || '';
				imgWrap.appendChild(clone);
			} else {
				imgWrap.textContent = help || i18n.fieldPreviewImage || 'Image';
			}
			stage.appendChild(imgWrap);
			return;
		}

		if (type === 'repeater') {
			field.appendChild(previewLabelNode(label, required));
			var row = document.createElement('div');
			row.className = 'nestform-card__preview-repeater';
			var subs = card.querySelectorAll('[data-nestform-subfield]');
			if (!subs.length) {
				var emptyRep = document.createElement('p');
				emptyRep.className = 'nestform-card__preview-empty';
				emptyRep.textContent = i18n.fieldPreviewEmpty || '';
				field.appendChild(emptyRep);
			} else {
				Array.prototype.forEach.call(subs, function (sub, index) {
					var col = document.createElement('div');
					col.className = 'nestform-card__preview-repeater-col';
					var subLabel = sub.querySelector('[data-nestform-subfield-label]');
					var subName = sub.querySelector('[data-nestform-subfield-name]');
					var title =
						(subLabel && String(subLabel.value || '').trim()) ||
						(subName && String(subName.value || '').trim()) ||
						((i18n.subColFallback || 'Column %d').replace('%d', String(index + 1)));
					col.appendChild(previewLabelNode(title, false));
					col.appendChild(previewControlNode(''));
					row.appendChild(col);
				});
				field.appendChild(row);
			}
			var repHint = previewHintNode(i18n.fieldPreviewRepeater || 'Repeater row');
			if (repHint) {
				field.appendChild(repHint);
			}
			stage.appendChild(field);
			return;
		}

		field.appendChild(previewLabelNode(label, required));

		if (type === 'textarea') {
			field.appendChild(previewControlNode(placeholder || defaultVal, true));
		} else if (type === 'select') {
			field.appendChild(
				previewControlNode(
					placeholder ||
						parsePreviewOptions(readVisibleControlValue(card, '[data-nestform-show="options"] textarea'))[0] ||
						(i18n.selectPlaceholder || 'Select...')
				)
			);
		} else if (type === 'radio' || type === 'checkboxes') {
			field.appendChild(
				previewChoicesNode(
					parsePreviewOptions(readVisibleControlValue(card, '[data-nestform-show="options"] textarea')),
					type === 'checkboxes' ? 'check' : 'radio'
				)
			);
		} else if (type === 'checkbox' || type === 'acceptance') {
			field.textContent = '';
			field.appendChild(previewChoicesNode([label || 'Accept'], 'check'));
		} else if (type === 'file') {
			field.appendChild(previewControlNode(i18n.fieldPreviewFile || 'Choose files…'));
		} else if (type === 'rating') {
			var ratingOpts = readVisibleControlValue(card, '[data-nestform-show="options"] textarea');
			var ratingMax = parseRatingMax(ratingOpts);
			var ratingActive =
				defaultVal !== '' && isFinite(Number(defaultVal))
					? Number(defaultVal)
					: Math.min(3, ratingMax);
			field.appendChild(previewStarsNode(ratingMax, ratingActive));
		} else if (type === 'nps') {
			field.appendChild(previewScaleNode(0, 10, 'Not likely', 'Very likely', defaultVal));
		} else if (type === 'scale') {
			var scaleCfg = parseScaleConfig(
				readVisibleControlValue(card, '[data-nestform-show="options"] textarea')
			);
			field.appendChild(
				previewScaleNode(
					scaleCfg.min,
					scaleCfg.max,
					scaleCfg.minLabel,
					scaleCfg.maxLabel,
					defaultVal
				)
			);
		} else if (type === 'range') {
			var rangeCfg = parseRangeConfig(
				readVisibleControlValue(card, '[data-nestform-show="options"] textarea')
			);
			field.appendChild(previewRangeNode(rangeCfg.min, rangeCfg.max, defaultVal));
		} else if (type === 'ranking') {
			field.appendChild(
				previewRankingNode(
					parsePreviewOptions(readVisibleControlValue(card, '[data-nestform-show="options"] textarea'))
				)
			);
		} else if (type === 'calculated') {
			var formula = readVisibleControlValue(card, '[data-nestform-show="formula"] textarea');
			field.appendChild(previewControlNode(formula || (i18n.fieldPreviewCalc || 'Calculated value')));
		} else if (type === 'matrix') {
			field.appendChild(
				previewMatrixNode(readVisibleControlValue(card, '[data-nestform-show="options"] textarea'))
			);
		} else if (type === 'signature') {
			field.appendChild(previewSignatureNode());
		} else if (type === 'payment') {
			var payAmountEl = card.querySelector('[data-nestform-payment-amount]');
			var payCurrencyEl = card.querySelector('[data-nestform-payment-currency]');
			var payAmount = payAmountEl ? String(payAmountEl.value || '').trim() : '';
			var payCurrency = payCurrencyEl ? String(payCurrencyEl.value || '').trim().toUpperCase() : '';
			if (!payAmount) {
				payAmount = '9.99';
			}
			if (!payCurrency) {
				payCurrency = 'USD';
			}
			field.appendChild(
				previewControlNode(
					(i18n.fieldPreviewPayment || 'Card payment') + ' · ' + payAmount + ' ' + payCurrency
				)
			);
		} else {
			field.appendChild(previewControlNode(placeholder || defaultVal));
		}

		var hint = previewHintNode(help);
		if (hint) {
			field.appendChild(hint);
		}
		stage.appendChild(field);
	}

	function collectFieldNameOptions(root, excludeName) {
		var options = [];
		var seen = {};
		allFieldCards(root).forEach(function (card) {
			var type = card.getAttribute('data-field-type') || '';
			if (isLayoutType(type)) {
				return;
			}
			var nameInput = card.querySelector('[data-nestform-name]');
			var labelInput = card.querySelector('[data-nestform-label]');
			var name = nameInput ? nameInput.value.trim() : '';
			if (!name || seen[name] || name === excludeName) {
				return;
			}
			seen[name] = true;
			var label = labelInput ? stripTags(labelInput.value) : '';
			options.push({
				value: name,
				label: label ? label + ' {' + name + '}' : '{' + name + '}',
			});
		});
		return options;
	}

	function fillSelectOptions(select, options, emptyLabel, current) {
		if (!select) {
			return;
		}
		var keep = current != null ? String(current) : String(select.value || '');
		select.textContent = '';
		var empty = document.createElement('option');
		empty.value = '';
		empty.textContent = emptyLabel || '—';
		select.appendChild(empty);
		options.forEach(function (opt) {
			var option = document.createElement('option');
			option.value = opt.value;
			option.textContent = opt.label;
			select.appendChild(option);
		});
		if (keep && !Array.prototype.some.call(select.options, function (o) { return o.value === keep; })) {
			var orphan = document.createElement('option');
			orphan.value = keep;
			orphan.textContent = '{' + keep + '}';
			select.appendChild(orphan);
		}
		select.value = keep;
	}

	function syncAllFieldNameSelects(root) {
		var options = collectFieldNameOptions(root, '');
		allFieldCards(root).forEach(function (card) {
			var nameInput = card.querySelector('[data-nestform-name]');
			var selfName = nameInput ? nameInput.value.trim() : '';
			var filtered = options.filter(function (opt) {
				return opt.value !== selfName;
			});
			fillSelectOptions(
				card.querySelector('[data-nestform-condition-field]'),
				filtered,
				i18n.alwaysShow || '— Always show —',
				null
			);
			syncCardSummary(card);
		});
		fillSelectOptions(
			root.querySelector('[data-nestform-extra-condition-field]'),
			options,
			i18n.alwaysShow || '— Always show —',
			null
		);
		root.querySelectorAll('[data-nestform-auto-field]').forEach(function (sel) {
			fillSelectOptions(sel, options, '— Select —', null);
		});
		var branchField = root.querySelector('[data-nestform-branch-field]');
		if (branchField) {
			fillSelectOptions(branchField, options, i18n.pickField || '— Field —', null);
		}
		refreshBranchList(root);
	}

	function parseBranchRules(raw) {
		var lines = String(raw || '').split(/\r\n|\r|\n/);
		var out = [];
		lines.forEach(function (line) {
			line = String(line || '').trim();
			if (!line || line.charAt(0) === '#') {
				return;
			}
			var parts = line.split('|');
			if (parts.length < 5) {
				return;
			}
			out.push({
				from: String(parseInt(parts[0], 10) || 1),
				field: String(parts[1] || '').trim(),
				op: String(parts[2] || 'equals').trim(),
				value: String(parts[3] || '').trim(),
				to: String(parseInt(parts[4], 10) || 1),
			});
		});
		return out;
	}

	function writeBranchRules(root, rules) {
		var ta = root.querySelector('[data-nestform-branch-rules]');
		if (!ta) {
			return;
		}
		ta.value = rules
			.map(function (r) {
				return [r.from, r.field, r.op, r.value, r.to].join('|');
			})
			.join('\n');
	}

	function fillStepSelect(select, maxStep, current) {
		if (!select) {
			return;
		}
		var keep = current != null ? String(current) : String(select.value || '1');
		select.textContent = '';
		for (var s = 1; s <= maxStep; s++) {
			var opt = document.createElement('option');
			opt.value = String(s);
			opt.textContent = (i18n.step || 'Step') + ' ' + s;
			select.appendChild(opt);
		}
		select.value = keep;
	}

	function refreshBranchList(root) {
		var list = root.querySelector('[data-nestform-branch-list]');
		var ta = root.querySelector('[data-nestform-branch-rules]');
		if (!list || !ta) {
			return;
		}
		var rules = parseBranchRules(ta.value);
		var ops = (window.nestformAdmin && window.nestformAdmin.operators) || {};
		list.textContent = '';
		if (!rules.length) {
			var empty = document.createElement('li');
			empty.className = 'nestform-branch__empty';
			empty.textContent = i18n.noRules || 'No branch rules yet.';
			list.appendChild(empty);
			return;
		}
		rules.forEach(function (rule, index) {
			var li = document.createElement('li');
			li.className = 'nestform-branch__item';
			var text = document.createElement('span');
			text.className = 'nestform-branch__text';
			text.textContent =
				(i18n.fromStep || 'From') +
				' ' +
				rule.from +
				' · {' +
				rule.field +
				'} ' +
				(ops[rule.op] || rule.op) +
				(rule.op !== 'empty' && rule.op !== 'not_empty' && rule.value ? ' “' + rule.value + '”' : '') +
				' → ' +
				(i18n.toStep || 'To') +
				' ' +
				rule.to;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-branch__remove';
			btn.textContent = i18n.removeRule || 'Remove';
			btn.setAttribute('data-nestform-branch-remove', String(index));
			li.appendChild(text);
			li.appendChild(btn);
			list.appendChild(li);
		});
	}

	function syncBranchStepSelects(root) {
		var max = Math.max(2, root.querySelectorAll('[data-nestform-step-group]').length || 1);
		fillStepSelect(root.querySelector('[data-nestform-branch-from]'), max, null);
		fillStepSelect(root.querySelector('[data-nestform-branch-to]'), max, null);
	}

	function initBranchUi(root) {
		var ui = root.querySelector('[data-nestform-branch-ui]');
		if (!ui) {
			return;
		}
		syncBranchStepSelects(root);
		refreshBranchList(root);

		function eventElement(event) {
			var target = event && event.target;
			if (!target) {
				return null;
			}
			if (target.nodeType !== 1) {
				target = target.parentElement;
			}
			return target && target.closest ? target : null;
		}

		function markInvalid(el, on) {
			if (!el) {
				return;
			}
			el.classList.toggle('is-invalid', !!on);
			if (on) {
				el.setAttribute('aria-invalid', 'true');
			} else {
				el.removeAttribute('aria-invalid');
			}
		}

		ui.addEventListener('click', function (event) {
			var target = eventElement(event);
			if (!target) {
				return;
			}
			var add = target.closest('[data-nestform-branch-add]');
			if (add) {
				event.preventDefault();
				event.stopPropagation();
				syncBranchStepSelects(root);
				syncAllFieldNameSelects(root);
				var from = ui.querySelector('[data-nestform-branch-from]');
				var field = ui.querySelector('[data-nestform-branch-field]');
				var op = ui.querySelector('[data-nestform-branch-op]');
				var value = ui.querySelector('[data-nestform-branch-value]');
				var to = ui.querySelector('[data-nestform-branch-to]');
				var hint = ui.querySelector('[data-nestform-branch-hint]');
				markInvalid(field, false);
				if (hint) {
					hint.hidden = true;
					hint.textContent = '';
				}
				if (!from || !field || !op || !to) {
					return;
				}
				if (!field.value) {
					markInvalid(field, true);
					field.focus();
					if (hint) {
						hint.hidden = false;
						hint.textContent =
							i18n.pickFieldHint ||
							'Choose a field for this branch rule.';
					}
					return;
				}
				var ta = root.querySelector('[data-nestform-branch-rules]');
				var rules = parseBranchRules(ta ? ta.value : '');
				rules.push({
					from: from.value || '1',
					field: field.value,
					op: op.value || 'equals',
					value: value ? String(value.value || '').trim() : '',
					to: to.value || '1',
				});
				writeBranchRules(root, rules);
				if (value) {
					value.value = '';
				}
				markInvalid(field, false);
				refreshBranchList(root);
				return;
			}
			var remove = target.closest('[data-nestform-branch-remove]');
			if (remove) {
				event.preventDefault();
				var idx = parseInt(remove.getAttribute('data-nestform-branch-remove') || '-1', 10);
				var ta2 = root.querySelector('[data-nestform-branch-rules]');
				var rules2 = parseBranchRules(ta2 ? ta2.value : '');
				if (idx >= 0 && idx < rules2.length) {
					rules2.splice(idx, 1);
					writeBranchRules(root, rules2);
					refreshBranchList(root);
				}
			}
		});

		var fieldSelect = ui.querySelector('[data-nestform-branch-field]');
		if (fieldSelect) {
			fieldSelect.addEventListener('change', function () {
				markInvalid(fieldSelect, false);
				var hint = ui.querySelector('[data-nestform-branch-hint]');
				if (hint) {
					hint.hidden = true;
				}
			});
		}
	}

	function syncMailBodyEditor() {
		if (!window.tinymce) {
			return;
		}
		var id = 'nestform_mail_body_template';
		var ed = tinymce.get(id);
		if (ed && !ed.isHidden()) {
			ed.save();
		}
	}

	function initMailBodyEditor() {
		var id = 'nestform_mail_body_template';
		var wrap = document.getElementById('wp-' + id + '-wrap');
		if (!wrap) {
			return;
		}
		if (window.tinymce && tinymce.get(id)) {
			return;
		}
		if (window.switchEditors) {
			switchEditors.go(id, 'tmce');
		}
	}

	function initStickySave(root) {
		var dirtyEl = root.querySelector('[data-nestform-dirty]');
		var form = root.closest('form') || document.getElementById('post');
		var dirty = false;

		function setDirty(on) {
			dirty = !!on;
			if (dirtyEl) {
				dirtyEl.hidden = !dirty;
			}
			document.body.classList.toggle('nestform-is-dirty', dirty);
		}

		if (form) {
			form.addEventListener('input', function () {
				setDirty(true);
			});
			form.addEventListener('change', function () {
				setDirty(true);
			});
			form.addEventListener('submit', function () {
				syncMailBodyEditor();
				setDirty(false);
			});
		}

		['#publish', '#save-post', '[name="save"]'].forEach(function (sel) {
			document.querySelectorAll(sel).forEach(function (btn) {
				btn.addEventListener('click', syncMailBodyEditor);
			});
		});

		document.addEventListener('keydown', function (event) {
			if (!(event.ctrlKey || event.metaKey) || event.shiftKey || event.altKey) {
				return;
			}
			if (String(event.key).toLowerCase() !== 's') {
				return;
			}
			if (!document.body.classList.contains('post-type-nestform')) {
				return;
			}
			var target = event.target;
			if (target && target.closest && target.closest('.media-modal, .thimbleform-pro-modal')) {
				return;
			}
			event.preventDefault();
			var btn =
				root.querySelector('[data-nestform-save].nestform-btn--primary') ||
				root.querySelector('[data-nestform-save]');
			if (btn) {
				btn.click();
			}
		});
	}

	function syncRequiredPill(card) {
		var checkbox = card.querySelector('[data-nestform-required]');
		var wrap = card.querySelector('[data-nestform-required-wrap]');
		var on = !!(checkbox && checkbox.checked);
		if (wrap) {
			wrap.classList.toggle('is-on', on);
		}
	}

	function syncWidthCustomWrap(card) {
		var select = card.querySelector('[data-nestform-width]');
		var wrap = card.querySelector('[data-nestform-width-custom-wrap]');
		if (!wrap) {
			return;
		}
		var isCustom = !!(select && select.value === 'custom');
		wrap.hidden = !isCustom;
	}

	function syncEnabledState(card) {
		var checkbox = card.querySelector('[data-nestform-enabled]');
		var wrap = card.querySelector('[data-nestform-enabled-wrap]');
		var on = !(checkbox && !checkbox.checked);
		card.classList.toggle('is-disabled', !on);
		if (wrap) {
			wrap.classList.toggle('is-off', !on);
			var tip = on
				? i18n.hideField || 'Hide from form'
				: i18n.showField || 'Show on form';
			wrap.setAttribute('data-nestform-tooltip', tip);
			wrap.setAttribute('aria-label', tip);
			wrap.removeAttribute('title');
			var sr = wrap.querySelector('.screen-reader-text');
			if (sr) {
				sr.textContent = tip;
			}
		}
		syncCardSummary(card);
	}

	function syncStepBadge(card) {
		var stepInput = card.querySelector('[data-nestform-step]');
		var badge = card.querySelector('[data-nestform-step-badge]');
		var move = card.querySelector('[data-nestform-move-step]');
		if (!stepInput) {
			return;
		}
		var step = stepInput.value || '1';
		card.setAttribute('data-field-step', step);
		if (badge) {
			badge.textContent = (i18n.step || 'Step') + ' ' + step;
		}
		if (move && move.value !== step) {
			move.value = step;
		}
	}

	function syncEmpty(root) {
		root.querySelectorAll('[data-nestform-step-group]').forEach(function (group) {
			var list = group.querySelector('[data-nestform-fields]');
			var empty = group.querySelector('[data-nestform-empty]');
			if (list && empty) {
				empty.hidden = list.children.length > 0;
			}
		});
	}

	function syncStepLabelsTextarea(root) {
		var ta = root.querySelector('[data-nestform-step-labels]');
		if (!ta) {
			return;
		}
		var lines = [];
		root.querySelectorAll('[data-nestform-step-group]').forEach(function (group) {
			var title = group.querySelector('[data-nestform-step-title]');
			lines.push(title ? title.value.trim() : '');
		});
		while (lines.length && lines[lines.length - 1] === '') {
			lines.pop();
		}
		ta.value = lines.join('\n');
	}

	function syncNav(root) {
		var nav = root.querySelector('[data-nestform-step-nav]');
		if (!nav) {
			return;
		}
		nav.innerHTML = '';
		var active = getActiveStep(root);
		root.querySelectorAll('[data-nestform-step-group]').forEach(function (group) {
			var step = group.getAttribute('data-step') || '1';
			var titleInput = group.querySelector('[data-nestform-step-title]');
			var list = group.querySelector('[data-nestform-fields]');
			var title =
				(titleInput && titleInput.value.trim()) ||
				(i18n.step || 'Step') + ' ' + step;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className =
				'nestform-step-nav__btn' + (String(active) === String(step) ? ' is-active' : '');
			btn.setAttribute('data-nestform-step-tab', step);
			btn.setAttribute('role', 'tab');
			btn.setAttribute('aria-selected', String(active) === String(step) ? 'true' : 'false');
			btn.innerHTML =
				'<span class="nestform-step-nav__index">' +
				step +
				'</span>' +
				'<span class="nestform-step-nav__title" data-nestform-step-nav-title></span>' +
				'<span class="nestform-step-nav__count" data-nestform-step-nav-count></span>';
			btn.querySelector('[data-nestform-step-nav-title]').textContent = title;
			btn.querySelector('[data-nestform-step-nav-count]').textContent = list
				? String(list.children.length)
				: '0';
			nav.appendChild(btn);
		});
	}

	function refreshMoveOptions(root) {
		var count = root.querySelectorAll('[data-nestform-step-group]').length;
		allFieldCards(root).forEach(function (card) {
			var move = card.querySelector('[data-nestform-move-step]');
			var wrap = card.querySelector('[data-nestform-move-step-wrap]');
			if (wrap) {
				wrap.hidden = !isStepsMode(root) || count < 2;
			}
			if (!move) {
				syncSections(card);
				return;
			}
			var current = card.querySelector('[data-nestform-step]');
			var curVal = current ? current.value : '1';
			move.innerHTML = '';
			for (var s = 1; s <= count; s++) {
				var opt = document.createElement('option');
				opt.value = String(s);
				opt.textContent = (i18n.step || 'Step') + ' ' + s;
				if (String(s) === String(curVal)) {
					opt.selected = true;
				}
				move.appendChild(opt);
			}
			syncSections(card);
		});
	}

	function setFieldStep(card, step) {
		var input = card.querySelector('[data-nestform-step]');
		if (input) {
			input.value = String(step);
		}
		syncStepBadge(card);
	}

	function activateStep(root, step) {
		step = String(step);
		root.querySelectorAll('[data-nestform-step-group]').forEach(function (group) {
			var match = group.getAttribute('data-step') === step;
			group.classList.toggle('is-active', match);
			group.hidden = isStepsMode(root) ? !match : group.getAttribute('data-step') !== '1';
		});
		syncNav(root);
	}

	function renumberGroups(root) {
		root.querySelectorAll('[data-nestform-step-group]').forEach(function (group, index) {
			var step = index + 1;
			group.setAttribute('data-step', String(step));
			var list = group.querySelector('[data-nestform-fields]');
			if (list) {
				list.setAttribute('data-step', String(step));
			}
			var badge = group.querySelector('.nestform-step-group__badge');
			if (badge) {
				badge.textContent = (i18n.step || 'Step') + ' ' + step;
			}
			list &&
				list.querySelectorAll('[data-nestform-field]').forEach(function (card) {
					setFieldStep(card, step);
				});
		});
		var removers = root.querySelectorAll('[data-nestform-remove-step]');
		removers.forEach(function (btn) {
			btn.hidden = root.querySelectorAll('[data-nestform-step-group]').length <= 1;
		});
		syncStepLabelsTextarea(root);
		refreshMoveOptions(root);
		reindex(root);
		syncNav(root);
		syncEmpty(root);
	}

	function addStep(root) {
		var tpl = root.querySelector('[data-nestform-step-group-template]');
		var groups = root.querySelector('[data-nestform-step-groups]');
		if (!tpl || !groups) {
			return;
		}
		var next = groups.querySelectorAll('[data-nestform-step-group]').length + 1;
		var html = tpl.innerHTML.replace(/__STEP__/g, String(next));
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var node = wrap.firstElementChild;
		if (!node) {
			return;
		}
		groups.appendChild(node);
		renumberGroups(root);
		activateStep(root, next);
		syncBranchStepSelects(root);
	}

	function removeStep(root, group) {
		var groups = root.querySelectorAll('[data-nestform-step-group]');
		if (groups.length <= 1) {
			return;
		}
		var list = group.querySelector('[data-nestform-fields]');
		var fieldCount = list ? list.querySelectorAll('[data-nestform-field]').length : 0;
		var msg =
			fieldCount > 0
				? (i18n.confirmDelStepFields || 'Remove this step and its %d field(s)?').replace(
						'%d',
						String(fieldCount)
				  )
				: i18n.confirmDelStep || 'Remove this step?';
		if (!window.confirm(msg)) {
			return;
		}
		var step = parseInt(group.getAttribute('data-step') || '1', 10);
		if (list) {
			list.querySelectorAll('[data-nestform-field]').forEach(function (card) {
				card.remove();
			});
		}
		group.remove();
		renumberGroups(root);
		reindex(root);
		syncEmpty(root);
		syncNav(root);
		refreshMoveOptions(root);
		syncAllFieldNameSelects(root);
		syncBranchStepSelects(root);
		var remaining = root.querySelectorAll('[data-nestform-step-group]').length;
		activateStep(root, Math.min(Math.max(1, step - 1), remaining));
	}

	function setStepsMode(root, on) {
		var ws = getWorkspace(root);
		var extra = root.querySelector('[data-nestform-steps-extra]');
		var branch = root.querySelector('[data-nestform-steps-branch]');
		var setup = root.querySelector('[data-nestform-steps-setup]');
		var nav = root.querySelector('[data-nestform-step-nav]');
		var hint = root.querySelector('[data-nestform-add-hint]');
		var toggle = root.querySelector('.nestform-steps-setup__toggle');
		if (ws) {
			ws.setAttribute('data-mode', on ? 'steps' : 'flat');
		}
		root.setAttribute('data-steps-enabled', on ? '1' : '0');
		if (setup) {
			setup.classList.toggle('is-on', !!on);
		}
		if (toggle) {
			toggle.classList.toggle('is-on', !!on);
		}
		if (extra) {
			extra.hidden = !on;
		}
		if (branch) {
			branch.hidden = !on;
			if (!on) {
				branch.open = false;
			}
		}
		if (nav) {
			nav.hidden = !on;
			if (!on) {
				nav.innerHTML = '';
			}
		}
		if (hint) {
			hint.hidden = !on;
		}

		root.querySelectorAll('[data-nestform-step-head]').forEach(function (head) {
			head.hidden = !on;
		});

		if (on) {
			var groups = root.querySelectorAll('[data-nestform-step-group]');
			if (groups.length < 2) {
				addStep(root);
			}
			allFieldCards(root).forEach(function (card) {
				var stepInput = card.querySelector('[data-nestform-step]');
				var step = stepInput ? stepInput.value : '1';
				var dest = root.querySelector(
					'[data-nestform-step-group][data-step="' + step + '"] [data-nestform-fields]'
				);
				if (dest && card.parentElement !== dest) {
					dest.appendChild(card);
				}
			});
			activateStep(root, 1);
		} else {
			var flatList = root.querySelector(
				'[data-nestform-step-group][data-step="1"] [data-nestform-fields]'
			);
			if (flatList) {
				allFieldCards(root).forEach(function (card) {
					setFieldStep(card, 1);
					if (card.parentElement !== flatList) {
						flatList.appendChild(card);
					}
				});
			}
			// Drop extra step groups — only step 1 remains when wizard is off.
			root.querySelectorAll('[data-nestform-step-group]').forEach(function (group, index) {
				if (index === 0) {
					group.hidden = false;
					group.classList.add('is-active');
					group.removeAttribute('hidden');
				} else {
					group.remove();
				}
			});
			var firstHead = root.querySelector('[data-nestform-step-group] [data-nestform-step-head]');
			if (firstHead) {
				firstHead.hidden = true;
			}
		}
		refreshMoveOptions(root);
		reindex(root);
		syncEmpty(root);
		if (on) {
			syncNav(root);
		}
		syncBranchStepSelects(root);
		syncStepLabelsTextarea(root);
	}

	function moveCardToStep(root, card, step) {
		var dest = root.querySelector(
			'[data-nestform-step-group][data-step="' + step + '"] [data-nestform-fields]'
		);
		if (!dest) {
			return;
		}
		setFieldStep(card, step);
		dest.appendChild(card);
		reindex(root);
		syncEmpty(root);
		syncNav(root);
		activateStep(root, step);
		setCollapsed(card, false);
		card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function fieldNameBase(type) {
		if (type === 'tel') {
			return 'phone';
		}
		if (type === 'checkboxes') {
			return 'choices';
		}
		if (type === 'heading' || type === 'image' || type === 'html' || type === 'paragraph' || type === 'divider' || type === 'spacer' || type === 'file') {
			return type;
		}
		return type;
	}

	function syncPhonePicker(card) {
		var picker = card.querySelector('[data-nestform-phone-picker]');
		var isoWrap = card.querySelector('[data-nestform-phone-iso-wrap]');
		var isoSelect = card.querySelector('[data-nestform-phone-iso]');
		var offInput = card.querySelector('[data-nestform-phone-off]');
		if (!picker) {
			return;
		}
		var isTel = card.getAttribute('data-field-type') === 'tel';
		var on = isTel && !!picker.checked;
		picker.disabled = !isTel;
		if (isoWrap) {
			isoWrap.hidden = !on;
		}
		if (isoSelect) {
			isoSelect.disabled = !on;
		}
		if (offInput) {
			offInput.disabled = !isTel || on;
		}
	}

	function bindPhonePicker(card) {
		var picker = card.querySelector('[data-nestform-phone-picker]');
		if (!picker || picker.dataset.bound === '1') {
			return;
		}
		picker.dataset.bound = '1';
		picker.addEventListener('change', function () {
			syncPhonePicker(card);
		});
		syncPhonePicker(card);
	}

	function bindImagePicker(card) {
		var picker = card.querySelector('[data-nestform-image-picker]');
		if (!picker || picker.dataset.bound === '1') {
			return;
		}
		picker.dataset.bound = '1';
		var idInput = card.querySelector('[data-nestform-image-id]');
		var preview = picker.querySelector('[data-nestform-image-preview]');
		var pickBtn = picker.querySelector('[data-nestform-image-pick]');
		var clearBtn = picker.querySelector('[data-nestform-image-clear]');
		if (!idInput || !preview || !pickBtn) {
			return;
		}
		pickBtn.addEventListener('click', function () {
			if (typeof wp === 'undefined' || !wp.media) {
				window.alert(i18n.mediaUnavailable || 'WordPress media library is not available.');
				return;
			}
			var frame = wp.media({
				title: i18n.pickImage || 'Select image',
				button: { text: i18n.pickImage || 'Select image' },
				library: { type: 'image' },
				multiple: false,
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				idInput.value = String(attachment.id || '');
				var img = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				preview.classList.toggle('has-image', !!img);
				preview.innerHTML = img
					? '<img class="nestform-image-picker__img" src="' + img + '" alt="" />'
					: '<span class="nestform-image-picker__empty">' +
							(i18n.noImage || 'No image selected') +
							'</span>';
				if (clearBtn) {
					clearBtn.hidden = !idInput.value;
				}
				scheduleFieldPreview(card);
			});
			frame.open();
		});
		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				idInput.value = '';
				preview.classList.remove('has-image');
				preview.innerHTML =
					'<span class="nestform-image-picker__empty">' +
					(i18n.noImage || 'No image selected') +
					'</span>';
				clearBtn.hidden = true;
				scheduleFieldPreview(card);
			});
		}
	}

	function bindCard(card) {
		syncTypeUi(card);
		syncTitle(card);
		syncRequiredPill(card);
		syncEnabledState(card);
		syncStepBadge(card);
		syncConditionValue(card);
		syncWidthCustomWrap(card);
		bindImagePicker(card);
		bindPhonePicker(card);
		bindDrag(card);
		syncFieldPreview(card);
	}

	function bindDrag(card) {
		var handle = card.querySelector('[data-nestform-drag-handle]');
		if (!handle || handle.dataset.bound) {
			return;
		}
		handle.dataset.bound = '1';

		handle.addEventListener('mousedown', function () {
			card.setAttribute('draggable', 'true');
		});
		handle.addEventListener('mouseup', function () {
			card.setAttribute('draggable', 'false');
		});
		handle.addEventListener('mouseleave', function () {
			if (!dragState.card) {
				card.setAttribute('draggable', 'false');
			}
		});

		card.addEventListener('dragstart', function (event) {
			if (!card.getAttribute('draggable') || card.getAttribute('draggable') === 'false') {
				event.preventDefault();
				return;
			}
			dragState.card = card;
			card.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
			try {
				event.dataTransfer.setData('text/plain', 'nestform-field');
			} catch (e) {}
		});

		card.addEventListener('dragend', function () {
			card.classList.remove('is-dragging');
			card.setAttribute('draggable', 'false');
			dragState.card = null;
			document.querySelectorAll('.nestform-card.is-drop-target').forEach(function (el) {
				el.classList.remove('is-drop-target');
			});
		});
	}

	function addField(root, type) {
		var list = getActiveList(root);
		var tpl = root.querySelector('[data-nestform-field-template]');
		if (!list || !tpl) {
			return null;
		}
		var html = tpl.innerHTML.replace(/__INDEX__/g, String(allFieldCards(root).length));
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var node = wrap.firstElementChild;
		if (!node) {
			return null;
		}
		var step = isStepsMode(root) ? getActiveStep(root) : 1;
		list.appendChild(node);
		setFieldStep(node, step);
		if (type) {
			var typeSelect = node.querySelector('[data-nestform-type]');
			if (typeSelect) {
				typeSelect.value = type;
			}
			var label = node.querySelector('[data-nestform-label]');
			var name = node.querySelector('[data-nestform-name]');
			var pretty = typeLabels[type] || type;
			if (label && !label.value) {
				label.value = pretty;
			}
			if (name && !name.value) {
				name.value = uniqueFieldName(root, fieldNameBase(type), node);
				name.dataset.touched = '1';
			}
			if (type === 'ranking') {
				var rankOpts = node.querySelector('[data-nestform-show="options"] textarea');
				if (rankOpts && !String(rankOpts.value || '').trim()) {
					rankOpts.value = 'First\nSecond\nThird';
				}
			}
			if (type === 'range') {
				var opts = node.querySelector('[data-nestform-show="options"] textarea');
				if (opts && !String(opts.value || '').trim()) {
					opts.value = '0\n100\n1';
				}
			}
			if (type === 'rating') {
				var ratingOpts = node.querySelector('[data-nestform-show="options"] textarea');
				if (ratingOpts && !String(ratingOpts.value || '').trim()) {
					ratingOpts.value = '5';
				}
			}
			if (type === 'scale') {
				var scaleOpts = node.querySelector('[data-nestform-show="options"] textarea');
				if (scaleOpts && !String(scaleOpts.value || '').trim()) {
					scaleOpts.value = i18n.optionsPhScale || "1\n5\nVery dissatisfied\nVery satisfied";
				}
			}
			if (type === 'matrix') {
				var matrixOpts = node.querySelector('[data-nestform-show="options"] textarea');
				if (matrixOpts && !String(matrixOpts.value || '').trim()) {
					matrixOpts.value = i18n.optionsPhMatrix || "Support\nProduct\n---\nPoor\nFair\nGood";
				}
			}
			if (type === 'payment') {
				var payAmount = node.querySelector('[data-nestform-payment-amount]');
				var payCurrency = node.querySelector('[data-nestform-payment-currency]');
				if (payAmount && !String(payAmount.value || '').trim()) {
					payAmount.value = '9.99';
				}
				if (payCurrency && !String(payCurrency.value || '').trim()) {
					payCurrency.value = 'USD';
				}
			}
			if (type === 'select' || type === 'radio' || type === 'checkboxes') {
				var choiceOpts = node.querySelector('[data-nestform-show="options"] textarea');
				if (choiceOpts && !String(choiceOpts.value || '').trim()) {
					choiceOpts.value = i18n.optionsPhChoices || "Yes\nNo\nMaybe";
				}
			}
			if (type === 'calculated') {
				var calcOpts = node.querySelector('[data-nestform-show="formula"] textarea');
				if (calcOpts && !String(calcOpts.value || '').trim()) {
					calcOpts.value = '{price} * {qty}';
				}
			}
			if (type === 'select') {
				var phInput = node.querySelector('[data-nestform-placeholder]');
				if (phInput && !String(phInput.value || '').trim()) {
					phInput.value = i18n.selectPlaceholder || 'Select...';
				}
			}
		}
		bindCard(node);
		setCollapsed(node, false);
		reindex(root);
		syncEmpty(root);
		syncNav(root);
		refreshMoveOptions(root);
		syncAllFieldNameSelects(root);
		node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		var focusEl = node.querySelector('[data-nestform-label]');
		if (focusEl) {
			focusEl.focus();
		}
		return node;
	}

	function duplicateField(root, card) {
		var clone = card.cloneNode(true);
		clone.classList.remove('is-dragging', 'is-drop-target');
		clone.querySelectorAll('[data-bound]').forEach(function (el) {
			delete el.dataset.bound;
		});
		card.after(clone);
		var nameInput = clone.querySelector('[data-nestform-name]');
		if (nameInput) {
			var base = String(nameInput.value || 'field').replace(/_\d+$/, '');
			nameInput.value = uniqueFieldName(root, base, clone);
			nameInput.dataset.touched = '1';
		}
		var labelInput = clone.querySelector('[data-nestform-label]');
		if (labelInput && labelInput.value) {
			labelInput.value = labelInput.value + ' (' + (i18n.duplicate || 'copy') + ')';
		}
		bindCard(clone);
		setCollapsed(clone, false);
		reindex(root);
		syncEmpty(root);
		syncNav(root);
		syncAllFieldNameSelects(root);
		refreshMoveOptions(root);
		syncTitle(clone);
		clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function bindListDnD(root, list) {
		if (!list || list.dataset.dndBound) {
			return;
		}
		list.dataset.dndBound = '1';

		list.addEventListener('dragover', function (event) {
			if (!dragState.card) {
				return;
			}
			event.preventDefault();
			var target = event.target.closest('[data-nestform-field]');
			var group = list.closest('[data-nestform-step-group]');
			var step = group ? group.getAttribute('data-step') || '1' : '1';
			setFieldStep(dragState.card, step);

			if (!target || target === dragState.card) {
				if (!target && list.contains(event.target)) {
					list.appendChild(dragState.card);
				}
				return;
			}
			document.querySelectorAll('.nestform-card.is-drop-target').forEach(function (el) {
				el.classList.remove('is-drop-target');
			});
			target.classList.add('is-drop-target');
			var rect = target.getBoundingClientRect();
			var before = event.clientY < rect.top + rect.height / 2;
			if (before) {
				list.insertBefore(dragState.card, target);
			} else {
				list.insertBefore(dragState.card, target.nextSibling);
			}
		});

		list.addEventListener('drop', function (event) {
			event.preventDefault();
			reindex(root);
			syncEmpty(root);
			syncNav(root);
			document.querySelectorAll('.nestform-card.is-drop-target').forEach(function (el) {
				el.classList.remove('is-drop-target');
			});
		});
	}

	function onReady() {
		var root = document.querySelector('[data-nestform-admin]');
		if (!root) {
			return;
		}

		var editorTabs = ['fields', 'messages', 'mail', 'settings', 'appearance'];

		function editorTabStorageKey() {
			var id = root.getAttribute('data-form-id') || '0';
			return 'nestform_editor_tab_' + id;
		}

		function readEditorTab() {
			var hash = String(window.location.hash || '').replace(/^#/, '');
			var fromHash = '';
			if (hash.indexOf('nf-tab=') === 0) {
				fromHash = hash.slice(7).split('&')[0];
			} else if (editorTabs.indexOf(hash) !== -1) {
				fromHash = hash;
			}
			if (fromHash && editorTabs.indexOf(fromHash) !== -1) {
				return fromHash;
			}
			try {
				var stored = window.sessionStorage.getItem(editorTabStorageKey());
				if (stored && editorTabs.indexOf(stored) !== -1) {
					return stored;
				}
			} catch (err) {
				/* ignore */
			}
			var active = root.querySelector('[data-nestform-tab].is-active');
			if (active) {
				var current = active.getAttribute('data-nestform-tab') || '';
				if (editorTabs.indexOf(current) !== -1) {
					return current;
				}
			}
			return 'fields';
		}

		function persistEditorTab(id, options) {
			options = options || {};
			try {
				window.sessionStorage.setItem(editorTabStorageKey(), id);
			} catch (err) {
				/* ignore */
			}
			try {
				document.cookie =
					editorTabStorageKey() +
					'=' +
					encodeURIComponent(id) +
					'; path=/; max-age=2592000; SameSite=Lax';
			} catch (errCookie) {
				/* ignore */
			}
			if (options.skipHash) {
				return;
			}
			var nextHash = 'nf-tab=' + id;
			if (String(window.location.hash || '').replace(/^#/, '') === nextHash) {
				return;
			}
			if (window.history && typeof window.history.replaceState === 'function') {
				var base = window.location.href.replace(/#.*$/, '');
				window.history.replaceState(null, '', base + '#' + nextHash);
			} else {
				window.location.hash = nextHash;
			}
		}

		function activateEditorTab(id, options) {
			options = options || {};
			if (editorTabs.indexOf(id) === -1) {
				id = 'fields';
			}
			root.querySelectorAll('[data-nestform-tab]').forEach(function (t) {
				var active = t.getAttribute('data-nestform-tab') === id;
				t.classList.toggle('is-active', active);
				t.setAttribute('aria-selected', active ? 'true' : 'false');
				t.setAttribute('tabindex', active ? '0' : '-1');
			});
			root.querySelectorAll('[data-nestform-panel]').forEach(function (panel) {
				var match = panel.getAttribute('data-nestform-panel') === id;
				panel.classList.toggle('is-active', match);
				panel.hidden = !match;
			});
			if (id === 'mail') {
				window.setTimeout(function () {
					initMailBodyEditor();
					refreshVisibleSubtabs(root);
				}, 60);
			}
			if (id === 'settings' || id === 'mail') {
				refreshVisibleSubtabs(root);
			}
			persistEditorTab(id, { skipHash: !!options.skipHash });
		}

		function editorSubtabStorageKey(scope) {
			var formId = root.getAttribute('data-form-id') || '0';
			return 'nestform_subtab_' + formId + '_' + scope;
		}

		function activateSubtabs(wrap, id) {
			if (!wrap) {
				return;
			}
			var tabs = wrap.querySelectorAll('[data-nestform-subtab]');
			var panels = wrap.querySelectorAll('[data-nestform-subpanel]');
			var ids = [];
			tabs.forEach(function (tab) {
				var tid = tab.getAttribute('data-nestform-subtab') || '';
				if (tid) {
					ids.push(tid);
				}
			});
			if (ids.indexOf(id) === -1) {
				id = wrap.getAttribute('data-nestform-subtabs-default') || ids[0] || '';
			}
			tabs.forEach(function (tab) {
				var on = tab.getAttribute('data-nestform-subtab') === id;
				tab.classList.toggle('nestform-settings__subnav-item--active', on);
				tab.setAttribute('aria-selected', on ? 'true' : 'false');
				tab.tabIndex = on ? 0 : -1;
			});
			panels.forEach(function (panel) {
				var on = panel.getAttribute('data-nestform-subpanel') === id;
				panel.hidden = !on;
				panel.classList.toggle('is-active', on);
			});
			var key = wrap.getAttribute('data-nestform-subtabs-key') || '';
			if (key) {
				try {
					window.sessionStorage.setItem(editorSubtabStorageKey(key), id);
				} catch (err) {
					/* ignore */
				}
			}
			if (key === 'mail' && id === 'notification') {
				window.setTimeout(initMailBodyEditor, 60);
			}
		}

		function refreshVisibleSubtabs(scope) {
			scope.querySelectorAll('[data-nestform-subtabs]').forEach(function (wrap) {
				var panel = wrap.closest('[data-nestform-panel]');
				if (panel && panel.hidden) {
					return;
				}
				var active = wrap.querySelector('[data-nestform-subtab].nestform-settings__subnav-item--active');
				var id = active ? active.getAttribute('data-nestform-subtab') : '';
				activateSubtabs(wrap, id || wrap.getAttribute('data-nestform-subtabs-default') || '');
			});
		}

		function initEditorSubtabs() {
			root.querySelectorAll('[data-nestform-subtabs]').forEach(function (wrap) {
				var key = wrap.getAttribute('data-nestform-subtabs-key') || '';
				var initial = wrap.getAttribute('data-nestform-subtabs-default') || '';
				if (key) {
					try {
						var stored = window.sessionStorage.getItem(editorSubtabStorageKey(key));
						if (stored) {
							initial = stored;
						}
					} catch (err) {
						/* ignore */
					}
				}
				activateSubtabs(wrap, initial);
				var tabs = wrap.querySelectorAll('[data-nestform-subtab]');
				tabs.forEach(function (tab) {
					tab.addEventListener('click', function (event) {
						event.preventDefault();
						activateSubtabs(wrap, tab.getAttribute('data-nestform-subtab') || '');
					});
					tab.addEventListener('keydown', function (event) {
						if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
							return;
						}
						event.preventDefault();
						var list = Array.prototype.slice.call(tabs);
						var idx = list.indexOf(tab);
						if (idx < 0) {
							return;
						}
						var next =
							event.key === 'ArrowRight'
								? list[(idx + 1) % list.length]
								: list[(idx - 1 + list.length) % list.length];
						next.focus();
						activateSubtabs(wrap, next.getAttribute('data-nestform-subtab') || '');
					});
				});
			});
		}

		root.querySelectorAll('[data-nestform-tab]').forEach(function (tab) {
			tab.addEventListener('click', function () {
				activateEditorTab(tab.getAttribute('data-nestform-tab'));
			});
		});

		window.addEventListener('hashchange', function () {
			activateEditorTab(readEditorTab(), { skipHash: true });
		});

		initEditorSubtabs();
		activateEditorTab(readEditorTab());

		root.querySelectorAll('[data-nestform-field]').forEach(bindCard);
		root.querySelectorAll('[data-nestform-fields]').forEach(function (list) {
			bindListDnD(root, list);
		});
		bindSubfieldTypeSync(root);
		syncEmpty(root);
		refreshMoveOptions(root);
		syncNav(root);
		syncAllFieldNameSelects(root);
		syncBranchStepSelects(root);
		initBranchUi(root);
		initStickySave(root);

		initAppearanceUi(root);
		initAutomationsUi(root);
		initQuizBandsUi(root);
		initWebhooksUi(root);
		initAddMenus(root);

		var enable = root.querySelector('[data-nestform-enable-steps]');
		var stepsSetup = root.querySelector('[data-nestform-steps-setup]');
		if (enable) {
			enable.addEventListener('change', function (event) {
				var canMulti =
					(stepsSetup && stepsSetup.getAttribute('data-nestform-can-multi-step') === '1') ||
					(window.nestformPro &&
						window.nestformPro.features &&
						window.nestformPro.features.multi_step);
				if (enable.checked && !canMulti) {
					event.preventDefault();
					enable.checked = false;
					return;
				}
				setStepsMode(root, enable.checked);
			});
		}

		var addStepBtn = root.querySelector('[data-nestform-add-step]');
		if (addStepBtn) {
			addStepBtn.addEventListener('click', function () {
				var canMulti =
					(stepsSetup && stepsSetup.getAttribute('data-nestform-can-multi-step') === '1') ||
					(window.nestformPro &&
						window.nestformPro.features &&
						window.nestformPro.features.multi_step);
				if (!canMulti) {
					return;
				}
				if (!isStepsMode(root)) {
					if (enable) {
						enable.checked = true;
					}
					setStepsMode(root, true);
				}
				addStep(root);
			});
		}

		root.addEventListener('click', function (event) {
			var addTypeBtn = event.target.closest('[data-nestform-add-type]');
			if (addTypeBtn && root.contains(addTypeBtn)) {
				addField(root, addTypeBtn.getAttribute('data-nestform-add-type'));
				closeAllAddMenus(root);
				return;
			}

			var subAdd = event.target.closest('[data-nestform-subfield-add]');
			if (subAdd && root.contains(subAdd)) {
				event.preventDefault();
				var subWrap = subAdd.closest('[data-nestform-subfields]');
				if (!subWrap) {
					return;
				}
				var subList = subWrap.querySelector('[data-nestform-subfields-list]');
				var subTpl = subWrap.querySelector('[data-nestform-subfield-template]');
				if (!subList || !subTpl) {
					return;
				}
				var sidx = subList.querySelectorAll('[data-nestform-subfield]').length;
				var subHtml = subTpl.innerHTML.replace(/__SI__/g, String(sidx));
				var subDiv = document.createElement('div');
				subDiv.innerHTML = subHtml.trim();
				if (subDiv.firstElementChild) {
					subList.appendChild(subDiv.firstElementChild);
					syncSubfieldOptions(subDiv.firstElementChild);
				}
				syncSubfieldsUi(subWrap);
				reindex(root);
				scheduleFieldPreview(subAdd.closest('[data-nestform-field]'));
				return;
			}

			var subRemove = event.target.closest('[data-nestform-subfield-remove]');
			if (subRemove && root.contains(subRemove)) {
				event.preventDefault();
				var subRow = subRemove.closest('[data-nestform-subfield]');
				var listEl = subRemove.closest('[data-nestform-subfields-list]');
				var wrapEl = subRemove.closest('[data-nestform-subfields]');
				if (subRow && listEl) {
					subRow.remove();
					syncSubfieldsUi(wrapEl);
					reindex(root);
					scheduleFieldPreview(subRemove.closest('[data-nestform-field]'));
				}
				return;
			}

			var stepTab = event.target.closest('[data-nestform-step-tab]');
			if (stepTab) {
				activateStep(root, stepTab.getAttribute('data-nestform-step-tab'));
				return;
			}

			var removeStepBtn = event.target.closest('[data-nestform-remove-step]');
			if (removeStepBtn) {
				var group = removeStepBtn.closest('[data-nestform-step-group]');
				if (group) {
					removeStep(root, group);
				}
				return;
			}

			var toggle = event.target.closest('[data-nestform-toggle]');
			if (toggle) {
				var card = toggle.closest('[data-nestform-field]');
				if (card) {
					setCollapsed(card, !card.classList.contains('is-collapsed'));
				}
				return;
			}

			var dup = event.target.closest('[data-nestform-duplicate]');
			if (dup) {
				var cardDup = dup.closest('[data-nestform-field]');
				if (cardDup) {
					duplicateField(root, cardDup);
				}
				return;
			}

			var remove = event.target.closest('[data-nestform-remove-field]');
			if (remove) {
				var row = remove.closest('[data-nestform-field]');
				if (row && window.confirm(i18n.confirmDel || 'Remove this field?')) {
					row.remove();
					reindex(root);
					syncEmpty(root);
					syncNav(root);
					syncAllFieldNameSelects(root);
				}
				return;
			}

			var head = event.target.closest('[data-nestform-card-head]');
			if (head && root.contains(head)) {
				// Restore header-click expand, but keep controls interactive.
				if (
					event.target.closest(
						'[data-nestform-drag-handle], [data-nestform-required-wrap], .nestform-card__actions, input, select, textarea, label, a, button'
					)
				) {
					return;
				}
				var cardHead = head.closest('[data-nestform-field]');
				if (cardHead) {
					setCollapsed(cardHead, !cardHead.classList.contains('is-collapsed'));
				}
				return;
			}
		});

		root.addEventListener('change', function (event) {
			var card = event.target.closest('[data-nestform-field]');
			if (card) {
				if (event.target.matches('[data-nestform-type]')) {
					syncTypeUi(card);
					syncAllFieldNameSelects(root);
				}
				if (event.target.matches('[data-nestform-allow-other]')) {
					syncOtherLabel(card);
					syncSections(card);
				}
				if (event.target.matches('[data-nestform-required]')) {
					syncRequiredPill(card);
				}
				if (event.target.matches('[data-nestform-enabled]')) {
					syncEnabledState(card);
				}
				if (event.target.matches('[data-nestform-condition-op]')) {
					syncConditionValue(card);
					syncCardSummary(card);
				}
				if (event.target.matches('[data-nestform-condition-field]')) {
					syncConditionValue(card);
					syncCardSummary(card);
				}
				if (event.target.matches('[data-nestform-move-step]')) {
					moveCardToStep(root, card, event.target.value);
				}
				if (event.target.matches('[data-nestform-width], select[name*="[width]"]')) {
					syncWidthCustomWrap(card);
					syncCardSummary(card);
				}
				if (event.target.matches('[data-nestform-width-custom]')) {
					syncCardSummary(card);
				}
				if (event.target.matches('[data-nestform-payment-currency]')) {
					syncCardSummary(card);
				}
				scheduleFieldPreview(card);
			}
		});

		root.addEventListener('input', function (event) {
			if (event.target.matches('[data-nestform-step-title]')) {
				syncStepLabelsTextarea(root);
				syncNav(root);
				return;
			}
			var card = event.target.closest('[data-nestform-field]');
			if (!card) {
				return;
			}
			if (event.target.matches('[data-nestform-label]')) {
				var nameInput = card.querySelector('[data-nestform-name]');
				if (nameInput && (!nameInput.dataset.touched || nameInput.value === '')) {
					nameInput.value = uniqueFieldName(
						root,
						slugify(stripTags(event.target.value)),
						card
					);
					syncAllFieldNameSelects(root);
				}
				syncTitle(card);
			}
			if (event.target.matches('[data-nestform-name]')) {
				event.target.dataset.touched = '1';
				syncTitle(card);
				syncAllFieldNameSelects(root);
			}
			if (event.target.matches('[data-nestform-condition-value]')) {
				syncCardSummary(card);
			}
			if (event.target.matches('[data-nestform-payment-amount], [data-nestform-payment-currency]')) {
				syncCardSummary(card);
			}
			scheduleFieldPreview(card);
		});

		// Observe new step groups for DnD binding.
		var groupsWrap = root.querySelector('[data-nestform-step-groups]');
		if (groupsWrap && window.MutationObserver) {
			var mo = new MutationObserver(function () {
				root.querySelectorAll('[data-nestform-fields]').forEach(function (list) {
					bindListDnD(root, list);
				});
			});
			mo.observe(groupsWrap, { childList: true });
		}

		document.querySelectorAll('[data-nestform-copy]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var row = btn.closest('.nestform-embed__row');
				var code = row && row.querySelector('[data-nestform-copy-text]');
				if (!code || !navigator.clipboard) {
					return;
				}
				navigator.clipboard.writeText(code.textContent || '').then(function () {
					btn.classList.add('is-copied');
					setTimeout(function () {
						btn.classList.remove('is-copied');
					}, 1200);
				});
			});
		});

		initUndo(root);
		initPreview();
		initTemplates();
	}

	function snapshotFields(root) {
		var form = root.closest('form') || document.getElementById('post');
		if (!form) {
			return '';
		}
		var data = new FormData(form);
		var parts = [];
		data.forEach(function (value, key) {
			if (String(key).indexOf('nestform[fields]') === 0) {
				parts.push(key + '=' + String(value));
			}
		});
		return parts.join('&');
	}

	function initUndo(root) {
		var history = [];
		var pushing = false;
		function push() {
			if (pushing) {
				return;
			}
			var snap = snapshotFields(root);
			if (!snap) {
				return;
			}
			if (history.length && history[history.length - 1] === snap) {
				return;
			}
			history.push(snap);
			if (history.length > 30) {
				history.shift();
			}
		}
		push();
		root.addEventListener('change', push);
		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-nestform-add-type], [data-nestform-remove], [data-nestform-duplicate], [data-nestform-add-step], [data-nestform-remove-step]')) {
				window.setTimeout(push, 0);
			}
		});

		function undo() {
			if (history.length < 2) {
				return;
			}
			history.pop();
			var prev = history[history.length - 1];
			var form = root.closest('form') || document.getElementById('post');
			if (!form || !prev) {
				return;
			}
			pushing = true;
			var map = {};
			prev.split('&').forEach(function (pair) {
				var i = pair.indexOf('=');
				if (i < 0) {
					return;
				}
				map[decodeURIComponent(pair.slice(0, i))] = decodeURIComponent(pair.slice(i + 1));
			});
			form.querySelectorAll('[name^="nestform[fields]"]').forEach(function (el) {
				var name = el.getAttribute('name');
				if (!name || !(name in map)) {
					return;
				}
				if (el.type === 'checkbox') {
					el.checked = map[name] === '1' || map[name] === 'on' || map[name] === 'true';
				} else {
					el.value = map[name];
				}
			});
			root.querySelectorAll('[data-nestform-field]').forEach(function (card) {
				syncTypeUi(card);
				syncTitle(card);
				syncRequiredPill(card);
				syncEnabledState(card);
			});
			pushing = false;
		}

		var undoBtn = root.querySelector('[data-nestform-undo]');
		if (undoBtn) {
			undoBtn.addEventListener('click', undo);
		}
		document.addEventListener('keydown', function (event) {
			if ((event.ctrlKey || event.metaKey) && !event.shiftKey && String(event.key).toLowerCase() === 'z') {
				var tag = (event.target && event.target.tagName) || '';
				if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
					return;
				}
				if (!root.contains(document.activeElement) && document.activeElement !== document.body) {
					return;
				}
				event.preventDefault();
				undo();
			}
		});
	}

	function initTemplates() {
		var drawer = document.querySelector('[data-nestform-templates-drawer]');
		var openBtns = document.querySelectorAll('[data-nestform-templates-open]');
		if (!drawer || !openBtns.length) {
			return;
		}
		var INTRO_KEY = 'nestform_templates_intro';
		var fromIntro = false;
		var nudgeTimer = 0;
		var activeCategory = 'all';
		var searchInput = drawer.querySelector('[data-nestform-templates-search]');
		var noResults = drawer.querySelector('[data-nestform-templates-no-results]');

		function introState() {
			try {
				return window.localStorage.getItem(INTRO_KEY) || '';
			} catch (err) {
				return 'done';
			}
		}
		function setIntroState(value) {
			try {
				window.localStorage.setItem(INTRO_KEY, value);
			} catch (err) {}
		}
		function prefersReducedMotion() {
			return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		}
		function hideNudge() {
			var home = document.querySelector('.nestform-templates-home');
			var nudge = document.querySelector('[data-nestform-templates-nudge]');
			if (nudgeTimer) {
				window.clearTimeout(nudgeTimer);
				nudgeTimer = 0;
			}
			if (home) {
				home.classList.remove('is-hinting');
			}
			if (nudge) {
				nudge.hidden = true;
			}
			setIntroState('done');
		}
		function showNudge() {
			var home = document.querySelector('.nestform-templates-home');
			var nudge = document.querySelector('[data-nestform-templates-nudge]');
			var homeBtn = document.querySelector('[data-nestform-templates-home]');
			if (!home || !nudge) {
				setIntroState('done');
				return;
			}
			nudge.hidden = false;
			home.classList.add('is-hinting');
			nudgeTimer = window.setTimeout(hideNudge, prefersReducedMotion() ? 1600 : 4200);
			if (homeBtn) {
				homeBtn.addEventListener('click', hideNudge, { once: true });
			}
		}
		function applyFilters() {
			var query = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
			var visible = 0;
			drawer.querySelectorAll('[data-nestform-templates-card]').forEach(function (card) {
				var cardCat = card.getAttribute('data-category') || 'other';
				var haystack = card.getAttribute('data-nestform-templates-search') || '';
				var matchCat = activeCategory === 'all' || cardCat === activeCategory;
				var matchQuery = !query || haystack.indexOf(query) !== -1;
				var show = matchCat && matchQuery;
				card.hidden = !show;
				if (show) {
					visible += 1;
				}
			});
			if (noResults) {
				noResults.hidden = visible > 0;
			}
		}
		function close() {
			var wasIntro = fromIntro && !drawer.hidden;
			drawer.hidden = true;
			if (wasIntro) {
				fromIntro = false;
				showNudge();
			}
		}
		function open() {
			if (drawer.parentNode !== document.body) {
				document.body.appendChild(drawer);
			}
			drawer.hidden = false;
			if (searchInput) {
				window.setTimeout(function () {
					searchInput.focus();
				}, 0);
			}
		}
		openBtns.forEach(function (btn) {
			btn.addEventListener('click', open);
		});
		drawer.querySelectorAll('[data-nestform-templates-close]').forEach(function (btn) {
			btn.addEventListener('click', close);
		});
		drawer.querySelectorAll('[data-nestform-templates-filter]').forEach(function (chip) {
			chip.addEventListener('click', function () {
				activeCategory = chip.getAttribute('data-nestform-templates-filter') || 'all';
				drawer.querySelectorAll('[data-nestform-templates-filter]').forEach(function (c) {
					c.classList.toggle('is-active', c === chip);
				});
				applyFilters();
			});
		});
		if (searchInput) {
			searchInput.addEventListener('input', applyFilters);
			searchInput.addEventListener('search', applyFilters);
		}
		document.querySelectorAll('[data-nestform-templates-save-first]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var cfg = window.nestformAdmin || {};
				var msg = (cfg.i18n && cfg.i18n.templateSaveFirst) || 'Save the form as a draft first, then apply a template.';
				window.alert(msg);
			});
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !drawer.hidden) {
				close();
			}
		});

		applyFilters();

		var admin = document.querySelector('[data-nestform-admin]');
		var isEmpty = (admin && admin.getAttribute('data-nestform-empty') === '1')
			|| !!document.querySelector('[data-nestform-templates-empty]');
		if (isEmpty && introState() !== 'done') {
			fromIntro = true;
			open();
		}
	}

	function initPreview() {
		var drawer = document.querySelector('[data-nestform-preview-drawer]');
		var frame = document.querySelector('[data-nestform-preview-frame]');
		var openBtns = document.querySelectorAll('[data-nestform-preview]');
		if (!drawer || !frame || !openBtns.length) {
			return;
		}
		function close() {
			drawer.hidden = true;
			frame.removeAttribute('src');
		}
		function open() {
			var cfg = window.nestformAdmin || {};
			var formId = cfg.formId || 0;
			if (!formId) {
				window.alert(cfg.i18n && cfg.i18n.previewNeedSave ? cfg.i18n.previewNeedSave : 'Save first');
				return;
			}
			var url =
				(cfg.previewUrl || '') +
				'&form_id=' +
				encodeURIComponent(String(formId)) +
				'&nonce=' +
				encodeURIComponent(cfg.previewNonce || '');
			frame.src = url;
			drawer.hidden = false;
		}
		openBtns.forEach(function (btn) {
			btn.addEventListener('click', open);
		});
		drawer.querySelectorAll('[data-nestform-preview-close]').forEach(function (btn) {
			btn.addEventListener('click', close);
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !drawer.hidden) {
				close();
			}
		});
	}

	function syncSubfieldOptions(row) {
		if (!row) {
			return;
		}
		var typeSelect = row.querySelector('[data-nestform-subfield-type]');
		var optsWrap = row.querySelector('[data-nestform-subfield-options]');
		var optsLabel = row.querySelector('[data-nestform-subfield-options-label]');
		var optsInput = row.querySelector('[data-nestform-subfield-options-input]');
		var optsHint = row.querySelector('[data-nestform-subfield-options-hint]');
		if (!typeSelect || !optsWrap || !optsInput) {
			return;
		}
		var type = typeSelect.value || 'text';
		var needs = ['select', 'radio', 'checkboxes', 'range', 'calculated'].indexOf(type) >= 0;
		optsWrap.hidden = !needs;
		optsInput.disabled = !needs;
		if (!needs) {
			return;
		}
		var labelText = i18n.subOptChoices || 'Choices (one per line)';
		var hintText = i18n.subHintChoices || '';
		var ph = i18n.subPhChoices || '';
		if (type === 'calculated') {
			labelText = i18n.subOptFormula || 'Formula';
			hintText = i18n.subHintFormula || '';
			ph = i18n.subPhFormula || '{price} * {qty}';
		} else if (type === 'range') {
			labelText = i18n.subOptRange || 'Min / max / step';
			hintText = i18n.subHintRange || '';
			ph = i18n.subPhRange || "0\n100\n1";
		}
		if (optsLabel) {
			optsLabel.textContent = labelText;
		}
		if (optsHint) {
			optsHint.textContent = hintText;
		}
		optsInput.placeholder = ph;
	}

	function syncSubfieldsUi(wrap) {
		if (!wrap) {
			return;
		}
		var list = wrap.querySelector('[data-nestform-subfields-list]');
		var empty = wrap.querySelector('[data-nestform-subfields-empty]');
		var addMore = wrap.querySelector('[data-nestform-subfield-add-more]');
		var preview = wrap.querySelector('[data-nestform-subfields-preview]');
		var previewCols = wrap.querySelector('[data-nestform-subfields-preview-cols]');
		var rows = list ? list.querySelectorAll('[data-nestform-subfield]') : [];
		var hasRows = rows.length > 0;
		if (list) {
			list.hidden = !hasRows;
		}
		if (empty) {
			empty.hidden = hasRows;
		}
		if (addMore) {
			addMore.hidden = !hasRows;
		}
		if (!preview || !previewCols) {
			return;
		}
		previewCols.textContent = '';
		if (!hasRows) {
			preview.hidden = true;
			return;
		}
		preview.hidden = false;
		Array.prototype.forEach.call(rows, function (row, index) {
			var labelInput = row.querySelector('[data-nestform-subfield-label]');
			var nameInput = row.querySelector('[data-nestform-subfield-name]');
			var typeSelect = row.querySelector('[data-nestform-subfield-type]');
			var label = labelInput ? String(labelInput.value || '').trim() : '';
			var name = nameInput ? String(nameInput.value || '').trim() : '';
			var type = typeSelect ? String(typeSelect.value || 'text') : 'text';
			var text = label || name || ((i18n.subColFallback || 'Column %d').replace('%d', String(index + 1)));
			var chip = document.createElement('span');
			chip.className = 'nestform-subfields-preview__col';
			chip.textContent = text;
			chip.title = type + (name ? ' {' + name + '}' : '');
			previewCols.appendChild(chip);
		});
	}

	function bindSubfieldTypeSync(root) {
		if (!root || root.getAttribute('data-nestform-subfield-bound')) {
			return;
		}
		root.setAttribute('data-nestform-subfield-bound', '1');
		root.addEventListener('change', function (event) {
			var select = event.target.closest('[data-nestform-subfield-type]');
			if (!select || !root.contains(select)) {
				return;
			}
			var row = select.closest('[data-nestform-subfield]');
			syncSubfieldOptions(row);
			syncSubfieldsUi(select.closest('[data-nestform-subfields]'));
			scheduleFieldPreview(select.closest('[data-nestform-field]'));
		});
		root.addEventListener('input', function (event) {
			var target = event.target;
			if (!target || !root.contains(target)) {
				return;
			}
			if (
				!target.matches('[data-nestform-subfield-label], [data-nestform-subfield-name]')
			) {
				return;
			}
			syncSubfieldsUi(target.closest('[data-nestform-subfields]'));
			scheduleFieldPreview(target.closest('[data-nestform-field]'));
		});
		root.querySelectorAll('[data-nestform-subfield]').forEach(syncSubfieldOptions);
		root.querySelectorAll('[data-nestform-subfields]').forEach(syncSubfieldsUi);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})();
