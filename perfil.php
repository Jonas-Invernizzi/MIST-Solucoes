<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

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
$erro = '';
$sucesso = '';
$fotoPerfilPadrao = 'FotoPerfilPadrao.jpg';
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;

// Identifica o ID do perfil a ser visualizado
$id_perfil = $_GET['id'] ?? $_SESSION['usuario_id'] ?? null;

if (!$id_perfil) {
    header("Location: tela_login.php");
    exit();
}

$usuario_id_logado = $_SESSION['usuario_id'] ?? null;
$usuario_logado_id = $_SESSION['usuario_id'] ?? null;
$eh_proprio_perfil = ($usuario_logado_id == $id_perfil);
$pode_editar = $eh_proprio_perfil;

// Identifica se o perfil acessado existe e pega dados básicos
$stmtUserBase = $pdo->prepare("SELECT u.email, u.tipo_base, u.status, u.data_criacao FROM usuarios u WHERE u.id = :id");
$stmtUserBase->execute(['id' => $id_perfil]);
$uBase = $stmtUserBase->fetch(PDO::FETCH_ASSOC);

if (!$uBase) {
    echo $twig->render('perfil.html', ['erro' => 'Usuário não encontrado.']);
    exit();
}

// --- AÇÕES (POST) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $usuario_logado_id) {
    
    // Processamento de Avaliação (Inserir/Editar)
    if (isset($_POST['avaliar']) || isset($_POST['editar_avaliacao'])) {
        if ($eh_proprio_perfil) {
            $erro = "Você não pode avaliar seu próprio perfil.";
        } elseif ($uBase['tipo_base'] !== 'profissional') {
            $erro = "Somente profissionais podem receber avaliações.";
        } else {
            $nota = intval($_POST['nota'] ?? 0);
            $comentario = trim($_POST['comentario'] ?? '');

            if ($nota < 1 || $nota > 5) {
                $erro = "A nota deve ser entre 1 e 5 estrelas.";
            } elseif (empty($comentario)) {
                $erro = "O comentário não pode ser vazio.";
            } else {
                try {
                    // Verifica se o usuário já avaliou este perfil
                    $stmtCheck = $pdo->prepare("SELECT id FROM avaliacoes WHERE profissional_id = :prof_id AND cliente_id = :cli_id");
                    $stmtCheck->execute([
                        'prof_id' => $id_perfil,
                        'cli_id' => $usuario_logado_id
                    ]);
                    $avaliacaoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($avaliacaoExistente) {
                        $stmtUpdate = $pdo->prepare("UPDATE avaliacoes SET nota = :nota, comentario = :comentario, data_criacao = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmtUpdate->execute([
                            'nota' => $nota,
                            'comentario' => $comentario,
                            'id' => $avaliacaoExistente['id']
                        ]);
                        $sucesso = "Avaliação atualizada com sucesso!";
                    } else {
                        $stmtInsert = $pdo->prepare("INSERT INTO avaliacoes (profissional_id, cliente_id, nota, comentario) VALUES (:prof_id, :cli_id, :nota, :comentario)");
                        $stmtInsert->execute([
                            'prof_id' => $id_perfil,
                            'cli_id' => $usuario_logado_id,
                            'nota' => $nota,
                            'comentario' => $comentario
                        ]);
                        $sucesso = "Avaliação enviada com sucesso!";
                    }
                } catch (PDOException $e) {
                    $erro = "Erro ao salvar avaliação: " . $e->getMessage();
                }
            }
        }
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
    // Processamento de Troca de Foto
    if (isset($_FILES['foto_perfil']) && $eh_proprio_perfil) {
        try {
            $file = $_FILES['foto_perfil']; // Define a variável $file aqui
            $fileTmpPath = $file['tmp_name'];
            if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($fileTmpPath)) {
                if (!class_exists('finfo')) {
                    $erro = "A extensão 'fileinfo' não está ativa no seu PHP. Habilite-a no php.ini.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($fileTmpPath);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

                    if (!in_array($mimeType, $allowedMimes)) {
                        throw new Exception("Formato inválido. Use JPG, PNG ou GIF.");
                    }
                    if ($file['size'] > 5 * 1024 * 1024) {
                        throw new Exception("A imagem não pode ser maior que 5MB.");
                    }

                    // Busca dados atuais para saber qual tabela atualizar e deletar a foto antiga
                    $stmtCheck = $pdo->prepare("SELECT u.tipo_base, COALESCE(c.foto_perfil, co.foto_perfil) as foto_atual 
                                                FROM usuarios u 
                                                LEFT JOIN clientes c ON u.id = c.usuario_id 
                                                LEFT JOIN profissionais co ON u.id = co.usuario_id 
                                                WHERE u.id = :id");
                    $stmtCheck->execute(['id' => $id]);
                    $dadosAtuais = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($dadosAtuais) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $novoNome = uniqid('perfil_', true) . '.' . $ext;

                        if (move_uploaded_file($fileTmpPath, $uploadDir . $novoNome)) {
                            $tabela = ($dadosAtuais['tipo_base'] === 'profissional') ? 'profissionais' : 'clientes';
                            
                            // Atualiza banco de dados
                            $stmtUpdate = $pdo->prepare("UPDATE $tabela SET foto_perfil = :foto WHERE usuario_id = :id");
                            $stmtUpdate->execute(['foto' => $novoNome, 'id' => $id_perfil]);

                            // Deleta foto antiga se não for a padrão
                            if ($dadosAtuais['foto_atual'] && $dadosAtuais['foto_atual'] !== $fotoPerfilPadrao && file_exists($uploadDir . $dadosAtuais['foto_atual'])) {
                                unlink($uploadDir . $dadosAtuais['foto_atual']);
                            }

                            // Atualiza a foto na sessão
                            $_SESSION['usuario_foto'] = $novoNome;
                            
                            header("Location: perfil.php?id=$id_perfil&sucesso=1");
                            exit();
                        }
                    }
                }
            }
        } catch(Exception $e) {
            $erro = "❌ Erro no upload: " . $e->getMessage();
        }
    }
}

    // --- Lógica de Upload de Foto de Trabalho (Portfólio / Carrossel) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nova_foto_trabalho']) && $pode_editar) {
        try {
            $files = $_FILES['nova_foto_trabalho'];
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;

            // Garante que é um profissional e busca o seu ID
            $stmtProf = $pdo->prepare("SELECT id FROM profissionais WHERE usuario_id = :uid");
            $stmtProf->execute(['uid' => $id]);
            $profissional_id = $stmtProf->fetchColumn();

            if (!$profissional_id) {
                throw new Exception("Apenas profissionais podem adicionar fotos ao portfólio.");
            }

            if (!class_exists('finfo')) {
                throw new Exception("A extensão 'fileinfo' não está ativa no PHP.");
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            // Verifica se múltiplos arquivos foram enviados através de name="nova_foto_trabalho[]"
            $isMultiple = is_array($files['name']);
            $fileCount = $isMultiple ? count($files['name']) : 1;
            $uploadedCount = 0;

            for ($i = 0; $i < $fileCount; $i++) {
                $tmpName = $isMultiple ? $files['tmp_name'][$i] : $files['tmp_name'];
                $error = $isMultiple ? $files['error'][$i] : $files['error'];
                $name = $isMultiple ? $files['name'][$i] : $files['name'];
                $size = $isMultiple ? $files['size'][$i] : $files['size'];

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue; // Ignora se o campo de upload veio vazio
                }

                if ($error !== UPLOAD_ERR_OK) {
                    throw new Exception("Erro no upload do arquivo $name (Código: $error)");
                }

                $mimeType = $finfo->file($tmpName);
                if (!in_array($mimeType, $allowedMimes)) {
                    throw new Exception("O arquivo $name tem formato inválido. Use JPG, PNG, WEBP ou GIF.");
                }
                if ($size > 5 * 1024 * 1024) {
                    throw new Exception("O arquivo $name é maior que 5MB.");
                }

                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $novoNome = uniqid('port_', true) . '.' . $ext;

                if (move_uploaded_file($tmpName, $uploadDir . $novoNome)) {
                    $stmtInsert = $pdo->prepare("INSERT INTO profissional_fotos (profissional_id, arquivo) VALUES (:pid, :arquivo)");
                    $stmtInsert->execute(['pid' => $profissional_id, 'arquivo' => $novoNome]);
                    $uploadedCount++;
                }
            }

            if ($uploadedCount > 0) {
                header("Location: perfil.php?id=$id&sucesso=1");
                exit();
            }
        } catch (Exception $e) {
            $erro = "❌ Erro no upload do portfólio: " . $e->getMessage();
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
            SELECT u.id as usuario_id, u.email, u.tipo_base,
                   COALESCE(c.nome, co.nome) as nome,
                   COALESCE(c.endereco, co.endereco) as endereco,
                   COALESCE(c.telefone, co.telefone) as telefone,
                   COALESCE(c.descricao, co.descricao) as descricao,
                   COALESCE(c.foto_perfil, co.foto_perfil) as foto_perfil,
                   co.trabalho,
                   COALESCE(c.data_nascimento, co.data_nascimento) as data_nascimento,
                   COALESCE(AVG(a.nota), 0) as nota_media,
                   COUNT(a.id) as total_avaliacoes
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.usuario_id
            LEFT JOIN profissionais co ON u.id = co.usuario_id
            LEFT JOIN avaliacoes a ON co.id = a.profissional_id
            WHERE u.id = :id
            GROUP BY u.id
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
                $stmtFotos = $pdo->prepare("SELECT id, arquivo FROM profissional_fotos WHERE profissional_id = :pid ORDER BY id ASC");
                $stmtFotos->execute(['pid' => $usuario['profissional_id']]);
                $usuario['fotos_trabalho'] = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Busca as avaliações detalhadas para o perfil visualizado
            $stmtAvaliacoes = $pdo->prepare("
                SELECT 
                    a.id as avaliacao_id,
                    a.cliente_id,
                    a.nota, 
                    a.comentario, 
                    a.data_criacao,
                    COALESCE(cl.nome, p.nome) as autor_nome,
                    COALESCE(cl.foto_perfil, p.foto_perfil) as autor_foto
                FROM avaliacoes a
                JOIN usuarios u_autor ON a.cliente_id = u_autor.id
                LEFT JOIN clientes cl ON u_autor.id = cl.usuario_id
                LEFT JOIN profissionais p ON u_autor.id = p.usuario_id
                WHERE a.profissional_id = :id
                ORDER BY a.data_criacao DESC
            ");
            $stmtAvaliacoes->execute(['id' => $id]);
            $avaliacoes = $stmtAvaliacoes->fetchAll(PDO::FETCH_ASSOC);
        }
        $usuario['tags'] = !empty($usuario['trabalho']) 
            ? array_filter(array_map('trim', explode(',', $usuario['trabalho']))) 
            : [];
    }
} catch (Exception $e) {
    $erro = "⚠️ Erro ao carregar perfil: " . $e->getMessage();
}

