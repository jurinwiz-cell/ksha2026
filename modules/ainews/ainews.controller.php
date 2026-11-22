<?php
/**
 * @class  ainewsController
 * @author 관리자 (admin@example.com)
 * @brief  ainews module controller class.
 */
class ainewsController extends ainews
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
	}

	/**
	 * @brief 크론잡: 뉴스 수집 및 에이전트 트리거
	 */
	public function cronCollectNews()
	{
		// TODO: 안티그래비티 브라우저 서브에이전트 트리거 (로컬 스크립트 실행 또는 API 호출)
		// 에이전트가 다음 뉴스를 수집한 후 procAinewsInsertArticle API를 호출하도록 설계
		
		return new BaseObject();
	}

	/**
	 * @brief API: 안티그래비티 에이전트가 뉴스 기사를 수집하여 DB에 Insert할 때 호출
	 */
	public function procAinewsInsertArticle()
	{
		$args = Context::getRequestVars();
		
		// 필수 파라미터 체크
		if(!$args->title || !$args->original_url) return new BaseObject(-1, 'msg_invalid_request');
		
		$args->article_srl = getNextSequence();
		$args->status = 'TEMP';
		
		// DB Insert
		$output = executeQuery('ainews.insertArticle', $args);
		if(!$output->toBool()) return $output;
		
		$this->add('article_srl', $args->article_srl);
		$this->setMessage('success_saved');
	}

	/**
	 * @brief API: 에이전트가 주제별로 뉴스레터를 요약하여 임시 저장(TEMP)할 때 호출
	 */
	public function procAinewsGenerateNewsletter()
	{
		$args = Context::getRequestVars();
		
		if(!$args->title || !$args->content) return new BaseObject(-1, 'msg_invalid_request');
		
		// 1. ainews_newsletters에 저장
		$newsletter_args = new stdClass();
		$newsletter_args->newsletter_srl = getNextSequence();
		$newsletter_args->module_srl = $args->module_srl ?: 0;
		$newsletter_args->title = $args->title;
		$newsletter_args->content = $args->content;
		$newsletter_args->status = 'TEMP';
		
		$output = executeQuery('ainews.insertNewsletter', $newsletter_args);
		if(!$output->toBool()) return $output;
		
		// 2. 라이믹스 게시판(document 모듈)에 임시 저장 게시물 생성
		$oDocumentController = getController('document');
		$doc_args = new stdClass();
		$doc_args->document_srl = getNextSequence();
		$doc_args->module_srl = $args->target_module_srl; // 게시될 게시판 srl
		$doc_args->title = $args->title;
		$doc_args->content = $args->content;
		$doc_args->status = 'TEMP';
		
		$doc_output = $oDocumentController->insertDocument($doc_args);
		
		if($doc_output->toBool())
		{
			// 편집자에게 푸시 또는 이메일 알림
			$this->notifyEditor($doc_args->document_srl);
		}
		
		$this->add('newsletter_srl', $newsletter_args->newsletter_srl);
		$this->setMessage('success_saved');
	}

	/**
	 * @brief 편집자에게 알림 발송
	 */
	private function notifyEditor($document_srl)
	{
		// TODO: Mail 클래스 또는 Communication 모듈을 사용하여 알림 전송 로직 구현
		// "새로운 AI 뉴스레터 초안이 작성되었습니다. 검토 및 발행해주세요."
	}
}
