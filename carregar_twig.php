<?php
// A sessão deve ser iniciada antes de qualquer outra lógica para garantir persistência
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("vendor/autoload.php");
// Carrega PDO para permitir acesso a assets do sistema (logo, avatar etc.)
require_once(__DIR__ . '/carregar_pdo.php');

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

$twig->addGlobal('session', $_SESSION);
// Tenta carregar o logo do banco e registrá-lo como global do Twig
try {
    $stmt = $pdo->prepare("SELECT arquivo, mime_type FROM sistema_assets WHERE nome = 'logo' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['arquivo']) {
        $logo_site = 'data:' . $row['mime_type'] . ';base64,' . base64_encode($row['arquivo']);
    } else {
        // Fallback inline simples caso o logo do banco não esteja disponível.
        $logo_site = 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"><rect width="120" height="40" fill="%231f3e65"/><text x="50%" y="55%" fill="%23ffffff" font-family="Arial,sans-serif" font-size="14" text-anchor="middle" alignment-baseline="middle">MIST Soluções</text></svg>';
    }
    $twig->addGlobal('logo_site', $logo_site);
} catch (Exception $e) {
    $twig->addGlobal('logo_site', 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"><rect width="120" height="40" fill="%231f3e65"/><text x="50%" y="55%" fill="%23ffffff" font-family="Arial,sans-serif" font-size="14" text-anchor="middle" alignment-baseline="middle">MIST Soluções</text></svg>');
}
