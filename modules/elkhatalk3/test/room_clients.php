<?php

$_SERVER['HTTPS'] = 'on';
$GLOBALS['__PROTECT__'] = TRUE;
define('__XE__', TRUE);
require(__DIR__.'/../../../config/config.inc.php');

$oClass = getClass('elkhatalk3');
$params = array('room' => 'public1');
$output = $oClass->sendSocket('clients', $params);
echo $output->getMessage();
