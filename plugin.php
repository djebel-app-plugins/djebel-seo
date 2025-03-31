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
Dj_App_Hooks::addAction( 'app.page.html.head', [ $obj, 'renderMetaData', ] );

class Djebel_SEO
{
    public function renderMetaData()
    {
        $req_obj = Dj_App_Request::getInstance();
        $options_obj = Dj_App_Options::getInstance();

        $segments = $req_obj->segments();

        if (empty($segments)) {
            $meta_title = $options_obj->get('meta.meta_title');
            $meta_description = $options_obj->get('meta.description');
            $meta_keywords = $options_obj->get('meta.keywords');
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

        // @todo replace it Dj_App_Util::replaceTagContent('title', 'New Title', $buff);
        if (!empty($meta_title)) {
            echo "<meta name='title' content='$meta_title' />\n";
        }

        if (!empty($meta_description)) {
            echo "<meta name='description' content='$meta_description' />\n";
        }

        if (!empty($meta_keywords)) {
            echo "<meta name='keywords' content='$meta_keywords' />\n";
        }
    }
}