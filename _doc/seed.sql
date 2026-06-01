-- Script de Seed para testes do MIST-Soluções
-- Este script popula as tabelas 'usuarios' e 'profissionais' com profissionais fictícios.
-- Nota: Todas as senhas abaixo são o hash para 'senha123'.

-- 1. Inserir Usuários (Tipo Profissional)
INSERT INTO usuarios (email, senha, tipo_base, status) VALUES
('joao.eletricista@email.com', '$2y$10$6mXWlYhYp8p8p8p8p8p8pOu6R.K9p9p9p9p9p9p9p9p9p9p9p9p9p', 'profissional', 'ativo'),
('maria.encanadora@email.com', '$2y$10$6mXWlYhYp8p8p8p8p8p8pOu6R.K9p9p9p9p9p9p9p9p9p9p9p9p9p', 'profissional', 'ativo'),
('carlos.pedreiro@email.com', '$2y$10$6mXWlYhYp8p8p8p8p8p8pOu6R.K9p9p9p9p9p9p9p9p9p9p9p9p9p', 'profissional', 'ativo'),
('ana.pintora@email.com', '$2y$10$6mXWlYhYp8p8p8p8p8p8pOu6R.K9p9p9p9p9p9p9p9p9p9p9p9p9p', 'profissional', 'ativo'),
('pedro.jardineiro@email.com', '$2y$10$6mXWlYhYp8p8p8p8p8p8pOu6R.K9p9p9p9p9p9p9p9p9p9p9p9p9p', 'profissional', 'ativo'),
('lucas.marceneiro@email.com', '$2y$10$6mXWlYhYp8p8p8p8p8p8pOu6R.K9p9p9p9p9p9p9p9p9p9p9p9p9p', 'profissional', 'ativo');

-- 2. Inserir Dados dos Profissionais
INSERT INTO profissionais (usuario_id, nome, cpf, data_nascimento, endereco, endereco_trabalho, telefone, descricao, trabalho, foto_perfil) VALUES
((SELECT id FROM usuarios WHERE email = 'joao.eletricista@email.com'), 
 'João Silva', '123.456.789-01', '1985-05-20', 'Rua das Flores, 100', 'Bento Gonçalves e região', '(54) 99123-4567', 
 'Eletricista residencial e industrial com 10 anos de experiência. Instalações, manutenção e quadros elétricos.', 'Eletricista', NULL),

((SELECT id FROM usuarios WHERE email = 'maria.encanadora@email.com'), 
 'Maria Souza', '234.567.890-12', '1990-10-15', 'Av. Planalto, 500', 'Bento Gonçalves', '(54) 99234-5678', 
 'Especialista em caça-vazamentos, limpeza de caixas d\'água e instalações hidráulicas em geral.', 'Encanadora', NULL),

((SELECT id FROM usuarios WHERE email = 'carlos.pedreiro@email.com'), 
 'Carlos Lima', '345.678.901-23', '1978-03-08', 'Rua São Paulo, 250', 'Serra Gaúcha', '(54) 99345-6789', 
 'Atuou em grandes obras e agora focado em reformas residenciais, do alicerce ao telhado.', 'Pedreiro', NULL),

((SELECT id FROM usuarios WHERE email = 'ana.pintora@email.com'), 
 'Ana Oliveira', '456.789.012-34', '1992-07-12', 'Rua Humaitá, 30', 'Bento Gonçalves e Farroupilha', '(54) 99456-7890', 
 'Pintura residencial, decorativa e aplicação de texturas e grafiatos com acabamento fino.', 'Pintora', NULL),

((SELECT id FROM usuarios WHERE email = 'pedro.jardineiro@email.com'), 
 'Pedro Santos', '567.890.123-45', '1982-11-30', 'Rua Tiradentes, 12', 'Bento Gonçalves', '(54) 99567-8901', 
 'Manutenção de jardins, poda de árvores e paisagismo para residências e condomínios.', 'Jardineiro', NULL),

((SELECT id FROM usuarios WHERE email = 'lucas.marceneiro@email.com'), 
 'Lucas Bortolotto', '678.901.234-56', '1988-01-25', 'Rua Olavo Bilac, 44', 'Bento Gonçalves', '(54) 99678-9012', 
 'Conserto de móveis sob medida, restauração de antiguidades e montagem de móveis novos.', 'Marceneiro', NULL);