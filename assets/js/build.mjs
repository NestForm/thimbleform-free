/**
 * Minify Thimbleform JS sources into sibling *.min.js bundles.
 *
 * Usage (from plugin root):
 *   npm run build:js
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { minify } from 'terser';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');

function formatKb(bytes) {
	return `${(bytes / 1024).toFixed(1)} KB`;
}

function isSourceJs(name) {
	return name.endsWith('.js') && !name.endsWith('.min.js');
}

async function minifyFile(sourcePath) {
	const sourceName = path.basename(sourcePath);
	const targetPath = sourcePath.replace(/\.js$/, '.min.js');
	const code = fs.readFileSync(sourcePath, 'utf8');
	const result = await minify(code, {
		compress: true,
		mangle: true,
		format: {
			comments: /^!/,
		},
	});

	if (!result.code) {
		throw new Error(`Empty minify result: ${sourcePath}`);
	}

	const banner = `/*! ${sourceName} — minified. Edit source file, then: npm run build:js */\n`;
	const out = banner + result.code;
	fs.writeFileSync(targetPath, out);

	const saved = code.length - out.length;
	const pct = code.length ? Math.round((saved / code.length) * 100) : 0;
	console.log(
		`${sourceName}: ${formatKb(code.length)} → ${formatKb(out.length)} (−${pct}%) → ${path.relative(pluginRoot, targetPath)}`
	);
}

async function minifyDirectory(dir) {
	if (!fs.existsSync(dir)) {
		return;
	}

	for (const name of fs.readdirSync(dir)) {
		if (!isSourceJs(name)) {
			continue;
		}
		await minifyFile(path.join(dir, name));
	}
}

async function main() {
	const dirs = [
		path.join(pluginRoot, 'assets/js/admin'),
		path.join(pluginRoot, 'assets/js/front'),
	];
	const files = [path.join(pluginRoot, 'blocks/form/editor.js')];

	for (const dir of dirs) {
		await minifyDirectory(dir);
	}

	for (const file of files) {
		if (fs.existsSync(file)) {
			await minifyFile(file);
		}
	}
}

main().catch((error) => {
	console.error(error);
	process.exit(1);
});
