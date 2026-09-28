/**
 * Thimbleform dashboard Chart.js (Free + Pro series).
 */
(function () {
	'use strict';

	var ChartLib = window.Chart;
	var i18n = (window.thimbleformDashChart && window.thimbleformDashChart.i18n) || {};

	var LOCALE = 'en-US';

	var SERIES_PALETTE = {
		submissions: {
			line: '#2563eb',
			softLight: 'rgba(37, 99, 235, 0.16)',
			softDark: 'rgba(59, 130, 246, 0.22)',
		},
		views: {
			line: '#6366f1',
			softLight: 'rgba(99, 102, 241, 0.16)',
			softDark: 'rgba(129, 140, 248, 0.22)',
		},
		conversion: {
			line: '#1e3a8a',
			softLight: 'rgba(30, 58, 138, 0.16)',
			softDark: 'rgba(37, 99, 235, 0.24)',
		},
	};

	function seriesPalette(key) {
		return SERIES_PALETTE[key] || SERIES_PALETTE.submissions;
	}

	function isDarkAdmin() {
		var body = document.body;
		if (!body) {
			return false;
		}
		if (body.classList.contains('thimbleform-theme-dark')) {
			return true;
		}
		if (body.classList.contains('thimbleform-theme-light')) {
			return false;
		}
		return (
			window.matchMedia &&
			window.matchMedia('(prefers-color-scheme: dark)').matches
		);
	}

	function chartColors(metric) {
		var dark = isDarkAdmin();
		var series = seriesPalette(metric || 'submissions');
		return {
			line: series.line,
			accentSoft: dark ? series.softDark : series.softLight,
			grid: dark ? 'rgba(148, 163, 184, 0.14)' : 'rgba(15, 23, 42, 0.08)',
			tick: dark ? '#9aa3b5' : '#64748b',
			pointBg: dark ? '#161921' : '#ffffff',
			tooltipBg: dark ? '#1c2030' : '#0f172a',
			tooltipTitle: dark ? '#eef0f5' : '#f8fafc',
			tooltipBody: dark ? '#c5cdd8' : '#e2e8f0',
			tooltipBorder: dark ? 'rgba(148, 163, 184, 0.28)' : 'rgba(148, 163, 184, 0.35)',
		};
	}

	var state = {
		wrap: null,
		canvas: null,
		chart: null,
		active: 'submissions',
		series: {},
	};

	function parseJson(node) {
		if (!node) {
			return null;
		}
		try {
			return JSON.parse(node.textContent || '{}');
		} catch (e) {
			return null;
		}
	}

	function formatDate(iso) {
		if (!iso) {
			return '';
		}
		try {
			var d = new Date(iso + 'T12:00:00');
			return d.toLocaleDateString(LOCALE, { day: 'numeric', month: 'short' });
		} catch (e) {
			return iso;
		}
	}

	function formatValue(value, unit) {
		if (value === null || value === undefined || (typeof value === 'number' && isNaN(value))) {
			return '—';
		}
		var n = Number(value);
		if (!isFinite(n)) {
			return '—';
		}
		if (unit === 'percent') {
			return (
				(Math.round(n * 10) / 10).toLocaleString(LOCALE, {
					minimumFractionDigits: n % 1 ? 1 : 0,
					maximumFractionDigits: 1,
				}) + '%'
			);
		}
		if (Math.abs(n - Math.round(n)) < 0.001) {
			return Math.round(n).toLocaleString(LOCALE);
		}
		return n.toLocaleString(LOCALE, { maximumFractionDigits: 1 });
	}

	function seriesLabel(key, data) {
		if (data && data.label) {
			return data.label;
		}
		return i18n[key] || key;
	}

	function activeSeries() {
		return state.series[state.active] || null;
	}

	function seriesToChartData(data) {
		var labels = [];
		var values = [];
		if (data && Array.isArray(data.labels) && Array.isArray(data.values)) {
			labels = data.labels.slice();
			values = data.values.map(function (v) {
				if (v === null || v === undefined || v === '') {
					return null;
				}
				var n = Number(v);
				return isFinite(n) ? n : null;
			});
		} else if (data && Array.isArray(data.dots)) {
			data.dots.forEach(function (dot) {
				labels.push(dot.date);
				if (dot.value === null || dot.value === undefined || dot.value === '') {
					values.push(null);
					return;
				}
				var n = Number(dot.value);
				values.push(isFinite(n) ? n : null);
			});
		}
		return { labels: labels, values: values };
	}

	function updateLegend(data) {
		if (!state.wrap || !data) {
			return;
		}
		var labelEl = state.wrap.querySelector('[data-thimbleform-chart-legend-label]');
		var peakEl = state.wrap.querySelector('[data-thimbleform-chart-peak]');
		var avgEl = state.wrap.querySelector('[data-thimbleform-chart-avg]');
		if (labelEl) {
			labelEl.textContent = seriesLabel(state.active, data);
		}
		if (peakEl) {
			var peakDate = formatDate(data.peak_day || '');
			peakEl.textContent =
				'Peak ' + (data.peak != null ? data.peak : '—') + (peakDate ? ' · ' + peakDate : '');
		}
		if (avgEl) {
			avgEl.textContent =
				data.avg != null ? 'Avg ' + data.avg + ' / day' : 'Avg —';
		}
	}

	function buildChart(data) {
		if (!ChartLib || !state.canvas || !data) {
			return;
		}
		var colors = chartColors(state.active);
		var packed = seriesToChartData(data);
		var unit = data.unit || 'count';
		var label = seriesLabel(state.active, data);

		if (state.wrap) {
			state.wrap.setAttribute('data-thimbleform-chart-metric', state.active);
		}

		if (state.chart) {
			state.chart.destroy();
			state.chart = null;
		}

		state.chart = new ChartLib(state.canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: packed.labels,
				datasets: [
					{
						label: label,
						data: packed.values,
						borderColor: colors.line,
						backgroundColor: colors.accentSoft,
						borderWidth: 2,
						fill: true,
						tension: 0.35,
						spanGaps: true,
						clip: false,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHitRadius: 12,
						pointBackgroundColor: colors.pointBg,
						pointBorderColor: colors.line,
						pointBorderWidth: 2,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				devicePixelRatio: (window.devicePixelRatio || 1) > 1 ? window.devicePixelRatio : 1,
				animation: false,
				layout: {
					padding: {
						top: 18,
						right: 10,
						bottom: 6,
						left: 4,
					},
				},
				interaction: {
					mode: 'index',
					intersect: false,
				},
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: colors.tooltipBg,
						titleColor: colors.tooltipTitle,
						bodyColor: colors.tooltipBody,
						borderColor: colors.tooltipBorder,
						borderWidth: 1,
						padding: 10,
						displayColors: false,
						caretPadding: 10,
						caretSize: 6,
						callbacks: {
							title: function (items) {
								if (!items.length) {
									return '';
								}
								return formatDate(String(items[0].label || ''));
							},
							label: function (item) {
								var y = item.parsed && item.parsed.y;
								if (y === null || y === undefined || (typeof y === 'number' && isNaN(y))) {
									return '— · ' + label;
								}
								return formatValue(y, unit) + ' · ' + label;
							},
						},
					},
				},
				scales: {
					x: {
						grid: { display: false },
						border: { display: false },
						ticks: {
							color: colors.tick,
							padding: 10,
							maxRotation: 0,
							autoSkip: true,
							maxTicksLimit: packed.labels.length > 40 ? 6 : packed.labels.length > 14 ? 7 : 5,
							callback: function (value) {
								var raw = this.getLabelForValue(value);
								return formatDate(String(raw || ''));
							},
						},
					},
					y: (function () {
						var scale = {
							beginAtZero: true,
							border: { display: false },
							grid: {
								color: colors.grid,
								drawTicks: false,
							},
							ticks: {
								color: colors.tick,
								padding: 8,
								precision: unit === 'percent' ? 1 : 0,
								maxTicksLimit: 5,
								callback: function (value) {
									if (unit === 'percent') {
										return formatValue(value, 'percent');
									}
									return Number(value).toLocaleString();
								},
							},
						};
						if (unit === 'percent') {
							scale.max = 100;
							scale.grace = 0;
						} else {
							scale.grace = '12%';
						}
						return scale;
					})(),
				},
			},
		});
	}

	function applyMetric(key) {
		if (!state.series[key]) {
			return;
		}
		state.active = key;
		updateLegend(state.series[key]);
		buildChart(state.series[key]);
	}

	function boot() {
		state.wrap = document.querySelector('[data-thimbleform-chart-wrap]');
		if (!state.wrap || !ChartLib) {
			return;
		}
		state.canvas = state.wrap.querySelector('[data-thimbleform-chart-canvas]');

		var free = parseJson(state.wrap.querySelector('[data-thimbleform-chart]'));
		var pro = parseJson(state.wrap.querySelector('[data-thimbleform-pro-chart]'));
		if (pro && typeof pro === 'object') {
			state.series = pro;
			state.active = 'submissions';
			if (
				state.series.submissions &&
				!(state.series.submissions.total > 0) &&
				state.series.views &&
				state.series.views.total > 0
			) {
				state.active = 'views';
			}
		} else if (free && free.series) {
			state.series = free.series;
			state.active = free.active || 'submissions';
		}

		if (!activeSeries()) {
			return;
		}
		updateLegend(activeSeries());
		buildChart(activeSeries());

		var plot = state.wrap.querySelector('[data-thimbleform-chart-plot]');
		if (plot && typeof ResizeObserver !== 'undefined') {
			var resizeTimer = null;
			var ro = new ResizeObserver(function () {
				if (!state.chart) {
					return;
				}
				window.clearTimeout(resizeTimer);
				resizeTimer = window.setTimeout(function () {
					if (state.chart) {
						state.chart.resize();
					}
				}, 50);
			});
			ro.observe(plot);
		}

		if (window.matchMedia) {
			window
				.matchMedia('(prefers-color-scheme: dark)')
				.addEventListener('change', function () {
					var series = activeSeries();
					if (series) {
						buildChart(series);
					}
				});
		}
	}

	window.thimbleformDashChartApi = {
		applyMetric: applyMetric,
		setActive: applyMetric,
		getSeries: function () {
			return state.series;
		},
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
