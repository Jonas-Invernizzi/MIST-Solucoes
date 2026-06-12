<?php
// A sessão deve ser iniciada antes de qualquer outra lógica para garantir persistência
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("vendor/autoload.php");

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

$twig->addGlobal('session', $_SESSION);

// Sistema de Notificações: Conta mensagens não lidas para o usuário logado
$mensagens_nao_lidas = 0;
if (isset($_SESSION['usuario_id'])) {
    if (!isset($pdo)) { require_once __DIR__ . '/carregar_pdo.php'; }
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM mensagens WHERE destinatario_id = :me AND lida = 0");
    $stmtCount->execute(['me' => $_SESSION['usuario_id']]);
    $mensagens_nao_lidas = (int) $stmtCount->fetchColumn();
}
$twig->addGlobal('mensagens_nao_lidas', $mensagens_nao_lidas);
