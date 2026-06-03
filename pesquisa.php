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

try {
    $sql = "
        SELECT 
            p.usuario_id,
            p.nome, 
            p.trabalho, 
            p.descricao,
            p.foto_perfil,
            p.endereco,
            COALESCE(AVG(a.nota), 0) as nota_media,
            COUNT(a.id) as total_avaliacoes
        FROM profissionais p
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN avaliacoes a ON p.usuario_id = a.profissional_id
    ";

    $conditions = [];
    $params = [];

    if (!empty($query_term)) {
        $sql .= " WHERE (
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

    // Adiciona agrupamento necessário para as funções de média e contagem
    $sql .= " GROUP BY p.id, u.id ";

    // Mapeia as opções de ordenação para evitar injeção de SQL.
    $sort_map = [
        'relevance' => 'p.nome ASC',       // Padrão: ordem alfabética
        'alpha-asc' => 'p.nome ASC',
        'alpha-desc' => 'p.nome DESC',
        'date-desc' => 'u.data_criacao DESC', // Mais recentes
        'date-asc' => 'u.data_criacao ASC'   // Mais antigos
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $profissionais = [];
    foreach ($rows as $p) {
        // Transforma a string de especialidades em array para exibição
        $p['tags'] = !empty($p['trabalho']) 
            ? array_filter(array_map('trim', explode(',', $p['trabalho']))) 
            : [];
        $profissionais[] = $p;
    }
} catch (PDOException $e) {
    // Em caso de erro no banco, a lista de profissionais ficará vazia.
    // Para depuração, o erro pode ser logado: error_log($e->getMessage());
    $profissionais = [];
}

echo $twig->render('pesquisa.html', [
    'profissionais' => $profissionais,
    'termo_buscado' => $query_term,
    'sort_option' => $sort_option
]);