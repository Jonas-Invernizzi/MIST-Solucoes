<?php 
session_start(); 
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

try {
    // Busca os 4 profissionais com melhor média de avaliação
    $query = "
        SELECT 
            c.nome, 
            c.trabalho, 
            c.foto_perfil,
            COALESCE(AVG(a.nota), 0) as nota_media,
            COUNT(a.id) as total_avaliacoes
        FROM contratantes c
        LEFT JOIN avaliacoes a ON c.usuario_id = a.profissional_id
        GROUP BY c.usuario_id, c.nome, c.trabalho, c.foto_perfil
        ORDER BY nota_media DESC, total_avaliacoes DESC
        LIMIT 4
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Caso a tabela ainda não exista ou haja erro, retornamos lista vazia para não quebrar a tela
    $profissionais = [];
}

echo $twig->render('tela_inicial.html', ['profissionais' => $profissionais]);