<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');
require_once 'vendor/autoload.php';

$erro = '';
$mostra_modal_codigo = false;
$email_modal = '';
$dados_usuario = [];

// Carregar Assets do Sistema (Logo e Imagem Padrão)
$stmtAssets = $pdo->prepare("SELECT nome, arquivo, mime_type FROM sistema_assets WHERE nome IN ('logo', 'default_avatar')");
$stmtAssets->execute();
$assets = $stmtAssets->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

$logo_site = isset($assets['logo']) 
    ? 'imagem.php?tipo=asset&nome=logo'
    : '';

$imagem_padrao_blob = $assets['default_avatar']['arquivo'] ?? null;


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
                try {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                } catch (Exception $rb) {}
                
                $erro = "⚠️ Erro ao ativar conta: " . $e->getMessage();
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
    $trabalho = trim($_POST['tags'] ?? $_POST['trabalho'] ?? '');
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

                // 2. Tratar imagem de perfil (Salvar como BLOB ou usar a padrão)
                $fotoParaSalvar = $imagem_padrao_blob;
                if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                    $fotoParaSalvar = file_get_contents($_FILES['foto_perfil']['tmp_name']);
                }

                // 3. Inserir em profissionais (incluindo campos específicos da estrutura.sql)
                $sqlProfissional = "INSERT INTO profissionais 
                    (usuario_id, nome, cpf, data_nascimento, endereco, endereco_trabalho, telefone, descricao, trabalho, foto_perfil) 
                    VALUES 
                    (:uid, :nome, :cpf, :nasc, :end, :end_t, :tel, :desc, :trab, :foto)";
                
                $stmtProf = $pdo->prepare($sqlProfissional);
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

                // --- Processar Upload de Fotos do Trabalho (Portfólio) ---
                if (isset($_FILES['fotos_trabalho'])) {
                    try {
                        $files = $_FILES['fotos_trabalho'];
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                        $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
                        
                        $stmtPid = $pdo->prepare("SELECT id FROM profissionais WHERE usuario_id = :uid");
                        $stmtPid->execute(['uid' => $usuarioId]);
                        $pid = $stmtPid->fetchColumn();

                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $mimeType = $finfo ? $finfo->file($files['tmp_name'][$i]) : $files['type'][$i];
                                if (in_array($mimeType, $allowedMimes) && $files['size'][$i] <= 5 * 1024 * 1024) {
                                    try {
                                        $conteudoFoto = file_get_contents($files['tmp_name'][$i]);
                                        $stmtIns = $pdo->prepare("INSERT INTO profissional_fotos (profissional_id, arquivo) VALUES (:pid, :arquivo)");
                                        $stmtIns->bindValue(':pid', $pid, PDO::PARAM_INT);
                                        $stmtIns->bindValue(':arquivo', $conteudoFoto, PDO::PARAM_LOB);
                                        $stmtIns->execute();
                                    } catch (Exception $e) {
                                        error_log("Erro ao salvar foto no portfólio (cadastro): " . $e->getMessage());
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Erro no upload de portfólio em cadastro_profissional: " . $e->getMessage());
                    }
                }

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
                try {
                    if ($pdo && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (PDOException $rollbackEx) {
                    // Conexão perdida
                }

                if (strpos($e->getMessage(), 'server has gone away') !== false) {
                    $erro = "⚠️ Imagem muito grande. Ajuste o 'max_allowed_packet' no seu phpMyAdmin/MySQL.";
                } else {
                    $erro = "⚠️ Erro ao processar registro: " . $e->getMessage();
                }
            }
        }
    }
}

echo $twig->render('cadastro_profissional.html', [
    'erro' => $erro,
    'mostra_modal_codigo' => $mostra_modal_codigo,
    'email_modal' => $email_modal,
    'user' => $dados_usuario,
    'logo_site' => $logo_site
]);