<?php
session_start();
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

$fotoPerfilPadrao = 'FotoPerfilPadrao.jpg';

// Tenta pegar o ID da URL. Se não existir, tenta pegar o ID do usuário logado na sessão.
$id = (!empty($_GET['id'])) ? $_GET['id'] : ($_SESSION['usuario_id'] ?? null);

$usuario = null;
$avaliacoes = [];
$erro = '';
$sucesso = '';

// Captura o ID do usuário logado (se houver)
$id_logado = $_SESSION['usuario_id'] ?? null;

// Verifica se o perfil sendo visualizado é o do próprio usuário logado
// Usamos cast para string para evitar erros de comparação entre tipos diferentes (int vs string)
$eh_proprio_perfil = ($id_logado && $id && (string)$id_logado === (string)$id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_avaliacao'])) {
    if (!$id_logado) {
        $erro = "⚠️ Você precisa estar logado para editar.";
    } else {
        $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 5]]);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($nota === false) {
            $erro = "⚠️ A nota deve ser um valor entre 1 e 5 estrelas.";
        } elseif (empty($comentario)) {
            $erro = "⚠️ O campo de comentário não pode estar vazio.";
        } else {
            try {
                $stmtUpdate = $pdo->prepare(
                    "UPDATE avaliacoes SET nota = :nota, comentario = :comentario, data_criacao = NOW() 
                     WHERE profissional_id = :pid AND cliente_id = :cid"
                );
                $stmtUpdate->execute([
                    'nota' => $nota,
                    'comentario' => $comentario,
                    'pid' => $id,
                    'cid' => $id_logado
                ]);
                header("Location: perfil.php?id=$id&sucesso_edicao=1");
                exit();
            } catch (Exception $e) {
                $erro = "⚠️ Erro ao atualizar avaliação: " . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['avaliar'])) {
    if (!$id_logado) {
        $erro = "⚠️ Você precisa estar logado para avaliar.";
    } elseif ($eh_proprio_perfil) {
        $erro = "⚠️ Você não pode avaliar o seu próprio perfil.";
    } else {
        $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 5]]);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($nota === false) {
            $erro = "⚠️ A nota deve ser um valor entre 1 e 5 estrelas.";
        } elseif (empty($comentario)) {
            $erro = "⚠️ O campo de comentário não pode estar vazio.";
        } else {
            try {
                $stmtInsert = $pdo->prepare(
                    "INSERT INTO avaliacoes (profissional_id, cliente_id, nota, comentario) 
                     VALUES (:profissional_id, :cliente_id, :nota, :comentario)"
                );
                $stmtInsert->execute([
                    'profissional_id' => $id,
                    'cliente_id' => $id_logado,
                    'nota' => $nota,
                    'comentario' => $comentario
                ]);
                header("Location: perfil.php?id=$id&sucesso_avaliacao=1");
                exit();
            } catch (Exception $e) {
                // Se o erro for de entrada duplicada (código 23000), significa que o usuário já avaliou.
                // Em vez de mostrar um erro, apenas recarregamos a página.
                // A lógica que vem depois irá esconder o formulário, criando uma experiência mais suave.
                if ($e->getCode() == '23000') {
                    header("Location: perfil.php?id=$id");
                    exit();
                }
                $erro = "⚠️ Erro ao salvar avaliação: " . $e->getMessage();
            }
        }
    }
}

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

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $novoNome)) {
                    $tabela = ($dadosAtuais['tipo_base'] === 'profissional') ? 'profissionais' : 'clientes';
                    
                    // Atualiza banco de dados
                    $stmtUpdate = $pdo->prepare("UPDATE $tabela SET foto_perfil = :foto WHERE usuario_id = :id");
                    $stmtUpdate->execute(['foto' => $novoNome, 'id' => $id]);

                    // Deleta foto antiga se não for a padrão
                    if ($dadosAtuais['foto_atual'] && $dadosAtuais['foto_atual'] !== $fotoPerfilPadrao && file_exists($uploadDir . $dadosAtuais['foto_atual'])) {
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
                   COALESCE(c.data_nascimento, co.data_nascimento) as data_nascimento,
                   COALESCE(AVG(a.nota), 0) as nota_media,
                   COUNT(a.id) as total_avaliacoes
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.usuario_id
            LEFT JOIN profissionais co ON u.id = co.usuario_id
            LEFT JOIN avaliacoes a ON u.id = a.profissional_id
            WHERE u.id = :id
            GROUP BY u.id
        ");
        $stmt->execute(['id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $erro = "❌ Prestador não encontrado no sistema.";
        } else {
            // Aplica foto padrão se o campo estiver vazio no banco
            if (empty($usuario['foto_perfil'])) {
                $usuario['foto_perfil'] = $fotoPerfilPadrao;
            }

            // Converte a string "tag1, tag2" em um array para o Twig
            $usuario['tags'] = !empty($usuario['trabalho']) 
                ? array_filter(array_map('trim', explode(',', $usuario['trabalho']))) 
                : [];
            
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

            // Garante foto padrão para os autores das avaliações listadas
            foreach ($avaliacoes as &$av) {
                if (empty($av['autor_foto'])) {
                    $av['autor_foto'] = $fotoPerfilPadrao;
                }
            }
            unset($av);
        }
    } catch (Exception $e) {
        $erro = "⚠️ Erro ao carregar perfil: " . $e->getMessage();
    }
}

if (isset($_GET['sucesso_avaliacao'])) {
    $sucesso = "✅ Avaliação enviada com sucesso!";
}
if (isset($_GET['sucesso_edicao'])) {
    $sucesso = "✅ Sua avaliação foi atualizada com sucesso!";
}

$minha_avaliacao = null;
$outras_avaliacoes = [];
if ($id_logado && !empty($avaliacoes)) {
    foreach ($avaliacoes as $avaliacao) {
        if (isset($avaliacao['cliente_id']) && $avaliacao['cliente_id'] == $id_logado) {
            $minha_avaliacao = $avaliacao;
        } else {
            $outras_avaliacoes[] = $avaliacao;
        }
    }
} else {
    $outras_avaliacoes = $avaliacoes;
}

echo $twig->render('perfil.html', [
    'usuario' => $usuario,
    'erro' => $erro,
    'sucesso' => $sucesso,
    'minha_avaliacao' => $minha_avaliacao,
    'avaliacoes' => $outras_avaliacoes,
    'eh_proprio_perfil' => $eh_proprio_perfil,
    'pode_editar' => $eh_proprio_perfil,
    'pode_avaliar' => $id_logado && !$eh_proprio_perfil && $usuario && !$minha_avaliacao,
    'foto_perfil_padrao' => $fotoPerfilPadrao
]);