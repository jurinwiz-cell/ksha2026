<?php

namespace Rhymix\Modules\Devtool\Models;

use Rhymix\Framework\Parsers\DBQueryParser;
use ModuleController;
use ModuleModel;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class DBQueryGenerator extends DBQueryParser
{
    protected $xml;
    protected $args;
    protected $query;
    protected $query_string;

    public function getQuery($xml, $args = [])
    {
        $this->xml = simplexml_load_string($xml);
        $this->args = $args;
        if(empty($this->args))
        {
            $this->args = $this->generateQueryValues($this->xml);
        }

        $this->query = $this->_parseQuery($this->xml);
        $this->query_string = $this->query->getQueryString('rx_', $this->args);
        return $this->query_string;
    }

    public function getValue($type)
    {
        switch($type)
        {
            case 'string':
                return 'test';

            case 'int':
            case 'number':
            default:
                return 1;
        }
    }

    public function generateQueryValues($xml)
    {
        $args  = [];

        if(isset($xml->tables))
        {
            foreach($xml->tables->children() as $child)
            {
                if ($child->attributes()->query == "true")
                {
                    $args = array_merge($args, $this->generateQueryValues($child));
                }
            }
        }

        if(isset($xml->columns))
        {
            foreach($xml->columns->children() as $child)
            {
                if (isset($child->query))
                {
                    $args = array_merge($args, $this->generateQueryValues($child));
                    continue;
                }
    
                $args[(string)$child->attributes()->var] = $child->attributes()->default ?? $this->getValue($child->attributes()->filter);
            }
        }

        if(isset($xml->conditions))
        {
            foreach($xml->conditions->children() as $child)
            {
                if (isset($child->query))
                {
                    $args = array_merge($args, $this->generateQueryValues($child));
                    continue;
                }
                
                $args[(string)$child->attributes()->var] = $child->attributes()->default ?? $this->getValue($child->attributes()->filter);
            }
        }

        if(isset($xml->navigation))
        {
            foreach($xml->navigation->children() as $child)
            {
                $args[(string)$child->attributes()->var] = $child->attributes()->default ?? $this->getValue($child->attributes()->filter);
                if(isset($child->attributes()->order))
                {
                    $args[(string)$child->attributes()->order] = $this->getValue($child->attributes()->filter);
                }
            }
        }

        return $args;
    }
}
