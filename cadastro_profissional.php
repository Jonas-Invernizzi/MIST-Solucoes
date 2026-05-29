<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');
require_once 'vendor/autoload.php';

$erro = '';
$mostra_modal_codigo = false;
$email_modal = '';
$dados_usuario = [];

// Processar verificação de código (mantendo o fluxo de ativação de conta)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verificar_codigo'])) {
    $codigo = trim($_POST['codigo'] ?? '');
    $email = trim($_POST['email_hidden'] ?? '');

    if ($codigo === '' || strlen($codigo) !== 6 || !ctype_digit($codigo)) {
        $erro = "⚠️ Informe um código de 6 dígitos válido.";
        $mostra_modal_codigo = true;
        $email_modal = $email;
    } else {
        $stmt = $pdo->prepare("SELECT id, token FROM usuarios WHERE email = :email AND status = 'inativo'");
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || $codigo !== $usuario['token']) {
            $erro = "❌ Código incorreto ou e-mail já confirmado.";
            $mostra_modal_codigo = true;
            $email_modal = $email;
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE usuarios SET status = 'ativo', token = NULL WHERE id = :id")
                    ->execute(['id' => $usuario['id']]);
                $pdo->commit();
                header("Location: tela_login.php?verificado=1");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = "⚠️ Erro ao ativar conta.";
            }
        }
    }
}

// Processar registro de profissional
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['verificar_codigo'])) {
    $nome = trim($_POST['nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $telefone = trim($_POST['telefone'] ?? '');
    $nascimento = $_POST['nascimento'] ?? '';
    $endereco = trim($_POST['endereco'] ?? '');
    $endereco_trabalho = trim($_POST['endereco_trabalho'] ?? '');
    $trabalho = trim($_POST['trabalho'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    $dados_usuario = $_POST; // Preservar dados para o formulário

    if (empty($nome) || empty($email) || empty($senha) || empty($cpf) || empty($trabalho)) {
        $erro = "⚠️ Preencha todos os campos obrigatórios.";
    } else {
        // Verificar se e-mail existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->rowCount() > 0) {
            $erro = "⚠️ Este e-mail já está cadastrado.";
        } else {
            try {
                $pdo->beginTransaction();

                $token = sprintf("%06d", mt_rand(0, 999999));
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                // 1. Criar usuário (tipo_base = profissional)
                $stmtUser = $pdo->prepare("INSERT INTO usuarios (email, senha, token, status, tipo_base) VALUES (:email, :senha, :token, 'inativo', 'profissional')");
                $stmtUser->execute([
                    'email' => $email,
                    'senha' => $senhaHash,
                    'token' => $token
                ]);
                $usuarioId = $pdo->lastInsertId();

                // 2. Tratar imagem de perfil
                $fotoParaSalvar = null;
                if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR;
                    $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
                    $newFileName = uniqid('perfil_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $uploadDir . $newFileName)) {
                        $fotoParaSalvar = $newFileName;
                    }
                }

                // 3. Inserir em profissionais (incluindo campos específicos da estrutura.sql)
                $sqlContratante = "INSERT INTO profissionais 
                    (usuario_id, nome, cpf, data_nascimento, endereco, endereco_trabalho, telefone, descricao, trabalho, foto_perfil) 
                    VALUES 
                    (:uid, :nome, :cpf, :nasc, :end, :end_t, :tel, :desc, :trab, :foto)";
                
                $stmtProf = $pdo->prepare($sqlContratante);
                $stmtProf->execute([
                    'uid'   => $usuarioId,
                    'nome'  => $nome,
                    'cpf'   => $cpf,
                    'nasc'  => $nascimento,
                    'end'   => $endereco,
                    'end_t' => $endereco_trabalho,
                    'tel'   => $telefone,
                    'desc'  => $descricao,
                    'trab'  => $trabalho,
                    'foto'  => $fotoParaSalvar
                ]);

                $pdo->commit();

                // Envio de E-mail
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
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
                    $mail->Subject = "Verifique seu Perfil Profissional";
                    $mail->Body = "Seu código de verificação é: $token";
                    $mail->send();
                    
                    $mostra_modal_codigo = true;
                    $email_modal = $email;
                } catch (Exception $e) {
                    $erro = "⚠️ Modo Acadêmico: Registro salvo. Código: <strong>$token</strong>";
                    $mostra_modal_codigo = true;
                    $email_modal = $email;
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erro = "⚠️ Erro ao processar registro: " . $e->getMessage();
            }
        }
    }
}

echo $twig->render('cadastro_profissional.html', [
    'erro' => $erro,
    'mostra_modal_codigo' => $mostra_modal_codigo,
    'email_modal' => $email_modal,
    'user' => $dados_usuario
]);