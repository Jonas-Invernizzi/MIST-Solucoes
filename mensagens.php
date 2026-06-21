<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$meu_id = $_SESSION['usuario_id'];
$contato_id = filter_input(INPUT_GET, 'u', FILTER_VALIDATE_INT);
$erro = '';

function carregarImagemMensagem(array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro ao enviar a imagem.');
    }

    if ($arquivo['size'] > 5 * 1024 * 1024) {
        throw new Exception('A imagem deve ter no maximo 5MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mime, $tiposPermitidos, true)) {
        throw new Exception('Envie uma imagem JPG, PNG, GIF ou WebP.');
    }

    return file_get_contents($arquivo['tmp_name']);
}

// --- Acao: Enviar Mensagem ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
    $destinatario = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT);
    $conteudo = trim($_POST['mensagem']);
    $imagem = null;

    try {
        $imagem = carregarImagemMensagem($_FILES['imagem_mensagem'] ?? []);
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }

    if ($destinatario && (trim($conteudo) !== '' || $imagem !== null) && empty($erro)) {
        $stmt = $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, imagem) VALUES (:rem, :dest, :cont, :img)");
        $conteudoParaSalvar = $conteudo !== '' ? $conteudo : null;
        $stmt->bindValue(':rem', $meu_id, PDO::PARAM_INT);
        $stmt->bindValue(':dest', $destinatario, PDO::PARAM_INT);
        $stmt->bindValue(':cont', $conteudoParaSalvar, $conteudoParaSalvar === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':img', $imagem, $imagem === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $stmt->execute();

        // Redireciona para evitar reenvio ao atualizar (F5).
        header("Location: mensagens.php?u=" . $destinatario);
        exit();
    }
}

// --- Busca Lista de Conversas (Inbox) ---
// Pega todos os usuarios com quem troquei mensagens.
$sqlConversas = "
    SELECT u.id,
           COALESCE(c.nome, p.nome) as nome,
           COALESCE(c.foto_perfil, p.foto_perfil) as foto,
           p.trabalho as profissao,
           (SELECT COUNT(*) FROM mensagens m2 WHERE m2.remetente_id = u.id AND m2.destinatario_id = :me AND m2.lida = 0) as unread_count,
           (SELECT MAX(data_envio) FROM mensagens m3
            WHERE (m3.remetente_id = :me AND m3.destinatario_id = u.id)
               OR (m3.remetente_id = u.id AND m3.destinatario_id = :me)) as ultima_data
    FROM usuarios u
    LEFT JOIN clientes c ON u.id = c.usuario_id
    LEFT JOIN profissionais p ON u.id = p.usuario_id
    WHERE u.id IN (
        SELECT remetente_id FROM mensagens WHERE destinatario_id = :me
        UNION
        SELECT destinatario_id FROM mensagens WHERE remetente_id = :me
    ) AND u.id != :me
    ORDER BY ultima_data DESC
";
$stmtConv = $pdo->prepare($sqlConversas);
$stmtConv->execute(['me' => $meu_id]);
$conversas = $stmtConv->fetchAll();
foreach ($conversas as &$conv) {
    $conv['foto'] = !empty($conv['foto'])
        ? 'imagem.php?tipo=perfil&id=' . $conv['id']
        : 'img/fotoPadrao.png';
}
unset($conv);

// --- Busca Mensagens da Conversa Ativa ---
$mensagens = [];
$usuario_destino = null;

if ($contato_id) {
    // Busca dados do destinatario para o cabecalho do chat.
    $stmtDest = $pdo->prepare("
        SELECT u.id, COALESCE(c.nome, p.nome) as nome
        FROM usuarios u
        LEFT JOIN clientes c ON u.id = c.usuario_id
        LEFT JOIN profissionais p ON u.id = p.usuario_id
        WHERE u.id = :id
    ");
    $stmtDest->execute(['id' => $contato_id]);
    $usuario_destino = $stmtDest->fetch();

    if ($usuario_destino) {
        // Marca como lidas as mensagens recebidas deste contato.
        $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE remetente_id = :contato AND destinatario_id = :me")
            ->execute(['contato' => $contato_id, 'me' => $meu_id]);

        // Busca o historico apenas entre o usuario logado e o contato.
        $stmtMsg = $pdo->prepare("
            SELECT * FROM mensagens
            WHERE (remetente_id = :me AND destinatario_id = :contato)
               OR (remetente_id = :contato AND destinatario_id = :me)
            ORDER BY data_envio ASC
        ");
        $stmtMsg->execute(['me' => $meu_id, 'contato' => $contato_id]);
        $mensagens = $stmtMsg->fetchAll();
    }
}

echo $twig->render('mensagens.html', [
    'conversas' => $conversas,
    'mensagens' => $mensagens,
    'usuario_destino' => $usuario_destino,
    'contato_id' => $contato_id,
    'meu_id' => $meu_id,
    'erro' => $erro
]);
