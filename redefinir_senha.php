<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');
require_once 'vendor/autoload.php'; // Para PHPMailer, se necessário para relatar erros, mas não para enviar aqui

$erro = '';
$sucesso = '';
$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$show_reset_form = false;

// Lidar com requisição GET (exibir formulário)
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (empty($email) || empty($token)) {
        $erro = "⚠️ Link de redefinição inválido ou incompleto.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "⚠️ E-mail inválido.";
    } else {
        // Verificar validade do token
        $stmt = $pdo->prepare("SELECT id, reset_token_expires_at FROM usuarios WHERE email = :email AND reset_token = :token");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $erro = "⚠️ Token de redefinição inválido ou já utilizado.";
        } else {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            $expires = strtotime($usuario['reset_token_expires_at']);

            if (time() > $expires) {
                $erro = "⚠️ O link de redefinição expirou. Por favor, solicite um novo.";
            } else {
                $show_reset_form = true; // Token é válido, exibir formulário de redefinição de senha
            }
        }
    }
}

// Lidar com requisição POST (processar nova senha)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $token = $_POST['token'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

    if (empty($email) || empty($token) || empty($nova_senha) || empty($confirma_senha)) {
        $erro = "⚠️ Por favor, preencha todos os campos.";
        $show_reset_form = true; // Manter formulário aberto
    } elseif ($nova_senha !== $confirma_senha) {
        $erro = "⚠️ As senhas não coincidem.";
        $show_reset_form = true; // Manter formulário aberto
    } elseif (strlen($nova_senha) < 6) { // Verificação básica de força da senha
        $erro = "⚠️ A nova senha deve ter pelo menos 6 caracteres.";
        $show_reset_form = true; // Manter formulário aberto
    } else {
        // Re-verificar validade do token antes de atualizar a senha
        $stmt = $pdo->prepare("SELECT id, reset_token_expires_at FROM usuarios WHERE email = :email AND reset_token = :token");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $erro = "⚠️ Token de redefinição inválido ou já utilizado.";
        } else {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            $expires = strtotime($usuario['reset_token_expires_at']);

            if (time() > $expires) {
                $erro = "⚠️ O link de redefinição expirou. Por favor, solicite um novo.";
            } else {
                // Atualizar senha e limpar token
                $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE usuarios SET senha = :senha, reset_token = NULL, reset_token_expires_at = NULL WHERE id = :id");
                $updateStmt->bindParam(':senha', $senhaHash);
                $updateStmt->bindParam(':id', $usuario['id']);
                $updateStmt->execute();

                $sucesso = "✅ Sua senha foi redefinida com sucesso! Você já pode fazer login.";
                header("Location: tela_login.php?sucesso=" . urlencode($sucesso));
                exit();
            }
        }
    }
}

echo $twig->render('redefinir_senha.html', [
    'erro' => $erro,
    'sucesso' => $sucesso,
    'email' => $email,
    'token' => $token,
    'show_reset_form' => $show_reset_form
]);
?>