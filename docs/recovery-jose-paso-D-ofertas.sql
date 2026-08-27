-- PASO D — Oferta y productos (solo si el contrato C existe)
INSERT INTO `venta_ofertas` (`id`, `venta_id`, `oferta_id`, `puntos`, `created_at`, `updated_at`) VALUES
(713, 344, 8, 9, '2025-10-28 15:17:11', '2025-10-28 16:12:54')
ON DUPLICATE KEY UPDATE `puntos`=VALUES(`puntos`);

INSERT INTO `venta_oferta_productos` (`id`, `venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`) VALUES
(2310, 713, 386, 1, 0, 4, 'comercial', '2025-10-28 15:17:11', '2025-10-28 16:12:54')
ON DUPLICATE KEY UPDATE `puntos_linea`=VALUES(`puntos_linea`);

INSERT INTO `venta_oferta_productos` (`id`, `venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`) VALUES
(2311, 713, 290, 1, 0, 1, 'comercial', '2025-10-28 15:17:11', '2025-10-28 15:17:11')
ON DUPLICATE KEY UPDATE `puntos_linea`=VALUES(`puntos_linea`);

INSERT INTO `venta_oferta_productos` (`id`, `venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`) VALUES
(2312, 713, 288, 1, 0, 1, 'comercial', '2025-10-28 15:17:11', '2025-10-28 15:17:11')
ON DUPLICATE KEY UPDATE `puntos_linea`=VALUES(`puntos_linea`);

INSERT INTO `venta_oferta_productos` (`id`, `venta_oferta_id`, `producto_id`, `cantidad`, `cantidad_entregada`, `puntos_linea`, `vendido_por`, `created_at`, `updated_at`) VALUES
(2313, 713, 262, 1, 0, 3, 'comercial', '2025-10-28 15:17:11', '2025-10-28 15:17:11')
ON DUPLICATE KEY UPDATE `puntos_linea`=VALUES(`puntos_linea`);
