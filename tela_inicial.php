<?php
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$fotoPerfilPadrao = 'FotoPerfilPadrao.jpg';

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
$query = "
    SELECT 
        p.usuario_id,
        p.nome, 
        p.trabalho, 
        p.foto_perfil,
        COALESCE(AVG(a.nota), 0) as nota_media,
        COUNT(a.id) as total_avaliacoes
    FROM profissionais p
    LEFT JOIN avaliacoes a ON p.usuario_id = a.profissional_id
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 4
";

$stmt = $pdo->query($query);
$profissionais = $stmt->fetchAll();

// Garante que todos os profissionais em destaque tenham uma foto de perfil (mesmo que seja a padrão)
foreach ($profissionais as &$p) {
    if (empty($p['foto_perfil'])) {
        $p['foto_perfil'] = $fotoPerfilPadrao;
    }
}
unset($p); // Boa prática: remover a referência após o loop

// Adicionar o nome do usuário logado para a saudação
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Visitante';

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'nome_usuario' => $nome_usuario,
    'foto_perfil_padrao' => $fotoPerfilPadrao
]);