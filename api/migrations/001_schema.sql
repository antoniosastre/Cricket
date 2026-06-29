-- Esquema de la base de datos: seguimiento de partes de trabajo
-- MySQL 5.7+ / 8.x. Codificacion utf8mb4.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  pin_hash      VARCHAR(255) NULL,
  rol           ENUM('admin','trabajador') NOT NULL DEFAULT 'trabajador',
  tarifa_hora   DECIMAL(8,2) NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(160) NOT NULL,
  nif        VARCHAR(32) NULL,
  direccion  VARCHAR(255) NULL,
  telefono   VARCHAR(40) NULL,
  email      VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instalaciones (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id  INT UNSIGNED NULL,
  nombre      VARCHAR(160) NOT NULL,
  direccion   VARCHAR(255) NULL,
  descripcion TEXT NULL,
  estado      ENUM('activa','archivada') NOT NULL DEFAULT 'activa',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_instalaciones_cliente (cliente_id),
  KEY idx_instalaciones_estado (estado),
  CONSTRAINT fk_instalaciones_cliente FOREIGN KEY (cliente_id)
    REFERENCES clientes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lotes_facturacion (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  admin_id   INT UNSIGNED NULL,
  referencia VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lotes_admin (admin_id),
  CONSTRAINT fk_lotes_admin FOREIGN KEY (admin_id)
    REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jornadas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid           CHAR(36) NOT NULL,
  instalacion_id INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NOT NULL,
  inicio         DATETIME NOT NULL,
  fin            DATETIME NULL,
  duracion_min   INT NULL,
  notas          VARCHAR(255) NULL,
  estado         ENUM('en_curso','confirmada','descartada') NOT NULL DEFAULT 'en_curso',
  lote_id        INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jornadas_uuid (uuid),
  KEY idx_jornadas_instalacion (instalacion_id),
  KEY idx_jornadas_usuario (usuario_id),
  KEY idx_jornadas_estado (estado),
  KEY idx_jornadas_lote (lote_id),
  CONSTRAINT fk_jornadas_instalacion FOREIGN KEY (instalacion_id)
    REFERENCES instalaciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_jornadas_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_jornadas_lote FOREIGN KEY (lote_id)
    REFERENCES lotes_facturacion (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materiales_catalogo (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  descripcion VARCHAR(200) NOT NULL,
  unidad      VARCHAR(20) NOT NULL DEFAULT 'ud',
  precio      DECIMAL(10,2) NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materiales_linea (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid           CHAR(36) NOT NULL,
  instalacion_id INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NOT NULL,
  fecha          DATE NOT NULL,
  catalogo_id    INT UNSIGNED NULL,
  descripcion    VARCHAR(200) NOT NULL,
  cantidad       DECIMAL(10,2) NOT NULL DEFAULT 1,
  unidad         VARCHAR(20) NOT NULL DEFAULT 'ud',
  precio_unit    DECIMAL(10,2) NULL,
  origen         ENUM('empresa','cliente') NOT NULL DEFAULT 'empresa',
  lote_id        INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_materiales_uuid (uuid),
  KEY idx_materiales_instalacion (instalacion_id),
  KEY idx_materiales_usuario (usuario_id),
  KEY idx_materiales_lote (lote_id),
  KEY idx_materiales_origen (origen),
  CONSTRAINT fk_materiales_instalacion FOREIGN KEY (instalacion_id)
    REFERENCES instalaciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_materiales_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_materiales_catalogo FOREIGN KEY (catalogo_id)
    REFERENCES materiales_catalogo (id) ON DELETE SET NULL,
  CONSTRAINT fk_materiales_lote FOREIGN KEY (lote_id)
    REFERENCES lotes_facturacion (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
