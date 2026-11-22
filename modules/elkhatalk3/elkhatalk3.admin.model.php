<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3AdminModel extends Elkhatalk3
{
	public function all_plugins() : array
	{
		static $all_plugins = [];
		if(count($all_plugins))
		{
			return $all_plugins;
		}
		// 플러그인 모두 로딩하기.
		$readDir = FileHandler::readDir($dir = _XE_PATH_.'modules/elkhatalk3/plugins');
		foreach($readDir as $val)
		{
			if(file_exists("$dir/$val/$val.php"))
			{
				static::plugin_skip(TRUE);
				require_once("$dir/$val/$val.php");
				static::plugin_skip(FALSE);
			}
		}
		$type_list = array_merge(array_keys(static::$plugins ?? []), array_keys(static::$plugins_skip ?? []));
		foreach($type_list as $val)
		{
			$plugins_list = array_merge(array_keys(static::$plugins[$val] ?? []), array_keys(static::$plugins_skip[$val] ?? []));
			foreach($plugins_list as $v)
			{
				if(!isset($all_plugins[$v]))
				{
					$all_plugins[$v] = array();
				}
				$all_plugins[$v][] = $val;
			}
		}
		return $all_plugins;
	}
	public function allPluginVars() : array
	{
		$all_plugins = $this->all_plugins();
		return static::$_plugin_vars;
	}
	public function init()
	{
		$this->checkUpdateCache();
		if(!$this->toBool())
		{
			return $this;
		}
	}
	public function getElkhatalk3AdminEmbedHtml()
	{
		$args = Context::gets('room','title','width','height');
		if($html_embed_iframe = Context::get('html_embed_iframe')=='Y')
		{
			$width = preg_replace('/^[0-9]+$/', '\0px', $args->width);
			$height = preg_replace('/^[0-9]+$/', '\0px', $args->height);
			$room_title = str_replace(['+','_','.','-'], ['%20','%5F','%2E','%2D'], urlencode($args->title));
			$iframe_url = getUrl('','act','dispElkhatalk3Popup','room','public' . $args->room,'room_title',$room_title,'popup','Y');

			$this->add('html', sprintf('<iframe src="%s" frameborder="no" scrolling="no" style="display:inline-block;border:0;overflow:hidden;background:#fff;width:%s;height:%s;"></iframe>', $iframe_url, $width, $height));
		}
		else
		{
			$html = sprintf("{@ echo getClass('elkhatalk3')->public_iframe('public%d', '%s', '%s', '%s'); }", $args->room, str_replace("'", "\\'", $args->title), $args->width, $args->height);
			$this->add('html', $html);
		}
	}
}
