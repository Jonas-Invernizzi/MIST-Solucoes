<?php
require_once('carregar_pdo.php');

$admin_email = 'admin@mist.com';
$admin_senha = 'admin67'; 
$admin_nome = 'Admin MIST';
$admin_tipo = 'contratante';

echo "<!DOCTYPE html><html><head><title>Criação de Admin</title><link rel='stylesheet' href='templates/css/base.css'></head><body style='padding: 20px;'>";
echo "<h1>Iniciando script de criação de admin...</h1>";

try {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute([':email' => $admin_email]);

    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Usuário admin ('" . htmlspecialchars($admin_email) . "') já existe. Nenhuma ação foi tomada.</p>";
    } else {
        echo "<p>Usuário admin não encontrado. Criando...</p>";
        

        $pdo->beginTransaction();

        $senhaHash = password_hash($admin_senha, PASSWORD_DEFAULT);
        
        $stmtUser = $pdo->prepare(
            "INSERT INTO usuarios (email, senha, tipo_base, status) VALUES (:email, :senha, :tipo_base, 'ativo')"
        );
        $stmtUser->execute([
            ':email' => $admin_email,
            ':senha' => $senhaHash,
            ':tipo_base' => $admin_tipo
        ]);

        $usuarioId = $pdo->lastInsertId();
        echo "<p>Registro criado na tabela 'usuarios' com ID: $usuarioId</p>";

        if ($admin_tipo === 'contratante') {
            $stmtDetalhes = $pdo->prepare(
                "INSERT INTO contratantes (usuario_id, nome, data_nascimento, endereco, telefone, descricao, foto_perfil) 
                 VALUES (:usuario_id, :nome, :data_nascimento, :endereco, :telefone, :descricao, :foto_perfil)"
            );
            $stmtDetalhes->execute([
                ':usuario_id' => $usuarioId,
                ':nome' => $admin_nome,
                ':data_nascimento' => '2000-01-01',
                ':endereco' => 'Endereço do Admin',
                ':telefone' => '(00) 00000-0000',
                ':descricao' => 'Usuário administrador para desenvolvimento e testes.',
                ':trabalho' => 'Gerenciamento',
                ':foto_perfil' => null
            ]);
        }
        
        echo "<p>Registro criado na tabela '$admin_tipo'.</p>";

        $pdo->commit();

        echo "<h2 style='color: green;'>✅ Sucesso!</h2>";
        echo "<p>Usuário admin criado com as seguintes credenciais:</p>";
        echo "<ul>";
        echo "<li><strong>E-mail:</strong> " . htmlspecialchars($admin_email) . "</li>";
        echo "<li><strong>Senha:</strong> " . htmlspecialchars($admin_senha) . "</li>";
        echo "</ul>";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2 style='color: red;'>❌ Erro!</h2>";
    echo "<p>Ocorreu um erro ao criar o usuário admin: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
