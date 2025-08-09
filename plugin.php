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

$obj = new Djebel_SEO();
Dj_App_Hooks::addAction( 'app.core.init', [ $obj, 'prepareMetaData' ] );
Dj_App_Hooks::addFilter( 'app.page.full_content', [ $obj, 'updateMeta' ], 50 );

//Dj_App_Hooks::addAction( 'app.page.html.head', [ $obj, 'renderMetaData' ] );

class Djebel_SEO
{
    public function updateMeta($content)
    {
        $page_obj = Dj_App_Page::getInstance();

        $fields = [
            'title' => $page_obj->meta_title,
            'description' => $page_obj->meta_description,
            'keywords' => $page_obj->meta_keywords,
        ];

        $ctx = [];
        $ctx['content'] = $content;
        $fields = Dj_App_Hooks::applyFilter( 'app.plugins.seo.meta_fields', $fields, $ctx );

        // handle title tag differently as it contains text between <title>...</title>
        if (isset($fields['title'])) {
            $content = Dj_App_Util::replaceTagContent('title', $fields['title'], $content);
            unset($fields['title']); // remove it so we don't have to check in each loop iteration
        }

        foreach ($fields as $field => $val) {
            if (empty($val)) {
                continue;
            }

            $content = Dj_App_Util::replaceMetaTagContent($field, $val, $content);
        }

        return $content;
    }

    public function prepareMetaData()
    {
        $req_obj = Dj_App_Request::getInstance();
        $options_obj = Dj_App_Options::getInstance();

        $segments = $req_obj->segments();

        // home page?
        if (empty($segments)) {
            $meta_title = $options_obj->get('meta.default.title');
            $meta_description = $options_obj->get('meta.default.description');
            $meta_keywords = $options_obj->get('meta.default.keywords');
        } else {
            // loop through the segments and start with the last one that's the current page.
            // if it has seo meta data then use it otherwise use the parent page
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

        $page_obj = Dj_App_Page::getInstance();

        if (!empty($meta_title)) {
            $page_obj->meta_title = $meta_title;
        }

        if (!empty($meta_description)) {
            $page_obj->meta_description = $meta_description;
        }

        if (!empty($meta_keywords)) {
            $page_obj->meta_keywords = $meta_keywords;
        }
    }
}