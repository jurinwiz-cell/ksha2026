{{-- Original design/markup: Copyright (C) NAVER <http://www.navercorp.com> (XE Contest 2014 Template A / Basic) --}}
{{-- Rhymix layout port: Copyright (C) YJSoft --}}
{{-- This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. --}}
@php
    $_colorset_demo_use = ($layout_info->COLORSET_DEMO_USE ?? 'N') == 'Y';
    if ($_colorset_demo_use) {
        // Cookie value is user input: only accept it if it matches one of the admin-defined colorset options.
        $_colorset_options = ['', 'color_grape_fruit', 'color_hot_pink', 'color_orchid', 'color_blue_jeans', 'color_mint', 'color_sunflower', 'color_bitter_sweet', 'color_java_coffee', 'color_gray'];
        $_cookie_colorset = $_COOKIE['rx_layout_colorset'] ?? null;
        if ($_cookie_colorset !== null && in_array($_cookie_colorset, $_colorset_options, true)) {
            $layout_info->colorset = $_cookie_colorset;
        }
        Context::loadJavascriptPlugin('cookie');
    }
    Context::addBodyClass($layout_info->colorset ?? '');
@endphp
<load target="../css/basic.layout.css" />
<load target="../css/icon.css" />
@if(($layout_info->WEB_FONT ?? 'N') == 'Y')
<load target="../css/basic.layout.webfont.css" />
@endif
<load target="../js/basic.layout.js" type="body" />
