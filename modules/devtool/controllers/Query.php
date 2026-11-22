<?php

namespace Rhymix\Modules\Devtool\Controllers;

use Context;
use ModuleModel;
use BaseObject;
use Rhymix\Framework\DB;
use Rhymix\Modules\Devtool\Models\DBQueryGenerator;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class Query extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'views/');
	}
	
	/**
	 * 쿼리 에디터 메인
	 */
	public function dispDevtoolQuery()
	{
		$vars = Context::getRequestVars();
		if (!isset($vars->target_module)) {
			return new BaseObject(-1, 'Invalid request.');
		}

		$target_module = $vars->target_module;
		$queries = [];
		$dir = \RX_BASEDIR . 'modules/' . $target_module . '/queries/';
		
		if (is_dir($dir)) {
			foreach (scandir($dir) as $file) {
				if (str_ends_with($file, '.xml')) {
					$queries[] = substr($file, 0, -4);
				}
			}
		}
		
		$schemas = [];
		$schema_dir = \RX_BASEDIR . 'modules/' . $target_module . '/schemas/';
		if (is_dir($schema_dir)) {
			foreach (scandir($schema_dir) as $file) {
				if (str_ends_with($file, '.xml')) {
					$schemas[] = substr($file, 0, -4);
				}
			}
		}
		Context::set('schemas', $schemas);
		Context::set('queries', $queries);
		Context::set('target_module', $target_module);
		Context::set('query_id', $vars->query_id ?? 'new');
		
		$this->setTemplateFile('query');
	}

	/**
	 * 쿼리 정보 가져오기 (JSON)
	 */
	public function getDevtoolQuery()
	{
		$vars = Context::getRequestVars();
		$file = \RX_BASEDIR . 'modules/' . $vars->target_module . '/queries/' . $vars->query_id . '.xml';
		
		$data = [
			'id' => $vars->query_id === 'new' ? '' : $vars->query_id,
			'action' => 'select',
			'sql' => '',
			'tables' => [],
			'columns' => [],
			'conditions' => [],
			'navigation' => [],
		];

		if (file_exists($file)) {
			$content = file_get_contents($file);
			$xml = simplexml_load_string($content);
			if ($xml) {
				$data['id'] = (string)$xml['id'] ?: $data['id'];
				$data['action'] = (string)$xml['action'] ?: $data['action'];
				
				if (isset($xml->tables->table)) {
					foreach ($xml->tables->table as $node) {
						$item = [];
						foreach ($node->attributes() as $k => $v) $item[$k] = (string)$v;
						$data['tables'][] = $item;
					}
				}
				if (isset($xml->columns->column)) {
					foreach ($xml->columns->column as $node) {
						$item = [];
						foreach ($node->attributes() as $k => $v) $item[$k] = (string)$v;
						$data['columns'][] = $item;
					}
				}
				if (isset($xml->conditions->condition)) {
					foreach ($xml->conditions->condition as $node) {
						$item = [];
						foreach ($node->attributes() as $k => $v) $item[$k] = (string)$v;
						$item['notnull'] = (string)$node['notnull'];
						$data['conditions'][] = $item;
					}
				}
				if (isset($xml->navigation)) {
					foreach ($xml->navigation->children() as $node) {
						$item = ['_tag' => $node->getName()];
						foreach ($node->attributes() as $k => $v) $item[$k] = (string)$v;
						$data['navigation'][] = $item;
					}
				}

                $dbQueryGenerator = new DBQueryGenerator();
                $data['sql'] = $dbQueryGenerator->getQuery($content);
			}
		}

		$this->add('data', $data);
	}

	/**
	 * 쿼리 저장
	 */
	public function procDevtoolQueryUpdate()
	{
		$vars = Context::getRequestVars();
		$module_path = \RX_BASEDIR . 'modules/' . $vars->target_module . '/queries/';
		if (!is_dir($module_path)) {
			mkdir($module_path, 0755, true);
		}

		$doc = new \DOMDocument('1.0', 'UTF-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;

		$root = $doc->createElement('query');
		$root->setAttribute('id', $vars->query_id);
		$root->setAttribute('action', $vars->action);
		$doc->appendChild($root);

		// Tables
		if (!empty($vars->tables)) {
			$tablesNode = $doc->createElement('tables');
			foreach ($vars->tables as $tbl) {
				if (empty($tbl['name'])) continue;
				$node = $doc->createElement('table');
				foreach ($tbl as $k => $v) {
					if ($v !== '') $node->setAttribute($k, $v);
				}
				$tablesNode->appendChild($node);
			}
			$root->appendChild($tablesNode);
		}

		// Columns
		if (!empty($vars->columns) && $vars->action !== 'delete') {
			$colsNode = $doc->createElement('columns');
			foreach ($vars->columns as $col) {
				if (empty($col['name'])) continue;
				$node = $doc->createElement('column');
				foreach ($col as $k => $v) {
					if ($v !== '') $node->setAttribute($k, $v);
				}
				$colsNode->appendChild($node);
			}
			$root->appendChild($colsNode);
		}

		// Conditions
		if (!empty($vars->conditions) && $vars->action !== 'insert') {
			$condsNode = $doc->createElement('conditions');
			foreach ($vars->conditions as $cond) {
				if (empty($cond['operation'])) continue;
				$node = $doc->createElement('condition');
				foreach ($cond as $k => $v) {
					if ($v !== '') $node->setAttribute($k, $v);
				}
				if (isset($cond['notnull']) && $cond['notnull'] === 'Y') $node->setAttribute('notnull', 'notnull');
				$condsNode->appendChild($node);
			}
			$root->appendChild($condsNode);
		}

		// Navigation
		if (!empty($vars->navigation) && $vars->action === 'select') {
			$navNode = $doc->createElement('navigation');
			foreach ($vars->navigation as $nav) {
				if (empty($nav['_tag'])) continue;
				$node = $doc->createElement($nav['_tag']);
				foreach ($nav as $k => $v) {
					if ($k !== '_tag' && $v !== '') $node->setAttribute($k, $v);
				}
				$navNode->appendChild($node);
			}
			$root->appendChild($navNode);
		}

		$xml_content = $doc->saveXML();
        $xml_content = preg_replace_callback('/^( +)/m', function ($m) {
            return str_repeat(' ', strlen($m[1]) * 2);
        }, $xml_content);
        file_put_contents($module_path . $vars->query_id . '.xml', $xml_content);
		
		// Rename handling: if original_query_id exists and is different, delete old file
		if ($vars->original_query_id && $vars->original_query_id !== 'new' && $vars->original_query_id !== $vars->query_id) {
			$old_file = $module_path . $vars->original_query_id . '.xml';
			if (file_exists($old_file)) unlink($old_file);
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getUrl('act', 'dispDevtoolQuery', 'target_module', $vars->target_module, 'query_id', $vars->query_id));
	}

	public function procDevtoolQueryDelete()
	{
		$vars = Context::getRequestVars();
		$file = \RX_BASEDIR . 'modules/' . $vars->target_module . '/queries/' . $vars->query_id . '.xml';
		if (file_exists($file)) unlink($file);
		
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getUrl('act', 'dispDevtoolQuery', 'target_module', $vars->target_module, 'query_id', 'new'));
	}

	/**
	 * 스키마 컬럼 정보 가져오기
	 */
	public function getDevtoolSchemaColumns()
	{
		$vars = Context::getRequestVars();
		$file = \RX_BASEDIR . 'modules/' . $vars->target_module . '/schemas/' . $vars->table_name . '.xml';
		
		$columns = [];
		$schema_details = [];
		if (file_exists($file)) {
			$content = file_get_contents($file);
			$xml = simplexml_load_string($content);
			if ($xml && isset($xml->column)) {
				foreach ($xml->column as $node) {
					$name = (string)$node['name'];
					$columns[] = $name;
					$schema_details[$name] = [
						'notnull' => (string)$node['notnull'],
						'default' => (string)$node['default'],
						'type' => (string)$node['type'],
					];
				}
			}
		}
		
		$this->add('columns', $columns);
		$this->add('schema_details', $schema_details);
	}

	public function getDevtoolQueryExecute()
	{
		$vars = Context::getRequestVars();

		if (!isset($vars->target_module) || !isset($vars->query_id)) {
			return new BaseObject(-1, 'Invalid request.');
		}

		$target_module = preg_replace('/[^a-zA-Z0-9_-]/', '', $vars->target_module);
		$query_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $vars->query_id);
		$params = [];
		foreach (($vars->params ?? []) as $k => $v) {
			$k = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
			$params[$k] = $v;
		}

		$oDB = DB::getInstance();
		$output = $oDB->executeQuery($target_module.'.'.$query_id, $params);
		$this->add('target_module', $target_module);
		$this->add('query_id', $query_id);
		$this->add('params', $params);
		$this->add('output', $output);
	}

	public function getDevtoolModelClasses()
	{
		$vars = Context::getRequestVars();
		$target_module = $vars->target_module;
		$modelsDir = \RX_BASEDIR . 'modules/' . $target_module . '/models';
		
		$classes = [];
		if (is_dir($modelsDir)) {
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsDir));
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$relativePath = substr($file->getPathname(), strlen($modelsDir) + 1);
					$relativePath = substr($relativePath, 0, -4);
					$relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
					$parts = explode('/', $relativePath);
					$parts = array_map('ucfirst', $parts);
					$classes[] = implode('/', $parts);
				}
			}
		}
		sort($classes);
		$this->add('classes', $classes);
	}

	public function getDevtoolQueryPhp()
	{
		$vars = Context::getRequestVars();
		$target_module = $vars->target_module;
		$query_id = $vars->query_id;
		$options = $vars->options ?? [];

		if ($query_id === 'new') {
			return new BaseObject(-1, 'Please save the query first.');
		}

		$funcName = lcfirst($options['func_name'] ?? $this->toCamelCase($query_id));
		$modelsDir = \RX_BASEDIR . 'modules/' . $target_module . '/models';
		
		// Check if function exists
		if (is_dir($modelsDir)) {
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsDir));
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$content = file_get_contents($file->getPathname());
					if (preg_match('/function\s+' . preg_quote($funcName, '/') . '\s*\(/i', $content)) {
						$this->add('exists', true);
						$relativePath = substr($file->getPathname(), strlen($modelsDir) + 1);
						$relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
						$this->setMessage("Function '{$funcName}' already exists in " . $relativePath);
						$this->add('existing_code', $this->extractMethodCode($content, $funcName));
						return;
					}
				}
			}
		}

		// Get table name
		$xmlFile = \RX_BASEDIR . 'modules/' . $target_module . '/queries/' . $query_id . '.xml';
		$tableName = '';
		$action = $options['action'] ?? 'select';
		if (file_exists($xmlFile)) {
			$content = file_get_contents($xmlFile);
			$xml = simplexml_load_string($content);
			if ($xml) {
				$action = (string)$xml['action'] ?: $action;
				if (isset($xml->tables->table)) {
					foreach ($xml->tables->table as $table) {
						$tableName = (string)$table['name'];
						break;
					}
				}
			}
		}

		// Determine class name
		$className = '';
		if ($tableName) {
			$className = $this->toPascalCase($tableName);
		} else {
			$className = 'DefaultModel';
		}

		$targetFile = $modelsDir . '/' . $className . '.php';
		$fileExists = file_exists($targetFile);
		$targetFileRel = 'modules/' . $target_module . '/models/' . $className . '.php';

		$methodCode = $this->generateMethodCode($query_id, $target_module, $action, $options);

		$code = '';
		if (!$fileExists) {
			$moduleNamespace = $this->toPascalCase($target_module);
			$code = <<<PHP
<?php

namespace Rhymix\Modules\\{$moduleNamespace}\Models;

use Rhymix\Framework\Cache;
use Rhymix\Framework\DB;
use Rhymix\Framework\Exception;
use Rhymix\Framework\Exceptions\DBError;
use Rhymix\Framework\Storage;
use BaseObject;

class {$className}
{
{$methodCode}
}
PHP;
		} else {
			$code = $methodCode;
		}

		$this->add('exists', false);
		$this->add('file_exists', $fileExists);
		$this->add('target_file', $targetFileRel);
		$this->add('code', $code);
	}

	public function procDevtoolQueryPhpSave()
	{
		$vars = Context::getRequestVars();
		$target_module = $vars->target_module;
		$query_id = $vars->query_id;
		$options = $vars->options ?? [];

		$funcName = lcfirst($options['func_name'] ?? $this->toCamelCase($query_id));
		$modelsDir = \RX_BASEDIR . 'modules/' . $target_module . '/models';
		if (!is_dir($modelsDir)) {
			mkdir($modelsDir, 0755, true);
		}

		// Get table name and action
		$xmlFile = \RX_BASEDIR . 'modules/' . $target_module . '/queries/' . $query_id . '.xml';
		$tableName = '';
		$action = $options['action'] ?? 'select';
		if (file_exists($xmlFile)) {
			$content = file_get_contents($xmlFile);
			$xml = simplexml_load_string($content);
			if ($xml) {
				$action = (string)$xml['action'] ?: $action;
				if (isset($xml->tables->table)) {
					foreach ($xml->tables->table as $table) {
						$tableName = (string)$table['name'];
						break;
					}
				}
			}
		}

		$className = $tableName ? $this->toPascalCase($tableName) : 'DefaultModel';
		$targetFile = $modelsDir . '/' . $className . '.php';
		$methodCode = $this->generateMethodCode($query_id, $target_module, $action, $options);

		if (!file_exists($targetFile)) {
			$moduleNamespace = $this->toPascalCase($target_module);
			$content = <<<PHP
<?php

namespace Rhymix\Modules\\{$moduleNamespace}\Models;

use Rhymix\Framework\Cache;
use Rhymix\Framework\DB;
use Rhymix\Framework\Exception;
use Rhymix\Framework\Exceptions\DBError;
use Rhymix\Framework\Storage;
use BaseObject;

class {$className}
{
{$methodCode}
}
PHP;
			file_put_contents($targetFile, $content);
		} else {
			$content = file_get_contents($targetFile);
			
			// Check if function already exists
			if (preg_match('/function\s+' . preg_quote($funcName, '/') . '\s*\(/i', $content)) {
				return new BaseObject(-1, "Function '{$funcName}' already exists.");
			}

			// Insert method before the last closing brace
			$pos = strrpos($content, '}');
			if ($pos !== false) {
				$content = substr_replace($content, "\n" . $methodCode . "\n}", $pos, 1);
				file_put_contents($targetFile, $content);
			} else {
				return new BaseObject(-1, "Could not find closing brace in class file.");
			}
		}

		$this->setMessage('Saved successfully.');
	}

	private function generateMethodCode($query_id, $target_module, $action, $options)
	{
		$funcName = lcfirst($options['func_name'] ?? $this->toCamelCase($query_id));
		$queryName = $target_module . '.' . $query_id;

		if ($action === 'insert' || $action === 'update') {
			$hasCreatedAt = false;
			$hasUpdatedAt = false;

			$xmlFile = \RX_BASEDIR . 'modules/' . $target_module . '/queries/' . $query_id . '.xml';
			if (file_exists($xmlFile)) {
				$content = file_get_contents($xmlFile);
				$xml = simplexml_load_string($content);
				if ($xml && isset($xml->tables->table)) {
					foreach ($xml->tables->table as $table) {
						$tableName = (string)$table['name'];
						if ($tableName) {
							$schemaFile = \RX_BASEDIR . 'modules/' . $target_module . '/schemas/' . $tableName . '.xml';
							if (file_exists($schemaFile)) {
								$schemaContent = file_get_contents($schemaFile);
								$schemaXml = simplexml_load_string($schemaContent);
								if ($schemaXml && isset($schemaXml->column)) {
									foreach ($schemaXml->column as $col) {
										$colName = (string)$col['name'];
										if ($colName === 'created_at') $hasCreatedAt = true;
										if ($colName === 'updated_at') $hasUpdatedAt = true;
									}
								}
							}
							break;
						}
					}
				}
			}

			$prepCode = '';

			if ($action === 'insert' && $hasCreatedAt) {
				$prepCode .= "        if (is_object(\$args)) {\n";
				$prepCode .= "            \$args = get_object_vars(\$args);\n";
				$prepCode .= "        }\n";
				$prepCode .= "        \$args['created_at'] ??= date('Y-m-d H:i:s');\n\n";
			}
			if ($action === 'update') {
				if ($hasCreatedAt || $hasUpdatedAt) {
					$prepCode .= "        if (is_object(\$args)) {\n";
					$prepCode .= "            \$args = get_object_vars(\$args);\n";
					$prepCode .= "        }\n\n";
				}
				if ($hasUpdatedAt) {
					$prepCode .= "        \$args['updated_at'] = date('Y-m-d H:i:s');\n";
				}
				if ($hasCreatedAt) {
					$prepCode .= "        unset(\$args['created_at']);\n";
				}
				$prepCode .= "\n";
			}

			return <<<PHP
    public static function {$funcName}(object|array \$args): \BaseObject
    {
{$prepCode}        \$output = executeQuery('{$queryName}', \$args);
        if (!\$output->toBool()) {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", \$output->getMessage(), __FILE__, __LINE__));
        }

        return \$output;
    }
PHP;
		} elseif ($action === 'delete') {
			$params = $options['params'] ?? [];
			$phpParams = [];
			$execParams = [];
			
			foreach ($params as $p) {
				$type = $p['type'] ?? 'string';
				$name = $p['name'] ?? $p['var'];
				$optional = isset($p['optional']) && ($p['optional'] === 'true' || $p['optional'] === true);
				
				$paramStr = ($optional ? '?' : '') . $type . ' $' . $name;
				if ($optional) {
					$paramStr .= ' = null';
				}
				$phpParams[] = $paramStr;
				$execParams[] = "'{$p['var']}' => \${$name}";
			}
			
			$paramSignature = implode(', ', $phpParams);
			$execArray = '[' . implode(', ', $execParams) . ']';

			return <<<PHP
    public static function {$funcName}({$paramSignature}): \BaseObject
    {
        \$output = executeQuery('{$queryName}', {$execArray});
        if (!\$output->toBool()) {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", \$output->getMessage(), __FILE__, __LINE__));
        }

        return \$output;
    }
PHP;
		} else { // select
			$returnType = $options['return_type'] ?? 'object';
			$params = $options['params'] ?? [];

			// Determine Item Class
			$returnClass = $options['return_class'] ?? 'object';
			$itemClass = null;
			if ($returnClass === 'custom') {
				$itemClass = $options['return_class_custom'] ?? null;
			} elseif ($returnClass !== 'object') {
				$itemClass = $returnClass;
			}

			$classRef = null;
			if ($itemClass) {
				$itemClass = str_replace('/', '\\', $itemClass);
				// Namespace simplification
				$currentNamespace = 'Rhymix\\Modules\\' . $this->toPascalCase($target_module) . '\\Models';
				$fqnPrefix = '\\' . $currentNamespace . '\\';

				if (str_starts_with($itemClass, $fqnPrefix)) {
					$classRef = substr($itemClass, strlen($fqnPrefix));
				} else {
					$classRef = $itemClass;
				}
			}

			$phpParams = [];
			$execParams = [];
			
			foreach ($params as $p) {
				$type = $p['type'] ?? 'string';
				$name = $p['name'] ?? $p['var'];
				$optional = isset($p['optional']) && ($p['optional'] === 'true' || $p['optional'] === true);
				
				$paramStr = ($optional ? '?' : '') . $type . ' $' . $name;
				if ($optional) {
					$paramStr .= ' = null';
				}
				$phpParams[] = $paramStr;
				$execParams[] = "'{$p['var']}' => \${$name}";
			}
			
			$paramSignature = implode(', ', $phpParams);
			$execArray = '[' . implode(', ', $execParams) . ']';
			
			// Return Type Hint
			$returnTypeHint = '?object';
			if ($returnType === 'array') {
				$returnTypeHint = 'array';
			} elseif ($classRef) {
				$returnTypeHint = '?' . $classRef;
			}

			// Execution Logic
			$execFunc = ($returnType === 'array') ? 'executeQueryArray' : 'executeQuery';
			$returnVal = '$output->data';

			if ($classRef) {
				$arg3 = '[]';

				if ($returnType === 'array') {
					$arg4 = $classRef . '::class';
					$execCall = "{$execFunc}('{$queryName}', {$execArray}, {$arg3}, {$arg4})";
				} else {
					$arg4 = "'auto'";
					$arg5 = $classRef . '::class';
					$execCall = "{$execFunc}('{$queryName}', {$execArray}, {$arg3}, {$arg4}, {$arg5})";
				}
			} else {
				$execCall = "{$execFunc}('{$queryName}', {$execArray})";
			}

			return <<<PHP
    public static function {$funcName}({$paramSignature}): {$returnTypeHint}
    {
        \$output = {$execCall};
        if (!\$output->toBool()) {
            throw new \Rhymix\Framework\Exceptions\DBError(sprintf("DB Error: %s in %s line %s", \$output->getMessage(), __FILE__, __LINE__));
        }

        return {$returnVal};
    }
PHP;
		}
	}

	private function toCamelCase($str)
	{
		return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $str))));
	}

	private function toPascalCase($str)
	{
		return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $str)));
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
