<?php

namespace Rhymix\Modules\Devtool\Controllers;

use Context;
use BaseObject;

/**
 * 개발자도구 Action Controller
 */
class Action extends Base
{
    /**
     * 초기화
     */
    public function init()
    {
        $this->setTemplatePath($this->module_path . 'views/');
    }

    /**
     * Action 상세/수정 화면
     */
    public function dispDevtoolAction()
    {
        $vars = Context::getRequestVars();
        if (!isset($vars->target_module) || !isset($vars->action_name)) {
            return new BaseObject(-1, 'Invalid request.');
        }

        $target_module = $vars->target_module;
        $action_name = $vars->action_name;
        $action_info = [];

        if ($action_name !== 'new') {
            $xml_path = \RX_BASEDIR . 'modules/' . $target_module . '/conf/module.xml';
            if (!file_exists($xml_path) || !is_readable($xml_path)) {
                return new BaseObject(-1, 'Module configuration file not found or not readable.');
            }

            $xml_content = file_get_contents($xml_path);
            $xml = ($xml_content !== false) ? simplexml_load_string($xml_content) : false;
            if ($xml === false) {
                return new BaseObject(-1, 'Failed to parse module configuration file.');
            }

            if (isset($xml->actions->action)) {
                foreach ($xml->actions->action as $action) {
                    if ((string)$action['name'] === $action_name) {
                        foreach ($action->attributes() as $k => $v) {
                            $action_info[$k] = (string)$v;
                            $action_info[str_replace('-', '_', $k)] = (string)$v;
                        }

                        $routes = [];
                        if (isset($action['route'])) {
                            $routes[] = ['route' => (string)$action['route']];
                        }
                        if (isset($action->route)) {
                            foreach ($action->route as $r) {
                                $r_attrs = [];
                                foreach ($r->attributes() as $rk => $rv) {
                                    $r_attrs[$rk] = (string)$rv;
                                }
                                $routes[] = $r_attrs;
                            }
                        }
                        $action_info['routes'] = $routes;
                        break;
                    }
                }
            }

            if (empty($action_info)) {
                return new BaseObject(-1, 'Action not found.');
            }
        }

        $controllersDir = \RX_BASEDIR . 'modules/' . $target_module . '/controllers';
        $controller_classes = [];
        if (is_dir($controllersDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllersDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = substr($file->getPathname(), strlen($controllersDir) + 1);
                    $relativePath = substr($relativePath, 0, -4);
                    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                    $parts = array_map('ucfirst', explode('/', $relativePath));
                    $className = 'Controllers\\' . implode('\\', $parts);
                    $controller_classes[] = $className;
                }
            }
        }
        sort($controller_classes);
        Context::set('controller_classes', $controller_classes);

        Context::set('target_module', $target_module);
        Context::set('action_name', $action_name);
        Context::set('action_info', $action_info);

