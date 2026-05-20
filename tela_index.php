<?php
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

try {
    // Consulta SQL para buscar profissionais (contratantes) ativos
    // Selecionamos o id do usuario para o link e os dados da tabela contratantes
    $sql = "SELECT u.id as usuario_id, c.nome, c.trabalho, c.descricao, c.foto_perfil 
            FROM usuarios u 
            INNER JOIN contratantes c ON u.id = c.usuario_id 
            WHERE u.tipo_base = 'contratante' AND u.status = 'ativo'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $profissionais = [];
    $erro = "Erro ao carregar profissionais: " . $e->getMessage();
}

echo $twig->render('tela_inicial.html', [
    'profissionais' => $profissionais,
    'erro_db' => $erro ?? null
]);


