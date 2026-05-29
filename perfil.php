<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

// Tenta pegar o ID da URL. Se não existir, tenta pegar o ID do usuário logado na sessão.
$id = $_GET['id'] ?? $_SESSION['usuario_id'] ?? null;

$usuario = null;
$erro = '';

if (!$id) {
    $erro = "⚠️ Usuário não especificado.";
} else {
    try {
        // Busca unificada utilizando COALESCE para pegar dados de ambas as tabelas (clientes ou contratantes)
        // Nota: 'trabalho' é específico de contratantes (prestadores).
        $stmt = $pdo->prepare("
            SELECT u.email, u.tipo_base,
                   COALESCE(c.nome, co.nome) as nome,
                   COALESCE(c.endereco, co.endereco) as endereco,
                   COALESCE(c.telefone, co.telefone) as telefone,
                   COALESCE(c.descricao, co.descricao) as descricao,
                   COALESCE(c.foto_perfil, co.foto_perfil) as foto_perfil,
                   co.trabalho,
                   COALESCE(c.data_nascimento, co.data_nascimento) as data_nascimento
            FROM usuarios u
            LEFT JOIN clientes c ON u.id = c.usuario_id
            LEFT JOIN contratantes co ON u.id = co.usuario_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $erro = "❌ Prestador não encontrado no sistema.";
        }
    } catch (Exception $e) {
        $erro = "⚠️ Erro ao carregar perfil: " . $e->getMessage();
    }
}

echo $twig->render('perfil.html', [
    'usuario' => $usuario,
    'erro' => $erro
]);