<?php
session_start();
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

$erro = '';
$sucesso = '';

// Identifica o ID do perfil a ser visualizado
$id_perfil = $_GET['id'] ?? $_SESSION['usuario_id'] ?? null;

if (!$id_perfil) {
    header("Location: tela_login.php");
    exit();
}

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

    // Processamento de Troca de Foto
    if (isset($_FILES['foto_perfil']) && $eh_proprio_perfil) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        if ($_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK && is_uploaded_file($fileTmpPath)) {
            if (!class_exists('finfo')) {
                $erro = "A extensão 'fileinfo' não está ativa no seu PHP. Habilite-a no php.ini.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($fileTmpPath);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

                if (!in_array($mimeType, $allowedMimes)) {
                    $erro = "Formato inválido. Use apenas JPG, PNG ou GIF.";
                } elseif ($_FILES['foto_perfil']['size'] > 5 * 1024 * 1024) {
                    $erro = "A imagem de perfil não pode ser maior que 5MB.";
                } else {
                    $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
                    $newFileName = uniqid('perfil_', true) . '.' . $ext;
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                        $tabela = ($uBase['tipo_base'] === 'profissional') ? 'profissionais' : 'clientes';
                        
                        $stmtOld = $pdo->prepare("SELECT foto_perfil FROM $tabela WHERE usuario_id = :id");
                        $stmtOld->execute(['id' => $usuario_logado_id]);
                        $oldFoto = $stmtOld->fetchColumn();

                        if ($oldFoto && $oldFoto !== 'default_profile.png' && file_exists($uploadDir . $oldFoto)) {
                            unlink($uploadDir . $oldFoto);
                        }

                        $stmtUpdateFoto = $pdo->prepare("UPDATE $tabela SET foto_perfil = :foto WHERE usuario_id = :id");
                        $stmtUpdateFoto->execute([
                            'foto' => $newFileName,
                            'id' => $usuario_logado_id
                        ]);

                        $_SESSION['usuario_foto'] = $newFileName;
                        $sucesso = "Foto de perfil atualizada com sucesso!";
                    } else {
                        $erro = "Erro ao salvar a imagem no servidor.";
                    }
                }
            }
        }
    }
}

// --- BUSCA DADOS DO PERFIL ---
try {
    if ($uBase['tipo_base'] === 'profissional') {
        $stmtPerfil = $pdo->prepare("SELECT * FROM profissionais WHERE usuario_id = :id");
    } else {
        $stmtPerfil = $pdo->prepare("SELECT * FROM clientes WHERE usuario_id = :id");
    }
    
    $stmtPerfil->execute(['id' => $id_perfil]);
    $perfilDetalhes = $stmtPerfil->fetch(PDO::FETCH_ASSOC);

    if (!$perfilDetalhes) {
        throw new Exception("Detalhes do perfil não encontrados.");
    }

    $usuario = array_merge($uBase, $perfilDetalhes);
    $usuario['usuario_id'] = $id_perfil;

    if ($uBase['tipo_base'] === 'profissional') {
        $stmtNota = $pdo->prepare("SELECT COALESCE(AVG(nota), 0) as nota_media, COUNT(id) as total_avaliacoes FROM avaliacoes WHERE profissional_id = :id");
        $stmtNota->execute(['id' => $id_perfil]);
        $notaInfo = $stmtNota->fetch(PDO::FETCH_ASSOC);

        $usuario['nota_media'] = $notaInfo['nota_media'];
        $usuario['total_avaliacoes'] = $notaInfo['total_avaliacoes'];
    } else {
        $usuario['nota_media'] = 0;
        $usuario['total_avaliacoes'] = 0;
    }

} catch (Exception $e) {
    echo $twig->render('perfil.html', ['erro' => $e->getMessage()]);
    exit();
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

        foreach ($todasAvaliacoes as $aval) {
            if ($usuario_logado_id && $aval['avaliador_id'] == $usuario_logado_id) {
                $minha_avaliacao = $aval;
            } else {
                $avaliacoes[] = $aval;
            }
        }
    } catch (PDOException $e) {
        $erro = "Erro ao buscar avaliações.";
    }
}

// Regras de Visualização/Ação
$pode_avaliar = false;
if ($usuario_logado_id && !$eh_proprio_perfil && !$minha_avaliacao && $uBase['tipo_base'] === 'profissional') {
    $pode_avaliar = true;
}

echo $twig->render('perfil.html', [
    'erro' => $erro,
    'sucesso' => $sucesso,
    'usuario' => $usuario,
    'avaliacoes' => $avaliacoes,
    'minha_avaliacao' => $minha_avaliacao,
    'pode_avaliar' => $pode_avaliar,
    'pode_editar' => $pode_editar,
    'eh_proprio_perfil' => $eh_proprio_perfil
]);