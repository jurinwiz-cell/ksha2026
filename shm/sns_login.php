<?php
/**
 * sns_login.php - 각 플랫폼별 OAuth 2.0 인가 코드 요청(Redirect)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/sns_config.php';

$provider = $_GET['provider'] ?? '';

if (!isset($sns_config[$provider])) {
    die('지원하지 않는 로그인 방식입니다.');
}

$config = $sns_config[$provider];
$clientId = $config['client_id'];
$redirectUri = urlencode($config['redirect_uri']);

// CSRF 방지용 상태 토큰 생성
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$authUrl = '';

switch ($provider) {
    case 'kakao':
        $authUrl = "https://kauth.kakao.com/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&state={$state}";
        break;
    case 'naver':
        $authUrl = "https://nid.naver.com/oauth2.0/authorize?client_id={$clientId}&response_type=code&redirect_uri={$redirectUri}&state={$state}";
        break;
    case 'google':
        $scope = urlencode('email profile');
        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&state={$state}&scope={$scope}";
        break;
    case 'facebook':
        $authUrl = "https://www.facebook.com/v16.0/dialog/oauth?client_id={$clientId}&redirect_uri={$redirectUri}&state={$state}&scope=email";
        break;
}

// 개발 모드 지원: 아직 키가 없는 플랫폼 테스트를 위해 API 키가 '여기에_'로 시작하면 기존의 더미 모드로 폴백
if (strpos($clientId, '여기에_') === 0) {
    echo "<script>alert('{$provider} API 키가 설정되지 않아 임시 테스트 모드로 진입합니다.'); location.href='sns_callback.php?provider={$provider}&dummy_test=1';</script>";
    exit;
}

header("Location: $authUrl");
exit;
