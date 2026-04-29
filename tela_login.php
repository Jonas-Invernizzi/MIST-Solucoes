<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

$erro = '';
$sucesso = '';

// Verificar se vem de verificação bem-sucedida
if (isset($_GET['verificado']) && $_GET['verificado'] === '1') {
    $sucesso = "✅ E-mail confirmado com sucesso! Faça login com suas credenciais.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
} 
if ((!isset($email) || !isset($senha)) && $_SERVER["REQUEST_METHOD"] !== "GET") {
    $erro = "⚠️ Preencha todos os campos.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Verificar se email foi confirmado
        if ($usuario['status'] !== 'ativo') {
            $erro = "⚠️ E-mail não confirmado. Verifique seu e-mail para ativar a conta.";
        } else {
            session_start();
            $_SESSION['usuario_id'] = $usuario['id'];
            header("Location: tela_index.php");
            exit();
        }
    } else if($_SERVER["REQUEST_METHOD"] !== "GET") {
        $erro = "⚠️ Email ou senha inválidos.";
    }
}
echo $twig->render('tela_login.html', ['erro' => $erro, 'sucesso' => $sucesso]);
