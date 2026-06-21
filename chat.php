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

// Validacao de seguranca: verifica se o usuario logado ainda existe no banco.
$stmtCheckSender = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id");
$stmtCheckSender->execute(['id' => $remetente_id]);
if (!$stmtCheckSender->fetch()) {
    session_destroy();
    header("Location: tela_login.php?erro=sessao_invalida");
    exit();
}

// Impede de abrir chat consigo mesmo ou sem um ID valido.
if (!$destinatario_id || $remetente_id == $destinatario_id) {
    header("Location: tela_inicial.php");
    exit();
}

// Busca informacoes do destinatario para exibir no topo do chat.
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
    die("Usuario nao encontrado.");
}

if (!empty($destinatario['foto_perfil'])) {
    $destinatario['foto_perfil'] = 'imagem.php?tipo=perfil&id=' . $destinatario['usuario_id'];
} else {
    $destinatario['foto_perfil'] = 'img/fotoPadrao.png';
}

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

// Processar envio de nova mensagem.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (isset($_POST['mensagem'])) {
        $mensagem = trim($_POST['mensagem']);
        $imagem = null;

        try {
            $imagem = carregarImagemMensagem($_FILES['imagem_mensagem'] ?? []);
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $erro_chat = $e->getMessage();
        }

        if (empty($mensagem) && $imagem === null) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Envie uma mensagem ou uma imagem.']);
                exit;
            }
            $erro_chat = 'Envie uma mensagem ou uma imagem.';
        }

        if (empty($erro_chat)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, imagem) VALUES (:r, :d, :m, :img)");
                $mensagemParaSalvar = $mensagem !== '' ? $mensagem : null;
                $stmt->bindValue(':r', $remetente_id, PDO::PARAM_INT);
                $stmt->bindValue(':d', $destinatario_id, PDO::PARAM_INT);
                $stmt->bindValue(':m', $mensagemParaSalvar, $mensagemParaSalvar === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':img', $imagem, $imagem === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
                $stmt->execute();
                $mensagemId = $pdo->lastInsertId();

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'mensagem' => $mensagem,
                        'imagem_url' => $imagem !== null ? 'imagem.php?tipo=mensagem&id=' . $mensagemId : null,
                        'hora' => date('H:i')
                    ]);
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
}

// Marcar mensagens recebidas deste usuario como lidas ao abrir a conversa.
$stmt = $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE remetente_id = :d AND destinatario_id = :r AND lida = 0");
$stmt->execute(['d' => $destinatario_id, 'r' => $remetente_id]);

// Buscar historico de mensagens entre os dois usuarios.
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
