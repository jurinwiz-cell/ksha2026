-- -------------------------------------------------------------
-- 파일명: insert_members.sql
-- 목적   : sh_members.xml 에 포함된 회원 3건을 sh_member 테이블에 삽입
-- -------------------------------------------------------------
INSERT INTO sh_member (
    member_srl, user_id, email_address, password, email_id, email_host,
    user_name, nick_name, find_account_question, find_account_answer,
    homepage, blog, birthday, allow_mailing, allow_message,
    denied, limit_date, regdate, last_login, change_password_date,
    is_admin, description, extra_vars, list_order
) VALUES
(
    529017, 'ss707sfs', 'ss707sfs@naver.com',
    'sha256:0008192:mmzdFpxBsIfo:AnpPt6H+camdE+l5whhKao9zhh93pvTU',
    'ss707sfs', 'naver.com',
    '신성산', '산이사이커', 4,
    'sha256:0008192:yB0XXdri0Wae:9tpqfOzGjZCaU6wkXElKBwM1TYuRBUcO',
    '', '', 19930827, 'N', 'Y',
    'N', NULL, '20260411011952', '20260411105502', '20260411105528',
    'N', NULL,
    'O:8:"stdClass":4:{s:15:"xe_validator_id";s:20:"modules/member/skins";s:18:"success_return_url";s:38:"/index.php?act=dispMemberInfo&amp;mid=home";s:3:"org";s:6:"없음";s:8:"category";a:1:{i:0;s:6:"개인";}}',
    -529017
),
(
    532520, 'pxvfgedzyh', 'ftynpyhz@immenseignite.info',
    'sha256:0008192:Ej5pJDFU5Rhx:fameamyVC3MxakAuvV61G+rpikeM4EJn',
    'ftynpyhz', 'immenseignite.info',
    'jvwiwqlujz', 'snnhwfljzi', 1,
    'sha256:0008192:O4aigL2AUABs:4/xDpxLvBRssQ6BWps04EENN5rauHqpM',
    'https://flkxes.com/test', 'https://xzhrzm.com/test', 0, 'N', 'N',
    'N', NULL, '20260422093524', '20260422093524', '20260422093524',
    'N', NULL,
    'O:8:"stdClass":5:{s:3:"vid";s:8:"hwterwor";s:15:"xe_validator_id";s:42:"modules/member/skins/default/modify_info/1";s:18:"success_return_url";s:38:"/index.php?act=dispMemberInfo&amp;mid=home";s:3:"org";s:10:"qrrshnjgdt";s:8:"category";a:11:{i:0;s:6:"개인";i:1;s:16:"회원사 대표";i:2;s:19:"회원사 임직원";i:3;s:26:"관련단체 및 연구자";i:4;s:23:"정부 및 공공기관";i:5;s:22:"사회주택 입주자";i:6;s:28:"사회주택 입주희망자";i:7;s:27:"건축 및 업계 종사자";i:8;s:32:"마을 및 도시재생활동가";i:9;s:12:"지역주민";i:10;s:6:"기타";}}',
    -532520
),
(
    532687, 't532687', 'mallogirl@naver.com',
    'sha256:0008192:zBvSmYoZ3Wc2:5UzQO0nHeUYRFhFq90HupdQ7v+ILzZTN',
    'mallogirl', 'naver.com',
    '김은숙', '샥샥', 2,
    'sha256:0008192:SKM97KomYuaw:vkjlSkxqNdAoSpVgPK9/mFRMI49NtafV',
    '', '', 0, 'N', 'Y',
    'N', NULL, '20260423095025', '20260423095025', '20260423095025',
    'N', NULL,
    'O:8:"stdClass":4:{s:15:"xe_validator_id";s:20:"modules/member/skins";s:18:"success_return_url";s:37:"/index.php?mid=qna&amp;act=dispMemberInfo";s:3:"org";s:6:"회사";s:8:"category";a:1:{i:0;s:6:"개인";}}',
    -532687
);
