<?php
require '/var/www/html/z/config/config.inc.php';
Context::getInstance()->init();
$obj = array('act' => 'getResourceapiPackages', 'package_srls' => '2353');
$xml = XmlGenerater::generate($obj);
$url = 'https://rhymix.repo.hoto.dev/';
$config = array('ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE, 'follow_redirects' => TRUE);
$res = FileHandler::getRemoteResource($url, $xml, 10, 'POST', 'application/xml', array(), array(), '', $config);
$parsed = \Rhymix\Framework\Parsers\XEXMLParser::loadXMLString($res);
$download_url = $parsed->response->packageList->item->download_url->body;
echo 'Download URL: ' . $download_url . PHP_EOL;
$dl_res = FileHandler::getRemoteResource($download_url, NULL, 15, 'GET', 'text/html', array(), array(), '', $config);
echo 'Size: ' . strlen($dl_res) . PHP_EOL;
if(strlen($dl_res) < 500) echo 'Content: ' . $dl_res . PHP_EOL;
