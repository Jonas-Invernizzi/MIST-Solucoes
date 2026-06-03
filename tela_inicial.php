<?php
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

// Lógica de "Auto-Cura": Se o nome do usuário sumiu da sessão (comum em trocas de PC ou sessões expiradas),
// tenta recuperá-lo do banco de dados antes de renderizar a página.
if (empty($_SESSION['usuario_nome'])) {
    $stmtHeal = $pdo->prepare("
        SELECT COALESCE(c.nome, p.nome) as nome 
        FROM usuarios u
        LEFT JOIN clientes c ON u.id = c.usuario_id
        LEFT JOIN profissionais p ON u.id = p.usuario_id
        WHERE u.id = :id
    ");
    $stmtHeal->execute([':id' => $_SESSION['usuario_id']]);
    $userData = $stmtHeal->fetch();
    if ($userData && !empty($userData['nome'])) {
        $_SESSION['usuario_nome'] = $userData['nome'];
    }
}

// Busca os 4 profissionais mais recentes para a vitrine
// Nota: O JOIN com a tabela 'avaliacoes' foi removido para evitar o erro Fatal, 
// já que a tabela não existe ou está vazia no banco de dados atual.
$query = "
    SELECT 
        c.usuario_id,
        c.nome, 
        c.trabalho, 
        c.foto_perfil,
        0 as nota_media,
        0 as total_avaliacoes
    FROM profissionais c 
    ORDER BY c.id DESC
    LIMIT 4
";

$stmt = $pdo->query($query);
$profissionais = $stmt->fetchAll();

// Adicionar o nome do usuário logado para a saudação
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Visitante';

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'nome_usuario' => $nome_usuario
]);