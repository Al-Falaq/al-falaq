import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { defineRouteMiddleware } from '@astrojs/starlight/route-data';

/**
 * Repairs the two things Starlight derives from `entry.filePath`:
 *
 * - the “Edit page” link, built as `editLink.baseUrl + filePath`;
 * - the “Last updated” date, looked up in the git-history map the Starlight
 *   integration builds from `src/content/docs` (a miss throws and is swallowed,
 *   so the date silently disappears).
 *
 * The user guides live in the repo's `docs/user/` directory — the location
 * readers browse on GitHub and contributors edit — and reach Astro through the
 * `docs_site/src/content/docs` symlink, which Vite preserves
 * (`vite.resolve.preserveSymlinks` in `astro.config.mjs`). `filePath` therefore
 * arrives as the *symlinked* path (`src/content/docs/<page>.md`), a path that
 * exists neither in git nor on GitHub: every edit link 404s and no update date
 * ever renders.
 *
 * Both are rebuilt here from the real repo path (`docs/user/<page>.md`) — the
 * same path the release workflow freezes into `docs/user/vX.Y.Z/`. This file is
 * the single owner of the edit URL: `astro.config.mjs` deliberately sets no
 * `editLink.baseUrl`, because the base Starlight would append to is the
 * symlinked one.
 *
 * The `lastUpdated` fill below mirrors `lastUpdated: true` in
 * `astro.config.mjs` — drop it if that option is ever turned off.
 */
const EDIT_BASE = 'https://github.com/Al-Falaq/al-falaq/edit/main/docs/user/';

/** The symlinked content directory, as Starlight reports it in `filePath`. */
const SYMLINKED_DOCS = 'src/content/docs/';

/**
 * The real `docs/user` directory, or `undefined` when it cannot be located.
 *
 * `import.meta.url` is not usable here: Vite bundles route middleware into
 * `dist/.prerender/chunks/`, so it resolves to the bundle rather than to this
 * file. `process.cwd()` depends on how the build was started, so both supported
 * entry points are tried — the docs site itself (`npm run build`, as CI does)
 * and the repository root (`astro build --root docs_site`).
 */
const DOCS_DIR = [resolve(process.cwd(), 'docs/user'), resolve(process.cwd(), '../docs/user')].find(
  (dir) => existsSync(dir),
);

if (DOCS_DIR === undefined) {
  console.warn(
    '[starlightRouteData] could not locate the repository docs/user directory — ' +
      'leaving edit links and update dates as Starlight built them.',
  );
}

/** Warn about a broken git environment once per build, not once per page. */
let warnedAboutGit = false;

/** The commit date of `page` (relative to `docs/user/`), or `undefined` if unknown. */
function lastCommitDate(page: string): Date | undefined {
  if (DOCS_DIR === undefined) return undefined;

  let output: string;
  try {
    output = execFileSync('git', ['-C', DOCS_DIR, 'log', '--format=%ct', '--max-count=1', '--', page], {
      encoding: 'utf-8',
      stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
  } catch (error) {
    // A failing `git` run is an environment problem (no git, no repository, an
    // unreadable checkout), not a page without history. Say so once, instead of
    // dropping every date silently.
    if (!warnedAboutGit) {
      warnedAboutGit = true;
      // `execFileSync` appends git's own stderr to `message`; collapse it so the
      // warning stays one greppable line.
      const detail = (error instanceof Error ? error.message : String(error)).replace(/\s+/g, ' ').trim();
      console.warn(`[starlightRouteData] could not read git history, so update dates will be missing: ${detail}`);
    }
    return undefined;
  }

  // Empty output means the page simply has no history yet (a new or untracked
  // file): an expected outcome, and an expected missing date.
  if (output === '') return undefined;

  const seconds = Number.parseInt(output, 10);
  return Number.isFinite(seconds) ? new Date(seconds * 1000) : undefined;
}

/** `page` as URL path segments, so URL-significant filename characters survive. */
function pageUrl(page: string): string {
  return page.split('/').map(encodeURIComponent).join('/');
}

export const onRequest = defineRouteMiddleware((context) => {
  const route = context.locals.starlightRoute;
  const filePath = route.entry.filePath.replaceAll('\\', '/');

  // An unexpected layout means Starlight's own values are the better guess.
  if (!filePath.startsWith(SYMLINKED_DOCS) || DOCS_DIR === undefined) return;

  const page = filePath.slice(SYMLINKED_DOCS.length);

  // Some routes are synthesised rather than sourced from a file (Starlight's
  // own 404), and Starlight's stub path for them does not exist anywhere — no
  // link is better than a link to nowhere.
  if (!existsSync(join(DOCS_DIR, page))) {
    route.editUrl = undefined;
    return;
  }

  // A page-level `editUrl` wins: `false` opts the page out of an edit link and a
  // string is the page's own URL. Only Starlight's schema default (`true`) is
  // rebuilt here, from the real repo path.
  if (route.entry.data.editUrl === true) {
    route.editUrl = new URL(EDIT_BASE + pageUrl(page));
  }

  // Only fill in what Starlight could not resolve, so a frontmatter
  // `lastUpdated` date still wins.
  route.lastUpdated ??= lastCommitDate(page);
});
