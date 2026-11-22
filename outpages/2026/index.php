<?php
/**
 * 한국사회주택협회 소식지 아카이브 - 상하 레이아웃 및 연도 필터링 버전
 */

// 1. API 설정
$api_url = "https://page.stibee.com/api/v1.0/lists/63562/contents";
$stibee_domain = "https://sohot.stibee.com/p/";

// 2. [프록시] 본문 불러오기 (기존 로직 유지)
if (isset($_GET['detail_url'])) {
    $target = $_GET['detail_url'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $content = curl_exec($ch);
    curl_close($ch);
    $content = str_replace(['href="/', 'src="/'], ['href="https://stibee.com/', 'src="https://stibee.com/'], $content);
    echo $content;
    exit;
}

// 3. 데이터 가져오기 및 전체 연도 추출
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$all_items = json_decode($response, true);
if (!is_array($all_items)) $all_items = [];

// 연도 목록 추출 (중복 제거 및 내림차순)
$years = [];
foreach ($all_items as $item) {
    $year = date("Y", strtotime($item['publishedAt']));
    if (!in_array($year, $years)) $years[] = $year;
}
rsort($years);

// 4. 필터링 및 페이지네이션
$selected_year = isset($_GET['year']) ? $_GET['year'] : $years[0];
$filtered_items = array_filter($all_items, function($item) use ($selected_year) {
    return date("Y", strtotime($item['publishedAt'])) == $selected_year;
});
$filtered_items = array_values($filtered_items); // 인덱스 재정렬

$items_per_page = 12;
$total_items = count($filtered_items);
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$display_list = array_slice($filtered_items, $offset, $items_per_page);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <style>
        /* 공간 효율성을 극대화한 상하 배치 레이아웃 */
        .archive-container { display: flex; flex-direction: column; height: 900px; border: 1px solid #ddd; background: #fff; font-family: 'Pretendard', sans-serif; }
        
        /* 상단 컨트롤러 (연도 선택 + 페이지네이션) */
        .archive-top-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
        .filter-group { display: flex; align-items: center; gap: 10px; }
        .year-select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; font-weight: bold; cursor: pointer; }

        /* 상단 목록 영역 (가로 배치) */
        .archive-list-area { padding: 15px; background: #fff; border-bottom: 1px solid #ddd; overflow-x: auto; }
        .list-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; min-width: 800px; }
        .list-item { 
            padding: 8px 12px; border: 1px solid #eee; border-radius: 4px; cursor: pointer; 
            font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
            transition: all 0.2s; background: #fff;
        }
        .list-item:hover { background: #f0f7ff; border-color: #004da0; }
        .list-item.active { background: #004da0; color: #fff; border-color: #004da0; }
        .item-date { font-size: 11px; opacity: 0.7; margin-right: 5px; }

        /* 하단 뷰어 영역 */
        .archive-viewer { flex: 1; background: #eee; }
        .archive-iframe { width: 100%; height: 100%; border: none; background: #fff; }

        /* 페이지네이션 (심플형) */
        .simple-paging { display: flex; gap: 5px; }
        .page-btn { padding: 4px 8px; border: 1px solid #ddd; background: #fff; font-size: 12px; cursor: pointer; text-decoration: none; color: #333; border-radius: 3px; }
        .page-btn.active { background: #333; color: #fff; border-color: #333; }
    </style>
</head>
<body>

<div class="archive-container">
    <div class="archive-top-bar">
        <div class="filter-group">
            <span style="font-size:13px; font-weight:bold; color:#004da0;">발행연도</span>
            <select class="year-select" onchange="navigateArchive(this.value, 1)">
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>><?php echo $y; ?>년</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="simple-paging">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a class="page-btn <?php echo ($i==$current_page)?'active':''; ?>" 
                   onclick="navigateArchive('<?php echo $selected_year; ?>', <?php echo $i; ?>)"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <div class="archive-list-area">
        <div class="list-grid">
            <?php foreach ($display_list as $index => $item): 
                $stibee_url = $stibee_domain . $item['id'];
                $short_date = date("m.d", strtotime($item['publishedAt']));
            ?>
                <div class="list-item <?php echo ($index === 0) ? 'active' : ''; ?>" 
                     onclick="loadDetail('<?php echo $stibee_url; ?>', this)">
                    <span class="item-date">[<?php echo $short_date; ?>]</span>
                    <?php echo htmlspecialchars($item['title']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="archive-viewer">
        <?php $initial_url = count($display_list) > 0 ? $stibee_domain . $display_list[0]['id'] : ""; ?>
        <iframe id="viewer_frame" class="archive-iframe" src="?detail_url=<?php echo urlencode($initial_url); ?>"></iframe>
    </div>
</div>

<script>
    /**
     * 경로 이탈 방지형 페이지 이동 함수
     * window.location.search만 조작하여 현재 가상 경로(Slug)를 유지함
     */
    function navigateArchive(year, page) {
        const url = new URL(window.location.href);
        url.searchParams.set('year', year);
        url.searchParams.set('page', page);
        url.searchParams.delete('detail_url'); // 이동 시 상세 파라미터는 제거
        
        // 현재 브라우저 주소창의 경로(pathname)는 유지하고 파라미터만 바꿈
        window.location.href = url.pathname + url.search;
    }

    /**
     * 상세 내용 로드 (iframe)
     */
    function loadDetail(url, el) {
        document.querySelectorAll('.list-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        
        const frame = document.getElementById('viewer_frame');
        // 상대 경로 방식을 사용하여 프록시 로직 호출
        frame.src = "?detail_url=" + encodeURIComponent(url);
    }
</script>

</body>
</html>