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
      logo: {
        src: './public/favicon.svg',
      },
      social: [
        { label: 'GitHub', href: 'https://github.com/Al-Falaq/al-falaq', icon: 'github' },
      ],
      editLink: {
        baseUrl: 'https://github.com/Al-Falaq/al-falaq/edit/main/docs/user/',
      },
      lastUpdated: true,
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
