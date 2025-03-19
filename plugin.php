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
min_dj_cms_ver: 1.0.0
tested_with_dj_cms_ver: 1.0.0
author_name: Svetoslav Marinov (Slavi)
company_name: Orbisius
author_uri: https://orbisius.com
text_domain: hello-world
license: gpl2
*/

$obj = new Djebel_SEO();
Dj_App_Hooks::addAction( 'app.page.html.head', [ $obj, 'renderMetaData', ] );

class Djebel_SEO
{
    public function renderMetaData()
    {

    }
}