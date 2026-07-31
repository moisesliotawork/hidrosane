-- CASO 3: Mercedes Guimerans Lorenzo — contrato octubre 1189 (y 1189-B)
-- Fuente: docs/diosmeayuda.sql
-- Dump: customer 1774 / nota 1837 / venta 207 (1189) / venta 359 (1189-B)
--
-- Ejecutar ESTE bloque primero en producción (Cmd+A → Cmd+Enter)

SELECT DATABASE() AS base_actual;

-- ¿Existe la cliente?
SELECT id, nro_cliente, first_names, last_names, dni, phone, secondary_phone
FROM customers
WHERE dni = '35301073Y'
   OR (first_names LIKE 'Mercedes%' AND last_names LIKE 'Guimerans Lorenzo%')
   OR id = 1774;

-- ¿Existe la nota del contrato de octubre?
SELECT id, nro_nota, customer_id, status, visit_date, deleted_at
FROM notes
WHERE id = 1837 OR nro_nota = '06031' OR customer_id = 1774;

-- ¿Existen los contratos de octubre?
SELECT id, note_id, customer_id, nro_contr_adm, nro_cliente_adm, fecha_venta, estado_venta, deleted_at
FROM ventas
WHERE nro_contr_adm IN ('1189', '1189-B')
   OR id IN (207, 359)
   OR note_id = 1837;

-- Usuarios del dump (comercial 18, companion 14, repartidor 15)
SELECT id, empleado_id, name, last_name
FROM users
WHERE id IN (14, 15, 18);
