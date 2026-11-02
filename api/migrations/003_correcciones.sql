-- MySQL confirma el DDL antes de registrar la versión. Un reintento es seguro.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns
                     WHERE table_schema=DATABASE() AND table_name='jornadas' AND column_name='editada_admin'),
              'DO 0', 'ALTER TABLE jornadas ADD COLUMN editada_admin TINYINT(1) NOT NULL DEFAULT 0');
PREPARE cambio FROM @ddl;
EXECUTE cambio;
DEALLOCATE PREPARE cambio;
-- MySQL confirma el DDL antes de registrar la versión. Un reintento es seguro.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns
                     WHERE table_schema=DATABASE() AND table_name='materiales_linea' AND column_name='editada_admin'),
              'DO 0', 'ALTER TABLE materiales_linea ADD COLUMN editada_admin TINYINT(1) NOT NULL DEFAULT 0');
PREPARE cambio FROM @ddl;
EXECUTE cambio;
DEALLOCATE PREPARE cambio;
