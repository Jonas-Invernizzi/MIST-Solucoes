<?php

session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$fotoPerfilPadrao = 'img/fotoPadrao.png';

// Lógica de "Auto-Cura": Se o nome ou a foto sumiram da sessão, recupera-os do banco.
if (empty($_SESSION['usuario_nome']) || empty($_SESSION['usuario_foto'])) {
    $stmtHeal = $pdo->prepare("
        SELECT 
            COALESCE(c.nome, p.nome) as nome,
            COALESCE(c.foto_perfil, p.foto_perfil) as foto_perfil
        FROM usuarios u
        LEFT JOIN clientes c ON u.id = c.usuario_id
        LEFT JOIN profissionais p ON u.id = p.usuario_id
        WHERE u.id = :id
    ");
    $stmtHeal->execute([':id' => $_SESSION['usuario_id']]);
    $userData = $stmtHeal->fetch();

    if ($userData) {
        if (!empty($userData['nome'])) {
            $_SESSION['usuario_nome'] = $userData['nome'];
        }
        
        if (!empty($userData['foto_perfil'])) {
            // Verifica se é um nome de arquivo (legado) ou BLOB binário
            if (strlen($userData['foto_perfil']) < 255 && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $userData['foto_perfil'])) {
                $_SESSION['usuario_foto'] = $userData['foto_perfil'];
            } else {
                $_SESSION['usuario_foto'] = 'data:image/jpeg;base64,' . base64_encode($userData['foto_perfil']);
            }
        } else {
            $_SESSION['usuario_foto'] = $fotoPerfilPadrao;
        }
    }
}

if (isset($userData) && $userData) {
    $_SESSION['usuario_foto'] = !empty($userData['foto_perfil'])
        ? 'imagem.php?tipo=perfil&id=' . $_SESSION['usuario_id']
        : $fotoPerfilPadrao;
}

// Busca os 4 profissionais mais recentes para a vitrine
$query = "
    SELECT 
        u.id as usuario_id,
        p.nome, 
        p.trabalho, 
        p.foto_perfil,
        COALESCE(AVG(a.nota), 0) as nota_media,
        COUNT(a.id) as total_avaliacoes
    FROM profissionais p
    INNER JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN avaliacoes a ON p.id = a.profissional_id
    WHERE u.status = 'ativo'
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 4
";

$stmt = $pdo->query($query);
$profissionais = $stmt->fetchAll();

// Garante que todos os profissionais em destaque tenham uma foto de perfil (mesmo que seja a padrão)
foreach ($profissionais as &$p) {
    if (!empty($p['foto_perfil'])) {
        // Verifica se é um nome de arquivo (legado) ou BLOB binário para evitar corrupção
        if (strlen($p['foto_perfil']) < 255 && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $p['foto_perfil'])) {
            $p['foto_perfil'] = $p['foto_perfil'];
        } else {
            // Converte o BLOB do banco para Base64 para exibição correta
            $p['foto_perfil'] = 'data:image/jpeg;base64,' . base64_encode($p['foto_perfil']);
        }
    } else {
        $p['foto_perfil'] = $fotoPerfilPadrao;
    }
    $p['foto_perfil'] = !empty($p['foto_perfil']) && $p['foto_perfil'] !== $fotoPerfilPadrao
        ? 'imagem.php?tipo=perfil&id=' . $p['usuario_id']
        : $fotoPerfilPadrao;
}
unset($p); // Boa prática: remover a referência após o loop

// Adicionar o nome do usuário logado para a saudação
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Visitante';

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'nome_usuario' => $nome_usuario,
    'foto_perfil_padrao' => $fotoPerfilPadrao
]);
