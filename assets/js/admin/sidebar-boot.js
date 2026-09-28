/**
 * Apply the collapsed sidebar class before first paint.
 */
(function () {
	'use strict';

	try {
		if (window.localStorage.getItem('thimbleform_sidebar_collapsed') === '1') {
			document.documentElement.classList.add('thimbleform-sidebar-collapsed');
		}
	} catch (err) {
		/* ignore */
	}
})();
