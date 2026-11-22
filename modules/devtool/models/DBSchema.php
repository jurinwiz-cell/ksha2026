<?php

namespace Rhymix\Modules\Devtool\Models;

use Rhymix\Framework\DB;
use Rhymix\Framework\Parsers\DBTableParser;
use ModuleController;
use ModuleModel;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class DBSchema extends DBTableParser
{
    public function getDDL($xml)
    {
        $oDB = DB::getInstance();
        $query = $this->loadXML('', $xml);
        return $query->getCreateQuery($oDB->prefix);
    }
}
