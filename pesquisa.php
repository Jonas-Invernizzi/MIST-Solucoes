<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$query_term = trim($_GET['q'] ?? '');
$sort_option = $_GET['sort'] ?? 'relevance'; // Padrão para relevância/nenhuma ordenação
$profissionais = [];

// Carregar Assets do Sistema (Logo e Avatar Padrão)
$stmtAssets = $pdo->prepare("SELECT nome, arquivo, mime_type FROM sistema_assets WHERE nome IN ('logo', 'default_avatar')");
$stmtAssets->execute();
$assets = $stmtAssets->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

$logo_site = isset($assets['logo']) 
    ? 'data:' . $assets['logo']['mime_type'] . ';base64,' . base64_encode($assets['logo']['arquivo']) 
    : '';

$default_avatar = isset($assets['default_avatar'])
    ? 'data:' . $assets['default_avatar']['mime_type'] . ';base64,' . base64_encode($assets['default_avatar']['arquivo'])
    : 'img/FotoPerfilPadrao.jpg';

try {
    $sql = "
        SELECT 
            p.usuario_id,
            p.nome, 
            p.trabalho, 
            p.descricao,
            p.foto_perfil,
            p.endereco,
            0 as nota_media,
            0 as total_avaliacoes
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

    // Mapeia as opções de ordenação para evitar injeção de SQL.
    $sort_map = [
        'relevance' => 'nota_media DESC, total_avaliacoes DESC, p.nome ASC', // Padrão: maior nota e mais avaliações primeiro
        'alpha-asc' => 'p.nome ASC',
        'alpha-desc' => 'p.nome DESC',
        'date-desc' => 'p.id DESC',        // Mais recentes (usando ID como fallback)
        'date-asc' => 'p.id ASC'           // Mais antigos
    ];

    // Define ordenação (sempre tem uma, começando com 'relevance')
    $order_by = $sort_map[$sort_option] ?? $sort_map['relevance'];
    $sql .= " ORDER BY " . $order_by;

    // Prepara e executa a consulta
    $stmt = $pdo->prepare($sql);

    if (!empty($query_term)) {
        $stmt->execute(['search' => "%$query_term%"]);
    } else {
        // Se não houver termo de busca, apenas executa a consulta.
        $stmt->execute();
    }

    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Converter fotos BLOB para Base64
    foreach ($profissionais as &$prof) {
        if ($prof['foto_perfil']) {
            $prof['foto_perfil'] = 'data:image/jpeg;base64,' . base64_encode($prof['foto_perfil']);
        } else {
            $prof['foto_perfil'] = $default_avatar;
        }
    }
} catch (PDOException $e) {
    // Em caso de erro no banco, a lista de profissionais ficará vazia.
    // Para depuração, o erro pode ser logado: error_log($e->getMessage());
    $profissionais = [];
}

echo $twig->render('pesquisa.html', [
    'profissionais' => $profissionais,
    'termo_buscado' => $query_term,
    'sort_option' => $sort_option,
    'logo_site' => $logo_site
]);