<?php
/**
 * 결제 게이트웨이 공통 베이스 (HTTP/결과 헬퍼)
 * @author 딱따고기 (https://ddgglabs.org)
 */
abstract class BaseGateway implements PaymentGateway
{
	/** @var object 모듈 설정 */
	protected $config;

	public function __construct($config)
	{
		$this->config = is_object($config) ? $config : new stdClass;
	}

	/** 기본값: 클라이언트 결제창 불필요 */
	public function requiresClientPayment()
	{
		return false;
	}

	/** 표준 결과 객체 생성 */
	protected function result($success, $message = '', $tid = '', $raw = null)
	{
		$r = new stdClass;
		$r->success = (bool)$success;
		$r->message = (string)$message;
		$r->tid = (string)$tid;
		$r->raw = $raw;
		return $r;
	}

	/**
	 * JSON POST 요청.
	 * 반환: array('status'=>int, 'body'=>string, 'error'=>string)
	 */
	protected function httpPost($url, $body, $headers = array())
	{
		if (!function_exists('curl_init'))
		{
			return array('status' => 0, 'body' => '', 'error' => 'curl not available');
		}
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
		));
		$resp = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		return array('status' => $status, 'body' => (string)$resp, 'error' => $error);
	}

	/**
	 * GET 요청.
	 * 반환: array('status'=>int, 'body'=>string, 'error'=>string)
	 */
	protected function httpGet($url, $headers = array())
	{
		if (!function_exists('curl_init'))
		{
			return array('status' => 0, 'body' => '', 'error' => 'curl not available');
		}
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
		));
		$resp = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		return array('status' => $status, 'body' => (string)$resp, 'error' => $error);
	}
}