        $this->setTemplateFile('action');
    }

    /**
     * Action 저장
     */
    public function procDevtoolActionUpdate()
    {
        $vars = Context::getRequestVars();
        if (!isset($vars->target_module) || !isset($vars->name)) {
            return new BaseObject(-1, 'Invalid request.');
        }

        $xml_path = \RX_BASEDIR . 'modules/' . $vars->target_module . '/conf/module.xml';
        if (!file_exists($xml_path) || !is_readable($xml_path)) {
            throw new \Exception('Unable to find module module.xml file.');
        }

        $xml_content = file_get_contents($xml_path);
        $xml = ($xml_content !== false) ? simplexml_load_string($xml_content) : false;
        if ($xml === false) {
            throw new \Exception('Failed to parse module module.xml file.');
        }
        
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($xml->saveXML());

        if (!$doc->documentElement) {
            $root = $doc->createElement('module');
            $doc->appendChild($root);
        }

        $xpath = new \DOMXPath($doc);
        $actionsNode = $xpath->query('/module/actions')->item(0);
        if (!$actionsNode) {
            $actionsNode = $doc->createElement('actions');
            $doc->documentElement->appendChild($actionsNode);
        }

        $targetNode = null;
        if ($vars->original_action_name && $vars->original_action_name !== 'new') {
            $nodes = $xpath->query('/module/actions/action[@name="' . $vars->original_action_name . '"]');
            if ($nodes->length > 0) {
                $targetNode = $nodes->item(0);
            }
        }

        if (!$targetNode) {
            $targetNode = $doc->createElement('action');
            $actionsNode->appendChild($targetNode);
        }

        // 속성 업데이트
        // 기존 속성 제거
        while ($targetNode->hasAttributes()) {
            $targetNode->removeAttributeNode($targetNode->attributes->item(0));
        }

        // 필수 및 선택 속성 설정
        $attributes = ['name', 'type', 'class', 'method', 'view', 'layout', 'admin_index', 'menu_name', 'menu_index', 'permission', 'ruleset', 'check_csrf', 'meta_noindex', 'index', 'standalone'];
        
        foreach ($attributes as $attr) {
            $val = $vars->{$attr} ?? null;
            if ($val !== null && $val !== '') {
                // boolean 값 처리 (checkbox)
                if (in_array($attr, ['admin_index', 'menu_index', 'meta_noindex', 'index'])) {
                    if ($val === 'Y' || $val === 'true') $val = 'true';
                    else continue; 
                }
                $targetNode->setAttribute(str_replace('_', '-', $attr), $val);
            }
        }

        // Routes 처리
        if ($targetNode->hasAttribute('route')) {
            $targetNode->removeAttribute('route');
        }
        
        $routeNodes = $xpath->query('route', $targetNode);
        foreach ($routeNodes as $node) {
            $targetNode->removeChild($node);
        }

        $routes = $vars->routes;
        $valid_routes = [];
        if (is_array($routes)) {
            foreach ($routes as $r) {
                $r = (array)$r;
                if (!empty($r['route'])) {
                    $valid_routes[] = $r;
                }
            }
        }

        if (count($valid_routes) === 1) {
            $r = $valid_routes[0];
            $has_extras = false;
            foreach ($r as $k => $v) {
                if ($k !== 'route' && $v !== '') {
                    $has_extras = true;
                    break;
                }
            }
            
            if (!$has_extras) {
                $targetNode->setAttribute('route', $r['route']);
            } else {
                $this->_appendRouteChild($doc, $targetNode, $r);
            }
        } elseif (count($valid_routes) > 1) {
            foreach ($valid_routes as $r) {
                $this->_appendRouteChild($doc, $targetNode, $r);
            }
        }

        $xml_content = $doc->saveXML();
        $xml_content = preg_replace_callback('/^( +)/m', function ($m) {
            return str_repeat(' ', strlen($m[1]) * 2);
        }, $xml_content);
        file_put_contents($xml_path, $xml_content);

        $this->setMessage('success_updated');
        $this->setRedirectUrl(getUrl('act', 'dispDevtoolAction', 'target_module', $vars->target_module, 'action_name', $vars->name));
    }

    /**
     * Action 삭제
     */
    public function procDevtoolActionDelete()
    {
        $vars = Context::getRequestVars();
        $xml_path = \RX_BASEDIR . 'modules/' . $vars->target_module . '/conf/module.xml';

        if (!file_exists($xml_path) || !is_readable($xml_path)) {
            throw new \Exception('Unable to find module module.xml file.');
        }

        $xml_content = file_get_contents($xml_path);
        $xml = ($xml_content !== false) ? simplexml_load_string($xml_content) : false;
        if ($xml === false) {
            throw new \Exception('Failed to parse module module.xml file.');
        }
        
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($xml->saveXML());
        
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('/module/actions/action[@name="' . $vars->action_name . '"]');
        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            $node->parentNode->removeChild($node);
            
            $xml_content = $doc->saveXML();
            $xml_content = preg_replace_callback('/^( +)/m', function ($m) {
                return str_repeat(' ', strlen($m[1]) * 2);
            }, $xml_content);
            file_put_contents($xml_path, $xml_content);
        }

        $this->setMessage('success_deleted');
        $this->setRedirectUrl(getUrl('act', 'dispDevtoolModule', 'target_module', $vars->target_module));
    }

    private function _appendRouteChild($doc, $targetNode, $r)
    {
        $routeNode = $doc->createElement('route');
        foreach ($r as $k => $v) {
            if ($v !== '') $routeNode->setAttribute($k, $v);
        }
        $targetNode->appendChild($routeNode);
    }

    public function getDevtoolActionClass()
    {
        $vars = Context::getRequestVars();
        $target_module = $vars->target_module;
        $class_name = $vars->class_name;
        $action_name = $vars->action_name;

        if (!str_starts_with($class_name, 'Controllers\\')) {
             return new BaseObject(-1, 'Only Controllers are supported.');
        }

        $subPath = substr($class_name, 12);
        $subPath = str_replace('\\', '/', $subPath);
        $filePath = \RX_BASEDIR . 'modules/' . $target_module . '/controllers/' . $subPath . '.php';
        $dirPath = dirname($filePath);
        $fileName = basename($filePath);
        
        $fileExists = file_exists($filePath);
        if (!$fileExists) {
            $lowFilePath = $dirPath . '/' . strtolower($fileName);
            if (file_exists($lowFilePath)) {
                $filePath = $lowFilePath;
                $fileExists = true;
            }
        }

        $methodExists = false;
        $code = '';
        
        if ($fileExists) {
            $code = file_get_contents($filePath);
            if ($action_name && preg_match('/function\s+' . preg_quote($action_name, '/') . '\s*\(/i', $code)) {
                $methodExists = true;
                $code = $this->extractMethodCode($code, $action_name);
            }
        }

        if (!$methodExists) {
            $methodCode = $this->generateActionMethodCode($target_module, $action_name);
            
            if ($fileExists) {
                $code = $methodCode;
            } else {
                $moduleNamespace = $this->toPascalCase($target_module);
                $namespace = "Rhymix\\Modules\\{$moduleNamespace}\\Controllers";
                $parts = explode('\\', $class_name);
                $realClassName = end($parts);
                
                $nsParts = explode('/', $subPath);
                array_pop($nsParts);
                if (!empty($nsParts)) {
                    $namespace .= '\\' . implode('\\', $nsParts);
                }

                $code = <<<PHP
<?php

namespace {$namespace};

use Rhymix\Framework\Cache;
use Rhymix\Framework\DB;
use Rhymix\Framework\Exception;
use Rhymix\Framework\Storage;
use BaseObject;
use Context;

class {$realClassName} extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		// 관리자 화면 템플릿 경로 지정
		\$this->setTemplatePath(\$this->module_path . 'views/');
	}

{$methodCode}
}
PHP;
            }
        }

        if (str_starts_with($action_name, 'disp')) {
            list($view_path, $view_path_rel, $templateName) = $this->getViewPath($target_module, $action_name);
            
            $this->add('view_file_exists', file_exists($view_path));
            $this->add('view_file_path', $view_path_rel);
            if (file_exists($view_path)) {
                $this->add('view_code', file_get_contents($view_path));
            }
        } else {
            $this->add('view_file_exists', null);
        }
        
        $this->add('code', $code);
        $this->add('file_exists', $fileExists);
        $this->add('method_exists', $methodExists);
        $this->add('file_path', 'modules/' . $target_module . '/controllers/' . $subPath . '.php');
    }

    public function procDevtoolActionClassSave()
    {
        $vars = Context::getRequestVars();
        $target_module = $vars->target_module;
        $class_name = $vars->class_name;
        $action_name = $vars->action_name;

        if (!str_starts_with($class_name, 'Controllers\\')) {
             return new BaseObject(-1, 'Only Controllers are supported.');
        }

        $subPath = substr($class_name, 12);
        $subPath = str_replace('\\', '/', $subPath);
        $filePath = \RX_BASEDIR . 'modules/' . $target_module . '/controllers/' . $subPath . '.php';
        
        $fileExists = file_exists($filePath);

        if (!$fileExists) {
            $moduleNamespace = $this->toPascalCase($target_module);
            $namespace = "Rhymix\\Modules\\{$moduleNamespace}\\Controllers";
            $parts = explode('\\', $class_name);
            $realClassName = end($parts);
            
            $nsParts = explode('/', $subPath);
            array_pop($nsParts);
            if (!empty($nsParts)) {
                $namespace .= '\\' . implode('\\', $nsParts);
            }

            $methodCode = $this->generateActionMethodCode($target_module, $action_name);

            $code = <<<PHP
<?php

namespace {$namespace};

use Rhymix\Framework\Cache;
use Rhymix\Framework\DB;
use Rhymix\Framework\Exception;
use Rhymix\Framework\Storage;
use BaseObject;
use Context;

class {$realClassName} extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		// 관리자 화면 템플릿 경로 지정
		\$this->setTemplatePath(\$this->module_path . 'views/');
	}

{$methodCode}
}
PHP;

            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($filePath, $code);
        } else {
            $content = file_get_contents($filePath);
            if ($action_name && preg_match('/function\s+' . preg_quote($action_name, '/') . '\s*\(/i', $content)) {
                 return new BaseObject(-1, 'Method already exists.');
            }

            $methodCode = $this->generateActionMethodCode($target_module, $action_name);

            $pos = strrpos($content, '}');
            if ($pos !== false) {
                $newContent = substr_replace($content, "\n" . $methodCode . "\n}", $pos, 1);
                file_put_contents($filePath, $newContent);
            } else {
                return new BaseObject(-1, 'Invalid class file format.');
            }
        }
        
        $message = lang('success_saved');
        if ($this->createViewFile($target_module, $action_name, $created_path)) {
            $message .= "\nView file created: " . $created_path;
        }
        $this->setMessage($message);
    }

    private function generateActionMethodCode($module, $action)
    {
        if (str_starts_with($action, 'disp')) {
            $prefix = 'disp' . $this->toPascalCase($module);
            if (str_starts_with($action, $prefix)) {
                $suffix = substr($action, strlen($prefix));
            } else {
                $suffix = substr($action, 4);
            }
            $templateName = $this->toSnakeCase($suffix);
            
            return <<<PHP
	public function {$action}()
	{
		// 뷰 파일명 지정
		\$this->setTemplateFile('{$templateName}');
	}
PHP;
        } else {
            return <<<PHP
	public function {$action}()
	{
		\$vars = Context::getRequestVars();

		// .. do something ..

		\$this->setMessage('success');
	}
PHP;
        }
    }

    private function createViewFile($module, $action, &$created_path = null)
    {
        if (str_starts_with($action, 'disp')) {
            list($view_path, $view_path_rel, $templateName) = $this->getViewPath($module, $action);
            
            if (!file_exists($view_path)) {
                $view_dir = dirname($view_path);
                if (!is_dir($view_dir)) {
                    mkdir($view_dir, 0755, true);
                }
                $view_content = "<h1>{$templateName}</h1>\n<p>Generated by DevTool.</p>";
                file_put_contents($view_path, $view_content);
                $created_path = $view_path_rel;
                return true;
            }
        }
        return false;
    }

    private function getViewPath($module, $action)
    {
        $prefix = 'disp' . $this->toPascalCase($module);
        if (str_starts_with($action, $prefix)) {
            $suffix = substr($action, strlen($prefix));
        } else {
            $suffix = substr($action, 4);
        }
        $templateName = $this->toSnakeCase($suffix);
        
        if (str_starts_with($suffix, 'Admin')) {
            $tpl_dir = \RX_BASEDIR . 'modules/' . $module . '/tpl/';
            if (is_dir($tpl_dir)) {
                $path = $tpl_dir . $templateName . '.blade.php';
                $rel = 'modules/' . $module . '/tpl/' . $templateName . '.blade.php';
            } else {
                $path = \RX_BASEDIR . 'modules/' . $module . '/views/admin/' . $templateName . '.blade.php';
                $rel = 'modules/' . $module . '/views/admin/' . $templateName . '.blade.php';
            }
        } else {
            $path = \RX_BASEDIR . 'modules/' . $module . '/views/' . $templateName . '.blade.php';
            $rel = 'modules/' . $module . '/views/' . $templateName . '.blade.php';
        }
        
        return [$path, $rel, $templateName];
    }

    private function toPascalCase($str)
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $str)));
    }

    private function toSnakeCase($str)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
    }

    private function extractMethodCode($content, $funcName)
    {
        if (!preg_match('/function\s+' . preg_quote($funcName, '/') . '\s*\(/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        
        $funcKeywordPos = $matches[0][1];
        
        $lineStart = strrpos(substr($content, 0, $funcKeywordPos), "\n");
        $startPos = ($lineStart === false) ? 0 : $lineStart + 1;
        
        $openBracePos = strpos($content, '{', $funcKeywordPos);
        if ($openBracePos === false) return null;
        
        $braceCount = 1;
        $currentPos = $openBracePos + 1;
        $len = strlen($content);
        
        while ($braceCount > 0 && $currentPos < $len) {
            $char = $content[$currentPos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $currentPos++;
        }
        
        return trim(substr($content, $startPos, $currentPos - $startPos));
    }
}