<?php
/*
plugin_name: Djebel SEO
plugin_uri: https://djebel.com/plugins/djebel-seo
description: Renders SEO meta tags.
version: 1.0.0
load_priority:20
tags: seo, meta, tags
stable_version: 1.0.0
min_php_ver: 5.6
min_dj_app_ver: 1.0.0
tested_with_dj_app_ver: 1.0.0
author_name: Svetoslav Marinov (Slavi)
company_name: Orbisius
author_uri: https://orbisius.com
text_domain: djebel-seo
license: gpl2
*/

$obj = Djebel_Plugin_SEO::getInstance();
Dj_App_Hooks::addFilter( 'app.page.full_content', [ $obj, 'updateMeta' ], 50 );

// The plugin's own formatting rides the same filter as everybody else's — unhook it and it's off.
Dj_App_Hooks::addFilter( 'app.plugin.seo.meta_fields', [ $obj, 'formatMetaFields' ] );

// Head tags are a separate job from the meta rewrite above: updateMeta() REPLACES tags
// already in the page buffer, this APPENDS new ones. So it rides core's head hook
// instead, whose captured output core injects before </head>. Unhook this one and the
// declared head tags are off without disturbing meta handling.
Dj_App_Hooks::addAction( 'app.page.html.head', [ $obj, 'renderHeadTags' ] );

class Djebel_Plugin_SEO
{
    public function updateMeta($content)
    {
        // Prepare meta data from all sources
        $req_obj = Dj_App_Request::getInstance();
        $options_obj = Dj_App_Options::getInstance();
        $segments = $req_obj->segments();

        // Start with config-based meta (home or segment-based)
        if (empty($segments)) {
            $meta_title = $options_obj->get('meta.home.title');
            $meta_description = $options_obj->get('meta.home.description');
            $meta_keywords = $options_obj->get('meta.home.keywords');
        } else {
            // Loop through segments and use the first one with meta data
            $reverse_segments = array_reverse($segments);

            foreach ($reverse_segments as $segment) {
                $page_link = $segment;
                $meta_title = $options_obj->get("meta.{$page_link}.title");
                $meta_description = $options_obj->get("meta.{$page_link}.description");
                $meta_keywords = $options_obj->get("meta.{$page_link}.keywords");

                if (!empty($meta_title) || !empty($meta_description)) {
                    break;
                }
            }
        }

        // Override with plugin-provided data (from static content plugin, etc)
        $page_data = Dj_App_Util::data('djebel_page_data');

        if (!empty($page_data['meta_title'])) {
            $meta_title = $page_data['meta_title'];
        }

        if (!empty($page_data['meta_keywords'])) {
            $meta_keywords = $page_data['meta_keywords'];
        }

        if (!empty($page_data['meta_description'])) {
            $meta_description = $page_data['meta_description'];
        }

        // Apply defaults if still empty
        if (empty($meta_title)) {
            $meta_title = $options_obj->get('meta.default.title');
        }

        if (empty($meta_description)) {
            $meta_description = $options_obj->get('meta.default.description');
        }

        if (empty($meta_keywords)) {
            $meta_keywords = $options_obj->get('meta.default.keywords');
        }

        // Build fields array for replacement
        $fields = [
            'title' => $meta_title,
            'keywords' => $meta_keywords,
            'description' => $meta_description,
        ];

        $ctx = ['content' => $content];
        $fields = Dj_App_Hooks::applyFilter('app.plugin.seo.meta_fields', $fields, $ctx);

        if (empty($fields)) {
            return $content;
        }

        // Replace title tag
        if (isset($fields['title'])) {
            $content = Dj_App_Util::replaceTagContent('title', $fields['title'], $content);
            unset($fields['title']);
        }

        // Replace meta tags
        foreach ($fields as $field => $val) {
            if (empty($val)) {
                continue;
            }

            $content = Dj_App_Util::replaceMetaTagContent($field, $val, $content);
        }

        return $content;
    }

