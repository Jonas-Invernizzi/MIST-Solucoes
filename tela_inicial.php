<?php 
session_start(); 
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

// Base da consulta SQL
// A lógica de busca foi movida para 'pesquisa.php' para centralizar a funcionalidade.
// A tela inicial agora apenas lista todos os profissionais.
$sql = "SELECT p.usuario_id, p.id, p.nome, p.trabalho, p.foto_perfil, p.descricao,
               COALESCE(AVG(av.nota), 0) as nota_media,
               COUNT(av.id) as total_avaliacoes
        FROM profissionais p
        JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN avaliacoes av ON p.id = av.profissional_id
        WHERE u.status = 'ativo' AND u.tipo_base = 'profissional'
        GROUP BY p.id, p.usuario_id, p.nome, p.trabalho, p.foto_perfil, p.descricao
        ORDER BY p.nome ASC";

$stmt = $pdo->query($sql);

$profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adicionar o nome do usuário logado para a saudação
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Visitante';

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'nome_usuario' => $nome_usuario
]);