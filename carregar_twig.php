<?php
// A sessão deve ser iniciada antes de qualquer outra lógica para garantir persistência
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("vendor/autoload.php");

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

$twig->addGlobal('session', $_SESSION);
