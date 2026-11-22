<?php
/*if(!isset($lang) || !is_object($lang))
{
	$lang = new stdClass();
	$response_json = TRUE;
}
else
{
	$response_json = FALSE;
}*/

$lang->elkhatalk3 = 'RXE 채팅 솔루션';
$lang->elkhatalk3_chat = '채팅';
$lang->elkhatalk3_invalid_request = '잘못된 요청입니다.';
$lang->elkhatalk3_talk = '대화하기';
$lang->elkhatalk3_doLogin = '로그인 후 쪽지를 확인할 수 있습니다.';
$lang->elkhatalk3_member_not_exists = '대화 상대가 없습니다.';
$lang->elkhatalk3_start_message = '채팅을 환영합니다.<br />대화를 시작해주세요.';
$lang->elkhatalk3_my_message = '내 대화함';
$lang->elkhatalk3_message_box = '대화하기';
$lang->elkhatalk3_shift_enter = '줄바꿈: Shift+Enter';
$lang->elkhatalk3_too_long_message = '내용이 너무 깁니다. (250자 이내)';
$lang->elkhatalk3_too_many_message = "짧은 시간 내에 반복적으로 대화할 수 없습니다.\n몇 초 후 채팅을 해보시기 바랍니다.";
$lang->elkhatalk3_is_bot_user_id = '대화할 수 없는 계정입니다.';
$lang->elkhatalk3_components = '컴포넌트';
$lang->elkhatalk3_call = '호출';
$lang->elkhatalk3_q = '귓';
$lang->elkhatalk3_ignore = '무시';
$lang->elkhatalk3_new_message = '새로운 메세지가 있습니다.';
$lang->elkhatalk3_leave_member = '탈퇴한 회원';
$lang->elkhatalk3_reconnected = '접속 완료';
$lang->elkhatalk3_disconnected = '접속 끊김';
$lang->elkhatalk3_clean = '채팅방 청소';
$lang->elkhatalk3_call_me = '님을 호출합니다.';
$lang->elkhatalk3_call_you = '님이 호출합니다.';
$lang->elkhatalk3_call_failed = '실패: 채팅방에 상대방이 없습니다.';
$lang->elkhatalk3_blind = "시간 동안 채팅이 무시됩니다.\n0 시간 입력하면 취소됩니다.";
$lang->elkhatalk3_blind_cancel = "블라인드가 취소 되었습니다.";
$lang->elkhatalk3_can_not_self_blind = '실패: 자기 자신을 블라인드 할 수 없습니다.';
$lang->elkhatalk3_show_help = '도움말';
$lang->elkhatalk3_open_popup = '팝업 열기';
$lang->elkhatalk3_help = "/귓 닉네임 할말\n/무시 닉네임 시간\n/호출 닉네임\n오른쪽 위 * 누르면 팝업이 가능합니다.";
$lang->elkhatalk3_blind2 = '블라인드';
$lang->elkhatalk3_blind2_failed = '블라인드 할 수 없습니다.';
$lang->elkhatalk3_sleep = '잠수';
$lang->elkhatalk3_send_to = '귓 보냄';
$lang->elkhatalk3_send_from = '귓 받음';
$lang->elkhatalk3_notice_update = '공지사항이 등록 되었습니다.';
$lang->elkhatalk3_notice_delete = '공지사항이 삭제 되었습니다.';
$lang->elkhatalk3_chat_room = '채팅방';
$lang->elkhatalk3_delete = '삭제';
$lang->elkhatalk3_deleted_message = '삭제된 메세지';
$lang->elkhatalk3_require_login = '로그인 바랍니다.';
$lang->elkhatalk3_close = '닫기';
$lang->elkhatalk3_hide = '숨김';
$lang->elkhatalk3_ban_message = '채팅이 %s 까지 제한됩니다: ';
$lang->elkhatalk3_ban_message_cancel = '채팅 제한이 해제 됩니다: ';
$lang->elkhatalk3_confirm_delete = \Context::getLang('confirm_delete');
$lang->elkhatalk3_invalid_logged_info = '로그인 정보가 올바르지 않습니다.';
$lang->elkhatalk3_hide_room = '채팅을 숨길까요?';
$lang->elkhatalk3_update = "서비스 업데이트가 진행 중입니다.\n잠시 후 다시 요청 해주세요.";
$lang->elkhatalk3_update_admin = "(개발자 메세지) 업데이트가 진행되는 동안 다음 파일이 유지됩니다:";
$lang->elkhatalk3_notify_new = '새로운 알림이 있습니다.';
$lang->elkhatalk3_notify_activated = '알림이 켜졌습니다.';
$lang->elkhatalk3_notify_deactivated = '알림이 꺼졌습니다.';
$lang->elkhatalk3_notify_off = '알림: 꺼짐';
$lang->elkhatalk3_notify_on = '알림: 켜짐';
$lang->elkhatalk3_notify_not_supported = '지원되지 않는 브라우저 입니다.';
$lang->elkhatalk3_notify_confirm = '브라우저 알림을 허용 하겠습니까?';
$lang->elkhatalk3_denied_word = '사용할 수 없는 단어입니다.';
$lang->elkhatalk3_confirm_delete = '대화를 지울까요?';
$lang->elkhatalk3_error_delete = '%s 후 삭제할 수 있습니다.';
$lang->elkhatalk3_prev_talk = '이전 대화 보기';
$lang->elkhatalk3_last_talk = '최신 대화 보기';
$lang->elkhatalk3_dark_mode = '다크 모드';

$lang->elkhatalk3_before_now = '방금 전';
$lang->elkhatalk3_before_minutes = '분 전';
$lang->elkhatalk3_before_hours = '시간 전';
$lang->elkhatalk3_before_days = '일 전';
$lang->elkhatalk3_before_years = '년 전';
$lang->elkhatalk3_date = '$1년 $2월 $3일';

// 권한 설정 관련 메세지
$lang->elkhatalk3_permit_changed = '권한이 설정 되었습니다: ';
$lang->elkhatalk3_notify_bot = '알리미';
$lang->elkhatalk3_grant_C = ' 승인된 회원만 채팅 가능';
$lang->elkhatalk3_grant_F = ' 채팅방이 얼었습니다. %s 만료';
$lang->elkhatalk3_grant_R = ' 손님 읽기 불가';
$lang->elkhatalk3_grant_W = ' 손님 채팅 불가';
$lang->elkhatalk3_grant_C2 = ' 로그인 후 채팅 가능';
$lang->elkhatalk3_grant_F2 = ' 얼었던 채팅방이 녹았습니다.';
$lang->elkhatalk3_grant_R2 = ' 손님 읽기 가능';
$lang->elkhatalk3_grant_W2 = ' 손님 채팅 가능';
$lang->elkhatalk3_grant_G = ' 지정 그룹만 채팅 가능';
$lang->elkhatalk3_chat_log = ' 채팅 기록 보관';
$lang->elkhatalk3_ban = '채팅 제한';
$lang->elkhatalk3_expire = '만료';
$lang->elkhatalk3_freeze = '채팅방 얼림';
$lang->elkhatalk3_manager = '부관리자';
