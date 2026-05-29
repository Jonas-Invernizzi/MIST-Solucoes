<?php 
session_start(); 
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

// Capturar termo de busca se existir
$search_query = $_GET['q'] ?? '';

// Base da consulta SQL
$sql = "SELECT u.id AS usuario_id, c.nome, c.trabalho, c.foto_perfil, c.descricao 
        FROM usuarios u 
        INNER JOIN contratantes c ON u.id = c.usuario_id 
        WHERE u.tipo_base = 'contratante' AND u.status = 'ativo'";

// Adicionar filtro de busca caso o usuário tenha pesquisado algo
if (!empty($search_query)) {
    $sql .= " AND (c.nome LIKE :search OR c.trabalho LIKE :search OR c.descricao LIKE :search)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => "%$search_query%"]);
} else {
    $stmt = $pdo->query($sql);
}

$profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'termo_buscado'  => $search_query
]);