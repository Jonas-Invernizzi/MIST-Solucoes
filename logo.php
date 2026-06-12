<?php
require_once(__DIR__ . '/carregar_pdo.php');

try {
    $stmt = $pdo->prepare("SELECT arquivo, mime_type FROM sistema_assets WHERE nome = 'logo' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['arquivo'])) {
        header('Content-Type: ' . $row['mime_type']);
        header('Cache-Control: public, max-age=86400');
        echo $row['arquivo'];
        exit();
    }

    $fallbackPath = __DIR__ . '/img/logo.jpg';
    if (file_exists($fallbackPath)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($fallbackPath);
        exit();
    }
} catch (Exception $e) {
    // fallback para SVG inline
}

header('Content-Type: image/svg+xml;charset=UTF-8');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="50" viewBox="0 0 160 50"><rect width="160" height="50" fill="#1f3e65"/><text x="50%" y="55%" fill="#ffffff" font-family="Arial, sans-serif" font-size="14" text-anchor="middle" alignment-baseline="middle">MIST Soluções</text></svg>';
