<?php
session_start();
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

// Tenta pegar o ID da URL. Se não existir, tenta pegar o ID do usuário logado na sessão.
$id = $_GET['id'] ?? $_SESSION['usuario_id'] ?? null;

$usuario = null;
$erro = '';
$sucesso = '';

if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
    $sucesso = "✅ Portfólio atualizado com sucesso!";
} elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 'deleted') {
    $sucesso = "🗑️ Foto removida do portfólio!";
}

// Verificar se o usuário logado é admin para permitir edição de terceiros
$is_admin = false;
if (isset($_SESSION['usuario_id'])) {
    $stmtAdminCheck = $pdo->prepare("SELECT email FROM usuarios WHERE id = :id");
    $stmtAdminCheck->execute(['id' => $_SESSION['usuario_id']]);
    $currentUser = $stmtAdminCheck->fetch(PDO::FETCH_ASSOC);
    // O admin é identificado pelo e-mail conforme padrão na tela_registro.php
    if ($currentUser && $currentUser['email'] === 'admin@mist.com') {
        $is_admin = true;
    }
}

// O usuário pode editar se for o dono do perfil ou se for um administrador
$pode_editar = (isset($_SESSION['usuario_id']) && $id && ($_SESSION['usuario_id'] == $id || $is_admin));

if (!$id) {
    $erro = "⚠️ Usuário não especificado.";
} else {
    // --- Lógica de Upload de Foto (Apenas se o dono do perfil estiver logado) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil']) && $pode_editar) {
        try {
            $file = $_FILES['foto_perfil'];
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;
            
            // Validações básicas
            if (!class_exists('finfo')) {
                throw new Exception("A extensão 'fileinfo' não está ativa no PHP.");
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception("Formato inválido. Use JPG, PNG ou GIF.");
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("A imagem não pode ser maior que 5MB.");
            }

            // Busca dados atuais para saber qual tabela atualizar e deletar a foto antiga
            $stmtCheck = $pdo->prepare("SELECT u.tipo_base, COALESCE(c.foto_perfil, p.foto_perfil) as foto_atual 
                                        FROM usuarios u 
                                        LEFT JOIN clientes c ON u.id = c.usuario_id 
                                        LEFT JOIN profissionais p ON u.id = p.usuario_id 
                                        WHERE u.id = :id");
            $stmtCheck->execute(['id' => $id]);
            $dadosAtuais = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($dadosAtuais) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $novoNome = uniqid('perfil_', true) . '.' . $ext;

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $novoNome)) {
                    $tabela = ($dadosAtuais['tipo_base'] === 'profissional') ? 'profissionais' : 'clientes';
                    
                    // Atualiza banco de dados
                    $stmtUpdate = $pdo->prepare("UPDATE $tabela SET foto_perfil = :foto WHERE usuario_id = :id");
                    $stmtUpdate->execute(['foto' => $novoNome, 'id' => $id]);

                    // Deleta foto antiga se não for a padrão
                    if ($dadosAtuais['foto_atual'] && $dadosAtuais['foto_atual'] !== 'default_profile.png' && file_exists($uploadDir . $dadosAtuais['foto_atual'])) {
                        unlink($uploadDir . $dadosAtuais['foto_atual']);
                    }

                    // Atualiza a foto na sessão
                    $_SESSION['usuario_foto'] = $novoNome;
                    
                    header("Location: perfil.php?id=$id&sucesso=1");
                    exit();
                }
            }
        } catch (Exception $e) {
            $erro = "❌ Erro no upload: " . $e->getMessage();
        }
    }

    // --- Lógica de Exclusão de Foto do Trabalho ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_foto_id']) && $pode_editar) {
        try {
            $fotoId = $_POST['excluir_foto_id'];
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;

            // Busca o nome do arquivo e valida se pertence ao profissional
            $stmtFoto = $pdo->prepare("
                SELECT pf.arquivo FROM profissional_fotos pf
                JOIN profissionais p ON pf.profissional_id = p.id
                WHERE pf.id = :foto_id AND p.usuario_id = :user_id
            ");
            $stmtFoto->execute(['foto_id' => $fotoId, 'user_id' => $id]);
            $fotoData = $stmtFoto->fetch(PDO::FETCH_ASSOC);

            if ($fotoData) {
                $pdo->prepare("DELETE FROM profissional_fotos WHERE id = :id")->execute(['id' => $fotoId]);
                $caminho = $uploadDir . $fotoData['arquivo'];
                if (file_exists($caminho)) unlink($caminho);
                
                header("Location: perfil.php?id=$id&sucesso=deleted");
                exit();
            }
        } catch (Exception $e) {
            $erro = "❌ Erro ao excluir foto: " . $e->getMessage();
        }
    }

    try {
        // Busca unificada utilizando COALESCE para pegar dados de ambas as tabelas (clientes ou contratantes)
        // Nota: 'trabalho' é específico de profissionais.
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.tipo_base,
                   COALESCE(c.nome, p.nome) as nome,
                   COALESCE(c.endereco, p.endereco) as endereco,
                   COALESCE(c.telefone, p.telefone) as telefone,
                   COALESCE(c.descricao, p.descricao) as descricao,
                   COALESCE(c.foto_perfil, p.foto_perfil) as foto_perfil,
                   p.id as profissional_id,
                   p.trabalho,
                   COALESCE(c.data_nascimento, p.data_nascimento) as data_nascimento
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.usuario_id
            LEFT JOIN profissionais p ON u.id = p.usuario_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $erro = "❌ Prestador não encontrado no sistema.";
        } else {
            // Converte a string "tag1, tag2" em um array para o Twig
            $usuario['tags'] = !empty($usuario['trabalho']) 
                ? array_filter(array_map('trim', explode(',', $usuario['trabalho']))) 
                : [];

            // Busca as fotos do portfólio caso seja um profissional
            if ($usuario['tipo_base'] === 'profissional' && $usuario['profissional_id']) {
                $stmtFotos = $pdo->prepare("SELECT id, arquivo FROM profissional_fotos WHERE profissional_id = :pid");
                $stmtFotos->execute(['pid' => $usuario['profissional_id']]);
                $usuario['fotos_trabalho'] = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $erro = "⚠️ Erro ao carregar perfil: " . $e->getMessage();
    }
}

// Verifica se é o próprio perfil do usuário logado
$eh_proprio_perfil = isset($_SESSION['usuario_id']) && $id && $_SESSION['usuario_id'] == $id;

echo $twig->render('perfil.html', [
    'usuario' => $usuario,
    'erro' => $erro,
    'sucesso' => $sucesso,
    'pode_editar' => $pode_editar,
    'eh_proprio_perfil' => $eh_proprio_perfil,
    'url_edicao' => ($is_admin && $id != $_SESSION['usuario_id']) ? "tela_registro.php?id=$id" : "tela_registro.php"
]);