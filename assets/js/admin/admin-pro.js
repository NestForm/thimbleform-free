/**
 * Thimbleform app shell helpers (sidebar collapse, Pro promotion).
 */
(function () {
	'use strict';

	var cfg = window.nestformPro || {};

	function initSidebarToggle() {
		var app = document.querySelector('[data-nestform-app]');
		var toggle = document.querySelector('[data-nestform-sidebar-toggle]');
		if (!app || !toggle) {
			return;
		}

		var i18n = (cfg.i18n && cfg.i18n.sidebar) || {};
		var labelEl = toggle.querySelector('.nestform-app__sidebar-toggle-label');

		function isCollapsed() {
			return (
				app.classList.contains('nestform-app--sidebar-collapsed') ||
				document.documentElement.classList.contains('nestform-sidebar-collapsed')
			);
		}

		function applyCollapsed(collapsed) {
			app.classList.toggle('nestform-app--sidebar-collapsed', collapsed);
			document.documentElement.classList.toggle('nestform-sidebar-collapsed', collapsed);
		}

		if (document.documentElement.classList.contains('nestform-sidebar-collapsed')) {
			app.classList.add('nestform-app--sidebar-collapsed');
		}

		function syncToggleUi() {
			var collapsed = isCollapsed();
			toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			var label = collapsed
				? i18n.expand || 'Expand'
				: i18n.collapse || 'Collapse';
			toggle.title = label;
			if (labelEl) {
				labelEl.textContent = label;
			}
		}

		syncToggleUi();

		toggle.addEventListener('click', function () {
			var collapsed = !isCollapsed();
			applyCollapsed(collapsed);
			try {
				window.localStorage.setItem(
					'nestform_sidebar_collapsed',
					collapsed ? '1' : '0'
				);
			} catch (err) {
				/* ignore */
			}
			syncToggleUi();
		});
	}

	function setBilling(plan) {
		document.querySelectorAll('[data-nestform-billing] [data-plan]').forEach(function (btn) {
			btn.classList.toggle('is-active', btn.getAttribute('data-plan') === plan);
		});
		document.querySelectorAll('[data-nestform-price-monthly]').forEach(function (el) {
			el.hidden = plan !== 'monthly';
		});
		document.querySelectorAll('[data-nestform-price-yearly]').forEach(function (el) {
			el.hidden = plan !== 'yearly';
		});
		document.querySelectorAll('[data-nestform-billed-yearly]').forEach(function (el) {
			el.hidden = plan !== 'yearly';
		});
	}

	document.addEventListener('click', function (event) {
		var bill = event.target.closest('[data-nestform-billing] [data-plan]');
		if (bill) {
			event.preventDefault();
			setBilling(bill.getAttribute('data-plan') || 'monthly');
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSidebarToggle);
	} else {
		initSidebarToggle();
	}
})();
