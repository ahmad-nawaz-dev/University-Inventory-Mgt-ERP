<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/db.php';
$pdo = getPDO();

echo "--- sys_roles ---\n";
print_r($pdo->query("SELECT * FROM sys_roles")->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- sys_pages ---\n";
print_r($pdo->query("SELECT * FROM sys_pages")->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- role_access ---\n";
print_r($pdo->query("SELECT * FROM role_access")->fetchAll(PDO::FETCH_ASSOC));
