<?php
require_once('carregar_pdo.php');

// Mapeamento de arquivos para assets do banco
$assetsParaImportar = [
    'logo' => 'img/logo.jpg',
    'default_avatar' => 'img/fotoPadrao.png' // Certifique-se que este arquivo existe
];

echo "<h1>🔄 Importador de Ativos do Sistema</h1>";

foreach ($assetsParaImportar as $nome => $caminho) {
    if (file_exists($caminho)) {
        try {
            $data = file_get_contents($caminho);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($caminho);

            // Tenta atualizar se já existir, senão insere
            $stmtCheck = $pdo->prepare("SELECT id FROM sistema_assets WHERE nome = :nome");
            $stmtCheck->execute(['nome' => $nome]);
            
            if ($stmtCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE sistema_assets SET arquivo = :arquivo, mime_type = :mime WHERE nome = :nome");
            } else {
                $stmt = $pdo->prepare("INSERT INTO sistema_assets (nome, arquivo, mime_type) VALUES (:nome, :arquivo, :mime)");
            }

            $stmt->execute([
                'nome' => $nome,
                'arquivo' => $data,
                'mime' => $mimeType
            ]);

            echo "<p style='color: green;'>✅ Ativo <strong>$nome</strong> importado com sucesso (Tipo: $mimeType).</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erro ao importar $nome: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Arquivo não encontrado para o ativo <strong>$nome</strong> (Caminho esperado: $caminho).</p>";
    }
}

echo "<br><a href='tela_inicial.php'>Ir para a Tela Inicial</a>";
?>
