-- CASO 3 PASO C: Contrato principal de octubre — nro_contr_adm 1189
-- Sin forzar id (en producción el id 207 puede ser otra venta).
-- Requiere note_id 1837 y customer_id 1774 (ajusta si usaste otros ids).
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
  1837, 1774, 18, 14, '2025-10-02 14:21:54', 'noviembre',
  '1189', '91', '2025-10-03 00:00:00', 'TD', 2099.00,
  0.00, 173.00, 2272.00, 58.26, 2099, 0,
  'Financiado', NULL, NULL, 53.82, 39, NULL,
  0, 1, 1, 1, 'Eliminación de miedos',
  NULL, '3ª personas', 0, '[]', 'ventas/IMG_0463.jpeg',
  NULL, NULL, NULL, NULL, 'BORRADOR',
  '2025-10-02 16:21:54', '2026-01-14 05:17:22',
  'ventas/4224393c-12ef-4a52-b6b0-03c5715816e5.jpeg',
  'ventas/1a195219-113d-4377-9704-e7b24c011c80.jpeg',
  NULL, NULL, NULL, NULL, NULL, 15, 0, NULL, NULL,
  'facturado', 0, 0, 0, 0.00, 15.00, 0.00,
  0, 0, NULL, '', '', ''
);

-- Verifica: SELECT id, nro_contr_adm, note_id, customer_id, fecha_venta FROM ventas WHERE nro_contr_adm = '1189';
