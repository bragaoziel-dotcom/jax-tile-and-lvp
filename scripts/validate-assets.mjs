import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const required = [
  'assets/jax-tile-lvp-logo.webp',
  'assets/projects/lvp-open-room.webp',
  'assets/projects/tile-shower-marble.webp',
];

for (const rel of required) {
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) errors.push(`missing optimized asset: ${rel}`);
  else if (fs.statSync(abs).size === 0) errors.push(`empty optimized asset: ${rel}`);
}

function walk(dir = '') {
  return fs.readdirSync(path.join(root, dir), { withFileTypes: true }).flatMap((entry) => {
    const rel = path.posix.join(dir, entry.name);
    if (['.git', 'node_modules', 'dist'].includes(entry.name)) return [];
    return entry.isDirectory() ? walk(rel) : [rel];
  });
}

for (const rel of walk().filter((f) => f.endsWith('.html'))) {
  const html = fs.readFileSync(path.join(root, rel), 'utf8');
  if (/<img[^>]+src=["']\/assets\/jax-tile-lvp-logo\.png["']/i.test(html)) {
    errors.push(`${rel}: visible logo still uses heavy PNG instead of WebP`);
  }
  if (html.includes('braga-guardian-angel.lovable.app')) {
    errors.push(`${rel}: project image still depends on Lovable host`);
  }
}

if (errors.length) {
  console.error(`Asset validation failed with ${errors.length} issue(s):`);
  errors.forEach((e) => console.error(`- ${e}`));
  process.exit(1);
}
console.log('Asset validation passed: optimized logo and project images are local and no visible HTML depends on Lovable assets.');
