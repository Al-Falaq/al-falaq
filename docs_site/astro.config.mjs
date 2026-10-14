import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightVersions from 'starlight-versions';
import versionsConfig from './versions.json' with { type: 'json' };

// https://astro.build/config
export default defineConfig({
  site: 'https://al-falaq.github.io',
  base: '/al-falaq',
  vite: {
    resolve: {
      preserveSymlinks: true,
    },
  },
  integrations: [
    starlight({
      title: 'al-falaq',
      description: 'An offline PHP library for Islamic astronomy: prayer times, Qibla, Sunnah markers, crescent visibility, and Hijri conversion.',
      // Both variants are generated from `al-falaq-logo.png` at the repository
      // root by `npm run build:logo` (scripts/build-logo.mjs). The pair matters:
      // the mark is a navy disc, which all but disappears against Starlight's
      // near-black dark sidebar, so dark mode renders a colour-negated copy.
      // `alt` is deliberately left at its "" default: the artwork sits beside
      // the site title, which already names the link, so the image is
      // decorative — giving it alt text would announce the link twice.
      logo: {
        light: './src/assets/logo.png',
        dark: './src/assets/logo-dark.png',
      },
      favicon: '/favicon.png',
      social: [
        { label: 'GitHub', href: 'https://github.com/Al-Falaq/al-falaq', icon: 'github' },
      ],
      lastUpdated: true,
      // The content directory is the `docs/user` symlink, so Starlight's
      // path-derived edit links and update dates point at the symlinked path
      // (which exists in neither git nor GitHub). The middleware rebuilds both
      // from the real repo path and owns the edit URL, so there is deliberately
      // no `editLink.baseUrl` here — see src/starlightRouteData.ts.
      routeMiddleware: ['./src/starlightRouteData.ts'],
      expressiveCode: {
        styleOverrides: {
          borderRadius: '0.375rem',
        },
      },
      sidebar: [
        {
          label: 'Home',
          link: '/',
        },
        {
          label: 'Getting Started',
          link: '/getting-started/',
        },
        {
          label: 'Prayer Times',
          link: '/prayer-times/',
        },
        {
          label: 'Calculation Methods',
          link: '/calculation-methods/',
        },
        {
          label: 'Madhab',
          link: '/madhab/',
        },
        {
          label: 'High Latitude Rules',
          link: '/high-latitude/',
        },
        {
          label: 'Polar Regions',
          link: '/polar-regions/',
        },
        {
          label: 'Timetable & Next Prayer',
          link: '/timetable/',
        },
        {
          label: 'Qibla Direction',
          link: '/qibla/',
        },
        {
          label: 'Sunnah Times',
          link: '/sunnah-times/',
        },
        {
          label: 'Moon Sighting',
          link: '/moon-sighting/',
        },
        {
          label: 'Hijri Converter',
          link: '/hijri-converter/',
        },
        {
          label: 'Timezones & Display',
          link: '/timezone/',
        },
        {
          label: 'Framework Integration',
          link: '/integration/',
        },
        {
          label: 'Errors',
          link: '/errors/',
        },
        {
          label: 'API Reference',
          link: '/api-reference/',
        },
        {
          label: 'Migration',
          link: '/migration/',
        },
        {
          label: 'Changelog',
          link: '/changelog/',
        },
        {
          label: 'Citations',
          link: '/citations/',
        },
      ],
      plugins: versionsConfig.versions.length
        ? [
            starlightVersions({
              versions: versionsConfig.versions,
              current: { label: versionsConfig.latest.label },
            }),
          ]
        : [],
    }),
  ],
});
