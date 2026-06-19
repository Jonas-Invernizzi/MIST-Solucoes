-- Script de Seed para testes do MIST-Soluções
-- Este script popula as tabelas com dados fictícios e relacionamentos completos.
-- Nota: Todas as senhas abaixo são o hash para 'senha123'.

-- Limpar banco antes de popular para evitar duplicações
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE avaliacoes;
TRUNCATE TABLE profissional_tags;
TRUNCATE TABLE tags;
TRUNCATE TABLE profissional_fotos;
TRUNCATE TABLE mensagens;
TRUNCATE TABLE profissionais;
TRUNCATE TABLE clientes;
TRUNCATE TABLE sistema_assets;
TRUNCATE TABLE usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Inserir Usuários (Profissionais e Clientes)
-- Senha de todos: senha123
INSERT INTO usuarios (email, senha, tipo_base, status) VALUES
('joao.eletricista@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profissional', 'ativo'),
('maria.encanadora@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profissional', 'ativo'),
('carlos.pedreiro@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profissional', 'ativo'),
('ana.pintora@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profissional', 'ativo'),
('pedro.jardineiro@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profissional', 'ativo'),
('lucas.marceneiro@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'profissional', 'ativo'),
('cliente1@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente', 'ativo'),
('cliente2@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente', 'ativo');

-- 2. Inserir Dados dos Profissionais
INSERT INTO profissionais (usuario_id, nome, cpf, data_nascimento, endereco, endereco_trabalho, telefone, descricao, trabalho, foto_perfil) VALUES
(1, 'João Silva', '123.456.789-01', '1985-05-20', 'Rua das Flores, 100', 'Bento Gonçalves e região', '(54) 99123-4567', 
 'Eletricista residencial e industrial com 10 anos de experiência. Instalações, manutenção e quadros elétricos.', 'Eletricista, Manutenção', NULL),
(2, 'Maria Souza', '234.567.890-12', '1990-10-15', 'Av. Planalto, 500', 'Bento Gonçalves', '(54) 99234-5678', 
 'Especialista em caça-vazamentos, limpeza de caixas d\'água e instalações hidráulicas em geral.', 'Encanadora, Hidráulica', NULL),
(3, 'Carlos Lima', '345.678.901-23', '1978-03-08', 'Rua São Paulo, 250', 'Serra Gaúcha', '(54) 99345-6789', 
 'Atuou em grandes obras e agora focado em reformas residenciais, do alicerce ao telhado.', 'Pedreiro, Reformas', NULL),
(4, 'Ana Oliveira', '456.789.012-34', '1992-07-12', 'Rua Humaitá, 30', 'Bento Gonçalves e Farroupilha', '(54) 99456-7890', 
 'Pintura residencial, decorativa e aplicação de texturas e grafiatos com acabamento fino.', 'Pintora, Acabamento', NULL),
(5, 'Pedro Santos', '567.890.123-45', '1982-11-30', 'Rua Tiradentes, 12', 'Bento Gonçalves', '(54) 99567-8901', 
 'Manutenção de jardins, poda de árvores e paisagismo para residências e condomínios.', 'Jardineiro, Paisagismo', NULL),
(6, 'Lucas Bortolotto', '678.901.234-56', '1988-01-25', 'Rua Olavo Bilac, 44', 'Bento Gonçalves', '(54) 99678-9012', 
 'Conserto de móveis sob medida, restauração de antiguidades e montagem de móveis novos.', 'Marceneiro, Móveis', NULL);

-- 3. Inserir Dados dos Clientes
INSERT INTO clientes (usuario_id, nome, data_nascimento, endereco, telefone, descricao, foto_perfil) VALUES
(7, 'Roberto Almeida', '1980-06-15', 'Rua Assis Brasil, 120', '(54) 98888-1111', 'Cliente procurando bons profissionais na região.', NULL),
(8, 'Fernanda Costa', '1995-02-28', 'Av. Osvaldo Aranha, 890', '(54) 98888-2222', 'Gosto de manter minha casa sempre em ordem.', NULL);

-- 4. Criar Tags
INSERT INTO tags (nome) VALUES 
('Eletricista'), ('Manutenção'), ('Encanadora'), ('Hidráulica'), 
('Pedreiro'), ('Reformas'), ('Pintora'), ('Acabamento'), 
('Jardineiro'), ('Paisagismo'), ('Marceneiro'), ('Móveis');

-- 5. Vincular Tags aos Profissionais
INSERT INTO profissional_tags (profissional_id, tag_id) VALUES
(1, 1), (1, 2),
(2, 3), (2, 4),
(3, 5), (3, 6),
(4, 7), (4, 8),
(5, 9), (5, 10),
(6, 11), (6, 12);

-- 6. Adicionar Avaliações (Clientes avaliando Profissionais)
INSERT INTO avaliacoes (profissional_id, cliente_id, nota, comentario) VALUES
(1, 1, 5, 'Excelente profissional! Resolveu o curto-circuito super rápido.'),
(2, 2, 4, 'Muito boa, mas atrasou 10 minutinhos. Tirando isso, serviço impecável.'),
(6, 1, 5, 'Móveis de primeira qualidade e ótimo atendimento.');
