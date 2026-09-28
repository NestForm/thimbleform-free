/**
 * Thimbleform app shell helpers (sidebar collapse, Pro promotion).
 */
(function () {
	'use strict';

	var cfg = window.thimbleformPro || {};

	function initSidebarToggle() {
		var app = document.querySelector('[data-thimbleform-app]');
		var toggle = document.querySelector('[data-thimbleform-sidebar-toggle]');
		if (!app || !toggle) {
			return;
		}

		var i18n = (cfg.i18n && cfg.i18n.sidebar) || {};
		var labelEl = toggle.querySelector('.thimbleform-app__sidebar-toggle-label');

		function isCollapsed() {
			return (
				app.classList.contains('thimbleform-app--sidebar-collapsed') ||
				document.documentElement.classList.contains('thimbleform-sidebar-collapsed')
			);
		}

		function applyCollapsed(collapsed) {
			app.classList.toggle('thimbleform-app--sidebar-collapsed', collapsed);
			document.documentElement.classList.toggle('thimbleform-sidebar-collapsed', collapsed);
		}

		if (document.documentElement.classList.contains('thimbleform-sidebar-collapsed')) {
			app.classList.add('thimbleform-app--sidebar-collapsed');
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
					'thimbleform_sidebar_collapsed',
					collapsed ? '1' : '0'
				);
			} catch (err) {
				/* ignore */
			}
			syncToggleUi();
		});
	}

	function setBilling(plan) {
		document.querySelectorAll('[data-thimbleform-billing] [data-plan]').forEach(function (btn) {
			btn.classList.toggle('is-active', btn.getAttribute('data-plan') === plan);
		});
		document.querySelectorAll('[data-thimbleform-price-monthly]').forEach(function (el) {
			el.hidden = plan !== 'monthly';
		});
		document.querySelectorAll('[data-thimbleform-price-yearly]').forEach(function (el) {
			el.hidden = plan !== 'yearly';
		});
		document.querySelectorAll('[data-thimbleform-billed-yearly]').forEach(function (el) {
			el.hidden = plan !== 'yearly';
		});
	}

	document.addEventListener('click', function (event) {
		var bill = event.target.closest('[data-thimbleform-billing] [data-plan]');
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
