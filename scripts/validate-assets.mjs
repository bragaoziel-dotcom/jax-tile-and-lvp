import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const webpAssets = [
  'assets/jax-tile-lvp-logo.webp',
  'assets/projects/lvp-open-room.webp',
  'assets/projects/tile-shower-marble.webp',
];
const socialPng = 'assets/jax-tile-lvp-logo.png';

function isWebP(abs) {
  const buf = fs.readFileSync(abs);
  return buf.length >= 12 && buf.subarray(0, 4).toString('ascii') === 'RIFF' && buf.subarray(8, 12).toString('ascii') === 'WEBP';
}
function isPng(abs) {
  const buf = fs.readFileSync(abs);
  return buf.length >= 8 && buf.subarray(0, 8).equals(Buffer.from([0x89,0x50,0x4e,0x47,0x0d,0x0a,0x1a,0x0a]));
}

for (const rel of webpAssets) {
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) errors.push(`missing optimized asset: ${rel}`);
  else if (fs.statSync(abs).size === 0) errors.push(`empty optimized asset: ${rel}`);
  else if (!isWebP(abs)) errors.push(`invalid WebP binary signature: ${rel}`);
}

const pngAbs = path.join(root, socialPng);
if (!fs.existsSync(pngAbs)) errors.push(`missing social/schema PNG: ${socialPng}`);
else {
  if (!isPng(pngAbs)) errors.push(`invalid PNG binary signature: ${socialPng}`);
  if (fs.statSync(pngAbs).size > 500000) errors.push(`${socialPng}: must stay under 500 KB after whitespace-crop optimization`);
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
    errors.push(`${rel}: visible logo still uses PNG instead of WebP`);
  }
  if (html.includes('braga-guardian-angel.lovable.app')) {
    errors.push(`${rel}: project image still depends on Lovable host`);
  }
  for (const src of ['/assets/projects/lvp-open-room.webp','/assets/projects/tile-shower-marble.webp']) {
    const escaped = src.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const matches = [...html.matchAll(new RegExp(`<img[^>]+src=["']${escaped}["'][^>]*>`, 'gi'))];
    for (const match of matches) {
      const tag = match[0];
      if (!/\bwidth=["']900["']/i.test(tag) || !/\bheight=["']1200["']/i.test(tag)) {
        errors.push(`${rel}: ${src} must declare width=900 and height=1200 to reserve layout space`);
      }
    }
  }
}

if (errors.length) {
  console.error(`Asset validation failed with ${errors.length} issue(s):`);
  errors.forEach((e) => console.error(`- ${e}`));
  process.exit(1);
}
console.log('Asset validation passed: image binaries are valid/optimized, project dimensions are declared, assets are local, and visible HTML uses WebP.');
