/**
 * Normalize border-radius to the 8-point grid (4 / 8 / 16 / 999).
 *
 * Usage: node assets/css/normalize-radius.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.join(__dirname, 'admin');

const PROP_RE =
	/^(?<indent>\s*)(?<prop>border-radius)\s*:\s*(?<value>[^;]+);(?<suffix>\s*(?:\/\*.*?\*\/)?\s*)$/;

const GRID = [4, 8, 16];

function snapPx(n) {
	if (n === 0) {
		return '0';
	}
	if (n >= 90) {
		return '999px';
	}
	const best = GRID.reduce((acc, value) =>
		Math.abs(value - n) < Math.abs(acc - n) ||
		(Math.abs(value - n) === Math.abs(acc - n) && value < acc)
			? value
			: acc
	);
	if (best === 8) {
		return 'var(--thimbleform-radius)';
	}
	if (best === 4) {
		return '4px';
	}
	return '16px';
}

function transformRadiusValue(raw) {
	let out = raw.trim();

	out = out.replace(
		/var\(--thimbleform-radius,\s*(?:6|10)px\)/g,
		'var(--thimbleform-radius)'
	);
	out = out.replace(
		/var\(--thimbleform-radius,\s*var\(--thimbleform-radius\)\)/g,
		'var(--thimbleform-radius)'
	);
	out = out.replace(
		/var\(--thimbleform-radius-sm,\s*4px\)/g,
		'var(--thimbleform-radius-sm)'
	);
	out = out.replace(
		/calc\(\s*var\(--thimbleform-radius\)\s*-\s*2px\s*\)/g,
		'4px'
	);
	out = out.replace(
		/calc\(\s*var\(--nest-form-radius\)\s*\+\s*0\.(?:2|25)rem\s*\)/g,
		'16px'
	);

	out = out.replace(/(\d+(?:\.\d+)?)px(!important)?/g, (match, num, important) => {
		const n = Number(num);
		const suf = important || '';
		if (n === 0) {
			return `0${suf}`;
		}
		if (n === 8) {
			return `var(--thimbleform-radius)${suf}`;
		}
		if (n === 4) {
			return `4px${suf}`;
		}
		if (n === 16) {
			return `16px${suf}`;
		}
		if (n === 999) {
			return `999px${suf}`;
		}
		return `${snapPx(n)}${suf}`;
	});

	return out;
}

function processFile(filePath) {
	const original = fs.readFileSync(filePath, 'utf8');
	const lines = original.split('\n');
	let changes = 0;
	const next = lines.map((line) => {
		const endedWithCr = line.endsWith('\r');
		const body = endedWithCr ? line.slice(0, -1) : line;
		const m = body.match(PROP_RE);
		if (!m) {
			return line;
		}
		const oldValue = m.groups.value.trim();
		const newValue = transformRadiusValue(oldValue);
		if (newValue === oldValue) {
			return line;
		}
		changes += 1;
		const rebuilt = `${m.groups.indent}${m.groups.prop}: ${newValue};${m.groups.suffix || ''}`;
		return endedWithCr ? `${rebuilt}\r` : rebuilt;
	});

	if (changes > 0) {
		fs.writeFileSync(filePath, next.join('\n'), 'utf8');
	}
	return changes;
}

let total = 0;
const files = fs
	.readdirSync(adminRoot)
	.filter((name) => name.endsWith('.css'))
	.sort();

for (const name of files) {
	const filePath = path.join(adminRoot, name);
	const n = processFile(filePath);
	if (n > 0) {
		console.log(`${name}: ${n}`);
		total += n;
	}
}

console.log(`Normalized ${total} border-radius declarations`);
