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

// --- Ação: Enviar Mensagem ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destinatario = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT);
    $conteudo = trim($_POST['mensagem'] ?? '');

    $arquivoNome = null;
    $arquivoTipo = null;

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'chat' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
        $arquivoNome = uniqid('media_', true) . '.' . $ext;
        $arquivoTipo = mime_content_type($_FILES['arquivo']['tmp_name']);
        if (!$arquivoTipo) $arquivoTipo = $_FILES['arquivo']['type'];
        move_uploaded_file($_FILES['arquivo']['tmp_name'], $uploadDir . $arquivoNome);
    } elseif (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
        $arquivoNome = uniqid('audio_', true) . '.webm';
        $arquivoTipo = 'audio/webm';
        move_uploaded_file($_FILES['audio']['tmp_name'], $uploadDir . $arquivoNome);
    }

    if ($destinatario && (!empty($conteudo) || $arquivoNome)) {
        $prefix = "";
        if ($arquivoNome) {
            $prefix = "[MEDIA:" . $arquivoTipo . "|" . $arquivoNome . "]";
        }
        $conteudoFinal = $prefix . $conteudo;

        $stmt = $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem) VALUES (:rem, :dest, :cont)");
        $stmt->execute([
            'rem'  => $meu_id,
            'dest' => $destinatario,
            'cont' => $conteudoFinal
        ]);

        if (isset($_FILES['audio'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }

        // Redireciona para evitar reenvio ao atualizar (F5)
        header("Location: mensagens.php?u=" . $destinatario);
        exit();
    }
}

// --- Busca Lista de Conversas (Inbox) ---
// Pega todos os usuários com quem troquei mensagens
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

// --- Busca Mensagens da Conversa Ativa ---
$mensagens = [];
$usuario_destino = null;

if ($contato_id) {
    // Busca dados do destinatário para o cabeçalho do chat
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
        // Marca como lidas as mensagens recebidas deste contato
        $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE remetente_id = :contato AND destinatario_id = :me")
            ->execute(['contato' => $contato_id, 'me' => $meu_id]);

        // Busca o histórico (Garante isolamento: apenas entre EU e ELE)
        $stmtMsg = $pdo->prepare("
            SELECT * FROM mensagens 
            WHERE (remetente_id = :me AND destinatario_id = :contato)
               OR (remetente_id = :contato AND destinatario_id = :me)
            ORDER BY data_envio ASC
        ");
        $stmtMsg->execute(['me' => $meu_id, 'contato' => $contato_id]);
        $mensagens = $stmtMsg->fetchAll();

        foreach ($mensagens as &$msg) {
            $msg['arquivo'] = null;
            $msg['tipo_arquivo'] = null;
            $texto = $msg['mensagem'];
            if (preg_match('/^\[MEDIA:(.*?)\|(.*?)\](.*)$/s', $texto, $matches)) {
                $msg['tipo_arquivo'] = $matches[1];
                $msg['arquivo'] = $matches[2];
                $msg['mensagem'] = $matches[3];
            }
        }
        unset($msg);
    }
}

echo $twig->render('mensagens.html', [
    'conversas' => $conversas,
    'mensagens' => $mensagens,
    'usuario_destino' => $usuario_destino,
    'contato_id' => $contato_id,
    'meu_id' => $meu_id
]);