    /**
     * Listener on app.plugin.seo.meta_fields — formats fields ONLY when the
     * site explicitly configures a format in app.ini; nothing is assumed.
     * Every field is treated the same: title, description, keywords, and anything a
     * filter added. Each looks up its own format key, page-specific first then the
     * default — the same chain as the meta values:
     *   home:  meta.home.<field>_format -> meta.default.<field>_format
     *   inner: meta.<page>.<field>_format -> meta.default.<field>_format
     * A field with no format configured is left untouched.
     * @param array $fields title/keywords/description
     * @param array $ctx
     * @return array
     */
    public function formatMetaFields($fields, $ctx = [])
    {
        if (empty($fields)) {
            return $fields;
        }

        $options_obj = Dj_App_Options::getInstance();

        // Every [site] key is a tag under its own config name ({site_title}, {lang}, ...),
        // so adding one is config alone. Merged BEFORE any formatting runs, so a format
        // interpolates raw values and never depends on which field formatted first; the
        // fields win a name clash, which is what a meta format means by {description}.
        $site_opt_obj = $options_obj->getSection('site');
        $site_data = $site_opt_obj->toArray();
        $tags = array_merge($site_data, $fields);

        // Current page slug (deepest segment); empty on the home page.
        $page_obj = Dj_App_Page::getInstance();
        $page_link = $page_obj->get('page');
        $format_page = empty($page_link) ? 'home' : $page_link;

        foreach ($fields as $field => $val) {
            if (empty($val)) {
                continue;
            }

            // Fallback keys resolve the whole chain in ONE lookup: page-specific
            // format wins, the default is the fallback, nothing configured -> no formatting.
            $format_key = "meta.{$format_page}.{$field}_format,meta.default.{$field}_format";
            $format = $options_obj->get($format_key);

            if (empty($format)) {
                continue;
            }

            $fields[$field] = $this->formatMetaValue($val, $format, $tags);
        }

        return $fields;
    }

    /**
     * Formats one meta value — title, description, keywords, any of them — through the
     * given format string, interpolating the supplied tags.
     * Returns the value unchanged when the format is empty, when the value already
     * carries the site title (appending it would duplicate it), or when the formatted
     * result comes out empty.
     * @param string $value
     * @param string $format e.g. "{title} | {site_title}"
     * @param array $tags Bare tag name => value
     * @return string
     */
    public function formatMetaValue($value, $format, $tags = [])
    {
        $value = Dj_App_String_Util::trim($value);

        if (empty($value)) {
            return $value;
        }

        $format = Dj_App_String_Util::trim($format);

        if (empty($format)) {
            return $value;
        }

        $site_title = empty($tags['site_title']) ? '' : $tags['site_title'];

        // A value that already names the site skips a format that would append the site
        // title again. The format has to be checked too, or "About oterm" would also skip
        // a format that never mentions the site title.
        if (Dj_App_String_Util::contains($format, 'site_title') && Dj_App_String_Util::contains($value, $site_title)) {
            return $value;
        }

        $formatted_value = Dj_App_Util::replaceTags($format, $tags);
        $formatted_value = Dj_App_String_Util::trim($formatted_value);

        if (empty($formatted_value)) {
            return $value;
        }

        return $formatted_value;
    }

    /**
     * Listener on core's app.page.html.head — core captures whatever is echoed here and
     * injects it before </head>.
     * Magic vars (__CONTENT_URL__ etc.) are NOT an option in this content: core replaces
     * them earlier in the app.page.full_content chain than it injects head output, so
     * every href is built as a real URL.
     * Filter: app.plugin.seo.head_tags_html — last word on the markup before it's echoed.
     * @return bool whether anything was rendered
     */
    public function renderHeadTags()
    {
        $tags = $this->getHeadTags();
        $buff = '';

        foreach ($tags as $tag) {
            $buff .= $this->renderHeadTag($tag);
        }

        $buff = Dj_App_Hooks::applyFilter( 'app.plugin.seo.head_tags_html', $buff );

        if (empty($buff)) {
            return false;
        }

        echo $buff;

        return true;
    }

