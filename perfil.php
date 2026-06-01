<?php
session_start();
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

// Tenta pegar o ID da URL. Se não existir, tenta pegar o ID do usuário logado na sessão.
$id = (!empty($_GET['id'])) ? $_GET['id'] : ($_SESSION['usuario_id'] ?? null);

$usuario = null;
$erro = '';

// Captura o ID do usuário logado (se houver)
$id_logado = $_SESSION['usuario_id'] ?? null;

// Verifica se o perfil sendo visualizado é o do próprio usuário logado
// Usamos cast para string para evitar erros de comparação entre tipos diferentes (int vs string)
$eh_proprio_perfil = ($id_logado && $id && (string)$id_logado === (string)$id);

if (!$id) {
    $erro = "⚠️ Usuário não especificado.";
} else {
    // --- Lógica de Upload de Foto (Apenas se o dono do perfil estiver logado) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil']) && $eh_proprio_perfil) {
        try {
            $file = $_FILES['foto_perfil'];
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;
            
            // Validações básicas
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

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
                                        LEFT JOIN profissionais co ON u.id = co.usuario_id 
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

    try {
        // Busca unificada utilizando COALESCE para pegar dados de ambas as tabelas (clientes ou contratantes)
        // Nota: 'trabalho' é específico de profissionais.
        $stmt = $pdo->prepare("
            SELECT u.id as usuario_id, u.email, u.tipo_base,
                   COALESCE(c.nome, co.nome) as nome,
                   COALESCE(c.endereco, co.endereco) as endereco,
                   COALESCE(c.telefone, co.telefone) as telefone,
                   COALESCE(c.descricao, co.descricao) as descricao,
                   COALESCE(c.foto_perfil, co.foto_perfil) as foto_perfil,
                   co.trabalho,
                   COALESCE(c.data_nascimento, co.data_nascimento) as data_nascimento
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.usuario_id
            LEFT JOIN profissionais co ON u.id = co.usuario_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $erro = "❌ Prestador não encontrado no sistema.";
        }
    } catch (Exception $e) {
        $erro = "⚠️ Erro ao carregar perfil: " . $e->getMessage();
    }
}

echo $twig->render('perfil.html', [
    'usuario' => $usuario,
    'erro' => $erro,
    'eh_proprio_perfil' => $eh_proprio_perfil, // Nova flag recomendada
    'pode_editar' => $eh_proprio_perfil       // Restaurada para não quebrar seu HTML atual
]);