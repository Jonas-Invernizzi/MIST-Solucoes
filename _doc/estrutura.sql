-- Desativa a verificação para permitir o DROP de tabelas com dependências
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS profissional_tags;
DROP TABLE IF EXISTS profissional_fotos;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS avaliacoes;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS profissionais;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS sistema_assets;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo_base ENUM('cliente', 'profissional') DEFAULT 'cliente',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo') DEFAULT 'inativo',
    token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires_at DATETIME DEFAULT NULL
);

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNIQUE NOT NULL, 
    nome VARCHAR(255) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_nascimento DATE NOT NULL,
    descricao TEXT NOT NULL,
    foto_perfil LONGBLOB DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE profissionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNIQUE NOT NULL, 
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14),
    data_nascimento DATE NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    endereco_trabalho VARCHAR(255) NOT NULL, 
    telefone VARCHAR(20) NOT NULL,
    descricao TEXT NOT NULL,
    trabalho TEXT NOT NULL, 
    foto_perfil LONGBLOB DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE profissional_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    arquivo LONGBLOB NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profissional_fotos FOREIGN KEY (profissional_id) 
        REFERENCES profissionais(id) ON DELETE CASCADE
);

CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE profissional_tags (
    profissional_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (profissional_id, tag_id),
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    cliente_id INT NOT NULL,
    nota INT NOT NULL CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

CREATE TABLE sistema_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) UNIQUE NOT NULL,
    arquivo LONGBLOB NOT NULL,
    mime_type VARCHAR(50) NOT NULL
);

-- Reativa a verificação de chaves estrangeiras
SET FOREIGN_KEY_CHECKS = 1;