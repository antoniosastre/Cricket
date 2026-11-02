-- MySQL confirma el DDL antes de registrar la versión. Un reintento es seguro.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns
                     WHERE table_schema=DATABASE() AND table_name='jornadas' AND column_name='tarifa_hora'),
              'DO 0', 'ALTER TABLE jornadas ADD COLUMN tarifa_hora DECIMAL(8,2) NULL');
PREPARE cambio FROM @ddl;
EXECUTE cambio;
DEALLOCATE PREPARE cambio;
UPDATE jornadas j JOIN usuarios u ON u.id=j.usuario_id
SET j.tarifa_hora=u.tarifa_hora WHERE j.lote_id IS NOT NULL AND j.tarifa_hora IS NULL;
