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
        $meta_title = empty($meta_title) ? $options_obj->meta->default->title : $meta_title;
        $meta_description = empty($meta_description) ? $options_obj->meta->default->description : $meta_description;
        $meta_keywords = empty($meta_keywords) ? $options_obj->meta->default->keywords : $meta_keywords;

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