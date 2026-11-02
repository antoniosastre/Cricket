CREATE TABLE IF NOT EXISTS intentos_login (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cuenta CHAR(64) NULL,
  ip VARCHAR(45) NOT NULL,
  fecha DATETIME NOT NULL,
  KEY idx_intentos_cuenta (cuenta, fecha),
  KEY idx_intentos_ip (ip, fecha),
  KEY idx_intentos_fecha (fecha)
) ENGINE=InnoDB;
