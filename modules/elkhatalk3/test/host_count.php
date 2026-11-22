<?php

$_SERVER['HTTPS'] = 'on';
$GLOBALS['__PROTECT__'] = TRUE;
define('__XE__', TRUE);
require(__DIR__.'/../../../config/config.inc.php');

\Context::init();

$oClass = getClass('elkhatalk3');
$params = array();
$output = $oClass->sendSocket('hosts_clients', $params);
echo $output->getMessage();
