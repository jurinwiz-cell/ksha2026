<?php
define('__XE__', TRUE);
$_SERVER['HTTPS'] = 'on';
$GLOBALS['__PROTECT__'] = TRUE;

require(realpath(__DIR__.'/../../config/config.inc.php'));
$oContext = Context::getInstance();
$oContext->init();

$oModel = getModel('elkhatalk3');
$cache_val = $oModel->getElkhatalk3HostCount(TRUE);
