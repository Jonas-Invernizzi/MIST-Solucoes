<?php 
session_start(); 
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$profissionais = [];
$nome_usuario = 'Usuário';

try {
    // Busca o nome do usuário logado (seja cliente ou contratante)
    $stmtUser = $pdo->prepare("
        SELECT COALESCE(c.nome, co.nome) as nome_real 
        FROM usuarios u
        LEFT JOIN clientes c ON u.id = c.usuario_id
        LEFT JOIN profissionais co ON u.id = co.usuario_id
        WHERE u.id = :id
    ");
    $stmtUser->execute(['id' => $_SESSION['usuario_id']]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($userRow && !empty($userRow['nome_real'])) {
        $nome_usuario = $userRow['nome_real'];
    }

    // Busca os 4 profissionais com melhor média de avaliação
    $query = "
        SELECT 
            c.nome, 
            c.trabalho, 
            c.foto_perfil,
            COALESCE(AVG(a.nota), 0) as nota_media,
            COUNT(a.id) as total_avaliacoes
        FROM profissionais c 
        LEFT JOIN avaliacoes a ON c.usuario_id = a.profissional_id
        GROUP BY c.usuario_id, c.nome, c.trabalho, c.foto_perfil 
        ORDER BY nota_media DESC, total_avaliacoes DESC 
        LIMIT 4
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profissionais = [];
}

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'nome_usuario' => $nome_usuario
]);