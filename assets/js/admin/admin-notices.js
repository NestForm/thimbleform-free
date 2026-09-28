/**
 * Move WP notices into a body-level toast stack after common.js relocates them.
 */
(function ($) {
	'use strict';

	var HIDE_MS = 5000;
	var OUT_MS = 220;
	var armed = typeof WeakSet === 'function' ? new WeakSet() : null;

	function isToastNotice(el) {
		if (!el || el.nodeType !== 1) {
			return false;
		}
		if (el.classList.contains('inline') || el.classList.contains('below-h2') || el.classList.contains('hidden') || el.classList.contains('update-nag')) {
			return false;
		}
		if (el.closest('.postbox, .media-modal, #screen-meta, .thimbleform-toasts')) {
			return false;
		}
		return el.classList.contains('notice') || el.classList.contains('updated') || el.classList.contains('error');
	}

	function host() {
		var el = document.querySelector('.thimbleform-toasts');
		if (el) {
			return el;
		}
		el = document.createElement('div');
		el.className = 'thimbleform-toasts';
		el.setAttribute('aria-live', 'polite');
		document.body.appendChild(el);
		return el;
	}

	function hide(notice) {
		if (!notice || notice.classList.contains('is-leaving')) {
			return;
		}
		notice.classList.add('is-leaving');
		window.setTimeout(function () {
			if (notice.parentNode) {
				notice.parentNode.removeChild(notice);
			}
		}, OUT_MS);
	}

	function arm(notice) {
		if (armed) {
			if (armed.has(notice)) {
				return;
			}
			armed.add(notice);
		} else if (notice.getAttribute('data-thimbleform-toast') === '1') {
			return;
		} else {
			notice.setAttribute('data-thimbleform-toast', '1');
		}
		var timer = window.setTimeout(function () {
			hide(notice);
		}, HIDE_MS);
		notice.addEventListener(
			'click',
			function (event) {
				if (event.target.closest('.notice-dismiss')) {
					window.clearTimeout(timer);
					hide(notice);
				}
			},
			true
		);
	}

	function adoptAll() {
		var nodes = document.querySelectorAll('div.notice, div.updated, div.error');
		var stack = null;
		Array.prototype.forEach.call(nodes, function (notice) {
			if (!isToastNotice(notice)) {
				return;
			}
			if (!stack) {
				stack = host();
			}
			if (notice.parentNode !== stack) {
				stack.appendChild(notice);
			}
			arm(notice);
		});
	}

	function boot() {
		adoptAll();
		var root = document.getElementById('wpbody-content');
		if (!root || !window.MutationObserver) {
			return;
		}
		var mo = new MutationObserver(function () {
			adoptAll();
		});
		mo.observe(root, { childList: true, subtree: true });
	}

	$(boot);
})(window.jQuery);