-- Diagnóstico (ejecuta TODO este bloque)
SELECT DATABASE() AS base_actual;

SELECT id, note_id, customer_id, nro_contr_adm, nro_cliente_adm, deleted_at
FROM ventas
WHERE id = 344 OR note_id = 3400 OR nro_contr_adm = '1296';

SHOW INDEX FROM ventas WHERE Column_name IN ('id', 'note_id', 'nro_contr_adm');
