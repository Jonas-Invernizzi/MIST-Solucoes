<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');
// É provável que a falta desta linha esteja causando a falha no envio de e-mail.
require_once 'vendor/autoload.php'; 

$erro = '';
$mostra_modal_codigo = false;
$email_modal = '';
$usuario_id_modal = '';

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
                $updateStmt = $pdo->prepare("UPDATE usuarios SET status = :status, token = NULL WHERE id = :id");
                $updateStmt->bindValue(':status', 'ativo');
                $updateStmt->bindValue(':id', $usuario['id']);
                $updateStmt->execute();

                // Redirecionar para login com sucesso
                header("Location: tela_login.php?verificado=1");
                exit();
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

    // --- Lógica de Upload de Imagem ---
    $caminhoFotoPerfil = null;
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/imagens_perfil/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['foto_perfil']['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

        if (in_array($mimeType, $allowedMimes)) {
            if ($_FILES['foto_perfil']['size'] <= 5 * 1024 * 1024) { // Max 5MB
                $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
                $newFileName = uniqid('perfil_', true) . '.' . $ext;
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destination)) {
                    $caminhoFotoPerfil = $destination;
                } else {
                    $erro = "⚠️ Houve um erro ao salvar sua imagem de perfil.";
                }
            } else {
                $erro = "⚠️ A imagem de perfil não pode ser maior que 5MB.";
            }
        } else {
            $erro = "⚠️ Formato de imagem inválido. Apenas JPG, PNG e GIF são permitidos.";
        }
    } elseif (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $erro = "⚠️ Ocorreu um erro no upload da imagem: código " . $_FILES['foto_perfil']['error'];
    }

    // --- Validações e Registro ---
    if ($erro === '') {
        if ($nome === '' || $nascimento === '' || $telefone === '' || $email === '' || $endereco === '' || $descricao === '' || $senha === '') {
            $erro = "⚠️ Preencha todos os campos obrigatórios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "⚠️ Informe um e-mail válido.";
        } else {
        // Verificar se email já existe
        $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
        $checkStmt->bindValue(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $erro = "⚠️ Este e-mail já está registrado.";
        } else {
            // Gerar token numérico de 6 dígitos
            $token = sprintf("%06d", mt_rand(0, 999999));
            
            // Hash seguro da senha
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            try {
                // Iniciar transação
                $pdo->beginTransaction();

                // Inserir usuário
                $stmt = $pdo->prepare("INSERT INTO usuarios (email, senha, token, status) VALUES (:email, :senha, :token, :status)");
                $stmt->bindValue(':email', $email);
                $stmt->bindValue(':senha', $senhaHash);
                $stmt->bindValue(':token', $token);
                $stmt->bindValue(':status', 'inativo');
                $stmt->execute();

                $usuarioId = $pdo->lastInsertId();

                // Inserir dados complementares do cliente
                $stmtCliente = $pdo->prepare("INSERT INTO clientes (usuario_id, nome, endereco, telefone, data_nascimento, descricao, foto_perfil) VALUES (:usuario_id, :nome, :endereco, :telefone, :nascimento, :descricao, :foto_perfil)");
                $stmtCliente->bindValue(':usuario_id', $usuarioId);
                $stmtCliente->bindValue(':nome', $nome);
                $stmtCliente->bindValue(':endereco', $endereco);
                $stmtCliente->bindValue(':telefone', $telefone);
                $stmtCliente->bindValue(':nascimento', $nascimento);
                $stmtCliente->bindValue(':descricao', $descricao);
                $stmtCliente->bindValue(':foto_perfil', $caminhoFotoPerfil, $caminhoFotoPerfil ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmtCliente->execute();

                // Enviar email com token
                $destinatario = $email;
                $assunto = "Confirme seu cadastro - Código de Verificação";
                
                $mensagem = "Olá $nome,\n\n";
                $mensagem .= "Obrigado por se registrar na Mist Soluções!\n\n";
                $mensagem .= "Seu código de verificação é: " . $token . "\n\n";
                $mensagem .= "Digite este código na tela para ativar sua conta.\n\n";
                $mensagem .= "Este código expira em 24 horas.\n\n";
                $mensagem .= "Atenciosamente,\n";
                $mensagem .= "Equipe Mist Soluções";

                // NOTA DE SEGURANÇA: É altamente recomendável mover estas credenciais para variáveis de ambiente ou um arquivo de configuração seguro, em vez de deixá-las no código.
                // Configuração do PHPMailer com credenciais do Gmail
                $smtpHost = 'smtp.gmail.com';
                $smtpUser = 'dominandoenem0@gmail.com';
                $smtpPass = 'xgzj qtbt bzdt arfl';
                $smtpPort = 587;

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $smtpPort;
                $mail->CharSet = 'UTF-8';
                
                // Configurar remetente
                $mail->setFrom('dominandoenem0@gmail.com', 'Mist Soluções');
                $mail->addAddress($destinatario, $nome);
                $mail->Subject = $assunto;
                $mail->Body = $mensagem;
                $mail->isHTML(false);

                // Enviar email
                if ($mail->send()) {
                    $pdo->commit();
                    // Mostrar modal de verificação
                    $mostra_modal_codigo = true;
                    $email_modal = $email;
                } else {
                    $pdo->rollBack();
                    $erro = "⚠️ Erro ao enviar e-mail de confirmação. Tente novamente.";
                }

            } catch (\PHPMailer\PHPMailer\Exception $e) {
                $pdo->rollBack();
                $erro = "⚠️ Erro ao enviar e-mail: " . $e->getMessage();
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = "⚠️ Erro ao processar seu registro: " . $e->getMessage();
            }
        }
        }
    }
}

echo $twig->render('tela_registro.html', [
    'erro' => $erro,
    'mostra_modal_codigo' => $mostra_modal_codigo,
    'email_modal' => $email_modal
]);
