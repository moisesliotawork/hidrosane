-- CASO 3 PASO C2 (opcional): Contrato asociado 1189-B (también octubre)
-- Ejecutar solo si falta el -B. Misma nota/cliente que 1189.
-- Cmd+A → Cmd+Enter

INSERT INTO `ventas` (
  `note_id`, `customer_id`, `comercial_id`, `companion_id`, `fecha_venta`, `mes_contr`,
  `nro_contr_adm`, `nro_cliente_adm`, `fecha_entrega`, `horario_entrega`, `importe_total`,
  `entrada`, `monto_extra`, `total_final`, `cuota_final`, `importe_comercial`, `importe_repartidor`,
  `modalidad_pago`, `financiera`, `forma_pago`, `cuota_mensual`, `num_cuotas`, `accesorio_entregado`,
  `crema`, `mostrar_ingresos`, `mostrar_tipo_vivienda`, `mostrar_situacion_lab`, `motivo_venta`,
  `origen_venta`, `motivo_horario`, `interes_art`, `productos_externos`, `precontractual`,
  `foto_sorteo`, `otros_documentos`, `interes_art_detalle`, `observaciones_repartidor`, `status`,
  `created_at`, `updated_at`, `dni_anverso`, `dni_reverso`, `documento_titularidad`, `nomina`,
  `pension`, `contrato_firmado`, `contrato_firmado_at`, `repartidor_id`, `de_camino`, `lat`, `lng`,
  `estado_venta`, `vta_rep`, `vta_esp`, `vta_ac`, `com_venta`, `com_entrega`, `com_conpago`,
  `pas_comercial`, `pas_repartidor`, `repartidor_2`, `seguimiento`, `financieras_reparto`, `pasadas_financieras`
) VALUES (
  1837, 1774, 18, 14, '2025-10-29 17:55:47', 'noviembre',
  '1189-B', '91', '2025-10-03 00:00:00', 'TD', 1556.10,
  0.00, -266.10, 1290.00, 33.08, 1556.1, 0,
  'Financiado', NULL, NULL, 39.90, 39, NULL,
  0, 1, 1, 1, 'Eliminación de miedos',
  NULL, '3ª personas', 0, '[]', 'ventas/IMG_0463.jpeg',
  NULL, NULL, NULL, NULL, 'BORRADOR',
  '2025-10-29 18:55:47', '2026-01-14 05:17:27',
  NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15, 0, NULL, NULL,
  'facturado', 0, 0, 0, 0.00, 7.50, 0.00,
  0, 0, NULL, '', '', ''
);

-- Enlazar A ↔ B (si la tabla existe y aún no está el vínculo)
INSERT INTO `transaction_venta` (`id_contrato`, `id_contrato_asoc`, `created_at`, `updated_at`, `deleted_at`)
SELECT a.id, b.id, '2025-10-29 18:55:47', '2025-10-29 18:55:47', NULL
FROM ventas a
JOIN ventas b ON b.nro_contr_adm = '1189-B'
WHERE a.nro_contr_adm = '1189'
  AND NOT EXISTS (
    SELECT 1 FROM transaction_venta tv
    WHERE tv.id_contrato = a.id AND tv.id_contrato_asoc = b.id
  )
LIMIT 1;

-- Verifica: SELECT id, nro_contr_adm FROM ventas WHERE nro_contr_adm IN ('1189','1189-B');
