<?php

require_once '../_authz.php';

$me = requireLogin();

$workspace_id = $_GET['workspace_id'] ?? '';

try {
    if (!empty($workspace_id)) {
        $stmt = db()->prepare("
            SELECT 
                l.id,
                l.workspace_id,
                l.item_id,
                i.mtr_ttl,
                i.mtr_div,
                i.wrt_dt,
                i.link_url,
                l.note,
                l.is_favorite
            FROM library_entries l
            JOIN items i ON l.item_id = i.id
            WHERE l.workspace_id = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$workspace_id]);
    } else {
        $stmt = db()->prepare("
            SELECT 
                l.id,
                l.workspace_id,
                l.item_id,
                i.mtr_ttl,
                i.mtr_div,
                i.wrt_dt,
                i.link_url,
                l.note,
                l.is_favorite
            FROM library_entries l
            JOIN items i ON l.item_id = i.id
            ORDER BY l.created_at DESC
        ");
        $stmt->execute();
    }
    
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database failure: ' . $e->getMessage()]);
}