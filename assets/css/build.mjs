/**
 * Concatenate CSS partials and minify shipped bundles.
 *
 * Usage (from plugin root):
 *   npm run build:css
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import CleanCSS from 'clean-css';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function createMinifier() {
	// Level 2 restructures shorthand and can strip color-mix() from border — keep level 1.
	return new CleanCSS({
		level: 1,
		format: 'keep-breaks',
	});
}

function readSortedCss(dir) {
	return fs
		.readdirSync(dir)
		.filter((name) => name.endsWith('.css'))
		.sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
}

function formatKb(bytes) {
	return `${(bytes / 1024).toFixed(1)} KB`;
}

function minifyChunk(body, label, minifier) {
	const raw = body.trimEnd() + '\n';
	const result = minifier.minify(raw);

	if (result.errors.length) {
		console.error(`Minify failed (${label}):`, result.errors.join('; '));
		process.exit(1);
	}

	if (result.warnings.length) {
		console.warn(`Minify warnings (${label}):`);
		for (const warning of result.warnings) {
			console.warn(`  - ${warning}`);
		}
		process.exit(1);
	}

	return { raw, styles: result.styles };
}

function writeMinifiedBundle(target, header, body, label) {
	const minifier = createMinifier();
	const { raw, styles } = minifyChunk(body, label, minifier);

	fs.writeFileSync(target, header + styles);
	const saved = raw.length - styles.length;
	const pct = raw.length ? Math.round((saved / raw.length) * 100) : 0;
	console.log(
		`Wrote ${target} (${label}: ${formatKb(raw.length)} → ${formatKb(styles.length)}, −${pct}%)`
	);
}

function bundleAdmin() {
	const adminDir = path.join(__dirname, 'admin');
	const files = readSortedCss(adminDir);
	const header =
		'/**\n' +
		' * Thimbleform admin — bundled + minified from assets/css/admin/*.css\n' +
		' * Do not edit directly. Run: npm run build:css\n' +
		' */\n';

	const minifier = createMinifier();
	let body = '';
	let minifiedBody = '';

	for (const file of files) {
		const chunk = fs.readFileSync(path.join(adminDir, file), 'utf8').trimEnd();
		body += `/* ${file} */\n${chunk}\n\n`;

		const { styles } = minifyChunk(`/* ${file} */\n${chunk}\n`, file, minifier);
		minifiedBody += `/* ${file} */\n${styles.trimEnd()}\n\n`;
	}

	const rawLen = body.length;
	const outLen = minifiedBody.length;
	fs.writeFileSync(path.join(__dirname, 'admin.css'), header + minifiedBody.trimEnd() + '\n');
	const pct = rawLen ? Math.round(((rawLen - outLen) / rawLen) * 100) : 0;
	console.log(
		`Wrote ${path.join(__dirname, 'admin.css')} (${files.length} partials: ${formatKb(rawLen)} → ${formatKb(outLen)}, −${pct}%)`
	);
}

function bundleFront() {
	const frontFile = path.join(__dirname, 'front', 'forms.css');
	if (!fs.existsSync(frontFile)) {
		console.warn('Skip front: assets/css/front/forms.css missing');
		return;
	}

	const header =
		'/**\n' +
		' * Thimbleform front — bundled + minified from assets/css/front/forms.css\n' +
		' * Do not edit directly. Run: npm run build:css\n' +
		' */\n';

	writeMinifiedBundle(
		path.join(__dirname, 'front.css'),
		header,
		fs.readFileSync(frontFile, 'utf8'),
		'front'
	);
}

bundleAdmin();
bundleFront();
