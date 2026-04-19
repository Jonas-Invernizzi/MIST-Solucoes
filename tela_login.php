<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

$erro = '';

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
        session_start();
        $_SESSION['usuario_id'] = $usuario['id'];
        header("Location: tela_index.php");
        exit();
    } else if($_SERVER["REQUEST_METHOD"] !== "GET") {
        $erro = "⚠️ Email ou senha inválidos.";
    }
}
echo $twig->render('tela_login.html', ['erro' => $erro]);
