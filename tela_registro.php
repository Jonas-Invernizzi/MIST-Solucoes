<?php
session_start();
require_once('carregar_twig.php');
require_once('carregar_pdo.php');
// É provável que a falta desta linha esteja causando a falha no envio de e-mail.
require_once 'vendor/autoload.php';

$erro = '';
$mostra_modal_codigo = false;
$email_modal = '';
$usuario_id_modal = '';
$dados_usuario = [];
$is_edicao = false;

// --- Lógica de Edição ---
$id_alvo = null; // ID do usuário a ser editado
$is_admin = false;

// 1. Verificar se o usuário logado é admin
if (isset($_SESSION['usuario_id'])) {
    $stmtAdminCheck = $pdo->prepare("SELECT email FROM usuarios WHERE id = :id");
    $stmtAdminCheck->execute(['id' => $_SESSION['usuario_id']]);
    $currentUser = $stmtAdminCheck->fetch(PDO::FETCH_ASSOC);
    if ($currentUser && $currentUser['email'] === 'admin@mist.com') {
    
    // Agora reconhece qualquer e-mail que contenha 'admin' e o domínio '@mist.com'
    if ($currentUser && str_contains($currentUser['email'], 'admin') && str_contains($currentUser['email'], '@mist.com')) {
        $is_admin = true;
    }
}}

// 2. Determinar se é edição e quem está sendo editado
if (isset($_SESSION['usuario_id'])) { // Apenas usuários logados podem editar
    $is_edicao = true;

    if ($is_admin && isset($_GET['id'])) {
        // Se o usuário é admin e está acessando com um ID na URL, ele está editando outro perfil.
        $id_alvo = $_GET['id'];
    } else {
        // Caso contrário, qualquer usuário logado (admin ou não) está editando o próprio perfil.
        $id_alvo = $_SESSION['usuario_id'];
    }
}

// 3. Se for edição, carregar os dados do usuário alvo
if ($is_edicao && $id_alvo) {
    $stmtUser = $pdo->prepare("SELECT email, tipo_base FROM usuarios WHERE id = :id");
    $stmtUser->execute(['id' => $id_alvo]);
    $uBase = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($uBase) {
        $tabela = ($uBase['tipo_base'] === 'profissional') ? 'profissionais' : 'clientes';
        $stmt = $pdo->prepare("SELECT u.email, u.tipo_base, t.* FROM usuarios u LEFT JOIN $tabela t ON u.id = t.usuario_id WHERE u.id = :id");
        $stmt->execute(['id' => $id_alvo]);
        $dados_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Mapeia o campo 'trabalho' do banco de dados para a chave 'tags', 
        // garantindo que as tags registradas apareçam no formulário de edição.
        if ($uBase['tipo_base'] === 'profissional' && isset($dados_usuario['trabalho'])) {
            $dados_usuario['tags'] = $dados_usuario['trabalho'];
        }
    }
}

// Processar verificação de código
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verificar_codigo'])) {
    $codigo = trim($_POST['codigo'] ?? '');
    $email = trim($_POST['email_hidden'] ?? '');

    if ($codigo === '') {
        $erro = "⚠️ Por favor, insira o código de verificação.";
        $mostra_modal_codigo = true;
        $email_modal = $email;
    } elseif (strlen($codigo) !== 6 || !ctype_digit($codigo)) {
        $erro = "⚠️ O código deve conter exatamente 6 dígitos.";
        $mostra_modal_codigo = true;
        $email_modal = $email;
    } else {
        // Verificar código
        $stmt = $pdo->prepare("SELECT id, token FROM usuarios WHERE email = :email AND status = :status");
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':status', 'inativo');
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $erro = "⚠️ E-mail não encontrado ou já confirmado.";
            $mostra_modal_codigo = true;
            $email_modal = $email;
        } else {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($codigo !== $usuario['token']) {
                $erro = "❌ Código de verificação incorreto. Tente novamente.";
                $mostra_modal_codigo = true;
                $email_modal = $email;
            } else {
                // Código correto! Ativar conta
                try {
                    $pdo->beginTransaction();

                    $updateStmt = $pdo->prepare("UPDATE usuarios SET status = :status, token = NULL WHERE id = :id");
                    $updateStmt->bindValue(':status', 'ativo');
                    $updateStmt->bindValue(':id', $usuario['id']);
                    $updateStmt->execute();

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $erro = "⚠️ Erro ao ativar conta: " . $e->getMessage();
                }

                if ($erro === '') {
                    // Redirecionar para login com sucesso
                    header("Location: tela_login.php?verificado=1");
                    exit();
                } else {
                    // Se erro, mostrar modal novamente
                    $mostra_modal_codigo = true;
                    $email_modal = $email;
                }
            }
        }
    }
}

