<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

$erro = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $nascimento = trim($_POST['nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tags = trim($_POST['tags'] ?? '');

    if ($nome === '' || $nascimento === '' || $telefone === '' || $email === '' || $endereco === '' || $descricao === '' || $senha === '') {
        $erro = "⚠️ Preencha todos os campos obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "⚠️ Informe um e-mail válido.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO usuarios (email, senha, token) VALUES (:email, :senha, :token)");

        $hashedSenha = password_hash($senha, PASSWORD_DEFAULT);
        $token = md5(uniqid(rand(), true));

        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':senha', $hashedSenha);
        $stmt->bindValue(':token', $token);

        $stmt->execute();

        $destinatario = $email;
        $assunto = "Confirme seu cadastro";
        $link = "http://localhost/3Info/MIST-Solucoes/confirmar.php?token=" . $token;
        $mensagem = "Olá $nome,\n\nObrigado por se registrar! Por favor, clique no link abaixo para confirmar seu cadastro:\n$link\n\nAtenciosamente,\nEquipe Mist Soluções";

        // Envio via PHPMailer (SMTP) - configure abaixo
        // Substitua os valores de SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_PORT conforme seu provedor
        $smtpHost = 'smtp.example.com';
        $smtpUser = 'seu_usuario@example.com';
        $smtpPass = 'sua_senha';
        $smtpPort = 587; // 587 para STARTTLS, 465 para SMTPS

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;

            $mail->setFrom('no-reply@seusite.com.br', 'Mist Soluções');
            $mail->addAddress($destinatario, $nome);
            $mail->Subject = $assunto;
            $mail->Body = $mensagem;

            $mail->send();
            echo "Verifique seu e-mail!";
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // Em desenvolvimento, mostrar erro detalhado; em produção, logue em arquivo
            echo "Erro ao enviar e-mail: " . $e->getMessage();
        }
    }
}
echo $twig->render('tela_registro.html', ['erro' => $erro]);
