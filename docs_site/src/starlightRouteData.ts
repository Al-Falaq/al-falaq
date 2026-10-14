import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { defineRouteMiddleware } from '@astrojs/starlight/route-data';

/**
 * Starlight derives two things from `entry.filePath`:
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

/** The real docs directory, resolved from the docs site rather than the symlink. */
const DOCS_DIR = resolve(process.cwd(), 'src/content/docs');

/** Last-commit dates by page, so each page shells out to git at most once. */
const commitDates = new Map<string, Date | undefined>();

/** The commit date of `page` (relative to `docs/user/`), or `undefined` if unknown. */
function lastCommitDate(page: string): Date | undefined {
  const cached = commitDates.get(page);
  if (cached !== undefined || commitDates.has(page)) return cached;

  let date: Date | undefined;
  try {
    const seconds = Number.parseInt(
      execFileSync('git', ['-C', DOCS_DIR, 'log', '--format=%ct', '--max-count=1', '--', page], {
        encoding: 'utf-8',
        stdio: ['ignore', 'pipe', 'ignore'],
      }).trim(),
      10,
    );
    if (Number.isFinite(seconds)) date = new Date(seconds * 1000);
  } catch {
    date = undefined; // No git, or no history for this page: leave the date hidden.
  }

  commitDates.set(page, date);
  return date;
}

export const onRequest = defineRouteMiddleware((context) => {
  const route = context.locals.starlightRoute;
  const filePath = route.entry.filePath.replaceAll('\\', '/');

  // An unexpected layout means Starlight's own values are the better guess.
  if (!filePath.startsWith(SYMLINKED_DOCS)) return;

  // The symlink is what makes Starlight's default content directory exist. If it
  // is missing, the site is being built from somewhere this middleware cannot
  // map back to the repo: say so instead of quietly dropping every link and date.
  if (!existsSync(DOCS_DIR)) {
    console.warn(
      `[starlightRouteData] ${DOCS_DIR} does not exist — leaving edit links and update dates as Starlight built them.`,
    );
    return;
  }

  const page = filePath.slice(SYMLINKED_DOCS.length);

  // Some routes are synthesised rather than sourced from a file (Starlight's
  // own 404), and Starlight's stub path for them does not exist anywhere — no
  // link is better than a link to nowhere.
  if (!existsSync(join(DOCS_DIR, page))) {
    route.editUrl = undefined;
    return;
  }

  route.editUrl = new URL(EDIT_BASE + page);

  // Only fill in what Starlight could not resolve, so a frontmatter
  // `lastUpdated` date still wins.
  route.lastUpdated ??= lastCommitDate(page);
});
