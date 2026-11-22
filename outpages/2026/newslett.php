<?php
// [에러 확인용: 문제가 생기면 화면에 에러를 보여줌]
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ======================================================
// 1. 여기에 뉴스레터 목록을 직접 입력해주세요. (가장 확실한 방법)
// ======================================================
$manual_list = [
    // [ '제목', '스티비_뉴스레터_주소' ] 순서로 입력
    ['2026년 1월호', 'https://sohot.stibee.com/p/83'],
    ['2025년 5월호', 'https://sohot.stibee.com/p/82'],
    ['2025년 3월호', 'https://sohot.stibee.com/p/81'],
];



// ======================================================
// 2. 오른쪽 화면(뷰어) 기능 (서버 환경에 맞춰 자동 대응)
// ======================================================
if (isset($_GET['view_url'])) {
    $target = $_GET['view_url'];
    
    // 보안 검사: 스티비 주소가 아니면 차단
    if (strpos($target, 'stibee.com') === false) {
        die('허용되지 않는 주소입니다.');
    }

    // 데이터를 가져오는 함수 (cURL -> file_get_contents 순서로 시도)
    $content = '';
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Compatible; ArchiveBot/1.0)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => ['header' => "User-Agent: Mozilla/5.0\r\n"]
        ]);
        $content = file_get_contents($target, false, $context);
    }

    if (!$content) {
        die('내용을 불러올 수 없습니다. (서버의 외부 접속 차단 가능성)');
    }

    // 이미지/스타일 경로 보정 (깨짐 방지)
    $content = str_replace('href="/', 'href="https://stibee.com/', $content);
    $content = str_replace('src="/', 'src="https://stibee.com/', $content);
    
    // 출력하고 종료 (중요: HTML 전체 출력을 막기 위해 exit)
    echo $content;
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>협회 뉴스레터</title>
    <style>
        /* 레이아웃 스타일 */
        body { margin: 0; padding: 0; font-family: 'Pretendard', sans-serif; height: 100vh; display: flex; overflow: hidden; }
        
        .sidebar { 
            width: 300px; 
            background: #f7f7f7; 
            border-right: 1px solid #ddd; 
            display: flex; 
            flex-direction: column; 
        }
        
        .sidebar-header {
            padding: 20px;
            background: #2c3e50;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .list-wrap {
            flex: 1;
            overflow-y: auto;
        }

        .list-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e1e1e1;
            cursor: pointer;
            font-size: 14px;
            background: #fff;
            transition: 0.2s;
        }
        
        .list-item:hover { background: #eef4ff; }
        .list-item.active { 
            background: #e6f0ff; 
            border-left: 5px solid #007bff; 
            font-weight: bold; 
        }

        .viewer {
            flex: 1;
            background: #fff;
            position: relative;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* 모바일 대응 */
        @media (max-width: 768px) {
            body { flex-direction: column; height: auto; }
            .sidebar { width: 100%; height: 300px; }
            .viewer { height: 600px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">뉴스레터 아카이브</div>
    <div class="list-wrap">
        <?php foreach ($manual_list as $index => $item): ?>
            <div class="list-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                 onclick="loadNewsletter('<?php echo $item[1]; ?>', this)">
                <?php echo htmlspecialchars($item[0]); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="viewer">
    <?php $first_url = !empty($manual_list) ? $manual_list[0][1] : ''; ?>
    <iframe id="contentFrame" src="?view_url=<?php echo urlencode($first_url); ?>"></iframe>
</div>

<script>
    function loadNewsletter(url, element) {
        // 활성화 스타일 변경
        document.querySelectorAll('.list-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
        
        // iframe 내용 변경
        var viewer = document.getElementById('contentFrame');
        viewer.src = '?view_url=' + encodeURIComponent(url);
    }
</script>

</body>
</html>