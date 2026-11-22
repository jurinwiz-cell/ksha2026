<?php
class Ding_Button_Collection extends WidgetHandler
{
    function proc($args)
    {
        $options = array();

        $options['font_align'] = "text-align : ".$args->font_align.";";

        if($args->link)
            $options['link'] = $args->link;
        else
            $options['link'] = "#";

        $options['button_name'] = $args->button_name;
        if(!$options['button_name'])
            $options['button_name'] = "button";

        $options['font_size'] = $args->font_size;
        if($options['font_size'])
            $options['font_size'] = "font-size : ".$options['font_size']."px;";

        $options['font_height'] = $args->font_height;
        if($options['font_height'])
            $options['font_height'] = "top : ".$options['font_height']."px;";

        $options['font_style'] = $args->font_name;
        if($options['font_style'])
            $options['font_style'] = "font-family : ".$options['font_style'].";";

        $options['font_link'] = $args->font_link;

        Context::set("options", $options);

        $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
        Context::set('colorset', $args->colorset);

        $tpl_file = 'button';

        $oTemplate = &TemplateHandler::getInstance();
        return $oTemplate->compile($tpl_path, $tpl_file);
    }
}
?>