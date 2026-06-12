<?php
require_once('carregar_pdo.php');

// Array com as configurações dos administradores
$admins = [
    [
        'email' => 'admin_profissional@mist.com',
        'senha' => 'admin67',
        'nome' => 'Admin Profissional',
        'tipo' => 'profissional',
        'cpf' => '000.000.000-00',
        'nascimento' => '1990-01-01',
        'endereco' => 'Rua do Admin Profissional, 100',
        'endereco_trabalho' => 'Bento Gonçalves',
        'telefone' => '(54) 99999-9999',
        'descricao' => 'Usuário administrador (profissional) para desenvolvimento e testes.',
        'trabalho' => 'Gerenciamento'
    ],
    [
        'email' => 'admin_cliente@mist.com',
        'senha' => 'admin69',
        'nome' => 'Admin Cliente',
        'tipo' => 'cliente',
        'nascimento' => '1995-05-15',
        'endereco' => 'Av. do Admin Cliente, 500',
        'telefone' => '(54) 88888-8888',
        'descricao' => 'Usuário administrador (cliente) para desenvolvimento e testes.',
        'trabalho' => null // Clientes não têm 'trabalho'
    ]
];

echo "<!DOCTYPE html><html><head><title>Criação de Admin</title><link rel='stylesheet' href='templates/css/base.css'></head><body style='padding: 20px;'>";
echo "<h1>Iniciando script de criação de admins...</h1>";

foreach ($admins as $admin) {
    echo "<hr><h2>Processando: " . htmlspecialchars($admin['email']) . "</h2>";

    try {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmt->execute([':email' => $admin['email']]);

        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Usuário admin ('" . htmlspecialchars($admin['email']) . "') já existe. Nenhuma ação foi tomada.</p>";
        } else {
            echo "<p>Usuário admin não encontrado. Criando...</p>";
            
            $pdo->beginTransaction();

            $senhaHash = password_hash($admin['senha'], PASSWORD_DEFAULT);
            
            $stmtUser = $pdo->prepare(
                "INSERT INTO usuarios (email, senha, tipo_base, status) VALUES (:email, :senha, :tipo_base, 'ativo')"
            );
            $stmtUser->execute([
                ':email' => $admin['email'],
                ':senha' => $senhaHash,
                ':tipo_base' => $admin['tipo']
            ]);

            $usuarioId = $pdo->lastInsertId();
            echo "<p>Registro criado na tabela 'usuarios' com ID: $usuarioId</p>";

            if ($admin['tipo'] === 'profissional') {
                $stmtDetalhes = $pdo->prepare(
                    "INSERT INTO profissionais (usuario_id, nome, cpf, data_nascimento, endereco, endereco_trabalho, telefone, descricao, trabalho, foto_perfil) 
                     VALUES (:usuario_id, :nome, :cpf, :data_nascimento, :endereco, :endereco_trabalho, :telefone, :descricao, :trabalho, :foto_perfil)"
                );
                $stmtDetalhes->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome' => $admin['nome'],
                    ':cpf' => $admin['cpf'],
                    ':data_nascimento' => $admin['nascimento'],
                    ':endereco' => $admin['endereco'],
                    ':endereco_trabalho' => $admin['endereco_trabalho'],
                    ':telefone' => $admin['telefone'],
                    ':descricao' => $admin['descricao'],
                    ':trabalho' => $admin['trabalho'],
                    ':foto_perfil' => null
                ]);
                
                $profissionalId = $pdo->lastInsertId();
                
                // Indexar Tags do Admin para a pesquisa
                $tagsArray = array_unique(array_filter(array_map('trim', explode(',', $admin['trabalho']))));
                foreach ($tagsArray as $tagNome) {
                    $stmtTag = $pdo->prepare("INSERT IGNORE INTO tags (nome) VALUES (:nome)");
                    $stmtTag->execute(['nome' => $tagNome]);
                    $tagId = $pdo->query("SELECT id FROM tags WHERE nome = " . $pdo->quote($tagNome))->fetchColumn();
                    
                    $pdo->prepare("INSERT IGNORE INTO profissional_tags (profissional_id, tag_id) VALUES (:pid, :tid)")
                        ->execute(['pid' => $profissionalId, 'tid' => $tagId]);
                }
                
                echo "<p>Registro criado na tabela 'profissionais'.</p>";

            } elseif ($admin['tipo'] === 'cliente') {
                // Inserir na tabela 'clientes'
                $stmtDetalhes = $pdo->prepare(
                    "INSERT INTO clientes (usuario_id, nome, data_nascimento, endereco, telefone, descricao, foto_perfil) 
                     VALUES (:usuario_id, :nome, :data_nascimento, :endereco, :telefone, :descricao, :foto_perfil)"
                );
                $stmtDetalhes->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome' => $admin['nome'],
                    ':data_nascimento' => $admin['nascimento'],
                    ':endereco' => $admin['endereco'],
                    ':telefone' => $admin['telefone'],
                    ':descricao' => $admin['descricao'],
                    ':foto_perfil' => null
                ]);
                echo "<p>Registro criado na tabela 'clientes'.</p>";
            }
            
            $pdo->commit();

            echo "<h3 style='color: green;'>✅ Sucesso!</h3>";
            echo "<p>Usuário admin criado com as seguintes credenciais:</p>";
            echo "<ul>";
            echo "<li><strong>E-mail:</strong> " . htmlspecialchars($admin['email']) . "</li>";
            echo "<li><strong>Senha:</strong> " . htmlspecialchars($admin['senha']) . "</li>";
            echo "</ul>";
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<h2 style='color: red;'>❌ Erro!</h2>";
        echo "<p>Ocorreu um erro ao criar o usuário admin: " . $e->getMessage() . "</p>";
    }
}

echo "</body></html>";