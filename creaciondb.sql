-- Este script crea la base de datos y las tablas necesarias para la aplicación de lista de tareas.
-- Se ejecuta una sola vez para configurar la base de datos inicial. En localhost/phpmyadmin, se puede ejecutar directamente desde la pestaña SQL.
CREATE DATABASE IF NOT EXISTS lista
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE lista;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150) NULL,
    color VARCHAR(7) NULL
);

CREATE TABLE tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    categoria_id INT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    titulo VARCHAR(120) NOT NULL,
    descripcion VARCHAR(512) NULL,
    completada BOOLEAN NOT NULL DEFAULT FALSE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
