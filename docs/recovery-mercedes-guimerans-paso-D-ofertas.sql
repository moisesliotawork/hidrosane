-- CASO 3 PASO D: Ofertas del contrato 1189 (y opcional 1189-B)
-- Ejecutar DESPUÉS de que exista ventas.nro_contr_adm = '1189'
-- Cmd+A → Cmd+Enter

-- Oferta del contrato principal 1189 (oferta_id 2, 4 puntos)
INSERT INTO `venta_ofertas` (`venta_id`, `oferta_id`, `puntos`, `created_at`, `updated_at`)
SELECT v.id, 2, 4, '2025-10-29 18:49:00', '2025-10-29 18:49:00'
FROM ventas v
WHERE v.nro_contr_adm = '1189'
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 112, 1, 0, 2, 'comercial', '2025-10-29 18:49:00', '2025-10-29 18:49:00'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1189'
ORDER BY vo.id DESC
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 257, 1, 0, 1, 'comercial', '2025-10-29 18:49:00', '2025-10-29 18:49:00'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1189'
ORDER BY vo.id DESC
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 85, 1, 0, 1, 'comercial', '2025-10-29 18:49:00', '2025-10-29 18:49:00'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1189'
ORDER BY vo.id DESC
LIMIT 1;

-- Oferta del 1189-B (solo si restauraste el -B)
INSERT INTO `venta_ofertas` (`venta_id`, `oferta_id`, `puntos`, `created_at`, `updated_at`)
SELECT v.id, 9, 2, '2025-10-29 18:55:47', '2025-10-30 17:59:04'
FROM ventas v
WHERE v.nro_contr_adm = '1189-B'
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 353, 1, 0, 1, 'comercial', '2025-10-30 17:59:04', '2025-10-30 17:59:04'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1189-B'
ORDER BY vo.id DESC
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 91, 1, 0, 1, 'comercial', '2025-10-30 17:59:04', '2025-10-30 17:59:04'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1189-B'
ORDER BY vo.id DESC
LIMIT 1;

-- Verifica:
-- SELECT v.nro_contr_adm, vo.* FROM venta_ofertas vo JOIN ventas v ON v.id = vo.venta_id
-- WHERE v.nro_contr_adm IN ('1189','1189-B');
