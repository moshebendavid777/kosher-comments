(() => {
	const dashboard = document.querySelector('[data-kosher-comments-dashboard]');

	if (!dashboard) {
		return;
	}

	const jsonNode = document.getElementById('kosher-comments-analytics-data');
	let analytics = null;

	try {
		analytics = jsonNode ? JSON.parse(jsonNode.textContent || '{}') : null;
	} catch (error) {
		analytics = null;
	}

	const animateCount = (node) => {
		const target = Number(node.dataset.countUp || 0);
		const decimals = Number(node.dataset.decimals || 0);
		const duration = 1100;
		const start = performance.now();

		const frame = (now) => {
			const progress = Math.min((now - start) / duration, 1);
			const eased = 1 - Math.pow(1 - progress, 3);
			const value = target * eased;
			node.textContent = decimals > 0 ? value.toFixed(decimals) : Math.round(value).toLocaleString();

			if (progress < 1) {
				requestAnimationFrame(frame);
			} else {
				node.textContent = decimals > 0 ? target.toFixed(decimals) : Math.round(target).toLocaleString();
			}
		};

		requestAnimationFrame(frame);
	};

	const animateWidths = () => {
		dashboard.querySelectorAll('.kosher-comments-admin-rating-track i, .kosher-comments-admin-location-track i, .kosher-comments-admin-post-bar i').forEach((bar, index) => {
			const width = bar.style.width || '0%';
			bar.style.setProperty('--target-width', width);
			bar.style.width = width;
			bar.style.animationDelay = `${140 + (index * 35)}ms`;
		});

		dashboard.querySelectorAll('.kosher-comments-admin-weekday-bar i').forEach((bar, index) => {
			bar.style.animationDelay = `${180 + (index * 60)}ms`;
		});
	};

	const renderLineChart = ({ rows, selector, labelKey, valueKey, labelInterval }) => {
		if (!Array.isArray(rows) || !rows.length) {
			return;
		}

		const chart = dashboard.querySelector(selector);

		if (!chart) {
			return;
		}

		const line = chart.querySelector('.kosher-comments-chart-line');
		const area = chart.querySelector('.kosher-comments-chart-area');
		const pointsWrap = chart.querySelector('.kosher-comments-chart-points');
		const labelsWrap = chart.querySelector('.kosher-comments-chart-labels');

		if (!line || !area || !pointsWrap || !labelsWrap) {
			return;
		}

		const parsedRows = rows.map((row) => ({
			label: String(row[labelKey] ?? ''),
			total: Number(row[valueKey] ?? 0),
		}));
		const maxValue = Math.max(...parsedRows.map((row) => row.total), 1);
		const left = 60;
		const right = 860;
		const top = 40;
		const bottom = 290;
		const width = right - left;
		const height = bottom - top;
		const step = parsedRows.length > 1 ? width / (parsedRows.length - 1) : width;

		const points = parsedRows.map((row, index) => {
			const x = left + (step * index);
			const y = bottom - ((row.total / maxValue) * height);
			return { ...row, x, y };
		});

		const linePath = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
		const areaPath = `${linePath} L ${points[points.length - 1].x} ${bottom} L ${points[0].x} ${bottom} Z`;

		line.setAttribute('d', linePath);
		area.setAttribute('d', areaPath);

		pointsWrap.innerHTML = points.map((point, index) => (
			`<circle cx="${point.x}" cy="${point.y}" r="7" style="animation-delay:${220 + (index * 45)}ms"></circle>`
		)).join('');

		labelsWrap.innerHTML = points.map((point, index) => {
			if (index !== 0 && index !== points.length - 1 && index % labelInterval !== 0) {
				return '';
			}

			return `<text x="${point.x}" y="312" text-anchor="middle">${point.label}</text>`;
		}).join('');
	};

	dashboard.querySelectorAll('[data-count-up]').forEach(animateCount);
	animateWidths();

	if (analytics) {
		renderLineChart({
			rows: analytics.recentDays || [],
			selector: '[data-trend-chart]',
			labelKey: 'label',
			valueKey: 'total',
			labelInterval: 2,
		});

		renderLineChart({
			rows: (analytics.timeOfDay || []).map((row) => ({
				label: `${String(Number(row.hour_of_day)).padStart(2, '0')}:00`,
				total: Number(row.total || 0),
			})),
			selector: '[data-activity-chart]',
			labelKey: 'label',
			valueKey: 'total',
			labelInterval: 3,
		});
	}
})();
