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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['mensagem']) || isset($_FILES['midia']))) {
    $destinatario = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT);
    $conteudo = trim($_POST['mensagem'] ?? '');
    $arquivo_blob = null;
    $tipo_arquivo = null;

    if (isset($_FILES['midia']) && $_FILES['midia']['error'] === UPLOAD_ERR_OK) {
        $arquivo_blob = file_get_contents($_FILES['midia']['tmp_name']);
        $tipo_arquivo = $_FILES['midia']['type'];
    }

    if ($destinatario && (!empty($conteudo) || $arquivo_blob)) {
        $stmt = $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, arquivo_blob, tipo_arquivo) VALUES (:rem, :dest, :cont, :arq, :tipo)");
        $stmt->execute([
            'rem'  => $meu_id,
            'dest' => $destinatario,
            'cont' => $conteudo,
            'arq'  => $arquivo_blob,
            'tipo' => $tipo_arquivo
        ]);
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

// Converte foto BLOB para URL dinâmica nas conversas
foreach ($conversas as &$conv) {
    if (!empty($conv['foto'])) {
        $conv['foto'] = 'imagem.php?tipo=perfil&id=' . $conv['id'];
    } else {
        $conv['foto'] = 'img/FotoPerfilPadrao.jpg';
    }
}
unset($conv);

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
    }
}

echo $twig->render('mensagens.html', [
    'conversas' => $conversas,
    'mensagens' => $mensagens,
    'usuario_destino' => $usuario_destino,
    'contato_id' => $contato_id,
    'meu_id' => $meu_id
]);
