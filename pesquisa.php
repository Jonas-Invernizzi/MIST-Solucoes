<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$query_term = trim($_GET['q'] ?? '');
$profissionais = [];

try {
    $sql = "
        SELECT 
            c.nome, 
            c.trabalho, 
            c.foto_perfil,
            COALESCE(AVG(a.nota), 0) as nota_media,
            COUNT(a.id) as total_avaliacoes
        FROM contratantes c 
        LEFT JOIN avaliacoes a ON c.usuario_id = a.profissional_id
    ";

    if (!empty($query_term)) {
        $sql .= " WHERE c.nome LIKE :q OR c.trabalho LIKE :q OR c.descricao LIKE :q";
        $stmt = $pdo->prepare($sql . " GROUP BY c.usuario_id ORDER BY nota_media DESC");
        $stmt->execute([':q' => '%' . $query_term . '%']);
    } else {
        $stmt = $pdo->prepare($sql . " GROUP BY c.usuario_id ORDER BY nota_media DESC");
        $stmt->execute();
    }

    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profissionais = [];
}

echo $twig->render('pesquisa.html', [
    'profissionais' => $profissionais,
    'termo_buscado' => $query_term
]);