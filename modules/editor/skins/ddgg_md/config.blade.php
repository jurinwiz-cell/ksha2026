@php

// TipTap (Notion-style) editor configuration from the standard editor variables.
$ttconfig = new \stdClass;
$ttconfig->colorset       = $colorset ?: 'auto';
$ttconfig->auto_dark_mode = $editor_auto_dark_mode ?? false;
$ttconfig->language       = str_replace('jp', 'ja', Context::getLangType());
$ttconfig->height         = intval($editor_height ?? 360);
$ttconfig->focus          = $editor_focus ?? false;
$ttconfig->allow_upload   = $allow_fileupload ?? false;
$ttconfig->placeholder    = "글을 입력하거나 '/' 를 눌러 명령어를 사용하세요";

$ttconfig->content_font            = $content_font ?: 'none';
$ttconfig->content_font_size       = $content_font_size ?: '16';
$ttconfig->content_line_height     = $content_line_height ?: 'none';
$ttconfig->content_word_break      = $content_word_break ?: 'none';

$ttconfig->enable_component = $enable_component ?? false;

$ttconfig->initial_height = $ttconfig->height + 16;

@endphp
