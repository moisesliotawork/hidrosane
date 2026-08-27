-- PASO D corregido: oferta ligada al contrato 1299 (por nro, no por id viejo 347)
-- Ejecutar DESPUES de que exista ventas.nro_contr_adm = '1299'

INSERT INTO `venta_ofertas` (`venta_id`, `oferta_id`, `puntos`, `created_at`, `updated_at`)
SELECT v.id, 1, 4, '2025-10-28 19:51:56', '2025-10-28 19:51:56'
FROM ventas v
WHERE v.nro_contr_adm = '1299'
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 119, 1, 0, 3, 'comercial', '2025-10-28 19:51:56', '2025-10-28 19:51:56'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1299'
ORDER BY vo.id DESC
LIMIT 1;

INSERT INTO `venta_oferta_productos` (`venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`)
SELECT vo.id, 291, 1, 0, 1, 'comercial', '2025-10-28 19:51:56', '2025-10-29 09:13:33'
FROM venta_ofertas vo
JOIN ventas v ON v.id = vo.venta_id
WHERE v.nro_contr_adm = '1299'
ORDER BY vo.id DESC
LIMIT 1;
