<?php
require 'c:/Users/jurin.DESKTOP-KQQH3HQ/Z/ksha2026/config/config.inc.php';
Context::getInstance()->init();

$url = 'https://rhymix.repo.hoto.dev/?module=file&act=procFileDownload&file_srl=2357&sid=0cfdf9d9494372991db3d89045b80424&module_srl=0';
$config = array('ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE);
$res = FileHandler::getRemoteResource($url, NULL, 3, 'GET', 'text/html', array(), array(), '', $config);
echo 'Length: ' . strlen($res) . PHP_EOL;
echo 'Header: ' . substr($res, 0, 100) . PHP_EOL;
