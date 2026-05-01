<?php
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

// Verificar se o usuário já está logado para modo de edição
if (isset($_SESSION['usuario_id'])) {
    $is_edicao = true;

    // Primeiro, identificamos o tipo_base para saber qual tabela consultar
    $stmtUser = $pdo->prepare("SELECT email, tipo_base FROM usuarios WHERE id = :id");
    $stmtUser->execute(['id' => $_SESSION['usuario_id']]);
    $uBase = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($uBase) {
        $tabela = ($uBase['tipo_base'] === 'contratante') ? 'contratantes' : 'clientes';
        $stmt = $pdo->prepare("
            SELECT u.email, u.tipo_base, t.* 
            FROM usuarios u 
            LEFT JOIN $tabela t ON u.id = t.usuario_id 
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $_SESSION['usuario_id']]);
        $dados_usuario = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $nome = trim($_POST['nome'] ?? '');
    $nascimento = trim($_POST['nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tags = trim($_POST['tags'] ?? '');

    // Mantém o tipo_base original se estiver em modo edição (pois o campo não vai no POST)
    $tipo_base_post = $_POST['tipo_base'] ?? ($dados_usuario['tipo_base'] ?? 'cliente');

    // Preservar os dados digitados para o formulário (em caso de erro ou retorno do modal)
    $dados_usuario = [
        'nome' => $nome,
        'data_nascimento' => $nascimento,
        'telefone' => $telefone,
        'email' => $email,
        'endereco' => $endereco,
        'descricao' => $descricao,
        'tags' => $tags,
        'tipo_base' => $tipo_base_post,
        'foto_perfil' => $dados_usuario['foto_perfil'] ?? null
    ];

    // 1. Definir caminho absoluto para a pasta img
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;

    if ($is_edicao && empty($senha)) {
        $senha = ''; // Na edição, a senha pode ser mantida se vazia
    }

    // --- Validação de Upload de Imagem (SEM SALVAR AINDA) ---
    $imagemPadrao = 'default_profile.png';
    $caminhoFotoPerfil = null; // Começa nulo para identificar se houve novo upload
    $erroUpload = '';
    $imagemTemporaria = null;

    // Tenta capturar de 'foto_perfil' ou apenas 'foto' para evitar erros de digitação no HTML
    $fileKey = isset($_FILES['foto_perfil']) ? 'foto_perfil' : (isset($_FILES['foto']) ? 'foto' : null);

    if ($fileKey && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
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
        if ($nome === '' || $nascimento === '' || $telefone === '' || $email === '' || $endereco === '' || $descricao === '' || (!$is_edicao && $senha === '')) {
            $erro = "⚠️ Preencha todos os campos obrigatórios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "⚠️ Informe um e-mail válido.";
        } else {
            // Verificar se email já existe (ignorando o ID atual se for edição)
            $sqlCheck = "SELECT id FROM usuarios WHERE email = :email";
            if ($is_edicao) $sqlCheck .= " AND id != :id";


            $checkStmt = $pdo->prepare($sqlCheck);
            $checkStmt->bindValue(':email', $email);
            if ($is_edicao) $checkStmt->bindValue(':id', $_SESSION['usuario_id']);
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
                            $usuarioId = $_SESSION['usuario_id'];

                            $oldFotoPerfil = null;
                            $tabela = ($dados_usuario['tipo_base'] === 'contratante') ? 'contratantes' : 'clientes';

                            // Buscar a foto atual para decidir se mantém ou deleta
                            $stmtOldPhoto = $pdo->prepare("SELECT foto_perfil FROM $tabela WHERE usuario_id = :id");
                            $stmtOldPhoto->bindValue(':id', $usuarioId);
                            $stmtOldPhoto->execute();
                            $resultOldPhoto = $stmtOldPhoto->fetch(PDO::FETCH_ASSOC);
                            $fotoAtualNoBanco = $resultOldPhoto['foto_perfil'] ?? null;

                            if ($caminhoFotoPerfil) {
                                $oldFotoPerfil = $fotoAtualNoBanco;
                            }

                            // 1. Atualizar e-mail e senha (se fornecida)
                            $sqlU = "UPDATE usuarios SET email = :email" . (!empty($senha) ? ", senha = :senha" : "") . " WHERE id = :id";
                            $stmt = $pdo->prepare($sqlU);
                            $stmt->bindValue(':email', $email);
                            if (!empty($senha)) $stmt->bindValue(':senha', $senhaHash);
                            $stmt->bindValue(':id', $usuarioId);
                            $stmt->execute();

                            // 2. Atualizar dados do cliente (detecta se é cliente ou contratante)
                            // Se não houve upload novo e o banco está vazio, coloca a padrão. Se já tem, mantém a atual.
                            $fotoParaSalvar = $caminhoFotoPerfil ?: ($fotoAtualNoBanco ?: $imagemPadrao);

                            $sqlC = "UPDATE $tabela SET nome = :nome, endereco = :endereco, telefone = :telefone, data_nascimento = :nascimento, descricao = :descricao, foto_perfil = :foto WHERE usuario_id = :id";

                            $stmtC = $pdo->prepare($sqlC);
                            $stmtC->bindValue(':nome', $nome);
                            $stmtC->bindValue(':endereco', $endereco);
                            $stmtC->bindValue(':telefone', $telefone);
                            $stmtC->bindValue(':nascimento', $nascimento);
                            $stmtC->bindValue(':descricao', $descricao);
                            $stmtC->bindValue(':foto', $fotoParaSalvar);
                            $stmtC->bindValue(':id', $usuarioId);
                            $stmtC->execute();

                            $pdo->commit();
                            // Delete old photo after successful update and commit
                            if ($oldFotoPerfil && $oldFotoPerfil !== $imagemPadrao && file_exists($uploadDir . $oldFotoPerfil)) {
                                unlink($uploadDir . $oldFotoPerfil);
                            }
                            $_SESSION['usuario_foto'] = $fotoParaSalvar;
                            header("Location: tela_index.php?sucesso=perfil_atualizado");
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
                            $tabelaDestino = ($tipo_base_post === 'contratante') ? 'contratantes' : 'clientes';
                            $stmtCliente = $pdo->prepare("INSERT INTO $tabelaDestino (usuario_id, nome, endereco, telefone, data_nascimento, descricao, foto_perfil) 
                                                     VALUES (:usuario_id, :nome, :endereco, :telefone, :nascimento, :descricao, :foto_perfil)");
                            $stmtCliente->bindValue(':usuario_id', $usuarioId);
                            $stmtCliente->bindValue(':nome', $nome);
                            $stmtCliente->bindValue(':endereco', $endereco);
                            $stmtCliente->bindValue(':telefone', $telefone);
                            $stmtCliente->bindValue(':nascimento', $nascimento);
                            $stmtCliente->bindValue(':descricao', $descricao);
                            $stmtCliente->bindValue(':foto_perfil', $fotoParaSalvar);
                            $stmtCliente->execute();

                            // Efetuamos o commit aqui. Agora o usuário e a imagem 
                            // estão oficialmente registrados no banco de dados.
                            $pdo->commit();

                            // Configuração de e-mail
                            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = 'smtp.gmail.com';
                                $mail->SMTPAuth = true;
                                $mail->Username = 'dominandoenem0@gmail.com';
                                $mail->Password = 'xgzj qtbt bzdt arfl';
                                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = 587;
                                $mail->CharSet = 'UTF-8';

                                $mail->setFrom('dominandoenem0@gmail.com', 'Mist Soluções');
                                $mail->addAddress($email, $nome);
                                $mail->Subject = "Confirme seu cadastro - Código de Verificação";
                                $mail->Body = "Seu código de verificação é: $token";
                                $mail->isHTML(false);

                                $mail->send();
                                $mostra_modal_codigo = true;
                                $email_modal = $email;
                            } catch (Exception $e) {
                                // O registro já foi salvo, mas avisamos sobre o erro no e-mail
                                $erro = "⚠️ Cadastro realizado, mas houve um erro ao enviar o e-mail: " . $mail->ErrorInfo;
                                $mostra_modal_codigo = true; // Permite que o usuário tente validar se já tiver o código ou souber como proceder
                                $email_modal = $email;
                            }
                        }
                    } catch (\PHPMailer\PHPMailer\Exception $e) {
                        $erro = "⚠️ Erro PHPMailer: " . $mail->ErrorInfo;
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $erro = "⚠️ Erro ao processar seu registro: " . $e->getMessage();
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
    'is_edicao' => $is_edicao
]);
