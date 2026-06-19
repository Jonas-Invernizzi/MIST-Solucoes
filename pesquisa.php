<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$query_term = trim($_GET['q'] ?? '');
$sort_option = $_GET['sort'] ?? 'relevance'; 
$profissionais = [];

$stmtAssets = $pdo->prepare("SELECT nome, arquivo, mime_type FROM sistema_assets WHERE nome IN ('logo', 'default_avatar')");
$stmtAssets->execute();
$assets = $stmtAssets->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

$logo_site = isset($assets['logo']) 
    ? 'imagem.php?tipo=asset&nome=logo'
    : '';

$default_avatar = 'img/FotoPerfilPadrao.jpg';

try {
    $sql = "
        SELECT 
            p.usuario_id,
            p.nome, 
            p.trabalho, 
            p.descricao,
            p.foto_perfil,
            p.endereco
        FROM profissionais p
        INNER JOIN usuarios u ON p.usuario_id = u.id
        WHERE u.status = 'ativo'
    ";

    $conditions = [];
    $params = [];

    if (!empty($query_term)) {
        $sql .= " AND (
                    p.nome LIKE :search 
                    OR p.descricao LIKE :search 
                    OR p.trabalho LIKE :search
                    OR EXISTS (
                        SELECT 1
                        FROM profissional_tags pt
                        JOIN tags t ON pt.tag_id = t.id
                        WHERE pt.profissional_id = p.id AND t.nome LIKE :search
                    )
                )";
    }

    $sql .= " GROUP BY p.usuario_id, p.nome, p.trabalho, p.descricao, p.foto_perfil, p.endereco ";

    $sort_map = [
        'relevance' => 'p.nome ASC',
        'alpha-asc' => 'p.nome ASC',
        'alpha-desc' => 'p.nome DESC',
        'date-desc' => 'p.usuario_id DESC',        
        'date-asc' => 'p.usuario_id ASC'          
    ];
    
    $order_by = $sort_map[$sort_option] ?? $sort_map['relevance'];
    $sql .= " ORDER BY " . $order_by;

    $stmt = $pdo->prepare($sql);

    if (!empty($query_term)) {
        $stmt->execute(['search' => "%$query_term%"]);
    } else {
        $stmt->execute();
    }

    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($profissionais as &$prof) {
        if ($prof['foto_perfil']) {
            $prof['foto_perfil'] = 'imagem.php?tipo=perfil&id=' . $prof['usuario_id'];
        } else {
            $prof['foto_perfil'] = $default_avatar;
        }

        $prof['tags'] = !empty($prof['trabalho']) 
            ? array_filter(array_map('trim', explode(',', $prof['trabalho']))) 
            : [];
    }
    unset($prof);
} catch (PDOException $e) {
    $profissionais = [];
}

echo $twig->render('pesquisa.html', [
    'profissionais' => $profissionais,
    'termo_buscado' => $query_term,
    'sort_option' => $sort_option,
    'logo_site' => $logo_site
]);
