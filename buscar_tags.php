<?php
require_once('carregar_pdo.php');

$termo = trim($_GET['q'] ?? '');

if (strlen($termo) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT nome FROM tags WHERE nome LIKE :q LIMIT 5");
$stmt->execute([':q' => $termo . '%']);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));