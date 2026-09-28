/**
 * Shared Forms / Entries hub list: search, sort, filter, copy, pagination.
 */
(function () {
	'use strict';

	var cfg = window.thimbleformHub || {};
	var i18n = cfg.i18n || {};
	var pageSize = Math.max(1, parseInt(cfg.pageSize, 10) || 20);

	function bootHub(root) {
		var search = root.querySelector('[data-thimbleform-hub-search]');
		var sort = root.querySelector('[data-thimbleform-hub-sort]');
		var hasOnly = root.querySelector('[data-thimbleform-hub-has-count]');
		var list = root.querySelector('[data-thimbleform-hub-list]');
		var empty = root.querySelector('[data-thimbleform-hub-empty]');
		var result = root.querySelector('[data-thimbleform-hub-result]');
		var pagerTop = root.querySelector('[data-thimbleform-hub-pager]');
		var pagerBottom = root.querySelector('[data-thimbleform-hub-pager-bottom]');
		var chipButtons = Array.prototype.slice.call(
			root.querySelectorAll('[data-thimbleform-hub-chip]')
		);
		if (!list) {
			return;
		}

		var rows = Array.prototype.slice.call(list.querySelectorAll('[data-thimbleform-hub-row]'));
		var currentPage = 1;
		var filterToken = '';
		var chip = 'all';

		function filterKey(q, onlyWith, mode, chipKey) {
			return [q, onlyWith ? '1' : '0', mode, chipKey || 'all'].join('|');
		}

		function setActiveChip(next) {
			chip = next || 'all';
			chipButtons.forEach(function (btn) {
				var key = btn.getAttribute('data-thimbleform-hub-chip') || 'all';
				var on = key === chip;
				btn.classList.toggle('is-active', on);
				btn.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		}

		function renderPager(pager, matched, page, pages) {
			if (!pager) {
				return;
			}
			if (matched === 0) {
				pager.hidden = true;
				pager.innerHTML = '';
				return;
			}

			var from = (page - 1) * pageSize + 1;
			var to = Math.min(matched, page * pageSize);
			var metaTpl = i18n.showingRange || 'Showing %1$s–%2$s of %3$s';
			var meta = metaTpl
				.replace('%1$s', String(from))
				.replace('%2$s', String(to))
				.replace('%3$s', String(matched));

			var html = '<p class="thimbleform-hub__pager-meta">' + meta + '</p>';
			if (pages > 1) {
				html += '<div class="thimbleform-hub__pager-links">';
				var start = Math.max(1, page - 2);
				var end = Math.min(pages, page + 2);
				if (page > 1) {
					html +=
						'<button type="button" class="page-numbers" data-thimbleform-hub-page="' +
						(page - 1) +
						'">&lsaquo;</button>';
				}
				if (start > 1) {
					html += '<button type="button" class="page-numbers" data-thimbleform-hub-page="1">1</button>';
					if (start > 2) {
						html += '<span class="page-numbers dots">&hellip;</span>';
					}
				}
				for (var i = start; i <= end; i++) {
					if (i === page) {
						html += '<span class="page-numbers current" aria-current="page">' + i + '</span>';
					} else {
						html +=
							'<button type="button" class="page-numbers" data-thimbleform-hub-page="' +
							i +
							'">' +
							i +
							'</button>';
					}
				}
				if (end < pages) {
					if (end < pages - 1) {
						html += '<span class="page-numbers dots">&hellip;</span>';
					}
					html +=
						'<button type="button" class="page-numbers" data-thimbleform-hub-page="' +
						pages +
						'">' +
						pages +
						'</button>';
				}
				if (page < pages) {
					html +=
						'<button type="button" class="page-numbers" data-thimbleform-hub-page="' +
						(page + 1) +
						'">&rsaquo;</button>';
				}
				html += '</div>';
			}

			pager.innerHTML = html;
			pager.hidden = false;
		}

		function apply() {
			var q = search ? String(search.value || '').trim().toLowerCase() : '';
			var onlyWith = hasOnly && hasOnly.checked;
			var mode = sort ? sort.value : 'count';
			var token = filterKey(q, onlyWith, mode, chip);
			if (token !== filterToken) {
				filterToken = token;
				currentPage = 1;
			}

			rows.sort(function (a, b) {
				var ca = parseInt(a.getAttribute('data-count') || '0', 10);
				var cb = parseInt(b.getAttribute('data-count') || '0', 10);
				var fa = parseInt(a.getAttribute('data-fields') || '0', 10);
				var fb = parseInt(b.getAttribute('data-fields') || '0', 10);
				var na = parseInt(a.getAttribute('data-new') || '0', 10);
				var nb = parseInt(b.getAttribute('data-new') || '0', 10);
				var la = parseInt(a.getAttribute('data-last') || '0', 10);
				var lb = parseInt(b.getAttribute('data-last') || '0', 10);
				var ta = (a.getAttribute('data-title') || '').toLowerCase();
				var tb = (b.getAttribute('data-title') || '').toLowerCase();
				var ia = parseInt(a.getAttribute('data-id') || '0', 10);
				var ib = parseInt(b.getAttribute('data-id') || '0', 10);

				if (mode === 'title') {
					return ta < tb ? -1 : ta > tb ? 1 : ia - ib;
				}
				if (mode === 'id') {
					return ia - ib;
				}
				if (mode === 'fields') {
					if (fb !== fa) {
						return fb - fa;
					}
					return ta < tb ? -1 : ta > tb ? 1 : 0;
				}
				if (mode === 'new') {
					if (nb !== na) {
						return nb - na;
					}
					if (cb !== ca) {
						return cb - ca;
					}
					return ta < tb ? -1 : ta > tb ? 1 : 0;
				}
				if (mode === 'last') {
					if (lb !== la) {
						return lb - la;
					}
					return ta < tb ? -1 : ta > tb ? 1 : 0;
				}
				if (cb !== ca) {
					return cb - ca;
				}
				return ta < tb ? -1 : ta > tb ? 1 : 0;
			});

			rows.forEach(function (row) {
				list.appendChild(row);
			});

			var matchedRows = [];
			rows.forEach(function (row) {
				var title = (row.getAttribute('data-title') || '').toLowerCase();
				var id = row.getAttribute('data-id') || '';
				var count = parseInt(row.getAttribute('data-count') || '0', 10);
				var status = row.getAttribute('data-status') || '';
				var newCount = parseInt(row.getAttribute('data-new') || '0', 10);
				var match =
					(!q || title.indexOf(q) !== -1 || id.indexOf(q) !== -1) &&
					(!onlyWith || count > 0);

				if (match && chip === 'publish' && status !== 'publish') {
					match = false;
				}
				if (match && chip === 'draft' && status === 'publish') {
					match = false;
				}
				if (match && chip === 'new' && newCount <= 0) {
					match = false;
				}

				row.setAttribute('data-hub-match', match ? '1' : '0');
				if (match) {
					matchedRows.push(row);
				}
			});

			var matched = matchedRows.length;
			var pages = Math.max(1, Math.ceil(matched / pageSize));
			if (currentPage > pages) {
				currentPage = pages;
			}

			var start = (currentPage - 1) * pageSize;
			var end = start + pageSize;
			rows.forEach(function (row) {
				if (row.getAttribute('data-hub-match') !== '1') {
					row.hidden = true;
					return;
				}
				var idx = matchedRows.indexOf(row);
				row.hidden = idx < start || idx >= end;
			});

			if (empty) {
				empty.hidden = matched > 0;
			}
			if (result) {
				result.hidden = true;
			}

			renderPager(pagerTop, matched, currentPage, pages);
			renderPager(pagerBottom, matched, currentPage, pages);
		}

		if (search) {
			search.addEventListener('input', apply);
			window.setTimeout(function () {
				search.focus();
			}, 0);
		}
		if (sort) {
			sort.addEventListener('change', apply);
		}
		if (hasOnly) {
			hasOnly.addEventListener('change', apply);
		}

		function closeMoreMenus(except) {
			root.querySelectorAll('[data-thimbleform-hub-more]').forEach(function (wrap) {
				if (except && wrap === except) {
					return;
				}
				var menu = wrap.querySelector('.thimbleform-hub__more-menu');
				var toggle = wrap.querySelector('.thimbleform-hub__more-toggle');
				if (menu) {
					menu.hidden = true;
				}
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
		}

		function closeImportMenus(except) {
			document.querySelectorAll('[data-thimbleform-hub-import]').forEach(function (wrap) {
				if (except && wrap === except) {
					return;
				}
				var panel = wrap.querySelector('.thimbleform-hub__import-panel');
				var toggle = wrap.querySelector('.thimbleform-hub__import-toggle');
				if (panel) {
					panel.hidden = true;
				}
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
		}

		function onDocCloseMenus(event) {
			var t = event.target;
			if (!(t.closest && t.closest('[data-thimbleform-hub-more]'))) {
				closeMoreMenus();
			}
			if (!(t.closest && t.closest('[data-thimbleform-hub-import]'))) {
				closeImportMenus();
			}
		}

		function onDocKeyCloseMenus(event) {
			if (event.key === 'Escape') {
				closeMoreMenus();
				closeImportMenus();
			}
		}

		// Capture on document so clicks outside the hub (sidebar, header, etc.) close menus.
		document.addEventListener('pointerdown', onDocCloseMenus, true);
		document.addEventListener('keydown', onDocKeyCloseMenus);

		root.addEventListener('click', function (event) {
			var chipBtn = event.target.closest('[data-thimbleform-hub-chip]');
			if (chipBtn && root.contains(chipBtn)) {
				event.preventDefault();
				setActiveChip(chipBtn.getAttribute('data-thimbleform-hub-chip') || 'all');
				apply();
				return;
			}

			var moreToggle = event.target.closest('.thimbleform-hub__more-toggle');
			if (moreToggle && root.contains(moreToggle)) {
				event.preventDefault();
				var wrap = moreToggle.closest('[data-thimbleform-hub-more]');
				var menu = wrap ? wrap.querySelector('.thimbleform-hub__more-menu') : null;
				var open = menu && menu.hidden;
				closeMoreMenus(wrap);
				closeImportMenus();
				if (menu) {
					menu.hidden = !open;
				}
				moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				return;
			}

			var pageBtn = event.target.closest('[data-thimbleform-hub-page]');
			if (pageBtn && root.contains(pageBtn)) {
				event.preventDefault();
				var next = parseInt(pageBtn.getAttribute('data-thimbleform-hub-page') || '1', 10);
				if (!isNaN(next) && next >= 1) {
					currentPage = next;
					apply();
					if (list && typeof list.scrollIntoView === 'function') {
						list.scrollIntoView({ block: 'start', behavior: 'smooth' });
					}
				}
				return;
			}

			var del = event.target.closest('[data-thimbleform-hub-delete]');
			if (del) {
				var confirmMsg = del.getAttribute('data-confirm') || 'Move this form to Trash?';
				if (!window.confirm(confirmMsg)) {
					event.preventDefault();
				}
				return;
			}

			var btn = event.target.closest('[data-thimbleform-hub-copy]');
			if (btn) {
				event.preventDefault();
				var text = btn.getAttribute('data-thimbleform-hub-copy') || '';
				if (!text) {
					return;
				}
				var done = function () {
					var prev = btn.textContent;
					btn.textContent = i18n.copied || 'Copied';
					window.setTimeout(function () {
						btn.textContent = prev;
					}, 1200);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done).catch(function () {
						window.prompt(i18n.copyPrompt || 'Copy:', text);
					});
				} else {
					window.prompt(i18n.copyPrompt || 'Copy:', text);
				}
				return;
			}

			if (event.target.closest('a, button, input, select, textarea, label')) {
				return;
			}

			var row = event.target.closest('[data-thimbleform-hub-row]');
			if (!row || !list.contains(row)) {
				return;
			}
			var editUrl = row.getAttribute('data-edit-url') || '';
			if (editUrl) {
				window.location.href = editUrl;
			}
		});

		root.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' && event.key !== ' ') {
				return;
			}
			if (event.target.closest('a, button, input, select, textarea')) {
				return;
			}
			var row = event.target.closest('[data-thimbleform-hub-row]');
			if (!row || !list.contains(row) || event.target !== row) {
				return;
			}
			var editUrl = row.getAttribute('data-edit-url') || '';
			if (!editUrl) {
				return;
			}
			event.preventDefault();
			window.location.href = editUrl;
		});

		if (chipButtons.length) {
			setActiveChip('all');
		}
		apply();
	}

	function bootImportMenus() {
		document.querySelectorAll('[data-thimbleform-hub-import]').forEach(function (wrap) {
			var toggle = wrap.querySelector('.thimbleform-hub__import-toggle');
			var panel = wrap.querySelector('.thimbleform-hub__import-panel');
			if (!toggle || !panel) {
				return;
			}
			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				var open = panel.hidden;
				document.querySelectorAll('[data-thimbleform-hub-import]').forEach(function (other) {
					if (other === wrap) {
						return;
					}
					var p = other.querySelector('.thimbleform-hub__import-panel');
					var t = other.querySelector('.thimbleform-hub__import-toggle');
					if (p) {
						p.hidden = true;
					}
					if (t) {
						t.setAttribute('aria-expanded', 'false');
					}
				});
				panel.hidden = !open;
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-thimbleform-hub]').forEach(bootHub);
		bootImportMenus();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
