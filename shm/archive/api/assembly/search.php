<?php
// api/assembly/search.php

require_once '../_authz.php';

$me = requireLogin();

$q = $_GET['q'] ?? '';

// Mock database of Assembly Policy reports for the search interface
$mockMaterials = [
    [
        'source_key' => 'asm_2026_001',
        'mtr_ttl' => '인공지능(AI) 시대의 개인정보 보호 및 법제도적 대응 방안',
        'mtr_div' => '입법조사처 연구보고서',
        'wrt_dt' => '2026-03-15',
        'link_url' => 'https://www.nars.go.kr/report/view.do?reportId=001'
    ],
    // ... other materials
];

$results = [];

if (empty($q)) {
    $results = $mockMaterials;
} else {
    foreach ($mockMaterials as $item) {
        if (mb_strpos($item['mtr_ttl'], $q) !== false || mb_strpos($item['mtr_div'], $q) !== false) {
            $results[] = $item;
        }
    }
}

try {
    $db = db();
    foreach ($results as &$item) {
        $stmt = $db->prepare("SELECT id FROM items WHERE source = 'assembly' AND source_key = ?");
        $stmt->execute([$item['source_key']]);
        $row = $stmt->fetch();
        
        if ($row) {
            $item['id'] = $row['id'];
        } else {
            $insertIdx = $db->prepare("
                INSERT INTO items (source, source_key, mtr_ttl, mtr_div, wrt_dt, link_url, raw_json)
                VALUES ('assembly', ?, ?, ?, ?, ?, ?)
            ");
            $insertIdx->execute([
                $item['source_key'],
                $item['mtr_ttl'],
                $item['mtr_div'],
                $item['wrt_dt'],
                $item['link_url'],
                json_encode($item)
            ]);
            $item['id'] = $db->lastInsertId();
        }
    }
} catch (Exception $e) {
    foreach ($results as $index => &$item) {
        $item['id'] = $index + 1;
    }
}

echo json_encode($results);