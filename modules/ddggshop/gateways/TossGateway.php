<?php
/**
 * 토스페이먼츠 게이트웨이
 * 결제위젯/표준 SDK v2 + 서버 승인(REST) 방식.
 * @author 딱따고기 (https://ddgglabs.org)
 */
class TossGateway extends BaseGateway
{
	const SDK_URL     = 'https://js.tosspayments.com/v2/standard';
	const CONFIRM_URL = 'https://api.tosspayments.com/v1/payments/confirm';
	const CANCEL_URL  = 'https://api.tosspayments.com/v1/payments/';

	public function getName()
	{
		return 'toss';
	}

	public function getClientScript()
	{
		return self::SDK_URL;
	}

	public function requiresClientPayment()
	{
		return true;
	}

	public function buildRequest($order)
	{
		return array(
			'gateway'   => 'toss',
			'clientKey' => (string)$this->config->toss_client_key,
			'orderId'   => (string)$order->order_code,
			'amount'    => (int)$order->payment_price,
		);
	}

	/** Authorization: Basic base64(secretKey:) */
	protected function authHeader()
	{
		return 'Authorization: Basic ' . base64_encode($this->config->toss_secret_key . ':');
	}

	/**
	 * 결제 승인. $params: paymentKey, orderId, amount
	 */
	public function approve($order, $params)
	{
		$paymentKey = isset($params['paymentKey']) ? (string)$params['paymentKey'] : '';
		$orderId    = isset($params['orderId']) ? (string)$params['orderId'] : '';
		$amount     = isset($params['amount']) ? (int)$params['amount'] : 0;

		if ($paymentKey === '' || $orderId === '')
		{
			return $this->result(false, '결제 정보가 올바르지 않습니다.', '', null);
		}

		$body = json_encode(array(
			'paymentKey' => $paymentKey,
			'orderId'    => $orderId,
			'amount'     => $amount,
		));
		$headers = array($this->authHeader(), 'Content-Type: application/json');
		$resp = $this->httpPost(self::CONFIRM_URL, $body, $headers);
		$data = @json_decode($resp['body']);

		$ok = ($resp['status'] === 200 && is_object($data) && isset($data->status)
			&& in_array($data->status, array('DONE', 'WAITING_FOR_DEPOSIT'), true));
		if ($ok)
		{
			$tid = isset($data->paymentKey) ? $data->paymentKey : $paymentKey;
			return $this->result(true, $data->status, $tid, $resp['body']);
		}
		$msg = (is_object($data) && isset($data->message)) ? $data->message : '결제 승인에 실패했습니다.';
		return $this->result(false, $msg, $paymentKey, $resp['body']);
	}

	public function cancel($order, $reason)
	{
		$paymentKey = (string)$order->pg_tid;
		if ($paymentKey === '')
		{
			return $this->result(false, '거래번호가 없어 취소할 수 없습니다.', '', null);
		}
		$url = self::CANCEL_URL . rawurlencode($paymentKey) . '/cancel';
		$body = json_encode(array('cancelReason' => $reason ?: '주문취소'));
		$headers = array($this->authHeader(), 'Content-Type: application/json');
		$resp = $this->httpPost($url, $body, $headers);
		$data = @json_decode($resp['body']);
		if ($resp['status'] === 200)
		{
			return $this->result(true, '취소되었습니다.', $paymentKey, $resp['body']);
		}
		$msg = (is_object($data) && isset($data->message)) ? $data->message : '취소에 실패했습니다.';
		return $this->result(false, $msg, $paymentKey, $resp['body']);
	}

	/**
	 * 결제 단건 조회 (웹훅 검증용). 웹훅 본문을 신뢰하지 않고 토스 API로 직접 확인한다.
	 * 반환 result: success=조회성공, message=status(DONE 등), raw=응답본문, 그리고 amount 속성 부여.
	 */
	public function query($paymentKey)
	{
		$paymentKey = (string)$paymentKey;
		if ($paymentKey === '')
		{
			return $this->result(false, '거래번호가 없습니다.', '', null);
		}
		$url = self::CANCEL_URL . rawurlencode($paymentKey);
		$headers = array($this->authHeader());
		$resp = $this->httpGet($url, $headers);
		$data = @json_decode($resp['body']);
		if ($resp['status'] === 200 && is_object($data) && isset($data->status))
		{
			$r = $this->result(true, (string)$data->status, $paymentKey, $resp['body']);
			$r->amount = isset($data->totalAmount) ? (int)$data->totalAmount : (isset($data->balanceAmount) ? (int)$data->balanceAmount : 0);
			return $r;
		}
		$msg = (is_object($data) && isset($data->message)) ? $data->message : '결제 조회에 실패했습니다.';
		$r = $this->result(false, $msg, $paymentKey, $resp['body']);
		$r->amount = 0;
		return $r;
	}
}
