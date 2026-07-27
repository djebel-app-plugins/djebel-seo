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
     * Format keys live in [meta] next to the values they format, resolving
     * page-specific first then the default — same chain as the meta values:
     *   home:  meta.home.title_format -> meta.default.title_format
     *   inner: meta.<page>.title_format -> meta.default.title_format
     * Description etc. can join later.
     * @param array $fields title/keywords/description
     * @param array $ctx
     * @return array
     */
    public function formatMetaFields($fields, $ctx = [])
    {
        if (empty($fields['title'])) {
            return $fields;
        }

        // Current page slug (deepest segment); empty on the home page.
        $page_obj = Dj_App_Page::getInstance();
        $page_link = $page_obj->get('page');

        // Fallback keys resolve the whole chain in ONE lookup: page-specific
        // format wins, the default is the fallback, nothing configured -> no formatting.
        if (empty($page_link)) {
            $format_key = 'meta.home.title_format,meta.default.title_format';
        } else {
            $format_key = "meta.{$page_link}.title_format,meta.default.title_format";
        }

        $options_obj = Dj_App_Options::getInstance();
        $format = $options_obj->get($format_key);

        if (empty($format)) {
            return $fields;
        }

        $fields['title'] = $this->formatMetaTitle($fields['title'], $format);

        return $fields;
    }

    /**
     * Formats a meta title through the given format string.
     * Merge tags: %title%, %site_title%. Returns the title unchanged when the
     * format is empty, when the title already contains the site title (avoids
     * duplication), or when the formatted result comes out empty.
     * @param string $meta_title
     * @param string $format e.g. "%title% | %site_title%"
     * @return string
     */
    public function formatMetaTitle($meta_title, $format)
    {
        $meta_title = Dj_App_String_Util::trim($meta_title);

        if (empty($meta_title)) {
            return $meta_title;
        }

        $format = Dj_App_String_Util::trim($format);

        if (empty($format)) {
            return $meta_title;
        }

        $site_title = '';

        if (strpos($format, '%site_title%') !== false) {
            $options_obj = Dj_App_Options::getInstance();
            $site_title = $options_obj->get('site.site_title,site_title');
            $site_title = Dj_App_String_Util::trim($site_title);

            // The title already carries the site title -> formatting would duplicate it.
            if (!empty($site_title) && (stripos($meta_title, $site_title) !== false)) {
                return $meta_title;
            }
        }

        $replace = [
            '%title%' => $meta_title,
            '%site_title%' => $site_title,
        ];

        $formatted_title = Dj_App_String_Util::replaceMergeTags($format, $replace);
        $formatted_title = Dj_App_String_Util::trim($formatted_title);

        if (empty($formatted_title)) {
            return $meta_title;
        }

        return $formatted_title;
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
