<?php
require __DIR__ . '/connection/pdo.php';
$stmt = $db->query('SELECT * FROM role');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $db->query('SELECT * FROM wallet LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