if (isset($_GET['sucesso_avaliacao'])) {
    $sucesso = "✅ Avaliação enviada com sucesso!";
}

// --- BUSCA AVALIAÇÕES ---
$avaliacoes = [];
$minha_avaliacao = null;

if ($uBase['tipo_base'] === 'profissional') {
    try {
        $sqlAvaliacoes = "
            SELECT 
                a.id, a.nota, a.comentario, a.data_criacao, a.cliente_id as avaliador_id,
                COALESCE(cp.nome, cc.nome) as autor_nome,
                COALESCE(cp.foto_perfil, cc.foto_perfil) as autor_foto
            FROM avaliacoes a
            LEFT JOIN profissionais cp ON a.cliente_id = cp.usuario_id
            LEFT JOIN clientes cc ON a.cliente_id = cc.usuario_id
            WHERE a.profissional_id = :prof_id
            ORDER BY a.data_criacao DESC
        ";
        
        $stmtAval = $pdo->prepare($sqlAvaliacoes);
        $stmtAval->execute(['prof_id' => $id_perfil]);
        $todasAvaliacoes = $stmtAval->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todasAvaliacoes as &$aval) {
            // Foto padrão para autores no loop de avaliações
            if (empty($aval['autor_foto'])) {
                $aval['autor_foto'] = $fotoPerfilPadrao;
            }

            if ($usuario_logado_id && $aval['avaliador_id'] == $usuario_logado_id) {
                $minha_avaliacao = $aval;
            } else {
                $avaliacoes[] = $aval;
            }
        }
        unset($aval);
    } catch (PDOException $e) {
        $erro = "Erro ao buscar avaliações.";
    }
}

// Regras de Visualização/Ação
$pode_avaliar = false;
if ($usuario_logado_id && !$eh_proprio_perfil && !$minha_avaliacao && $uBase['tipo_base'] === 'profissional') {
    $pode_avaliar = true;
}

// Verifica se é o próprio perfil do usuário logado
$eh_proprio_perfil = isset($_SESSION['usuario_id']) && $id && $_SESSION['usuario_id'] == $id;

echo $twig->render('perfil.html', [
    'erro' => $erro,
    'sucesso' => $sucesso,
    'pode_editar' => $pode_editar,
    'eh_proprio_perfil' => $eh_proprio_perfil,
    'url_edicao' => ($is_admin && $id != $_SESSION['usuario_id']) ? "tela_registro.php?id=$id" : "tela_registro.php"
    'usuario' => $usuario,
    'avaliacoes' => $avaliacoes,
    'minha_avaliacao' => $minha_avaliacao,
    'eh_proprio_perfil' => $eh_proprio_perfil,
    'pode_editar' => $eh_proprio_perfil,
    'pode_avaliar' => $pode_avaliar,
    'foto_perfil_padrao' => $fotoPerfilPadrao
]);