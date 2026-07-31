SELECT v.id, v.nro_contr_adm, v.nro_cliente_adm, c.first_names, c.last_names, v.importe_total, v.deleted_at
FROM ventas v
JOIN customers c ON c.id = v.customer_id
WHERE v.nro_contr_adm = '1296' OR v.id = 344;