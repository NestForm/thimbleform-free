/**
 * Normalize layout spacing to --thimbleform-space-* tokens (8-point grid).
 *
 * Usage: node assets/css/normalize-spacing.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');
const proRoot = path.resolve(pluginRoot, '../thimbleform-pro');

const SPACING_PROPS =
	/^(?<indent>\s*)(?<prop>padding(?:-(?:top|right|bottom|left))?|margin(?:-(?:top|right|bottom|left))?|gap|row-gap|column-gap)\s*:\s*(?<value>[^;]+);(?<suffix>\s*(?:\/\*.*?\*\/)?\s*)$/;

const TOKEN_BY_PX = {
	4: 'var(--thimbleform-space-1)',
	8: 'var(--thimbleform-space-2)',
	16: 'var(--thimbleform-space-4)',
	24: 'var(--thimbleform-space-6)',
	32: 'var(--thimbleform-space-8)',
	40: 'var(--thimbleform-space-10)',
	48: 'var(--thimbleform-space-12)',
	64: 'var(--thimbleform-space-16)',
};

const GRID = [4, 8, 16, 24, 32, 40, 48, 64];

function nearestGrid(px) {
	return GRID.reduce((best, value) =>
		Math.abs(value - px) < Math.abs(best - px) ? value : best
	);
}

function normalizePx(px) {
	if (px <= 3) {
		return `${px}px`;
	}
	const snapped = nearestGrid(px);
	return TOKEN_BY_PX[snapped] || `${snapped}px`;
}

function transformSpacingValue(raw) {
	const trimmed = raw.trim();
	if (!trimmed || /^(0|auto|inherit|initial|unset|revert)$/.test(trimmed)) {
		return trimmed;
	}

	if (trimmed.includes('var(--thimbleform-space-')) {
		return trimmed
			.split(/\s+/)
			.map((part) => transformSpacingPart(part))
			.join(' ');
	}

	return trimmed
		.split(/\s+/)
		.map((part) => transformSpacingPart(part))
		.join(' ');
}

function transformSpacingPart(part) {
	if (!part || part === '0' || part === 'auto' || part.startsWith('var(')) {
		return part;
	}

	const pxMatch = part.match(/^(-?\d+(?:\.\d+)?)px(!important)?$/i);
	if (pxMatch) {
		const px = Math.round(Number(pxMatch[1]));
		const suffix = pxMatch[2] || '';
		// Optical tweaks (negative margins) stay as raw px — do not flip sign or token-map.
		if (px < 0) {
			return `${px}px${suffix}`;
		}
		return `${normalizePx(px)}${suffix}`;
	}

	const remMatch = part.match(/^(-?\d+(?:\.\d+)?)rem(!important)?$/i);
	if (remMatch) {
		const raw = Number(remMatch[1]);
		const px = Math.round(Math.abs(raw) * 16);
		const suffix = remMatch[2] || '';
		if (raw < 0 || px <= 3) {
			return part;
		}
		const snapped = nearestGrid(px);
		const rem = snapped / 16;
		const remText = Number.isInteger(rem) ? String(rem) : rem.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
		return `${remText}rem${suffix}`;
	}

	return part;
}

function processFile(filePath) {
	const source = fs.readFileSync(filePath, 'utf8');
	const lines = source.split('\n');
	let changes = 0;

	const next = lines.map((line) => {
		const match = line.match(SPACING_PROPS);
		if (!match) {
			return line;
		}

		const { indent, prop, value, suffix } = match.groups;
		const transformed = transformSpacingValue(value);
		if (transformed === value.trim()) {
			return line;
		}

		changes += 1;
		return `${indent}${prop}: ${transformed};${suffix || ''}`;
	});

	if (changes > 0) {
		fs.writeFileSync(filePath, next.join('\n'));
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
	...collectCssFiles(path.join(proRoot, 'assets')),
].filter((file) => fs.existsSync(file));

let total = 0;
for (const file of targets) {
	const count = processFile(file);
	if (count) {
		total += count;
		const rel = path.relative(pluginRoot, file).replace(/\\/g, '/');
		console.log(`${rel}: ${count} rules`);
	}
}

console.log(`Done. Updated ${total} spacing rules.`);
