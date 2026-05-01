<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

$erro = '';
$sucesso = '';
$mostra_modal_redefinir_senha = false; // Nova flag para o modal de redefinição de senha
$email_redefinir_modal = ''; // E-mail para exibir no modal de redefinição

// Verificar se vem de verificação bem-sucedida
if (isset($_GET['verificado']) && $_GET['verificado'] === '1') {
    $sucesso = "✅ E-mail confirmado com sucesso! Faça login com suas credenciais.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($action === 'request_password_reset') {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "⚠️ Por favor, insira um e-mail válido para redefinir a senha.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                $token = sprintf("%06d", mt_rand(0, 999999));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $updateStmt = $pdo->prepare("UPDATE usuarios SET reset_token = :token, reset_token_expires_at = :expires WHERE id = :id");
                $updateStmt->execute([':token' => $token, ':expires' => $expires, ':id' => $usuario['id']]);

                require_once 'vendor/autoload.php';
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
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Código de Redefinição de Senha - Mist Soluções';
                    $mail->Body = "Seu código de redefinição é: <strong>$token</strong><br>Ele expira em 1 hora.";
                    $mail->send();

                    $sucesso = "✅ Um código foi enviado para seu e-mail.";
                    $mostra_modal_redefinir_senha = true;
                    $email_redefinir_modal = $email;
                } catch (Exception $e) {
                    $erro = "⚠️ Erro ao enviar e-mail: " . $mail->ErrorInfo;
                }
            } else {
                $erro = "⚠️ E-mail não encontrado.";
            }
        }
    } elseif ($action === 'redefinir_senha_final') {
        $email_hidden = $_POST['email_hidden'] ?? '';
        $token_digitado = $_POST['token_digitado'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirmar_nova_senha = $_POST['confirmar_nova_senha'] ?? '';

        $mostra_modal_redefinir_senha = true;
        $email_redefinir_modal = $email_hidden;

        if (empty($token_digitado) || empty($nova_senha) || empty($confirmar_nova_senha)) {
            $erro = "⚠️ Preencha todos os campos.";
        } elseif ($nova_senha !== $confirmar_nova_senha) {
            $erro = "⚠️ As senhas não coincidem.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND reset_token = :token AND reset_token_expires_at > NOW()");
            $stmt->execute([':email' => $email_hidden, ':token' => $token_digitado]);

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE usuarios SET senha = :senha, reset_token = NULL, reset_token_expires_at = NULL WHERE id = :id");
                $update->execute([':senha' => $senhaHash, ':id' => $user['id']]);

                $sucesso = "✅ Senha alterada com sucesso!";
                $mostra_modal_redefinir_senha = false;
            } else {
                $erro = "⚠️ Código inválido ou expirado.";
            }
        }
    } else {
        // Fluxo de Login Normal
        if (empty($email) || empty($senha)) {
            $erro = "⚠️ Preencha e-mail e senha.";
        } else {
            $stmt = $pdo->prepare("
                SELECT u.*, COALESCE(c.foto_perfil, co.foto_perfil) as foto_perfil
                FROM usuarios u
                LEFT JOIN clientes c ON u.id = c.usuario_id
                LEFT JOIN contratantes co ON u.id = co.usuario_id
                WHERE u.email = :email
            ");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                if ($usuario['status'] !== 'ativo') {
                    $erro = "⚠️ E-mail não confirmado.";
                } else {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $foto = $usuario['foto_perfil'];
                    $_SESSION['usuario_foto'] = ($foto && file_exists(__DIR__ . "/img/$foto")) ? $foto : 'default_profile.png';
                    header("Location: tela_index.php");
                    exit();
                }
            } else {
                $erro = "⚠️ Credenciais inválidas.";
            }
        }
    }
}

echo $twig->render('tela_login.html', [
    'erro' => $erro,
    'sucesso' => $sucesso,
    'mostra_modal_redefinir_senha' => $mostra_modal_redefinir_senha,
    'email_redefinir_modal' => $email_redefinir_modal
]);
