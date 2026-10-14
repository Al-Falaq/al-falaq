/**
 * Regenerates the docs-site logo and favicon from the repository's artwork.
 *
 * Source of truth: `al-falaq-logo.png` at the repository root — 395x395 RGBA,
 * a navy disc holding a cream crescent and wordmark over a transparent
 * background. This script only reads that file; it is never written to.
 *
 * Outputs (committed, so the docs build itself needs neither this script nor
 * `sharp`):
 *
 *   src/assets/logo.png        light header logo
 *   src/assets/logo-dark.png   dark header logo (same artwork, colours negated)
 *   public/favicon.png         favicon (served from a stable, unhashed URL)
 *
 * Starlight renders the header logo at `--sl-nav-height - 2 * --sl-nav-pad-y`
 * — 32px, or 40px from the 50rem breakpoint up — so 128px covers it at 3x
 * device pixel ratio. The favicon uses 96px for the same reason at the
 * conventional 32px tab icon. `logo-dark.png` exists because the disc's navy
 * (rgb(0,16,32)) is all but invisible against Starlight's near-black dark
 * sidebar; negating the colours (alpha untouched) swaps the disc and its
 * details so the mark reads on both themes.
 *
 * Run: npm run build:logo
 */
import { mkdir, stat } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..', '..');
const docsSiteRoot = resolve(scriptDir, '..');

const source = resolve(repoRoot, 'al-falaq-logo.png');

/** @type {Array<[string, number, (image: import('sharp').Sharp) => import('sharp').Sharp]>} */
const outputs = [
  ['src/assets/logo.png', 128, (image) => image],
  ['src/assets/logo-dark.png', 128, (image) => image.negate({ alpha: false })],
  ['public/favicon.png', 96, (image) => image],
];

let sharp;

try {
  ({ default: sharp } = await import('sharp'));
} catch {
  console.error(
    'build-logo needs sharp, the image service that ships with Astro. Run `npm install` in docs_site/ first.',
  );
  process.exit(1);
}

for (const [file, size, transform] of outputs) {
  const target = resolve(docsSiteRoot, file);

  await mkdir(dirname(target), { recursive: true });
  // `contain` keeps the artwork square if the source aspect ratio ever changes.
  const image = transform(sharp(source).resize(size, size, { fit: 'contain' }));

  // Lossless 8-bit RGBA. PNG palette/effort tuning halves the bytes but visibly
  // mangles the soft edges (max channel error 113/255), so it is not used.
  await image.png({ compressionLevel: 9 }).toFile(target);

  const { size: bytes } = await stat(target);
  console.log(`${file}  ${size}x${size}  ${(bytes / 1024).toFixed(1)} kB`);
}
