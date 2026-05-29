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
        LEFT JOIN clientes c ON u.id = c.usuario_id AND u.tipo_base = 'cliente'
        LEFT JOIN profissionais co ON u.id = co.usuario_id AND u.tipo_base = 'profissional'
        WHERE u.id = :id
    ");
    // Adicionamos a checagem do tipo_base no JOIN para evitar ambiguidade
    $stmtUser->execute(['id' => $_SESSION['usuario_id']]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($userRow && !empty($userRow['nome_real'])) {
        $nome_usuario = $userRow['nome_real'];
    }

    // Busca os profissionais que possuem conta ATIVA
    $query = "
        SELECT 
            c.usuario_id,
            c.nome, 
            c.trabalho, 
            c.foto_perfil,
            COALESCE(AVG(a.nota), 0) as nota_media,
            COUNT(a.id) as total_avaliacoes
        FROM profissionais c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        LEFT JOIN avaliacoes a ON c.usuario_id = a.profissional_id
        WHERE u.status = 'ativo'
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
    'nome_usuario' => $nome_usuario,
    'session' => $_SESSION,
    'termo_buscado' => ''
]);