<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/db.php';
$pdo = getPDO();

$roles = ['super_admin', 'hod', 'coordinator', 'store_officer', 'faculty'];

foreach ($roles as $role) {
    echo "Top-level pages for role: $role\n";
    $sql = "SELECT sp.id, sp.page_name, sp.page_url
            FROM sys_pages sp
            JOIN role_access ra ON sp.id = ra.page_id
            WHERE ra.role_key = ? AND sp.parent_id IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$role]);
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($pages)) {
        echo "  [NONE]\n";
    } else {
        foreach ($pages as $p) {
            echo "  - ID: {$p['id']}, Name: {$p['page_name']}, URL: {$p['page_url']}\n";
        }
    }
    echo "\n";
}
