@php

// Build the TipTap editor configuration object from the standard editor variables
// that the editor module passes to every skin.
$ttconfig = new \stdClass;
$ttconfig->colorset           = $colorset ?: 'auto';
$ttconfig->auto_dark_mode     = $editor_auto_dark_mode ?? false;
$ttconfig->language           = str_replace('jp', 'ja', Context::getLangType());
$ttconfig->height             = intval($editor_height ?? 300);
$ttconfig->toolbar            = $editor_toolbar ?? 'default';   // default | simple
$ttconfig->hide_toolbar       = $editor_toolbar_hide ?? false;
$ttconfig->focus              = $editor_focus ?? false;
$ttconfig->allow_upload       = $allow_fileupload ?? false;
$ttconfig->placeholder        = $lang->cmd_write ?? '';

// Content typography (mirrors what ckeditor exposes via css_vars)
$ttconfig->content_font            = $content_font ?: 'none';
$ttconfig->content_font_size       = $content_font_size ?: '13';
$ttconfig->content_line_height     = $content_line_height ?: 'none';
$ttconfig->content_word_break      = $content_word_break ?: 'none';
$ttconfig->content_paragraph_spacing = $content_paragraph_spacing ?: 'none';

// Legacy editor components (image_link, emoticon, poll_maker, ...)
$ttconfig->enable_component = $enable_component ?? false;
$ttconfig->components = [];
$_comp_base = Context::getRequestUri();
foreach ($component_list ?? [] as $component_name => $component) {
	$_icon_fs = RX_BASEDIR . 'modules/editor/components/' . $component_name . '/icon.gif';
	$ttconfig->components[] = [
		'name'  => $component_name,
		'title' => escape($component->title, false),
		'icon'  => file_exists($_icon_fs) ? ($_comp_base . 'modules/editor/components/' . $component_name . '/icon.gif') : '',
	];
}

// Initial min-height to reduce layout shift while the editor mounts.
$ttconfig->initial_height = $ttconfig->height + ($ttconfig->hide_toolbar ? 12 : 48);

@endphp
