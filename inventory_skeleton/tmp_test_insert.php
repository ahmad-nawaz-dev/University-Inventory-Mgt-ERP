<?php
require 'core/db.php';
require 'core/config.php';
$pdo = getPDO();
$stmt = $pdo->prepare("INSERT INTO assets (asset_tag, name, category_id, department, status, purchase_date) VALUES ('TST-001', 'HDMI Cable', 1, '', 'in_stock', '2026-04-07')");
$stmt->execute();
$last_id = $pdo->lastInsertId();
$stmt = $pdo->query("SELECT id, name, department FROM assets WHERE id = " . $last_id);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
