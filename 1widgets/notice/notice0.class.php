<?php
    /**
     * @class notice
     * @author KnDol (kndol@kndol.net)
     * @brief widget to display notices
     * @version 0.1
     **/

    class notice extends WidgetHandler {

        /**
         * @brief Widget handler
         *
         * Get extra_vars declared in ./widgets/widget/conf/info.xml as arguments
         * After generating the result, do not print but return it.
         **/

        function proc($args) {
            $widget_info = new stdClass();
            // Targets to sort
            if(!in_array($args->order_target, array('list_order','update_order'))) $args->order_target = 'list_order';
            // Sort order
            if(!in_array($args->order_type, array('asc','desc'))) $args->order_type = 'asc';
            // The number of displayed lists
            $args->list_count = (int)$args->list_count;
            if($args->list_count==0) $args->list_count = "";
            // Cut the length of the title
            if(!$args->subject_cut_size) $args->subject_cut_size = 0;
            // Cut the length of contents
            if(!$args->content_cut_size) $args->content_cut_size = 0;
            // Display time of the latest post
            if(!$args->duration_new) $args->duration_new = 24;
            // Viewing options
            $args->option_view_arr = explode(',',$args->option_view);
            // markup options
            if(!$args->markup_type) $args->markup_type = 'table';
            // Set variables used internally
            $oModuleModel = getModel('module');
            $module_srls = $args->modules_info = $args->module_srls_info = $args->mid_lists = array();
            $site_module_info = Context::get('site_module_info');
            // Apply to all modules in the site if a target module is not specified
            if(!$args->module_srls){
                $obj = new stdClass();
                $obj->site_srl = (int)$site_module_info->site_srl;
                $output = executeQueryArray('widgets.notice.getMids', $obj);
                if($output->data) {
                    foreach($output->data as $key => $val) {
                        $args->modules_info[$val->mid] = $val;
                        $args->module_srls_info[$val->module_srl] = $val;
                        $args->mid_lists[$val->module_srl] = $val->mid;
                        $module_srls[] = $val->module_srl;
                    }
                    $idx = explode(',',$args->module_srls);
                }
            } else {
                $obj = new stdClass();
                $obj->module_srls = $args->module_srls;
                $output = executeQueryArray('widgets.notice.getMids', $obj);
                if($output->data) {
                    foreach($output->data as $key => $val) {
                        $args->modules_info[$val->mid] = $val;
                        $args->module_srls_info[$val->module_srl] = $val;
                        $args->mid_lists[$val->module_srl] = $val->mid;
                        $module_srls[] = $val->module_srl;
                    }
                }
            }

            // Get a list of documents
            if(count($module_srls)) {
                $obj = new stdClass();
                $obj->module_srl = implode(',',$module_srls);
                $obj->sort_index = $args->order_target;
                $obj->order_type = $args->order_type=="desc"?"asc":"desc";
                $obj->list_count = $args->list_count;

                $notice_items = $this->_getNoticeItems($args, $obj);
            }

            $oTemplate = TemplateHandler::getInstance();

            // Set variables for widget
            $widget_info->modules_info = $args->modules_info;
            $widget_info->option_view_arr = $args->option_view_arr;
            $widget_info->list_count = $args->list_count;
            $widget_info->subject_cut_size = (int)$args->subject_cut_size>0 ? (int)$args->subject_cut_size : 0;
            $widget_info->content_cut_size = (int)$args->content_cut_size>0 ? (int)$args->subject_cut_size : 0;

            $widget_info->duration_new = (int)$args->duration_new*60*60;
            $widget_info->mid_lists = $args->mid_lists;

            $widget_info->show_browser_title = $args->show_browser_title;
            $widget_info->show_category = $args->show_category;
//            $widget_info->show_comment_count = $args->show_comment_count;
//            $widget_info->show_trackback_count = $args->show_trackback_count;
            $widget_info->show_icon = $args->show_icon;

            $widget_info->tab_type = $args->tab_type;
            $widget_info->show_random = $args->show_random;
            $widget_info->bg_image = $args->bg_image;
            $widget_info->bg_width = $args->bg_width ? $args->bg_width : 0;
            $widget_info->bg_height = $args->bg_height ? $args->bg_height : 0;
            
            $isMobile = Mobile::isFromMobilePhone();
            $widget_info->min_width = ($isMobile) ? "1" : ($args->min_width ? $args->min_width : "200px");
            $widget_info->max_width = ($isMobile) ? "1" : ($args->max_width ? $args->max_width : "400px");
            $widget_info->min_height = ($isMobile) ? "1" : ($args->min_height ? $args->min_height : "200px");
            $widget_info->max_height = ($isMobile) ? "1" : ($args->max_height ? $args->max_height : "400px");
            $widget_info->widget_height = $args->widget_height ? $args->widget_height : "480px";
            $widget_info->use_closebutton = $args->use_closebutton;
            $widget_info->expiredays = (int)$args->expiredays;
            $widget_info->zindex = $args->zindex ? (int)$args->zindex : 50;
            $widget_info->title_size = $args->title_size ? $args->title_size : "16pt";
            $widget_info->content_size = $args->content_size ? $args->content_size : "10pt";
            $widget_info->resize_images = $args->resize_images;
            $widget_info->resize_videos = $args->resize_videos;
            
            // If it is a tab type, list up tab items and change key value(module_srl) to index 
            if($args->tab_type != 'none' && $args->tab_type) {
                $tab = array();
                foreach($args->mid_lists as $module_srl => $mid){
                    if(!is_array($notice_items[$module_srl]) || !count($notice_items[$module_srl])) continue;

                    $tab_item = new stdClass();
                    $tab_item->title = $notice_items[$module_srl][0]->getBrowserTitle();
                    $tab_item->notice_items = $notice_items[$module_srl];
                    $tab_item->domain = $notice_items[$module_srl][0]->getDomain();
                    $tab_item->url = getSiteUrl($tab_item->domain, '','mid',$mid);
                    $tab[] = $tab_item;
                }
                $widget_info->tab = $tab;
            } else {
                $widget_info->notice_items = $notice_items;
            }
            unset($args->option_view_arr);
            unset($args->modules_info);

            $widget_info->skin_path = $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);

            Context::set('colorset', $args->colorset);
            Context::set('widget_info', $widget_info);

            return $oTemplate->compile($tpl_path, "notices");
        }

        function _getNoticeItems($args, $obj) {
            $oDocumentModel = getModel('document');

            if ($args->tab_type != 'none' && $args->tab_type) {
                $module_srls = explode(',',$obj->module_srl);
                foreach($module_srls as $module_srl) {
                    $obj->module_srl = $module_srl;
                    $output = executeQueryArray('widgets.notice.getNoticeList', $obj);
                    if(!$output->toBool() || !count($output->data)) continue;

                    foreach($output->data as $key => $item) {
                        $oDocument = new documentItem();
                        $oDocument->setAttribute($item);
                        $GLOBALS['XE_DOCUMENT_LIST'][$oDocument->document_srl] = $oDocument;

                        $browser_title = $args->module_srls_info[$item->module_srl]->browser_title;
                        $domain = $args->module_srls_info[$item->module_srl]->domain;

                        $notice_item = new noticeItem($browser_title);
                        $notice_item->adds($item);
                        $notice_item->setDomain($domain);
                        $notice_items[$module_srl][] = $notice_item;
                    }
                }
            } else {
                $output = executeQueryArray('widgets.notice.getNoticeList', $obj);
                if($output->toBool() && count($output->data)) {
                    foreach($output->data as $key => $item) {
                        $oDocument = new documentItem();
                        $oDocument->setAttribute($item);
                        $GLOBALS['XE_DOCUMENT_LIST'][$oDocument->document_srl] = $oDocument;

                        $browser_title = $args->module_srls_info[$item->module_srl]->browser_title;
                        $domain = $args->module_srls_info[$item->module_srl]->domain;

                        $notice_item = new noticeItem($browser_title);
                        $notice_item->adds($item);
                        $notice_item->setDomain($domain);
                        $notice_items[] = $notice_item;
                    }
                }
            }

            return $notice_items;
        }

        function _compile($args,$notice_items){
            $oTemplate = TemplateHandler::getInstance();

            // Set variables for widget
            $widget_info = new stdClass();
            $widget_info->modules_info = $args->modules_info;
            $widget_info->option_view_arr = $args->option_view_arr;
            $widget_info->list_count = $args->list_count;
            $widget_info->subject_cut_size = $args->subject_cut_size;
            $widget_info->content_cut_size = $args->content_cut_size;

            $widget_info->duration_new = $args->duration_new * 60*60;
            $widget_info->mid_lists = $args->mid_lists;

            $widget_info->show_browser_title = $args->show_browser_title;
            $widget_info->show_category = $args->show_category;
            $widget_info->show_comment_count = $args->show_comment_count;
            $widget_info->show_trackback_count = $args->show_trackback_count;
            $widget_info->show_icon = $args->show_icon;

            $widget_info->list_type = $args->list_type;
            $widget_info->tab_type = $args->tab_type;

            // If it is a tab type, list up tab items and change key value(module_srl) to index 
            if($args->tab_type != 'none' && $args->tab_type) {
                $tab = array();
                foreach($args->mid_lists as $module_srl => $mid){
                    if(!is_array($notice_items[$module_srl]) || !count($notice_items[$module_srl])) continue;

                    $tab_item = new stdClass();
                    $tab_item->title = $notice_items[$module_srl][0]->getBrowserTitle();
                    $tab_item->notice_items = $notice_items[$module_srl];
                    $tab_item->domain = $notice_items[$module_srl][0]->getDomain();
                    $tab_item->url = getSiteUrl($tab_item->domain, '','mid',$mid);
                    $tab[] = $tab_item;
                }
                $widget_info->tab = $tab;
            } else {
                $widget_info->notice_items = $notice_items;
            }
            unset($args->option_view_arr);
            unset($args->modules_info);

            Context::set('colorset', $args->colorset);
            Context::set('widget_info', $widget_info);

            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
            return $oTemplate->compile($tpl_path, "notices");
        }
    }

    class noticeItem extends BaseObject {

        public $browser_title = null;
        public $contents_link = null;
        public $domain = null;

        function __construct($browser_title=''){
            $this->browser_title = $browser_title;
        }

        function setDomain($domain) {
            static $default_domain = null;
            if(!$domain) {
                if(is_null($default_domain)) $default_domain = Context::getDefaultUrl();
                $domain = $default_domain;
            }
            $this->domain = $domain;
        }

        function getBrowserTitle(){
            return $this->browser_title;
        }
        function getDomain() {
            return $this->domain;
        }

        function getLink(){
            return $this->get('url');
        }
        function getSrl(){
            return $this->get('srl');
        }
        function getModuleSrl(){
            return $this->get('module_srl');
        }
        function getTitle($cut_size = 0, $tail='...'){
            $title = strip_tags($this->get('title'));

            if($cut_size && $cut_size > 0) $title = cut_str($title, $cut_size, $tail);

            $attrs = array();
            if($this->get('title_bold') == 'Y') $attrs[] = 'font-weight:bold';
            if($this->get('title_color') && $this->get('title_color') != 'N') $attrs[] = 'color:#'.$this->get('title_color');

            if(count($attrs)) $title = sprintf("<span style=\"%s\">%s</span>", implode(';', $attrs), htmlspecialchars($title));

            return $title;
        }
        function getContent($cut_size = 0, $tail = '...'){
            $content = $this->get('content');
            $content = str_replace(array('&lt;','&gt;','&quot;','&nbsp;'), array('<','>','"',' '), $content);

            if($cut_size) $content = cut_str(strip_tags($content), $cut_size, $tail);
            return $content;
        }
        function getCategory(){
            return $this->get('category');
        }
        function getNickName(){
            return $this->get('nick_name');
        }
        function getCommentCount(){
            $comment_count = $this->get('comment_count');
            return $comment_count>0 ? $comment_count : '';
        }
        function getTrackbackCount(){
            $trackback_count = $this->get('trackback_count');
            return $trackback_count>0 ? $trackback_count : '';
        }
        function getRegdate($format = 'Y.m.d H:i:s') {
            return zdate($this->get('regdate'), $format);
        }
    }
?>