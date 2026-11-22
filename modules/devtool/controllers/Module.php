<?php

namespace Rhymix\Modules\Devtool\Controllers;

use Context;
use ModuleModel;
use Rhymix\Framework\Storage;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class Module extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'views/');
	}
	
	/**
	 * 메인 화면 예제
	 */
	public function dispDevtoolModuleIndex()
	{
		$oModuleModel = ModuleModel::getInstance();
		$module_list = $oModuleModel->getModuleList();
		Context::set('module_list', $module_list);

		// 뷰 파일명 지정
		$this->setTemplateFile('module_index');
	}
	
	/**
	 * 메인 화면 예제
	 */
	public function dispDevtoolModule()
	{
        $target_module = Context::get('target_module');
		$module_info = ModuleModel::getModuleInfoXml($target_module);
        $module_info->module = $target_module;
        Context::set('module_info', $module_info);
        
        $dir = \RX_BASEDIR . 'modules/' . $module_info->module . '/';
        Context::set('module_dir', $dir);

        $schemas_dir = $dir . 'schemas/';
        if (is_dir($schemas_dir)) {
            $schema_files = scandir($schemas_dir);
            $schemas = [];
            foreach ($schema_files as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }
                if (!is_file($schemas_dir . $file)) {
                    continue;
                }
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'xml') {
                    continue;
                }
                $schemas[] = str_replace('.xml', '', $file);
            }
            Context::set('schemas', $schemas);
        }
        
        $queries_dir = $dir . 'queries/';
        if (is_dir($queries_dir)) {
            $query_files = scandir($queries_dir);
            $queries = [];
            foreach ($query_files as $file) {
                if (in_array($file, ['.', '..'])) continue;
                if (!is_file($queries_dir . $file)) continue;
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'xml') continue;
                $queries[] = str_replace('.xml', '', $file);
            }
            Context::set('queries', $queries);
        }

        $xml_path = \RX_BASEDIR . 'modules/' . $target_module . '/conf/module.xml';
        if (!file_exists($xml_path) || !is_readable($xml_path)) {
            throw new \Exception('Unable to find module module.xml file.');
        }

        $actions = [];
        $xml_content = file_get_contents($xml_path);
        $xml = ($xml_content !== false) ? simplexml_load_string($xml_content) : false;
        if ($xml === false) {
            throw new \Exception('Failed to parse module module.xml file.');
        }

        if (isset($xml->actions->action)) {
            foreach ($xml->actions->action as $action) {
                $act = [];
                foreach ($action->attributes() as $k => $v) {
                    $act[$k] = (string)$v;
                }
                $actions[] = $act;
            }
        }
        Context::set('actions', $actions);

		// 뷰 파일명 지정
		$this->setTemplateFile('module');
	}

    public function dispDevtoolModuleInfo()
    {
        $target_module = Context::get('target_module');
        $xml_path = \RX_BASEDIR . 'modules/' . $target_module . '/conf/info.xml';
        if (!file_exists($xml_path) || !is_readable($xml_path)) {
            throw new \Exception('Unable to find module info.xml file.');
        }

        $info = [
            'title' => [],
            'description' => [],
            'version' => '',
            'date' => date('Y-m-d'),
            'category' => '',
            'author' => [],
            'license' => ['link' => '', 'text' => '']
        ];

        $content = file_get_contents($xml_path);
        $xml = ($content !== false) ? simplexml_load_string($content) : false;

        if ($xml === false) {
            throw new \Exception('Failed to parse module info.xml file.');
        }
        
        foreach ($xml->title as $title) {
            $attrs = $title->attributes('xml', true);
            $info['title'][] = [
                'lang' => isset($attrs->lang) ? (string)$attrs->lang : '',
                'value' => (string)$title
            ];
        }
        
        foreach ($xml->description as $desc) {
            $attrs = $desc->attributes('xml', true);
            $info['description'][] = [
                'lang' => isset($attrs->lang) ? (string)$attrs->lang : '',
                'value' => (string)$desc
            ];
        }
        
        $info['version'] = (string)$xml->version;
        $info['date'] = (string)$xml->date;
        $info['category'] = (string)$xml->category;
        
        if (isset($xml->author)) {
            foreach ($xml->author as $author) {
                $authorItem = [
                    'email_address' => (string)$author['email_address'],
                    'link' => (string)$author['link'],
                    'name' => []
                ];
                foreach ($author->name as $name) {
                    $attrs = $name->attributes('xml', true);
                    $authorItem['name'][] = [
                        'lang' => isset($attrs->lang) ? (string)$attrs->lang : '',
                        'value' => (string)$name
                    ];
                }
                $info['author'][] = $authorItem;
            }
        }
        
        if (isset($xml->license)) {
            $info['license']['link'] = (string)$xml->license['link'];
            $info['license']['text'] = (string)$xml->license;
        }

        Context::set('module_info_xml', $info);
        Context::set('target_module', $target_module);
		$this->setTemplateFile('info');
    }

    public function procDevtoolModuleInfoSave()
    {
        $target_module = Context::get('target_module');
        $vars = Context::getRequestVars();
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><module version="0.2"></module>');
        
        if (!empty($vars->title)) {
            foreach ($vars->title as $idx => $val) {
                if (!$val) continue;
                $lang = $vars->title_lang[$idx] ?? '';
                $t = $xml->addChild('title', $val);
                if ($lang) $t->addAttribute('xml:lang', $lang, 'http://www.w3.org/XML/1998/namespace');
            }
        }
        
        if (!empty($vars->description)) {
            foreach ($vars->description as $idx => $val) {
                if (!$val) continue;
                $lang = $vars->description_lang[$idx] ?? '';
                $d = $xml->addChild('description', $val);
                if ($lang) $d->addAttribute('xml:lang', $lang, 'http://www.w3.org/XML/1998/namespace');
            }
        }
        
        $xml->addChild('version', $vars->version);
        $xml->addChild('date', $vars->date);
        $xml->addChild('category', $vars->category);
        
        if (!empty($vars->authors) && is_array($vars->authors)) {
            foreach ($vars->authors as $authData) {
                $authData = (object)$authData;
                $author = $xml->addChild('author');
                if (!empty($authData->email_address)) $author->addAttribute('email_address', $authData->email_address);
                if (!empty($authData->link)) $author->addAttribute('link', $authData->link);
                
                if (!empty($authData->names) && is_array($authData->names)) {
                    foreach ($authData->names as $nameItem) {
                        $nameItem = (object)$nameItem;
                        if (empty($nameItem->value)) continue;
                        $n = $author->addChild('name', $nameItem->value);
                        if (!empty($nameItem->lang)) {
                            $n->addAttribute('xml:lang', $nameItem->lang, 'http://www.w3.org/XML/1998/namespace');
                        }
                    }
                }
            }
        }
        
        if (!empty($vars->license) || !empty($vars->license_link)) {
            $lic = $xml->addChild('license', $vars->license ?? '');
            if ($vars->license_link) $lic->addAttribute('link', $vars->license_link);
        }

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        $path = \RX_BASEDIR . 'modules/' . $target_module . '/conf/info.xml';
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $xml_content = $dom->saveXML();
        $xml_content = preg_replace_callback('/^( +)/m', function ($m) {
            return str_repeat(' ', strlen($m[1]) * 2);
        }, $xml_content);
        file_put_contents($path, $xml_content);
        
        $this->setMessage('success_saved');
    }

    /**
	 * Download the module as a zip file.
	 * 
	 * @return void
	 */
	public function procDevtoolModuleDownload()
	{
		$target_module = \Context::get('target_module');
		if (!$target_module)
		{
			throw new \Exception('Target module is required');
		}

		// Define module path
		$module_path = \RX_BASEDIR . 'modules/' . $target_module;
		if (!is_dir($module_path))
		{
			throw new \Exception('Module not found: ' . $target_module);
		}

		// Get version from info.xml
		$xml_file = $module_path . '/conf/info.xml';
		$version = '0.0.0';
		if (file_exists($xml_file))
		{
            $xml_content = file_get_contents($xml_file);
            $xml = ($xml_content !== false) ? simplexml_load_string($xml_content) : false;
            if ($xml !== false) {
                $version = (string)$xml->version;
            }
		}

		// Prepare zip filename and path
		$filename = $target_module . ' (' . $version . ').zip';
        $zip_path = \RX_BASEDIR . 'files/cache/tmp/' . uniqid('mod_') . '.zip';
        $destination_dir = dirname($zip_path);
		if (!Storage::exists($destination_dir))
		{
			$mkdir_success = Storage::createDirectory($destination_dir);
			if (!$mkdir_success && !Storage::exists($destination_dir))
			{
				trigger_error('Cannot create directory to write file: ' . $filename, \E_USER_WARNING);
				return false;
			}
		}

		$zip = new \ZipArchive();
		if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true)
		{
			throw new \Exception('Failed to create zip file');
		}

		// Iterate through files recursively
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($module_path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		$real_module_path = realpath($module_path);

		foreach ($files as $name => $file)
		{
			$filePath = $file->getRealPath();
			// Calculate relative path for the zip structure
			$relativePath = substr($filePath, strlen($real_module_path) + 1);

			// Exclude .git and other development files
			if (preg_match('#(^|/|\\\\)\.(git|vscode|idea)($|/|\\\\)#', $relativePath))
			{
				continue;
			}
			if (preg_match('#(^|/|\\\\)\.(gitignore|gitattributes|DS_Store)$#', $relativePath))
			{
				continue;
			}

			// Zip content at root
			$zipEntryName = $relativePath;

			if ($file->isDir())
			{
				$zip->addEmptyDir($zipEntryName);
			}
			else
			{
				$zip->addFile($filePath, $zipEntryName);
			}
		}
		
		$zip->close();

		// Set headers for download
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . filesize($zip_path));
		header('Pragma: no-cache');
		header('Expires: 0');
		
		// Output file and clean up
		readfile($zip_path);
		unlink($zip_path);
		exit;
	}
}
