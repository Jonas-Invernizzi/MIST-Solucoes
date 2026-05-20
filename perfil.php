<?php
require_once('carregar_twig.php');
require_once('carregar_pdo.php');

$id = $_GET['id'] ?? null;
$perfil = null;
$erro = '';

if (!$id) {
    $erro = "Perfil não encontrado ou ID inválido.";
} else {
    try {
        // 1. Primeiro buscamos o tipo de base do usuário para saber em qual tabela procurar
        $stmtUser = $pdo->prepare("SELECT tipo_base FROM usuarios WHERE id = :id");
        $stmtUser->execute(['id' => $id]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $tabela = ($usuario['tipo_base'] === 'contratante') ? 'contratantes' : 'clientes';
            
            // 2. Buscamos os dados reais na tabela correta usando JOIN para pegar o e-mail se necessário
            // Nota: O prepared statement impede SQL Injection
            $stmt = $pdo->prepare("
                SELECT u.email, t.* 
                FROM usuarios u 
                INNER JOIN $tabela t ON u.id = t.usuario_id 
                WHERE u.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$perfil) {
                $erro = "Os dados detalhados deste perfil não foram encontrados.";
            } else {
                // 3. Cálculo de idade simples
                $dataNasc = new DateTime($perfil['data_nascimento']);
                $hoje = new DateTime();
                $perfil['idade'] = $hoje->diff($dataNasc)->y;

                // 4. Tratamento da profissão/trabalho
                // Clientes (prestadores) usam a descrição, Contratantes têm o campo 'trabalho'
                $perfil['profissao'] = ($usuario['tipo_base'] === 'contratante') 
                                        ? $perfil['trabalho'] 
                                        : "Prestador de Serviços";
                
                // 5. Verificação de imagem padrão
                if (empty($perfil['foto_perfil']) || !file_exists(__DIR__ . '/img/' . $perfil['foto_perfil'])) {
                    $perfil['foto_perfil'] = 'default_profile.png';
                }
            }
        } else {
            $erro = "Usuário não existe em nossa base de dados.";
        }
    } catch (Exception $e) {
        $erro = "Erro ao carregar perfil: " . $e->getMessage();
    }
}

// Se houver erro e nenhum perfil, podemos redirecionar ou apenas mostrar a mensagem no Twig
echo $twig->render('perfil.html', [
    'perfil' => $perfil,
    'erro' => $erro,
    // Passamos a sessão se você quiser mostrar o menu de logado no header
    'session' => $_SESSION 
]);