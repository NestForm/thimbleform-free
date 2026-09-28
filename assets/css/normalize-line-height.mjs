/**
 * Normalize line-height to design tokens (1.5 rhythm).
 *
 * Usage: node assets/css/normalize-line-height.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');
const proRoot = path.resolve(pluginRoot, '../thimbleform-pro');

const LINE_HEIGHT_RE = /(?<![a-z0-9-])line-height\s*:\s*(?<value>[^;]+);/g;

const SKIP_VALUES = new Set(['0', '0px', 'inherit', 'initial', 'unset', 'normal']);

function tokenForFile(filePath) {
	const normalized = filePath.replace(/\\/g, '/');
	if (normalized.includes('/css/admin/') || normalized.includes('/css/admin\\')) {
		return 'var(--nestform-line-height)';
	}
	return 'var(--nest-form-line-height)';
}

function shouldSkipValue(raw) {
	const value = raw.trim().replace(/\s*!important\s*$/i, '').trim();
	if (SKIP_VALUES.has(value)) {
		return true;
	}
	if (value.startsWith('var(--nestform-line-height)') || value.startsWith('var(--nest-form-line-height)')) {
		return true;
	}
	return false;
}

function normalizeValue(raw, token) {
	const trimmed = raw.trim();
	const important = /\s*!important\s*$/i.test(trimmed);
	if (shouldSkipValue(trimmed)) {
		return null;
	}
	return `${token}${important ? ' !important' : ''}`;
}

function processFile(filePath) {
	const source = fs.readFileSync(filePath, 'utf8');
	const token = tokenForFile(filePath);
	let changes = 0;

	const next = source.replace(LINE_HEIGHT_RE, (match, prop, value, offset) => {
		const normalized = normalizeValue(value, token);
		if (!normalized || normalized === value.trim()) {
			return match;
		}
		changes += 1;
		return `line-height: ${normalized};`;
	});

	if (changes > 0) {
		fs.writeFileSync(filePath, next);
	}

	return changes;
}

function collectCssFiles(dir) {
	if (!fs.existsSync(dir)) {
		return [];
	}

	return fs
		.readdirSync(dir, { withFileTypes: true })
		.filter((entry) => entry.isFile() && entry.name.endsWith('.css'))
		.map((entry) => path.join(dir, entry.name));
}

const targets = [
	...collectCssFiles(path.join(__dirname, 'admin')).filter(
		(file) => !file.endsWith(`${path.sep}01-tokens.css`)
	),
	path.join(__dirname, 'front', 'forms.css'),
	...collectCssFiles(path.join(proRoot, 'assets')),
].filter((file) => fs.existsSync(file));

let total = 0;
for (const file of targets) {
	const count = processFile(file);
	if (count) {
		total += count;
		console.log(`${path.relative(pluginRoot, file).replace(/\\/g, '/')}: ${count}`);
	}
}

console.log(`Done. Updated ${total} line-height rules.`);
