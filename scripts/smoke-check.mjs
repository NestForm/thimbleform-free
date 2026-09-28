/**
 * Thimbleform smoke checks: PHP lint, build artifacts, dashboard wiring.
 *
 * Usage (from plugin root): npm test
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const proRoot = path.resolve(root, '..', 'thimbleform-pro');

const fails = [];
const passes = [];

function ok(msg) {
	passes.push(msg);
	console.log(`  PASS  ${msg}`);
}

function fail(msg) {
	fails.push(msg);
	console.error(`  FAIL  ${msg}`);
}

function findPhp() {
	if (process.env.PHP_BIN && fs.existsSync(process.env.PHP_BIN)) {
		return process.env.PHP_BIN;
	}
	const candidates = [
		'D:\\work\\OSPanel\\modules\\PHP-8.3\\php.exe',
		'D:\\work\\OSPanel\\modules\\PHP-8.2\\php.exe',
		'D:\\work\\OSPanel\\modules\\PHP-8.1\\php.exe',
		'D:\\OSPanel\\modules\\PHP-8.3\\php.exe',
		'php',
	];
	for (const bin of candidates) {
		const r = spawnSync(bin, ['-v'], { encoding: 'utf8' });
		if (r.status === 0) {
			return bin;
		}
	}
	return null;
}

function phpLintTree(phpBin, dir, label) {
	if (!fs.existsSync(dir)) {
		fail(`${label}: missing ${dir}`);
		return;
	}
	const files = [];
	function walk(d) {
		for (const name of fs.readdirSync(d)) {
			const full = path.join(d, name);
			const st = fs.statSync(full);
			if (st.isDirectory()) {
				walk(full);
			} else if (name.endsWith('.php')) {
				files.push(full);
			}
		}
	}
	walk(dir);
	let errors = 0;
	for (const file of files) {
		const r = spawnSync(phpBin, ['-l', file], { encoding: 'utf8' });
		if (r.status !== 0) {
			errors += 1;
			fail(`php -l ${path.relative(root, file)}: ${(r.stdout || r.stderr || '').trim()}`);
		}
	}
	if (errors === 0) {
		ok(`${label}: ${files.length} PHP files lint clean`);
	}
}

function assertContains(file, needle, label) {
	const text = fs.readFileSync(file, 'utf8');
	if (text.includes(needle)) {
		ok(label);
	} else {
		fail(`${label} (missing ${JSON.stringify(needle)} in ${path.relative(root, file)})`);
	}
}

function assertNotContains(file, needle, label) {
	const text = fs.readFileSync(file, 'utf8');
	if (!text.includes(needle)) {
		ok(label);
	} else {
		fail(`${label} (still has ${JSON.stringify(needle)} in ${path.relative(root, file)})`);
	}
}

console.log('\nThimbleform smoke check\n');

const php = findPhp();
if (!php) {
	fail('PHP binary not found (set PHP_BIN)');
} else {
	ok(`PHP: ${php}`);
	phpLintTree(php, path.join(root, 'includes'), 'thimbleform/includes');
	if (fs.existsSync(path.join(proRoot, 'includes'))) {
		phpLintTree(php, path.join(proRoot, 'includes'), 'thimbleform-pro/includes');
	} else {
		fail('thimbleform-pro/includes missing');
	}
}

const build = spawnSync(process.platform === 'win32' ? 'npm.cmd' : 'npm', ['run', 'build'], {
	cwd: root,
	encoding: 'utf8',
	shell: true,
});
if (build.status === 0) {
	ok('npm run build');
} else {
	fail(`npm run build failed:\n${build.stdout || ''}\n${build.stderr || ''}`);
}

const dash = path.join(root, 'includes', 'class-dashboard.php');
const leads = path.join(proRoot, 'includes', 'class-lead-insights.php');
const developers = path.join(root, 'includes', 'class-developers.php');
const adminCss = path.join(root, 'assets', 'css', 'admin.css');
const dashCss = path.join(root, 'assets', 'css', 'admin', '07-dashboard.css');
const layoutCss = path.join(root, 'assets', 'css', 'admin', '11-screens-layout.css');

assertContains(dash, 'thimbleform_dashboard_work_pulse_extra', 'dashboard exposes work_pulse_extra filter');
assertContains(dash, 'thimbleform-dash__pulse', 'dashboard Work pulse markup');
assertNotContains(dash, 'chart-foot', 'dashboard has no chart-foot markup');
assertNotContains(dash, 'Status mix', 'dashboard has no Status mix');

assertContains(leads, 'render_work_pulse', 'Pro Hot pulse handler');
assertContains(leads, '$has_sources', 'Pro Sources empty guard');
assertNotContains(leads, 'sources-tile--hours', 'Pro Sources no hours tile');

assertContains(developers, 'thimbleform_dashboard_work_pulse_extra', 'Developers docs list work_pulse_extra');

assertContains(adminCss, 'thimbleform-dash__pulse', 'bundled CSS includes pulse');
assertContains(adminCss, 'thimbleform-dash__quick-item', 'bundled CSS includes quick items');
assertNotContains(adminCss, 'thimbleform-dash__chart-foot{', 'bundled CSS dropped chart-foot');
assertNotContains(dashCss, '.thimbleform-dash__mix {', 'partial CSS dropped Status mix');
assertNotContains(layoutCss, '.thimbleform-dash__hours {', 'partial CSS dropped hours chart');
assertNotContains(layoutCss, '.thimbleform-dash__sources-foot {', 'partial CSS dropped sources-foot');
assertNotContains(layoutCss, '.thimbleform-dash__insights-sources-title {', 'partial CSS dropped insights-sources');

console.log(`\n${passes.length} passed, ${fails.length} failed\n`);
process.exit(fails.length ? 1 : 0);
