<?php
session_start();
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: tela_login.php");
    exit();
}

$remetente_id = $_SESSION['usuario_id'];
$destinatario_id = $_GET['id'] ?? null;

// Validação de Segurança: Verifica se o remetente (usuário logado) ainda existe no banco.
$stmtCheckSender = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id");
$stmtCheckSender->execute(['id' => $remetente_id]);
if (!$stmtCheckSender->fetch()) {
    session_destroy();
    header("Location: tela_login.php?erro=sessao_invalida");
    exit();
}

// Impede de abrir chat consigo mesmo ou sem um ID válido
if (!$destinatario_id || $remetente_id == $destinatario_id) {
    header("Location: tela_inicial.php");
    exit();
}

// Busca informações do destinatário para exibir no topo do chat
$stmt = $pdo->prepare("
    SELECT u.id, u.id as usuario_id, COALESCE(c.nome, p.nome) as nome, COALESCE(c.foto_perfil, p.foto_perfil) as foto_perfil
    FROM usuarios u
    LEFT JOIN clientes c ON u.id = c.usuario_id
    LEFT JOIN profissionais p ON u.id = p.usuario_id
    WHERE u.id = :id
");
$stmt->execute(['id' => $destinatario_id]);
$destinatario = $stmt->fetch();

if (!$destinatario) {
    die("Usuário não encontrado.");
}

if (!empty($destinatario['foto_perfil'])) {
    $destinatario['foto_perfil'] = 'imagem.php?tipo=perfil&id=' . $destinatario['usuario_id'];
} else {
    $destinatario['foto_perfil'] = 'img/FotoPerfilPadrao.jpg';
}

// Processar envio de nova mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (isset($_POST['mensagem']) || isset($_FILES['midia'])) {
        $mensagem = trim($_POST['mensagem'] ?? '');
        $arquivo_blob = null;
        $tipo_arquivo = null;

        if (isset($_FILES['midia']) && $_FILES['midia']['error'] === UPLOAD_ERR_OK) {
            // Valida tamanho: máximo 10MB para evitar estourar max_allowed_packet
            if ($_FILES['midia']['size'] > 10 * 1024 * 1024) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo permitido: 10MB.']);
                    exit;
                }
                die("Arquivo muito grande. Máximo permitido: 10MB.");
            }
            $arquivo_blob = file_get_contents($_FILES['midia']['tmp_name']);
            $tipo_arquivo = $_FILES['midia']['type'];
        }

        if (empty($mensagem) && !$arquivo_blob) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'A mensagem não pode estar vazia.']);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, arquivo_blob, tipo_arquivo) VALUES (:r, :d, :m, :arq, :tipo)");
            $success = $stmt->execute([
                'r' => $remetente_id, 
                'd' => $destinatario_id,
                'm' => $mensagem ?: null,
                'arq' => $arquivo_blob,
                'tipo' => $tipo_arquivo
            ]);
            
            $msg_id = $pdo->lastInsertId();
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'mensagem' => $mensagem, 'hora' => date('H:i'), 'msg_id' => $msg_id]);
                exit();
            }

            header("Location: chat.php?id=$destinatario_id");
            exit();
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
                exit();
            }
            $erro_chat = "Erro ao enviar mensagem.";
        }
    }
}

// Marcar mensagens recebidas deste usuário como lidas ao abrir a conversa
$stmt = $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE remetente_id = :d AND destinatario_id = :r AND lida = 0");
$stmt->execute(['d' => $destinatario_id, 'r' => $remetente_id]);

// Buscar histórico de mensagens entre os dois usuários
$stmt = $pdo->prepare("
    SELECT * FROM mensagens 
    WHERE (remetente_id = :r AND destinatario_id = :d) 
       OR (remetente_id = :d AND destinatario_id = :r)
    ORDER BY data_envio ASC
");
$stmt->execute(['r' => $remetente_id, 'd' => $destinatario_id]);
$mensagens = $stmt->fetchAll();

echo $twig->render('chat.html', [
    'destinatario' => $destinatario,
    'mensagens' => $mensagens,
    'erro' => $erro_chat ?? null
]);
