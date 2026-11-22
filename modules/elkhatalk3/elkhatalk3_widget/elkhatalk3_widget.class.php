<?php

class elkhatalk3_widget extends WidgetHandler
{
	public function proc($args)
	{
		Context::set('widget_info', $args);
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
		$oTemplatehandler = TemplateHandler::getInstance();
		return $oTemplatehandler->compile($tpl_path, 'skin');
	}
}
