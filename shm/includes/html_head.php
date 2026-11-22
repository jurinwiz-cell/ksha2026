<?php
/**
 * includes/html_head.php - 공통 <head> 블록
 * $pageTitle 변수로 제목을 변경할 수 있습니다.
 */
$pageTitle = ($pageTitle ?? '관리자') . ' | 한국사회주택협회';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="/static/vendor/js/phosphor-icons.js"></script>
    <script src="/static/vendor/js/chart.min.js"></script>
    <script src="/static/vendor/js/xlsx.full.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Noto Sans KR"', 'sans-serif'] },
                    colors: {
                        primary: '#1e3a8a',
                        secondary: '#3b82f6',
                        accent: '#f59e0b'
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
    </style>
</head>