// Processar novo registro
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['verificar_codigo'])) {
    // Guarda os dados atuais do banco para usar como fallback em modo de edição.
    $dados_atuais = $dados_usuario;

    $nome = trim($_POST['nome'] ?? '');
    $nascimento = trim($_POST['nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tags = trim($_POST['tags'] ?? '');

    $remover_foto = isset($_POST['remover_foto']) && $_POST['remover_foto'] === '1';

    // Se for edição, e um campo do formulário vier vazio, mantém o valor que já estava no banco.
    // Isso permite que o usuário edite apenas um campo sem ter que preencher todos os outros novamente.
    if ($is_edicao) {
        $nome = $nome ?: ($dados_atuais['nome'] ?? '');
        $nascimento = $nascimento ?: ($dados_atuais['data_nascimento'] ?? '');
        $telefone = $telefone ?: ($dados_atuais['telefone'] ?? '');
        $email = $email ?: ($dados_atuais['email'] ?? '');
        $endereco = $endereco ?: ($dados_atuais['endereco'] ?? '');
        $descricao = $descricao ?: ($dados_atuais['descricao'] ?? '');
        $tags = $tags ?: ($dados_atuais['tags'] ?? '');
    }

    // Mantém o tipo_base original se estiver em modo edição (pois o campo não vai no POST)
    $tipo_base_post = $_POST['tipo_base'] ?? ($dados_atuais['tipo_base'] ?? 'cliente');

    // Na edição, a senha só é processada se preenchida.
    if ($is_edicao && empty($senha)) {
        $senha = ''; 
    }

    // Atualiza $dados_usuario com os dados mesclados para que o formulário seja
    // repreenchido corretamente em caso de erro e para a lógica de salvamento.
    // Removido o fallback ?: para permitir remoção de tags e limpeza de campos.
    $dados_usuario = array_merge($dados_atuais, [
        'nome' => $nome, 'data_nascimento' => $nascimento, 'telefone' => $telefone,
        'email' => $email, 'endereco' => $endereco, 'descricao' => $descricao,
        'tags' => $tags, 'tipo_base' => $tipo_base_post
    ]);

    // 1. Definir caminho absoluto para a pasta img
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;

    if ($is_edicao && empty($senha)) {
        $senha = ''; // Na edição, a senha pode ser mantida se vazia
    }

    // --- Validação de Upload de Imagem (SEM SALVAR AINDA) ---
    // O valor padrão para foto de perfil é NULL, para que o template possa exibir um ícone.
    $imagemPadrao = null;
    $caminhoFotoPerfil = null; // Começa nulo para identificar se houve novo upload
    $erroUpload = '';
    $imagemTemporaria = null;

    // Tenta capturar de 'foto_perfil' ou apenas 'foto' para evitar erros de digitação no HTML
    $fileKey = isset($_FILES['foto_perfil']) ? 'foto_perfil' : (isset($_FILES['foto']) ? 'foto' : null);

    // Se a remoção for solicitada, não processamos o upload de um novo arquivo.
    if (!$remover_foto && $fileKey && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES[$fileKey]['tmp_name'];

            if (is_uploaded_file($fileTmpPath)) {
                if (!class_exists('finfo')) {
                    $erroUpload = "⚠️ A extensão 'fileinfo' não está ativa no seu PHP. Habilite-a no php.ini do XAMPP.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($fileTmpPath);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

                    if (!in_array($mimeType, $allowedMimes)) {
                        $erroUpload = "⚠️ Formato inválido. Use apenas JPG, PNG ou GIF.";
                    } elseif ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) {
                        $erroUpload = "⚠️ A imagem de perfil não pode ser maior que 5MB.";
                    } else {
                        // Validação passou, armazenar temporariamente
                        $imagemTemporaria = [
                            'tmp' => $fileTmpPath,
                            'ext' => strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION))
                        ];
                    }
                }
            } else {
                $erroUpload = "⚠️ Erro interno: O arquivo temporário não é um upload válido.";
            }
        } else {
            $error_code = $_FILES[$fileKey]['error'];
            if ($error_code === UPLOAD_ERR_INI_SIZE || $error_code === UPLOAD_ERR_FORM_SIZE) {
                $erroUpload = "⚠️ O arquivo é muito grande para o servidor.";
            } else {
                $erroUpload = "⚠️ Erro no upload da imagem (Código: $error_code).";
            }
        }
    }

    // Se há erro de upload, definir erro
    if ($erroUpload !== '') {
        $erro = $erroUpload;
    }

    // --- Validações e Registro ---
    if ($erro === '') {
        $campos_obrigatorios_vazios = (
            $nascimento === '' || $telefone === '' || $email === '' || $endereco === '' || $descricao === '' || (!$is_edicao && $senha === '')
        );

        // O campo 'nome' é obrigatório para ambos os tipos.
        if ($nome === '') {
            $campos_obrigatorios_vazios = true;
        }

        if ($campos_obrigatorios_vazios) {
            $erro = "⚠️ Preencha todos os campos obrigatórios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "⚠️ Informe um e-mail válido.";
        } else {
            // Em modo de edição, $id_alvo contém o ID do usuário que está sendo editado.
            $id_a_ignorar = $is_edicao ? $id_alvo : null;

            // Verificar se email já existe (ignorando o ID atual se for edição)
            $sqlCheck = "SELECT id FROM usuarios WHERE email = :email";
            if ($id_a_ignorar) {
                $sqlCheck .= " AND id != :id";
            }

            $checkStmt = $pdo->prepare($sqlCheck);
            $checkStmt->bindValue(':email', $email);
            if ($id_a_ignorar) $checkStmt->bindValue(':id', $id_a_ignorar);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $erro = "⚠️ Este e-mail já está registrado.";
            } else {
                // Gerar token numérico de 6 dígitos
                $token = sprintf("%06d", mt_rand(0, 999999));

                // Hash seguro da senha
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                // Processar salvamento da imagem antes de interagir com o banco
                if ($imagemTemporaria) {
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $newFileName = uniqid('perfil_', true) . '.' . $imagemTemporaria['ext'];
                    if (move_uploaded_file($imagemTemporaria['tmp'], $uploadDir . $newFileName)) {
                        $caminhoFotoPerfil = $newFileName;
                    } else {
                        $erro = "⚠️ Erro ao salvar a imagem de perfil no servidor.";
                    }
                }

                if ($erro === '') {
                    try {
                        // Iniciar transação
                        $pdo->beginTransaction();

                        if ($is_edicao) {
                            // A variável $id_alvo já contém o ID correto do usuário a ser editado (seja o próprio usuário ou outro, editado pelo admin).
                            $usuarioId = $id_alvo;

                            if (!$usuarioId) throw new Exception("ID do usuário a ser atualizado é inválido.");
                            
                            $tabela = ($tipo_base_post === 'profissional') ? 'profissionais' : 'clientes';
                            $oldFotoPerfil = null;
                            
                            // Buscar a foto atual para decidir se mantém ou deleta
                            $stmtOldPhoto = $pdo->prepare("SELECT foto_perfil FROM $tabela WHERE usuario_id = :id");
                            $stmtOldPhoto->bindValue(':id', $usuarioId);
                            $stmtOldPhoto->execute();
                            $resultOldPhoto = $stmtOldPhoto->fetch(PDO::FETCH_ASSOC);
                            $fotoAtualNoBanco = $resultOldPhoto['foto_perfil'] ?? null;

                            // Se uma nova foto foi enviada ou a remoção foi solicitada, a foto antiga é marcada para exclusão.
                            if ($caminhoFotoPerfil || $remover_foto) {
                                $oldFotoPerfil = $fotoAtualNoBanco;
                            }

                            // 1. Atualizar e-mail e senha (se fornecida)
                            $is_editing_self = ($usuarioId == ($_SESSION['usuario_id'] ?? null));
                            $sql_update_parts = ["email = :email"];
                            if (!empty($senha)) {
                                $sql_update_parts[] = "senha = :senha";
                            }
                            // Se o admin estiver editando o próprio perfil, garantir que o status permaneça 'ativo'.
                            if ($is_admin && $is_editing_self) {
                                $sql_update_parts[] = "status = 'ativo'";
                            }
                            $sqlU = "UPDATE usuarios SET " . implode(', ', $sql_update_parts) . " WHERE id = :id";
                            $stmt = $pdo->prepare($sqlU);
                            $stmt->bindValue(':email', $email);
                            if (!empty($senha)) $stmt->bindValue(':senha', $senhaHash);
                            $stmt->bindValue(':id', $usuarioId);
                            $stmt->execute();

                            // 2. Atualizar dados do cliente (detecta se é cliente ou contratante)
                            if ($remover_foto) {
                                $fotoParaSalvar = $imagemPadrao; // null
                            } else {
                                // Usa a nova foto se houver, senão a atual, senão a padrão (null).
                                $fotoParaSalvar = $caminhoFotoPerfil ?: ($fotoAtualNoBanco ?: $imagemPadrao);
                            }

                            // GARANTIA PARA ADMINS: Se o registro na tabela de perfil não existir, nós o criamos agora.
                            $checkProfile = $pdo->prepare("SELECT 1 FROM $tabela WHERE usuario_id = :id");
                            $checkProfile->execute(['id' => $usuarioId]);
                            if ($checkProfile->rowCount() === 0) {
                                $sqlInsertBase = "INSERT INTO $tabela (usuario_id, nome, endereco, telefone, data_nascimento, descricao) 
                                                 VALUES (:id, :n, :e, :t, :d, :desc)";
                                $stmtBase = $pdo->prepare($sqlInsertBase);
                                $stmtBase->execute([
                                    'id' => $usuarioId, 'n' => $nome, 'e' => $endereco, 
                                    't' => $telefone, 'd' => $nascimento ?: '1900-01-01', 'desc' => $descricao
                                ]);
                            }
                            
                            $update_fields = [
                                "nome = :nome",
                                "endereco = :endereco",
                                "telefone = :telefone",
                                "data_nascimento = :nascimento",
                                "descricao = :descricao",
                                "foto_perfil = :foto"
                            ];

                            // O campo 'trabalho' (tags) só existe para profissionais.
                            if ($tipo_base_post === 'profissional') {
                                $update_fields[] = "trabalho = :trabalho";
                            }

                            $sqlC = "UPDATE $tabela SET " . implode(', ', $update_fields) . " WHERE usuario_id = :id";
                            $stmtC = $pdo->prepare($sqlC);
                            $stmtC->bindValue(':nome', $nome);
                            if ($tipo_base_post === 'profissional') {
                                $stmtC->bindValue(':trabalho', $tags);
                            }
                            $stmtC->bindValue(':endereco', $endereco);
                            $stmtC->bindValue(':telefone', $telefone);
                            $stmtC->bindValue(':nascimento', $nascimento);
                            $stmtC->bindValue(':descricao', $descricao);
                            $stmtC->bindValue(':foto', $fotoParaSalvar);
                            $stmtC->bindValue(':id', $usuarioId);
                            $stmtC->execute();

                            // Sincronização de Tags (Estilo YouTube)
                            if ($tipo_base_post === 'profissional') {
                                $stmtGetProfissional = $pdo->prepare("SELECT id FROM profissionais WHERE usuario_id = :uid");
                                $stmtGetProfissional->execute(['uid' => $usuarioId]);
                                $profissionalId = $stmtGetProfissional->fetchColumn();

                                if ($profissionalId) {
                                    // 1. Remove vínculos antigos para reconstruir a lista
                                    $pdo->prepare("DELETE FROM profissional_tags WHERE profissional_id = :pid")->execute(['pid' => $profissionalId]);

                                    // 2. Transforma a string "tag1, tag2" em array e remove duplicatas
                                    $tagsArray = array_unique(array_filter(array_map('trim', explode(',', $tags))));
                                    
                                    foreach ($tagsArray as $tagNome) {
                                        // 3. Insere a tag na tabela global se ela não existir (permite novas tags)
                                        $stmtTag = $pdo->prepare("INSERT IGNORE INTO tags (nome) VALUES (:nome)");
                                        $stmtTag->execute(['nome' => $tagNome]);
                                        
                                        // 4. Busca o ID da tag de forma segura
                                        $stmtGetTagId = $pdo->prepare("SELECT id FROM tags WHERE nome = :nome");
                                        $stmtGetTagId->execute(['nome' => $tagNome]);
                                        $tagId = $stmtGetTagId->fetchColumn();

                                        $pdo->prepare("INSERT IGNORE INTO profissional_tags (profissional_id, tag_id) VALUES (:pid, :tid)")
                                            ->execute(['pid' => $profissionalId, 'tid' => $tagId]);
                                    }
                                }
                            }

                            $pdo->commit();
                            // Delete old photo after successful update and commit
                            if ($oldFotoPerfil && $oldFotoPerfil !== 'default_profile.png' && file_exists($uploadDir . $oldFotoPerfil)) {
                                unlink($uploadDir . $oldFotoPerfil);
                            }
                            // Atualiza os dados da sessão para refletir as mudanças no cabeçalho e boas-vindas imediatamente
                            if ($usuarioId == $_SESSION['usuario_id']) {
                                $_SESSION['usuario_nome'] = $nome;
                                $_SESSION['usuario_foto'] = $fotoParaSalvar;
                            }

                            // --- Processar Upload de Fotos do Trabalho (Portfólio) ---
                            if (isset($_FILES['fotos_trabalho']) && $dados_usuario['tipo_base'] === 'profissional') {
                                try {
                                    $files = $_FILES['fotos_trabalho'];
                                    
                                    // Busca o ID do profissional para vincular as fotos
                                    $stmtProfId = $pdo->prepare("SELECT id FROM profissionais WHERE usuario_id = :id");
                                    $stmtProfId->execute(['id' => $usuarioId]);
                                    $profData = $stmtProfId->fetch(PDO::FETCH_ASSOC);

                                    if ($profData) {
                                        $pid = $profData['id'];
                                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                                        $hasFinfo = class_exists('finfo');
                                        $finfo = $hasFinfo ? new finfo(FILEINFO_MIME_TYPE) : null;

                                        for ($i = 0; $i < count($files['name']); $i++) {
                                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                                $mimeType = $hasFinfo ? $finfo->file($files['tmp_name'][$i]) : $files['type'][$i];
                                                if (in_array($mimeType, $allowedMimes) && $files['size'][$i] <= 5 * 1024 * 1024) {
                                                    $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                                                    $novoNome = uniqid('trabalho_', true) . '.' . $ext;
                                                    
                                                    if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $novoNome)) {
                                                        $stmtIns = $pdo->prepare("INSERT INTO profissional_fotos (profissional_id, arquivo) VALUES (:pid, :arquivo)");
                                                        $stmtIns->execute(['pid' => $pid, 'arquivo' => $novoNome]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Se falhar, não impede a edição do perfil
                                    error_log("Erro ao fazer upload de fotos do trabalho: " . $e->getMessage());
                                }
                            }

                            header("Location: tela_inicial.php?sucesso=perfil_atualizado");
                            exit();
                        } else {
                            // Lógica de Novo Registro (INSERT)
                            // IMPORTANTE: Adicionado tipo_base para que o sistema saiba qual tabela consultar depois
                            $stmt = $pdo->prepare("INSERT INTO usuarios (email, senha, token, status, tipo_base) VALUES (:email, :senha, :token, :status, :tipo_base)");
                            $stmt->bindValue(':email', $email);
                            $stmt->bindValue(':senha', $senhaHash);
                            $stmt->bindValue(':token', $token);
                            $stmt->bindValue(':status', 'inativo');
                            $stmt->bindValue(':tipo_base', $tipo_base_post);
                            $stmt->execute();

                            $usuarioId = $pdo->lastInsertId();

                            // Para novo registro: se não subiu imagem, usa a padrão
                            $fotoParaSalvar = $caminhoFotoPerfil ?: $imagemPadrao;

                            // Salva na tabela correta baseada na escolha do usuário
                            $tabelaDestino = ($tipo_base_post === 'profissional') ? 'profissionais' : 'clientes';
                            
                            // Campos comuns a ambas as tabelas (conforme estrutura.sql)
                            $sqlColumns = 'usuario_id, nome, endereco, telefone, data_nascimento, descricao, foto_perfil';
                            $sqlValues = ':usuario_id, :nome, :endereco, :telefone, :nascimento, :descricao, :foto_perfil';

                            // Campos adicionais exclusivos do profissional
                            if ($tipo_base_post === 'profissional') {
                                $sqlColumns .= ', trabalho, cpf, endereco_trabalho';
                                $sqlValues .= ', :trabalho, :cpf, :endereco_trabalho';
                            }

                            $stmtInfo = $pdo->prepare("INSERT INTO $tabelaDestino ($sqlColumns) VALUES ($sqlValues)");
                            $stmtInfo->bindValue(':usuario_id', $usuarioId);
                            $stmtInfo->bindValue(':nome', $nome);
                            $stmtInfo->bindValue(':endereco', $endereco);
                            $stmtInfo->bindValue(':telefone', $telefone);
                            $stmtInfo->bindValue(':nascimento', $nascimento);
                            $stmtInfo->bindValue(':descricao', $descricao);
                            $stmtInfo->bindValue(':foto_perfil', $fotoParaSalvar);
                            
                            if ($tipo_base_post === 'profissional') {
                                $stmtInfo->bindValue(':trabalho', $tags);
                                $stmtInfo->bindValue(':cpf', $cpf);
                                $stmtInfo->bindValue(':endereco_trabalho', $endereco_trabalho);
                            }
                            $stmtInfo->execute();
                            
                            // Processar Tags Relacionais para o novo Profissional
                            if ($tipo_base_post === 'profissional') {
                                $profissionalId = $pdo->lastInsertId();
                                $tagsArray = array_unique(array_filter(array_map('trim', explode(',', $tags))));
                                foreach ($tagsArray as $tagNome) {
                                    // Insere a tag global se não existir
                                    $stmtTag = $pdo->prepare("INSERT IGNORE INTO tags (nome) VALUES (:nome)");
                                    $stmtTag->execute(['nome' => $tagNome]);
                                    
                                    // Busca o ID da tag de forma segura
                                    $stmtGetTagId = $pdo->prepare("SELECT id FROM tags WHERE nome = :nome");
                                    $stmtGetTagId->execute(['nome' => $tagNome]);
                                    $tagId = $stmtGetTagId->fetchColumn();
                                    
                                    // Cria o vínculo na tabela intermediária
                                    $pdo->prepare("INSERT IGNORE INTO profissional_tags (profissional_id, tag_id) VALUES (:pid, :tid)")
                                        ->execute(['pid' => $profissionalId, 'tid' => $tagId]);
                                }
                            }
                            
                            // A transação do banco de dados é confirmada ANTES do envio de e-mail.
                            // Isso garante que o usuário seja salvo mesmo se o e-mail falhar.
                            $pdo->commit();

                            try {
                                // Configuração de e-mail
                                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                                // $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;

                                $mail->isSMTP();
                                $mail->Host = 'smtp.gmail.com';
                                $mail->SMTPAuth = true;
                                $mail->Username = 'dominandoenem0@gmail.com';
                                $mail->Password = 'xgzj qtbt bzdt arfl';
                                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                                $mail->Port = 465;
                                $mail->CharSet = 'UTF-8';

                                $mail->setFrom('dominandoenem0@gmail.com', 'Mist Soluções');
                                $mail->addAddress($email, $nome);
                                $mail->Subject = "Confirme seu cadastro - Código de Verificação";
                                $mail->Body = "Seu código de verificação é: $token";
                                $mail->isHTML(false);

                                $mail->send();
                            } catch (Exception $e) {
                                // Fallback "Modo Acadêmico": se o e-mail falhar, mostra o código na tela.
                                $erro = "⚠️ Modo Acadêmico: Registro salvo. Use o código para ativar: <strong>$token</strong>";
                            }

                            $mostra_modal_codigo = true;
                            $email_modal = $email;
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        // Agora, este bloco captura principalmente erros de banco de dados, exibindo uma mensagem mais útil.
                        $erro = "⚠️ Erro ao processar registro: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

echo $twig->render('tela_registro.html', [
    'erro' => $erro,
    'mostra_modal_codigo' => $mostra_modal_codigo,
    'email_modal' => $email_modal,
    'user' => $dados_usuario,
    'is_edicao' => $is_edicao,
    'id_alvo' => $id_alvo // Passa o ID para o template (para o campo hidden)
]);
