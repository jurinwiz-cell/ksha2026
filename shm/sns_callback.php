<?php
/**
 * sns_callback.php - OAuth 2.0 토큰 발급 및 사용자 정보 조회 후 로그인 처리
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sns_config.php';

$provider = $_GET['provider'] ?? '';
$isDummy = isset($_GET['dummy_test']);

if (!$isDummy && (!isset($sns_config[$provider]))) {
    die('잘못된 접근입니다.');
}

$sns_id = '';
$email = '';
$name = '';

if ($isDummy) {
    // 더미 테스트 모드 (이전과 동일한 로직)
    $sns_id = 'dummy_' . $provider . '_' . rand(1000, 9999);
    $email = 'tester@' . $provider . '.com';
    $name = ucfirst($provider) . ' 사용자';
} else {
    // 실제 OAuth 2.0 토큰 발급 및 정보 조회 로직
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $savedState = $_SESSION['oauth_state'] ?? '';

    if (!$code || $state !== $savedState) {
        die('비정상적인 접근이거나 상태 검증에 실패했습니다.');
    }

    $config = $sns_config[$provider];
    $clientId = $config['client_id'];
    $clientSecret = $config['client_secret'];
    $redirectUri = urlencode($config['redirect_uri']);
    
    $tokenUrl = '';
    $postData = '';

    // 1. 토큰 발급 요청
    if ($provider === 'kakao') {
        $tokenUrl = "https://kauth.kakao.com/oauth/token";
        $postData = "grant_type=authorization_code&client_id={$clientId}&redirect_uri={$redirectUri}&code={$code}";
    } elseif ($provider === 'naver') {
        $tokenUrl = "https://nid.naver.com/oauth2.0/token";
        $postData = "grant_type=authorization_code&client_id={$clientId}&client_secret={$clientSecret}&state={$state}&code={$code}";
    } elseif ($provider === 'google') {
        $tokenUrl = "https://oauth2.googleapis.com/token";
        $postData = http_build_query([
            'client_id' => $clientId, 'client_secret' => $clientSecret,
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => urldecode($redirectUri)
        ]);
    } elseif ($provider === 'facebook') {
        $tokenUrl = "https://graph.facebook.com/v16.0/oauth/access_token";
        $postData = "client_id={$clientId}&redirect_uri={$redirectUri}&client_secret={$clientSecret}&code={$code}";
    }

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($provider !== 'naver') { // 네이버는 GET으로도 처리 가능하나 표준은 POST
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        if($provider === 'kakao' || $provider === 'facebook') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/x-www-form-urlencoded;charset=utf-8']);
        }
    } else {
        curl_setopt($ch, CURLOPT_URL, $tokenUrl . '?' . $postData);
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? '';

    if (!$accessToken) {
        die("액세스 토큰 발급 실패. 에러: " . htmlspecialchars($response));
    }

    // 2. 사용자 정보 조회
    $userInfoUrl = '';
    $headers = ["Authorization: Bearer {$accessToken}"];

    if ($provider === 'kakao') {
        $userInfoUrl = "https://kapi.kakao.com/v2/user/me";
    } elseif ($provider === 'naver') {
        $userInfoUrl = "https://openapi.naver.com/v1/nid/me";
    } elseif ($provider === 'google') {
        $userInfoUrl = "https://www.googleapis.com/oauth2/v2/userinfo";
    } elseif ($provider === 'facebook') {
        $userInfoUrl = "https://graph.facebook.com/me?fields=id,name,email&access_token={$accessToken}";
    }

    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($provider !== 'facebook') {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    $userInfo = json_decode($response, true);

    // 파싱 로직
    if ($provider === 'kakao') {
        $sns_id = $userInfo['id'] ?? '';
        $email = $userInfo['kakao_account']['email'] ?? '';
        $name = $userInfo['kakao_account']['profile']['nickname'] ?? '카카오사용자';
    } elseif ($provider === 'naver') {
        $sns_id = $userInfo['response']['id'] ?? '';
        $email = $userInfo['response']['email'] ?? '';
        $name = $userInfo['response']['name'] ?? '네이버사용자';
    } elseif ($provider === 'google') {
        $sns_id = $userInfo['id'] ?? '';
        $email = $userInfo['email'] ?? '';
        $name = $userInfo['name'] ?? '구글사용자';
    } elseif ($provider === 'facebook') {
        $sns_id = $userInfo['id'] ?? '';
        $email = $userInfo['email'] ?? '';
        $name = $userInfo['name'] ?? '페이스북사용자';
    }

    if (!$sns_id) {
        die("사용자 정보를 가져올 수 없습니다.");
    }
} // end of real auth

// 기존 사용자 확인 및 DB 저장
$stmt = $pdo->prepare("SELECT * FROM tb_users WHERE sns_provider = ? AND sns_id = ?");
$stmt->execute([$provider, $sns_id]);
$user = $stmt->fetch();

if (!$user) {
    // 신규 가입
    $insertStmt = $pdo->prepare("INSERT INTO tb_users (sns_provider, sns_id, email, name, role) VALUES (?, ?, ?, ?, 'company_user')");
    $insertStmt->execute([$provider, $sns_id, $email, $name]);
    $userId = $pdo->lastInsertId();
} else {
    $userId = $user['id'];
}

// 세션 저장
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['user_role'] = 'company_user';

// 사용자의 권한 신청 상태 확인
$reqStmt = $pdo->prepare("SELECT * FROM tb_user_company_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 1");
$reqStmt->execute([$userId]);
$request = $reqStmt->fetch();

if (!$request) {
    header('Location: request_auth.php');
} elseif ($request['status'] === 'pending') {
    header('Location: pending_auth.php');
} elseif ($request['status'] === 'approved') {
    $_SESSION['member_id'] = $request['member_id'];
    header('Location: member_index.php');
} else {
    header('Location: request_auth.php?rejected=1');
}
exit;
