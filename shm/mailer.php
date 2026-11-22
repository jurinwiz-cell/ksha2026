<?php
/**
 * includes/mailer.php - SMTP 메일 발송 공통 모듈 (SSL, fsockopen 기반)
 */

define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       465);
define('SMTP_USER',       'green@ksha.us');
define('SMTP_PASS',       '1@tkghlwnxor');
define('SMTP_FROM_NAME',  '한국사회주택협회');
define('SMTP_FROM_EMAIL', 'green@ksha.us');

/**
 * SMTP SSL(465) 방식으로 HTML 이메일을 발송한다.
 *
 * @param string $to       수신자 이메일
 * @param string $toName   수신자 이름
 * @param string $subject  메일 제목
 * @param string $htmlBody HTML 본문
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function sendMail(string $to, string $toName, string $subject, string $htmlBody): array
{
    $socket = @fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$socket) {
        return ['success' => false, 'error' => "SMTP 연결 실패: {$errstr} ({$errno})"];
    }

    // 응답 읽기 헬퍼
    $read = function () use ($socket): string {
        $resp = '';
        while ($line = fgets($socket, 515)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // 멀티라인 종료
        }
        return $resp;
    };

    $write = fn(string $cmd) => fputs($socket, $cmd . "\r\n");

    $read();                         // 220 greeting
    $write('EHLO localhost');
    $read();                         // EHLO multi-line

    // AUTH LOGIN
    $write('AUTH LOGIN');
    $read();                         // 334 username
    $write(base64_encode(SMTP_USER));
    $read();                         // 334 password
    $write(base64_encode(SMTP_PASS));
    $authResp = $read();

    if (strpos($authResp, '235') === false) {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP 인증 실패: {$authResp}"];
    }

    // ENVELOPE
    $write('MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
    $read();

    $write('RCPT TO:<' . $to . '>');
    $rcptResp = $read();
    if (strpos($rcptResp, '250') === false) {
        fclose($socket);
        return ['success' => false, 'error' => "수신자 오류: {$rcptResp}"];
    }

    // DATA
    $write('DATA');
    $read(); // 354

    $eFrom  = '=?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?=';
    $eTo    = '=?UTF-8?B?' . base64_encode($toName) . '?=';
    $eSubj  = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $msg  = "From: {$eFrom} <" . SMTP_FROM_EMAIL . ">\r\n";
    $msg .= "To: {$eTo} <{$to}>\r\n";
    $msg .= "Subject: {$eSubj}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.";

    $write($msg);
    $sendResp = $read();

    $write('QUIT');
    fclose($socket);

    if (strpos($sendResp, '250') !== false) {
        return ['success' => true, 'error' => null];
    }
    return ['success' => false, 'error' => "발송 실패: {$sendResp}"];
}
