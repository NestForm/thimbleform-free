/**
 * Export format dropdown (CSV / Excel) on entries and summary screens.
 */
(function () {
	'use strict';

	function closeExportMenus(except) {
		document.querySelectorAll('[data-thimbleform-export-menu]').forEach(function (wrap) {
			if (except && wrap === except) {
				return;
			}
			var panel = wrap.querySelector('.thimbleform-export-menu__panel');
			var toggle = wrap.querySelector('.thimbleform-export-menu__toggle');
			wrap.classList.remove('is-open');
			if (panel) {
				panel.hidden = true;
			}
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function initExportMenus() {
		if (!document.querySelector('[data-thimbleform-export-menu]')) {
			return;
		}

		document.addEventListener('click', function (event) {
			var toggle = event.target.closest('.thimbleform-export-menu__toggle');
			if (toggle) {
				event.preventDefault();
				event.stopPropagation();
				var wrap = toggle.closest('[data-thimbleform-export-menu]');
				if (!wrap) {
					return;
				}
				var panel = wrap.querySelector('.thimbleform-export-menu__panel');
				var willOpen = !wrap.classList.contains('is-open');
				closeExportMenus(wrap);
				if (willOpen && panel) {
					wrap.classList.add('is-open');
					panel.hidden = false;
					toggle.setAttribute('aria-expanded', 'true');
				}
				return;
			}

			if (!event.target.closest('[data-thimbleform-export-menu]')) {
				closeExportMenus();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeExportMenus();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initExportMenus);
	} else {
		initExportMenus();
	}
})();
