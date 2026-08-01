# Djebel SEO

Renders SEO meta tags — the page `<title>`, meta description, and meta
keywords — from the site's `app.ini` config, with optional per-page title
formatting. Also emits `<head>` tags declared in config — favicon, manifest,
preload, `og:image` and other link-preview metas. Design stays in the theme,
meta stays in config.

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

## Head tags — `<link>` and `<meta>`

Separate from the meta handling above: the meta code REWRITES tags already in
the page buffer, this APPENDS new ones, so it runs on core's
`app.page.html.head` action instead. Core injects whatever it echoes before
`</head>`. The theme stays markup-only — no tags forked into a per-site
`index.php`.

The site declares the tags; **the plugin knows no filenames, sizes, `rel` or
`property` values of its own**. Nothing configured, nothing emitted.

```ini
[plugins]
; Base dir for file= entries, relative to the content dir
djebel-seo.head_tags_dir = files/images/site/favicon

djebel-seo.head_tags[favicon_ico]      = "rel=icon&type=image/x-icon&file=favicon.ico"
djebel-seo.head_tags[favicon_16x16]    = "rel=icon&type=image/png&sizes=16x16&file=favicon-16x16.png"
djebel-seo.head_tags[favicon_32x32]    = "rel=icon&type=image/png&sizes=32x32&file=favicon-32x32.png"
djebel-seo.head_tags[apple_touch_icon] = "rel=apple-touch-icon&sizes=180x180&file=apple-touch-icon.png"
djebel-seo.head_tags[manifest]         = "rel=manifest&file=site.webmanifest"
```

Each value is query-string format. Rules:

- `tag=` picks the element and **defaults to `link`**. `tag=meta` is the other
  one that matters in `<head>`.
- A `link` needs `rel` plus one of `file`/`href`. A `meta` needs `property` or
  `name`, plus one of `file`/`content`. Entries missing those are skipped
  rather than rendered broken.
- `file=` resolves against `head_tags_dir` — into `href` for a link, `content`
  for a meta. Passing `href=`/`content=` directly covers assets hosted
  elsewhere.
- **Every other pair becomes an attribute verbatim** — `type`, `sizes`,
  `crossorigin`, `as`, `media`, `hreflang`, `color`, whatever comes next. No
  plugin change needed:

```ini
djebel-seo.head_tags[mask_icon]         = "rel=mask-icon&color=%23000000&file=safari.svg"
djebel-seo.head_tags[preload_body_font] = "rel=preload&as=font&href=https://cdn.example.com/f.woff2"

; One entry per translated version — key is alternate_lang_<code>
djebel-seo.head_tags[alternate_lang_bg] = "rel=alternate&hreflang=bg&href=/bg/"
djebel-seo.head_tags[alternate_lang_de] = "rel=alternate&hreflang=de&href=/de/"
```

Values are URL-decoded, so encode `&` as `%26` and `#` as `%23`.

**Smart default:** `as=font` gets `crossorigin=anonymous` added automatically. A
preloaded font is always fetched in CORS mode, even same-origin — without it the
browser discards the preload and downloads the font a second time, so declaring the
preload costs instead of saves. Pass `crossorigin=` explicitly to override it, or drop
the key via the `app.plugin.seo.head_tags` filter.

### Link previews (`og:` / `twitter:`)

Same mechanism, `tag=meta`. `file=` gives the absolute URL crawlers require:

```ini
djebel-seo.head_tags[og_image]        = "tag=meta&property=og:image&file=og-image.png"
djebel-seo.head_tags[og_image_width]  = "tag=meta&property=og:image:width&content=1200"
djebel-seo.head_tags[og_image_height] = "tag=meta&property=og:image:height&content=630"
djebel-seo.head_tags[og_type]         = "tag=meta&property=og:type&content=website"
djebel-seo.head_tags[og_site_name]    = "tag=meta&property=og:site_name&content=Example"
djebel-seo.head_tags[twitter_card]    = "tag=meta&name=twitter:card&content=summary_large_image"
djebel-seo.head_tags[twitter_image]   = "tag=meta&name=twitter:image&file=og-image.png"
```

Use 1200x630 for the image — the size Facebook, LinkedIn, Slack and X all
crop toward.

Do **not** declare `og:title` / `og:description` here. The `[meta]` section
already resolves those per page; a second static copy would drift out of sync.

No `file_exists()` checks are done — declaring an entry IS the opt-in, and
stat-ing every file on every request would cost more than the 404s it would
prevent. Ship the files you declare.

A link's `href` is escaped through `Dj_App_HTML::escUrl()`, which blanks
anything that isn't root-relative or `http(s)`, so a bad value drops the tag
instead of reaching the markup. Every other value — including a meta's
`content`, which is not always a URL — goes through `escAttr()`. Attribute
names are reduced to `\w`.

Magic vars (`__CONTENT_URL__` etc.) do **not** work in these values. Core
replaces magic vars earlier in the `app.page.full_content` chain than it
injects head output, so a placeholder would ship raw to the browser. Use
`file=` (resolved for you) or a full `href=`.

## Filters

| Filter | Purpose |
|---|---|
| `app.plugin.seo.meta_fields` | The resolved `title` / `keywords` / `description` fields before they're written. The plugin's own title formatting is a listener here — remove it and formatting is off. |
| `app.plugin.seo.head_tags` | The head tag definitions, keyed by their config name, so a single entry is addressable. Add, drop or repoint tags. Skipped entirely when the site declares no `head_tags`. |
| `app.plugin.seo.head_tags_html` | The rendered head markup, last word before it's echoed. Runs even when nothing is configured, so this is the one to use to inject into `<head>` unconditionally. |

```php
$seo_obj = Djebel_Plugin_SEO::getInstance();
Dj_App_Hooks::removeFilter('app.plugin.seo.meta_fields', [ $seo_obj, 'formatMetaFields' ]);

// Drop one declared tag without touching config
Dj_App_Hooks::addFilter('app.plugin.seo.head_tags', [ 'My_Plugin', 'dropAppleTouchIcon' ]);

// Turn head tags off entirely
Dj_App_Hooks::removeAction('app.page.html.head', [ $seo_obj, 'renderHeadTags' ]);
```

## Install

```bash
git submodule add https://github.com/djebel-app-plugins/djebel-seo.git .ht_djebel/app/plugins/djebel-seo
```

Djebel auto-loads plugins from that dir — no registration needed.

## License

GPLv2 or later. Author: Svetoslav Marinov (Slavi), [Orbisius](https://orbisius.com)
