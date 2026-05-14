<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/db.php';
$pdo = getPDO();

$hod_page_ids = [37, 6, 5, 7, 8, 19, 10, 12, 25];

echo "HOD Accessible Pages:\n";
$placeholders = implode(',', array_fill(0, count($hod_page_ids), '?'));
$sql = "SELECT id, page_name, parent_id, page_url FROM sys_pages WHERE id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($hod_page_ids);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pages as $p) {
    $parent_info = "None";
    if ($p['parent_id']) {
        $pst = $pdo->prepare("SELECT page_name FROM sys_pages WHERE id = ?");
        $pst->execute([$p['parent_id']]);
        $parent_info = $pst->fetchColumn() . " (ID: {$p['parent_id']})";
    }
    echo "- ID: {$p['id']}, Name: {$p['page_name']}, URL: {$p['page_url']}, Parent: $parent_info\n";
}
