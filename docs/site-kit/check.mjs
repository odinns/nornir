import { access, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const outputDir = join(root, 'docs', 'site');

const requiredFiles = [
  'index.html',
  'assets/site.css',
  'assets/site.js',
  'assets/brand/nornir-mark.svg',
  'assets/brand/nornir-logo.svg',
  'assets/brand/favicon.svg',
];

const checks = [];

const assert = (condition, message) => {
  checks.push({ ok: Boolean(condition), message });
};

const run = async () => {
  await Promise.all(requiredFiles.map((file) => access(join(outputDir, file))));

  const html = await readFile(join(outputDir, 'index.html'), 'utf8');
  const css = await readFile(join(outputDir, 'assets', 'site.css'), 'utf8');
  const js = await readFile(join(outputDir, 'assets', 'site.js'), 'utf8');

  assert(html.includes('href="/nornir/assets/site.css"'), 'CSS uses the GitHub Pages base path.');
  assert(html.includes('src="/nornir/assets/site.js"'), 'JS uses the GitHub Pages base path.');
  assert(html.includes('href="/nornir/assets/brand/favicon.svg"'), 'Favicon uses the GitHub Pages base path.');
  assert(html.includes('href="#sources"') && html.includes('id="sources"'), 'Navigation anchor for sources exists.');
  assert(html.includes('href="#pipeline"') && html.includes('id="pipeline"'), 'Navigation anchor for pipeline exists.');
  assert(html.includes('href="#evidence"') && html.includes('id="evidence"'), 'Navigation anchor for evidence exists.');
  assert(html.includes('href="#handoffs"') && html.includes('id="handoffs"'), 'Navigation anchor for handoffs exists.');
  assert(html.includes('href="#boundaries"') && html.includes('id="boundaries"'), 'Navigation anchor for boundaries exists.');
  assert(html.includes('https://github.com/odinns/nornir'), 'External links point to the GitHub repo.');
  assert(html.includes('php artisan import:gmail'), 'Gmail import command specimen is present.');
  assert(html.includes('evidence-first memory system'), 'Nornir positioning copy is present.');
  assert(html.includes('MySQL is canonical'), 'Storage boundary copy is present.');
  assert(html.includes('Heimdallr is read-only'), 'Access boundary copy is present.');
  assert(css.includes('@media (prefers-reduced-motion: reduce)'), 'Reduced-motion CSS is present.');
  assert(js.includes('prefers-reduced-motion: reduce'), 'Reduced-motion JS guard is present.');
  assert(!html.includes('{{'), 'Generated HTML has no leftover template tokens.');
  assert(!html.includes('Gjallr') && !html.includes('/gjallr/'), 'Gjallr copy and paths are gone.');

  const failed = checks.filter((check) => !check.ok);

  checks.forEach((check) => {
    console.log(`${check.ok ? 'ok' : 'fail'} - ${check.message}`);
  });

  if (failed.length > 0) {
    process.exitCode = 1;
  }
};

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
