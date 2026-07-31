-- Diagnostico Maria 1299
SELECT id, note_id, customer_id, nro_contr_adm, nro_cliente_adm, deleted_at
FROM ventas
WHERE id=347 OR note_id=3442 OR nro_contr_adm='1299';
