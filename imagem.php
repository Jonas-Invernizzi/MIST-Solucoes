<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('carregar_pdo.php');

$tipo = $_GET['tipo'] ?? '';
$id = $_GET['id'] ?? 0;

if (!$tipo) {
    http_response_code(400);
    exit('Parâmetros inválidos');
}

try {
    $arquivo = null;
    $mime = 'image/jpeg';

    if ($tipo === 'portfolio' && $id) {
        $stmt = $pdo->prepare("SELECT arquivo FROM profissional_fotos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $arquivo = $stmt->fetchColumn();
    } elseif ($tipo === 'perfil' && $id) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(c.foto_perfil, p.foto_perfil) 
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.usuario_id
            LEFT JOIN profissionais p ON u.id = p.usuario_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $arquivo = $stmt->fetchColumn();
    } elseif ($tipo === 'mensagem' && $id && isset($_SESSION['usuario_id'])) {
        $stmt = $pdo->prepare("
            SELECT imagem
            FROM mensagens
            WHERE id = :id
              AND imagem IS NOT NULL
              AND (remetente_id = :usuario_id OR destinatario_id = :usuario_id)
        ");
        $stmt->execute([
            'id' => $id,
            'usuario_id' => $_SESSION['usuario_id']
        ]);
        $arquivo = $stmt->fetchColumn();
    } elseif ($tipo === 'asset') {
        $stmt = $pdo->prepare("SELECT arquivo, mime_type FROM sistema_assets WHERE nome = :nome");
        $stmt->execute(['nome' => $_GET['nome']]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($asset) {
            $arquivo = $asset['arquivo'];
            $mime = $asset['mime_type'];
        }
    }

    if ($arquivo) {
        // Compatibilidade se o arquivo no DB for apenas o nome da foto antiga
        if (strlen($arquivo) < 255 && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $arquivo)) {
            $caminho = __DIR__ . '/img/' . $arquivo;
            if (file_exists($caminho)) {
                $arquivo = file_get_contents($caminho);
            }
        }
        
        if ($tipo !== 'asset' && class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($arquivo) ?: 'image/jpeg';
        }
        
        header("Content-Type: $mime");
        header("Cache-Control: max-age=86400, public");
        echo $arquivo;
        exit();
    }
} catch (Exception $e) {}

// Se a foto não for encontrada, retorna a padrão
header("Location: img/fotoPadrao.png");
exit();