    /**
     * Builds the head tag definitions from what the site declares in config. No filenames,
     * sizes, rel or property values are known here — an unconfigured site gets nothing.
     *
     * Config, in [plugins], value in query-string format. tag= picks the element and
     * defaults to link; meta is the other one that matters in <head>:
     *   djebel-seo.head_tags_dir = files/images/site/favicon
     *   djebel-seo.head_tags[favicon_32] = "rel=icon&type=image/png&sizes=32x32&file=favicon-32x32.png"
     *   djebel-seo.head_tags[og_image] = "tag=meta&property=og:image&file=og-image.png"
     *
     * file= resolves against head_tags_dir (relative to the content dir) into href= for a
     * link and content= for a meta. Passing href=/content= directly covers an asset hosted
     * elsewhere. Every other pair passes through as an attribute verbatim — a new
     * attribute needs no change here.
     * No file_exists() checks: declaring an entry is the opt-in, and stat-ing each file
     * every request would cost more than the 404s it prevents.
     * Filter: app.plugin.seo.head_tags — the returned array is keyed by config name,
     * which makes a single entry addressable for overriding or dropping.
     * @return array tag_id => attribute array
     */
    public function getHeadTags()
    {
        $options_obj = Dj_App_Options::getInstance();
        $head_tags_cfg = $options_obj->get('plugins.djebel-seo.head_tags');

        if (empty($head_tags_cfg) || !is_array($head_tags_cfg)) {
            return [];
        }

        $tags_dir = $options_obj->get('plugins.djebel-seo.head_tags_dir');
        $tags_dir = Dj_App_String_Util::trim($tags_dir, '/');
        $base_url = Dj_App_Util::getContentDirUrl();

        if (!empty($tags_dir)) {
            $base_url .= '/' . $tags_dir;
        }

        $tags = [];

        foreach ($head_tags_cfg as $tag_id => $tag_cfg) {
            if (empty($tag_cfg)) {
                continue;
            }

            $attribs = Dj_App_String_Util::parseQueryString($tag_cfg);

            if (empty($attribs)) {
                continue;
            }

            // A declared file lands in whichever attribute carries the URL for that
            // element. renderHeadTag() rejects whatever is still missing its target.
            if (!empty($attribs['file'])) {
                $file = Dj_App_String_Util::trim($attribs['file'], '/');
                $url = $base_url . '/' . $file;

                if (!empty($attribs['tag']) && $attribs['tag'] == 'meta') {
                    $attribs['content'] = $url;
                } else {
                    $attribs['href'] = $url;
                }

                unset($attribs['file']);
            }

            // A preloaded font is always fetched in CORS mode, even same-origin. Without
            // crossorigin the browser discards the preload and fetches the font a second
            // time, so the declaration costs instead of saves. Explicit config still wins.
            if (empty($attribs['crossorigin']) && !empty($attribs['as']) && $attribs['as'] == 'font') {
                $attribs['crossorigin'] = 'anonymous';
            }

            $tags[$tag_id] = $attribs;
        }

        $ctx = [ 'head_tags_cfg' => $head_tags_cfg, ];
        $tags = Dj_App_Hooks::applyFilter( 'app.plugin.seo.head_tags', $tags, $ctx );

        return $tags;
    }

    /**
     * Renders one <head> tag — <link> by default, <meta> when tag=meta. Attributes are
     * emitted verbatim rather than from a fixed list, which keeps type/sizes/crossorigin/
     * media and anything future working with no change here.
     * A link needs rel + href; a meta needs property or name, plus content.
     * @param array $params attribute => value, optional tag
     * @return string empty when the required attributes are missing or the href isn't safe
     */
    public function renderHeadTag($params)
    {
        $href = '';

        // link and meta are the only elements this renders — the required-attribute checks
        // live in each branch, cheapest first, so a reject costs one array read. Anything
        // that isn't meta falls back to link, where a junk entry fails rel/href and drops.
        if (!empty($params['tag']) && $params['tag'] == 'meta') {
            $tag = 'meta';

            if (empty($params['content'])) {
                return '';
            }

            // Reached only once content is there. property first — og:* is the common case,
            // so the chain usually stops on the first check.
            if (empty($params['property']) && empty($params['name']) && empty($params['http-equiv'])) {
                return '';
            }
        } else {
            $tag = 'link';

            if (empty($params['rel']) || empty($params['href'])) {
                return '';
            }

            // escUrl() blanks anything that isn't root-relative or http(s), so a bad config
            // value drops the tag instead of reaching the markup. Only href gets this —
            // a meta's content is not always a URL, and rides escAttr() below.
            $href = Dj_App_HTML::escUrl($params['href']);

            if (empty($href)) {
                return '';
            }

            unset($params['href']);
        }

        unset($params['tag']);

        // Parts joined with a single space beat repeated .= here — measured ~20% cheaper,
        // because the glue means no part carries its own leading space.
        $parts = [];
        $parts[] = '<' . $tag;

        foreach ($params as $attrib => $val) {
            if (empty($val) || !is_scalar($val)) {
                continue;
            }

            // Keeps word chars and the dash, drops the rest, so a junk name can't break out
            // of the tag. The dash matters: HTML defines hyphenated attributes (http-equiv,
            // accept-charset, data-*) and formatKey() would rewrite those to underscores,
            // leaving an attribute the browser ignores. An all-junk name empties out here.
            $attrib_fmt = Dj_App_String_Util::sanitizeAlphaNumericExt($attrib);

            if (empty($attrib_fmt)) {
                continue;
            }

            $val_esc = Dj_App_HTML::escAttr($val);
            $parts[] = $attrib_fmt . '="' . $val_esc . '"';
        }

        if (!empty($href)) {
            $parts[] = 'href="' . $href . '"';
        }

        $parts[] = '/>';
        $buff = implode(' ', $parts);
        $buff .= "\n";

        return $buff;
    }

    /**
     * Singleton pattern i.e. we have only one instance of this obj
     * @staticvar static $instance
     * @return static
     */
    public static function getInstance() {
        static $instance = null;

        // This will make the calling class to be instantiated.
        // no need each sub class to define this method.
        if (is_null($instance)) {
            $instance = new static();
        }

        return $instance;
    }
}
