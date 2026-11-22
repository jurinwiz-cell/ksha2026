<?php

namespace Rhymix\Modules\Devtool\Controllers;

use Context;
use ModuleModel;
use BaseObject;
use Rhymix\Modules\Devtool\Models\DBSchema;
use Rhymix\Framework\DB;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class Schema extends Base
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
	public function dispDevtoolSchema()
	{
        $vars = Context::getRequestVars();
        if (!isset($vars->target_module) || !isset($vars->schema_name)) {
            return new BaseObject(-1, 'Invalid request.');
        }

        // 신규 스키마 생성 모드
        if ($vars->schema_name === 'new') {
            $is_writable = is_writable(\RX_BASEDIR . 'modules/' . $vars->target_module . '/schemas/');
            Context::set('is_writable', $is_writable);

            $xml = new \SimpleXMLElement('<table name=""></table>');
            Context::set('schema_info', $xml);
            Context::set('schema_xml', '');
            Context::set('query_string', '');
            Context::set('target_module', $vars->target_module);
            Context::set('schema_name', 'new');
            $this->setTemplateFile('schema');
            return;
        }

        $file = \RX_BASEDIR . 'modules/' . $vars->target_module . '/schemas/' . $vars->schema_name . '.xml';
        if (!file_exists($file)) {
            return new BaseObject(-1, 'Schema file not found.');
        }

        $oDB = DB::getInstance();
        Context::set('is_table_exists', $oDB->isTableExists($vars->schema_name));

        $file_content = file_get_contents($file);
        Context::set('schema_xml', htmlspecialchars($file_content, ENT_QUOTES, 'UTF-8'));

        $oDBSchema = new DBSchema();
        $query_string = $oDBSchema->getDDL($file_content);
        Context::set('query_string', $query_string);

        $xml = simplexml_load_string($file_content);
        Context::set('schema_info', $xml);

        $is_writable = is_writable($file);
        Context::set('is_writable', $is_writable);

        Context::set('target_module', $vars->target_module);
        Context::set('schema_name', $vars->schema_name);

		// 뷰 파일명 지정
		$this->setTemplateFile('schema');
	}

    /**
     * 스키마 XML 업데이트
     */
    public function procDevtoolSchemaUpdate()
    {
        $vars = Context::getRequestVars();
        if (!isset($vars->target_module) || !isset($vars->schema_name)) {
            return new BaseObject(-1, 'Invalid request.');
        }

        $original_schema_name = $vars->original_schema_name ?? $vars->schema_name;
        $new_schema_name = $vars->schema_name;
        $module_path = \RX_BASEDIR . 'modules/' . $vars->target_module . '/schemas/';
        if (!is_dir($module_path)) {
            mkdir($module_path, 0755, true);
        }

        // DOMDocument를 사용하여 XML 로드 및 수정 (구조 유지)
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        if ($original_schema_name === 'new') {
            if (!is_writable($module_path)) {
                return new BaseObject(-1, 'Schema directory is not writable.');
            }

            // 신규 생성
            $root = $doc->createElement('table');
            $root->setAttribute('name', $new_schema_name);
            $doc->appendChild($root);
        } else {
            // 기존 파일 로드
            $file = $module_path . $original_schema_name . '.xml';
            if (!file_exists($file)) {
                return new BaseObject(-1, 'Schema file not found.');
            }
            if (!is_writable($file)) {
                return new BaseObject(-1, 'Schema file is not writable.');
            }
            $libxml_previous_state = libxml_use_internal_errors(true);
            $loaded = $doc->load($file);
            libxml_clear_errors();
            libxml_use_internal_errors($libxml_previous_state);

            if ($loaded && $doc->documentElement) {
                $root = $doc->documentElement;
            } else {
                while ($doc->hasChildNodes()) {
                    $doc->removeChild($doc->firstChild);
                }
                $root = $doc->createElement('table');
                $doc->appendChild($root);
            }
            $root->setAttribute('name', $new_schema_name); // 테이블명 업데이트
        }

        $xpath = new \DOMXPath($doc);

        // 기존 태그 삭제 (column, index, constraint)
        foreach (['column', 'index', 'constraint'] as $tagName) {
            $nodes = $xpath->query('/table/' . $tagName);
            foreach ($nodes as $node) {
                $root->removeChild($node);
            }
        }

        // 새 컬럼 추가
        if (isset($vars->columns) && is_array($vars->columns)) {
            foreach ($vars->columns as $col_data) {
                if (trim($col_data['name']) === '') continue;

                $newCol = $doc->createElement('column');
                $newCol->setAttribute('name', trim($col_data['name']));
                $newCol->setAttribute('type', trim($col_data['type']));

                if (trim($col_data['size'] ?? '')) {
                    $newCol->setAttribute('size', trim($col_data['size']));
                }
                if (isset($col_data['default']) && $col_data['default'] !== '') {
                    $newCol->setAttribute('default', $col_data['default']);
                }
                if (isset($col_data['notnull']) && $col_data['notnull'] === 'Y') {
                    $newCol->setAttribute('notnull', 'notnull');
                }
                if (isset($col_data['primary_key']) && $col_data['primary_key'] === 'Y') {
                    $newCol->setAttribute('primary_key', 'primary_key');
                }
                if (isset($col_data['auto_increment']) && $col_data['auto_increment'] === 'Y') {
                    $newCol->setAttribute('auto_increment', 'auto_increment');
                }
                if (trim($col_data['index'] ?? '')) {
                    $newCol->setAttribute('index', trim($col_data['index']));
                }

                $root->appendChild($newCol);
            }
        }

        // 새 인덱스 추가
        if (isset($vars->indexes) && is_array($vars->indexes)) {
            foreach ($vars->indexes as $idx_data) {
                if (trim($idx_data['name']) === '') continue;
                
                $newIdx = $doc->createElement('index');
                $newIdx->setAttribute('name', trim($idx_data['name']));
                $newIdx->setAttribute('columns', trim($idx_data['columns']));
                if (trim($idx_data['type'] ?? '')) {
                    $newIdx->setAttribute('type', trim($idx_data['type']));
                }
                $root->appendChild($newIdx);
            }
        }

        // 새 제약조건 추가
        if (isset($vars->constraints) && is_array($vars->constraints)) {
            foreach ($vars->constraints as $const_data) {
                if (trim($const_data['column']) === '') continue;

                $newConst = $doc->createElement('constraint');
                $newConst->setAttribute('type', trim($const_data['type'] ?: 'foreign key'));
                $newConst->setAttribute('column', trim($const_data['column']));
                $newConst->setAttribute('references', trim($const_data['references']));
                if (trim($const_data['onupdate'] ?? '')) {
                    $newConst->setAttribute('onupdate', trim($const_data['onupdate']));
                }
                if (trim($const_data['ondelete'] ?? '')) {
                    $newConst->setAttribute('ondelete', trim($const_data['ondelete']));
                }
                $root->appendChild($newConst);
            }
        }

        // 저장
        $new_file = $module_path . $new_schema_name . '.xml';
        $xml_content = $doc->saveXML();
        $xml_content = preg_replace_callback('/^( +)/m', function ($m) {
            return str_repeat(' ', strlen($m[1]) * 2);
        }, $xml_content);
        file_put_contents($new_file, $xml_content);

        // 파일명이 변경되었고 신규 생성이 아니라면 기존 파일 삭제
        if ($original_schema_name !== 'new' && $original_schema_name !== $new_schema_name) {
            $old_file = $module_path . $original_schema_name . '.xml';
            if (file_exists($old_file)) unlink($old_file);
        }

        $this->setMessage('success_updated');
        $this->setRedirectUrl(getUrl('act', 'dispDevtoolSchema', 'target_module', $vars->target_module, 'schema_name', $new_schema_name));
    }

    /**
     * 스키마 삭제
     */
    public function procDevtoolSchemaDelete()
    {
        $vars = Context::getRequestVars();
        if (!isset($vars->target_module)) {
            return new BaseObject(-1, 'Invalid request.');
        }

        $schema_name = $vars->original_schema_name ?? $vars->schema_name;
        $file = \RX_BASEDIR . 'modules/' . $vars->target_module . '/schemas/' . $schema_name . '.xml';
        
        if (file_exists($file)) {
            if (!unlink($file)) {
                return new BaseObject(-1, 'Failed to delete schema file.');
            }
        }

        $this->setMessage('success_deleted');
        $this->setRedirectUrl(getUrl('act', 'dispDevtoolModule', 'target_module', $vars->target_module));
    }

    /**
     * 테이블 생성
     */
    public function procDevtoolSchemaTableCreate()
    {
        $vars = Context::getRequestVars();
        $oDB = DB::getInstance();
        
        if ($oDB->isTableExists($vars->schema_name)) {
            return new BaseObject(-1, 'Table already exists.');
        }
        
        $file = \RX_BASEDIR . 'modules/' . $vars->target_module . '/schemas/' . $vars->schema_name . '.xml';
        if (!file_exists($file)) {
            return new BaseObject(-1, 'Schema file not found.');
        }
        
        $xml_content = file_get_contents($file);
        $output = $oDB->createTable('', $xml_content);
        
        if (!$output->toBool()) {
            return $output;
        }
        
        $this->setMessage('success_created');
    }

    /**
     * DTO 클래스 생성/갱신
     */
    public function procDevtoolSchemaApplyDto()
    {
        $vars = Context::getRequestVars();
        if (!isset($vars->target_module) || !isset($vars->schema_name)) {
            return new BaseObject(-1, 'Invalid request.');
        }

        $target_module = $vars->target_module;
        $schema_name = $vars->schema_name;

        $xml_file = \RX_BASEDIR . 'modules/' . $target_module . '/schemas/' . $schema_name . '.xml';
        if (!file_exists($xml_file)) {
            return new BaseObject(-1, 'Schema file not found. Please save the schema first.');
        }

        $file_content = file_get_contents($xml_file);
        $xml = simplexml_load_string($file_content);
        if ($xml === false) {
            return new BaseObject(-1, 'Failed to parse schema XML.');
        }
        if (!isset($xml->column) || count($xml->column) === 0) {
            return new BaseObject(-1, 'No columns defined in the schema file. Please save the schema first.');
        }
        
        $lines = [];
        $lines[] = '    /** Managed By Devtool START */';
        foreach ($xml->column as $column) {
            $name = (string)$column['name'];
            $type = (string)$column['type'];
            $notnull = isset($column['notnull']) && (string)$column['notnull'] === 'notnull';
            
            $phpType = 'string';
            if (in_array($type, ['number', 'int', 'bigint', 'tinyint'])) {
                $phpType = 'int';
            } elseif (in_array($type, ['float', 'double'])) {
                $phpType = 'float';
            }
            
            $prefix = 'public ';
            if (!$notnull) {
                $prefix .= '?';
            }
            
            $lines[] = "    {$prefix}{$phpType} \${$name};";
        }
        $lines[] = '    /** Managed By Devtool END */';
        $insert_content = implode("\n", $lines);

        $dto_dir = \RX_BASEDIR . 'modules/' . $target_module . '/models/schemas/';
        if (!is_dir($dto_dir)) {
            mkdir($dto_dir, 0755, true);
        }

        $className = $this->toPascalCase($schema_name) . 'DTO';
        $dto_file = $dto_dir . $className . '.php';

        if (!file_exists($dto_file)) {
            $moduleNamespace = $this->toPascalCase($target_module);
            $file_content = "<?php\n\nnamespace Rhymix\\Modules\\{$moduleNamespace}\\Models\\Schemas;\n\nclass {$className}\n{\n{$insert_content}\n}\n";
            file_put_contents($dto_file, $file_content);
        } else {
            $content = file_get_contents($dto_file);
            $startMarker = '/** Managed By Devtool START */';
            $endMarker = '/** Managed By Devtool END */';
            
            $startPos = strpos($content, $startMarker);
            $endPos = strpos($content, $endMarker);
            
            if ($startPos === false || $endPos === false) {
                return new BaseObject(-1, '자동생성 주석(Managed By Devtool)을 찾을 수 없어 업데이트할 수 없습니다.');
            }
            
            $preMarker = substr($content, 0, $startPos);
            $lastNewLine = strrpos($preMarker, "\n");
            $lineStart = ($lastNewLine === false) ? 0 : $lastNewLine + 1;
            $replaceStart = (trim(substr($preMarker, $lineStart)) === '') ? $lineStart : $startPos;

            $new_content = substr($content, 0, $replaceStart) . $insert_content . substr($content, $endPos + strlen($endMarker));
            file_put_contents($dto_file, $new_content);
        }

        $this->setMessage('DTO Class applied successfully.');
    }

    private function toPascalCase($str)
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $str)));
    }
}
