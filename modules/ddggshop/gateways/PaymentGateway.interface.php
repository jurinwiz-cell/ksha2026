<?php
/**
 * 결제 게이트웨이 인터페이스
 * 새 PG를 추가하려면 BaseGateway를 상속해 이 인터페이스를 구현하고
 * ddggshopModel::getGateway() 팩토리에 등록한다.
 * @author 딱따고기 (https://ddgglabs.org)
 */
interface PaymentGateway
{
	/** 게이트웨이 코드 (toss / banktransfer ...) */
	public function getName();

	/** 클라이언트가 결제창을 열기 위해 로드해야 하는 JS SDK URL (없으면 '') */
	public function getClientScript();

	/** 결제 직후 클라이언트(JS)가 결제창을 호출하는 데 필요한 데이터 배열 */
	public function buildRequest($order);

	/** 클라이언트 결제창/리다이렉트가 필요한 PG인지 여부 */
	public function requiresClientPayment();

	/**
	 * 서버 승인(confirm). 콜백 파라미터로 PG 승인 API를 호출한다.
	 * 반환: stdClass { success(bool), message, tid, raw }
	 */
	public function approve($order, $params);

	/** 결제 취소/환불. 반환: stdClass { success, message, tid, raw } */
	public function cancel($order, $reason);
}
