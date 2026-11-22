<?php
// api/_db.php
// 설치 경로: /var/www/html/z/shm/archive/api/_db.php

header('Content-Type: application/json; charset=UTF-8');

// 상위 디렉토리 영역의 공통 db.php 연동 모듈을 안전하게 호출합니다.
require_once __DIR__ . '/../../include/db.php';

// db() 함수가 include/db.php 에 정의되어 있으나, 기존 API와의 호환성을 위해 래핑 함수를 유지합니다.
if (!function_exists('db_connection')) {
    function db_connection() {
        return db();
    }
}