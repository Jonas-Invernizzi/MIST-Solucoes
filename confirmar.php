<?php
require_once('carregar_pdo.php');

$erro = '';
$sucesso = '';

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $token = trim($_GET['token'] ?? '');
    $email = trim($_GET['email'] ?? '');

    if ($token === '' || $email === '') {
        $erro = "Token ou e-mail não fornecido.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "E-mail inválido.";
    } else {
        try {
            // Buscar usuário com token correspondente
            $stmt = $pdo->prepare("SELECT id, token, status FROM usuarios WHERE email = :email AND token = :token");
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':token', $token);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $erro = "❌ Token inválido ou expirado. Verifique o código ou o e-mail fornecido.";
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user['status'] === 'ativo') {
                    // Já foi confirmado antes, redirecionar para login
                    header("Location: tela_login.php?verificado=1");
                    exit();
                } else {
                    // Ativar usuário e limpar token
                    $updateStmt = $pdo->prepare("UPDATE usuarios SET status = :status, token = NULL WHERE id = :id");
                    $updateStmt->bindValue(':status', 'ativo');
                    $updateStmt->bindValue(':id', $user['id']);
                    $updateStmt->execute();

                    // Redirecionar para login com sucesso
                    header("Location: tela_login.php?verificado=1");
                    exit();
                }
            }
        } catch (Exception $e) {
            $erro = "Erro ao processar confirmação: " . $e->getMessage();
        }
    }
} else {
    $erro = "Método de requisição inválido.";
}

// Se chegou aqui com erro, mostrar página com erro
if ($erro) {
    // Mostrar página de erro
    $email = trim($_GET['email'] ?? '');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erro na Confirmação</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                padding: 40px;
                max-width: 500px;
                text-align: center;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
                font-size: 28px;
            }
            .message {
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
                line-height: 1.5;
            }
            .error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .button-group {
                margin-top: 30px;
                display: flex;
                gap: 10px;
                justify-content: center;
            }
            .btn {
                padding: 12px 30px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.3s ease;
                display: inline-block;
            }
            .btn-primary {
                background-color: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background-color: #5568d3;
                transform: translateY(-2px);
            }
            .icon {
                font-size: 48px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">❌</div>
            <h1>Erro na Confirmação</h1>
            <div class="message error">
                <?php echo htmlspecialchars($erro); ?>
            </div>
            <div class="button-group">
                <a href="tela_registro.php" class="btn btn-primary">Novo Registro</a>
                <a href="tela_inicial.php" class="btn btn-primary" style="background-color: #6c757d;">Voltar ao Início</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
