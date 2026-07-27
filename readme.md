# Djebel SEO

Renders SEO meta tags — the page `<title>`, meta description, and meta
keywords — from the site's `app.ini` config, with optional per-page title
formatting. Design stays in the theme, meta stays in config.

## How it works

On every page render (filter on `app.page.full_content`) the plugin resolves
the meta values in this order — first source that has data wins:

1. **Page config** — `[meta]` keys for the current URL. Segments are checked
   deepest-first, so `/hosting/reseller-hosting` tries `reseller-hosting.*`
   before `hosting.*`. The home page uses `home.*`.
2. **Content front matter** — a content plugin (e.g. djebel-static-content)
   can publish `meta_title` / `meta_description` / `meta_keywords` page data,
   which overrides the config values.
3. **Defaults** — `default.*` keys fill anything still empty.

The resolved fields then pass through the `app.plugin.seo.meta_fields` filter
(see below) and get written into the rendered page's `<title>` and meta tags.

## What to set

Everything lives in the site's `.ht_djebel/conf/app.ini`, `[meta]` section:

```ini
[meta]
; Fallbacks for every page
default.title = fsite.net
default.keywords = static site hosting, wordpress hosting
default.description = Static Site Hosting and WordPress Hosting.

; Optional: title format — set it and titles get formatted; omit it and they don't
default.title_format = %title% | %site_title%

; Home page
home.title = Static Site Hosting and WordPress Hosting
home.keywords = static site hosting, wordpress hosting, managed hosting
home.description = Hosting with careful onboarding and safe defaults.
home.title_format = %site_title% | %title%

; Any other page, keyed by its URL slug (deepest segment)
hosting.title = Hosting
hosting.keywords = hosting plans, managed hosting
hosting.description = Hosting plans built for performance.
```

## Title formatting

Formatting runs ONLY when a format is configured — no config, no formatting,
no cost (one option lookup and out).

- Key resolution, page-specific first: `<page>.title_format` →
  `default.title_format`. The home page checks `home.title_format` →
  `default.title_format`.
- Merge tags: `%title%` (the resolved meta title) and `%site_title%`
  (`[site] site_title`).
- If the title already contains the site title, formatting is skipped —
  no `fsite.net | fsite.net` duplication.
- An empty formatted result falls back to the plain title.

`formatMetaTitle($meta_title, $format)` is a public, pure formatter — themes
or site plugins can call it directly.

## Filters

| Filter | Purpose |
|---|---|
| `app.plugin.seo.meta_fields` | The resolved `title` / `keywords` / `description` fields before they're written. The plugin's own title formatting is a listener here — remove it and formatting is off: |

```php
$seo_obj = Djebel_Plugin_SEO::getInstance();
Dj_App_Hooks::removeFilter('app.plugin.seo.meta_fields', [ $seo_obj, 'formatMetaFields' ]);
```

## Install

```bash
git submodule add https://github.com/djebel-app-plugins/djebel-seo.git .ht_djebel/app/plugins/djebel-seo
```

Djebel auto-loads plugins from that dir — no registration needed.

## License

GPLv2 or later. Author: Svetoslav Marinov (Slavi), [Orbisius](https://orbisius.com)
