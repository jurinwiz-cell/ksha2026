<?php
if (!defined('RX_VERSION')) exit;
if ($called_position !== 'before_display_content') return;
if (Context::getResponseMethod() !== 'HTML') return;

// message 모듈의 #access 로그인 폼이 없으면 스킵
if (strpos($output, 'id="access"') === false) return;

// zzan_snslogin 모듈이 로드되어 있는지 확인
if (!class_exists('\Rhymix\Modules\Zzan_snslogin\Models\User')) return;

// 카카오/구글/네이버 on/off 설정은 getLoginButtonsHtml() 내부에서 $config->sns_services 확인
// → 비활성 서비스 제외, 모두 비활성이면 빈 문자열 반환
$buttons_html = \Rhymix\Modules\Zzan_snslogin\Models\User::getLoginButtonsHtml();
if (!$buttons_html) return;

// .login-body 안의 </form> 바로 뒤에 삽입 (1회만)
$output = preg_replace(
	'/(<div[^>]+class="login-body"[^>]*>[\s\S]*?<\/form>)/i',
	'$1<div style="margin-top:12px">' . $buttons_html . '</div>',
	$output,
	1
);
