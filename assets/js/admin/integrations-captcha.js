/**
 * Integrations: captcha provider tabs (one active service).
 */
(function () {
	'use strict';

	function activate(root, slug) {
		var input = root.querySelector('[data-thimbleform-captcha-provider]');
		var tabs = root.querySelectorAll('[data-thimbleform-captcha-tab]');
		var panels = root.querySelectorAll('[data-thimbleform-captcha-panel]');
		if (input) {
			input.value = slug;
		}
		tabs.forEach(function (tab) {
			var on = tab.getAttribute('data-thimbleform-captcha-tab') === slug;
			tab.classList.toggle('thimbleform-settings__subnav-item--active', on);
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.tabIndex = on ? 0 : -1;
		});
		panels.forEach(function (panel) {
			var on = panel.getAttribute('data-thimbleform-captcha-panel') === slug;
			panel.hidden = !on;
			panel.classList.toggle('is-active', on);
		});
	}

	function init() {
		var root = document.querySelector('[data-thimbleform-captcha-tabs]');
		if (!root) {
			return;
		}
		var tabs = root.querySelectorAll('[data-thimbleform-captcha-tab]');
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function (event) {
				event.preventDefault();
				activate(root, tab.getAttribute('data-thimbleform-captcha-tab') || '');
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
				var next = event.key === 'ArrowRight' ? list[(idx + 1) % list.length] : list[(idx - 1 + list.length) % list.length];
				next.focus();
				activate(root, next.getAttribute('data-thimbleform-captcha-tab') || '');
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
