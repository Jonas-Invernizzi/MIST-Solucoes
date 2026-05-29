<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$query_term = trim($_GET['q'] ?? '');
$location_term = trim($_GET['l'] ?? '');
$profissionais = [];

try {
    $sql = "
        SELECT 
            c.usuario_id,
            c.nome, 
            c.trabalho, 
            c.foto_perfil,
            c.endereco,
            COALESCE(AVG(a.nota), 0) as nota_media,
            COUNT(a.id) as total_avaliacoes
        FROM profissionais c 
        LEFT JOIN avaliacoes a ON c.usuario_id = a.profissional_id
    ";

    $conditions = [];
    $params = [];

    if (!empty($query_term)) {
        $conditions[] = "(c.nome LIKE :q OR c.trabalho LIKE :q OR c.descricao LIKE :q)";
        $params[':q'] = '%' . $query_term . '%';
    }

    if (!empty($location_term)) {
        $conditions[] = "(c.endereco LIKE :l OR c.endereco_trabalho LIKE :l)";
        $params[':l'] = '%' . $location_term . '%';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    // Agrupamento completo para evitar erro de 'ONLY_FULL_GROUP_BY'
    $sql .= " GROUP BY c.usuario_id, c.nome, c.trabalho, c.foto_perfil, c.endereco ORDER BY nota_media DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profissionais = [];
}

echo $twig->render('pesquisa.html', [
    'profissionais' => $profissionais,
    'termo_buscado' => $query_term,
    'local_buscado' => $location_term,
    'session' => $_SESSION
]);