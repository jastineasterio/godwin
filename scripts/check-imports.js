/**
 * Verifies that every relative import in resources/js resolves to a real file.
 * Run: node scripts/check-imports.js   (fast, no bundling)
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(here, '..', 'resources', 'js');
const files = [];

(function walk(dir) {
    fs.readdirSync(dir, { withFileTypes: true }).forEach((entry) => {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full);
        else if (/\.jsx?$/.test(entry.name)) files.push(full);
    });
})(root);

let broken = 0;

for (const file of files) {
    const source = fs.readFileSync(file, 'utf8');
    const imports = [...source.matchAll(/from\s+'(\.[^']+)'/g)];

    for (const [, specifier] of imports) {
        const base = path.resolve(path.dirname(file), specifier);
        const candidates = [
            base,
            `${base}.js`,
            `${base}.jsx`,
            path.join(base, 'index.js'),
            path.join(base, 'index.jsx'),
        ];

        if (!candidates.some((c) => fs.existsSync(c) && fs.statSync(c).isFile())) {
            console.log(`BROKEN  ${path.relative(root, file)}  ->  ${specifier}`);
            broken += 1;
        }
    }
}

console.log(`\nChecked ${files.length} files. ${broken === 0 ? 'All relative imports resolve.' : `${broken} broken import(s).`}`);
process.exit(broken === 0 ? 0 : 1);
