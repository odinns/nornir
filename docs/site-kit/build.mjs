import { access, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const kitDir = join(root, 'docs', 'site-kit');
const outputDir = join(root, 'docs', 'site');
const configPath = join(root, 'docs', 'site.config.json');

const partialNames = [
  'header',
  'hero',
  'sections',
  'pipeline',
  'skills',
  'ai-handoff',
  'showcase',
  'quick-start',
  'boundary',
  'name-story',
  'cta',
  'footer',
];

const escapeHtml = (value) =>
  String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

const get = (source, path) =>
  path.split('.').reduce((value, key) => (value == null ? value : value[key]), source);

const assetPath = (config, path) => {
  const resolved = get(config, path) ?? path;
  return `${config.site.basePath}${String(resolved).replace(/^\/+/, '')}`;
};

const renderTemplate = (template, config, extras = {}) =>
  template.replace(/\{\{([^}]+)\}\}/g, (_, rawKey) => {
    const key = rawKey.trim();

    if (key.startsWith('asset:')) {
      return escapeHtml(assetPath(config, key.slice(6)));
    }

    if (extras[key] != null) {
      return extras[key];
    }

    const value = get(config, key);

    return value == null ? '' : escapeHtml(value);
  });

const linkList = (items, className) =>
  items
    .map((item) => `<a class="${className}" href="${escapeHtml(item.href)}">${escapeHtml(item.label)}</a>`)
    .join('\n');

const terminalBlocks = (terminals) =>
  terminals
    .map(
      (terminal) => `<div class="terminal">
  <div class="terminal-title">${escapeHtml(terminal.title)}</div>
  <pre><code>${terminal.lines.map((line) => `$ ${escapeHtml(line)}`).join('\n')}</code></pre>
</div>`,
    )
    .join('\n');

const featureSections = (sections) =>
  sections
    .map(
      (section) => `<section class="section-shell feature-section" id="${escapeHtml(section.id)}" data-reveal>
  <div class="section-intro">
    <p class="kicker">${escapeHtml(section.kicker)}</p>
    <h2>${escapeHtml(section.title)}</h2>
    <p>${escapeHtml(section.body)}</p>
  </div>
  <div class="feature-grid feature-grid-${section.items.length}">
    ${section.items
      .map(
        (item) => `<article class="handoff-card">
      <h3>${escapeHtml(item.title)}</h3>
      <p>${escapeHtml(item.body)}</p>
    </article>`,
      )
      .join('\n')}
  </div>
</section>`,
    )
    .join('\n');

const pipelineSteps = (steps) =>
  steps
    .map(
      (step) => `<li>
  <span class="step-marker" aria-hidden="true"></span>
  <div>
    <h3>${escapeHtml(step.title)}</h3>
    <p>${escapeHtml(step.body)}</p>
  </div>
</li>`,
    )
    .join('\n');

const cardItems = (items, className) =>
  items
    .map(
      (item) => `<article class="${className}">
  <h3>${escapeHtml(item.title)}</h3>
  <p>${escapeHtml(item.body)}</p>
</article>`,
    )
    .join('\n');

const showcaseFrames = (frames) =>
  frames
    .map(
      (frame) => `<article class="showcase-frame">
  <div class="frame-placeholder">
    <span>${escapeHtml(frame.label)}</span>
  </div>
  <h3>${escapeHtml(frame.title)}</h3>
  <p>${escapeHtml(frame.body)}</p>
</article>`,
    )
    .join('\n');

const ensureRequiredFiles = async () => {
  await Promise.all([
    readFile(join(kitDir, 'templates', 'page.html'), 'utf8'),
    readFile(join(kitDir, 'skins', 'odinn-dark.css'), 'utf8'),
    readFile(join(kitDir, 'assets', 'site.js'), 'utf8'),
    readFile(configPath, 'utf8'),
  ]);
};

const build = async () => {
  await ensureRequiredFiles();

  const config = JSON.parse(await readFile(configPath, 'utf8'));
  let page = await readFile(join(kitDir, 'templates', 'page.html'), 'utf8');
  const partials = Object.fromEntries(
    await Promise.all(
      partialNames.map(async (name) => [
        name,
        await readFile(join(kitDir, 'templates', 'partials', `${name}.html`), 'utf8'),
      ]),
    ),
  );

  for (const [name, template] of Object.entries(partials)) {
    page = page.replace(`{{> ${name}}}`, template);
  }

  const extras = {
    nav: linkList(config.nav, 'nav-link'),
    terminals: terminalBlocks(config.terminals),
    sections: featureSections(config.sections),
    pipelineSteps: pipelineSteps(config.pipeline.steps),
    skillsItems: cardItems(config.skills.items, 'handoff-card'),
    aiHandoffItems: cardItems(config.aiHandoff.items, 'handoff-card'),
    showcaseFrames: showcaseFrames(config.showcase.frames),
    quickStartCommands: config.quickStart.commands.map((command) => `$ ${escapeHtml(command)}`).join('\n'),
    ctaLinks: linkList(config.cta.links, 'button button-secondary'),
  };

  const html = renderTemplate(page, config, extras);
  const css = await readFile(join(kitDir, 'skins', `${config.site.skin}.css`), 'utf8');
  const js = await readFile(join(kitDir, 'assets', 'site.js'), 'utf8');

  await Promise.all(Object.values(config.site.logo).map((asset) => access(join(outputDir, asset))));
  await mkdir(join(outputDir, 'assets'), { recursive: true });
  await writeFile(join(outputDir, 'index.html'), html);
  await writeFile(join(outputDir, 'assets', 'site.css'), css);
  await writeFile(join(outputDir, 'assets', 'site.js'), js);
};

build().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
