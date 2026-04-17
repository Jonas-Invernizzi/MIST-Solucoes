DROP TABLE IF EXISTS usuarios,
DROP TABLE IF EXISTS clientes,
DROP TABLE IF EXISTS contratantes
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo_base ENUM('cliente', 'contratante') DEFAULT 'cliente',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNIQUE NOT NULL, 
    nome VARCHAR(255) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_nascimento DATE NOT NULL,
    descricao TEXT NOT NULL
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE TABLE contratantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNIQUE NOT NULL, 
    cpf VARCHAR(14),
    data_nascimento DATE NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    endereco_trabalho VARCHAR(255) NOT NULL, 
    telefone VARCHAR(20) NOT NULL,
    descricao TEXT NOT NULL,
    trabalho TEXT NOT NULL, 
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);