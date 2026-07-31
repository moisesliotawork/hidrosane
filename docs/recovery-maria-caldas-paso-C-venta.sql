-- CASO 2 PASO C (corregido): contrato 1299 sin forzar id=347
-- Porque en produccion el id 347 ya pertenece a OTRA venta.
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
  3442, 3367, 20, 19, '2025-10-28 18:51:56', 'diciembre',
  '1299', '727', '2025-10-29 00:00:00', '17-19', 1899.00,
  0.00, 0.00, 1899.00, 48.69, 1899, 0,
  'Financiado', NULL, NULL, 48.69, 39, NULL,
  0, 0, 1, 1, 'Muy rebatido de objeciones',
  NULL, '3a personas', 0, '[]', 'ventas/IMG_1162.jpeg',
  NULL, NULL, NULL, NULL, 'BORRADOR',
  '2025-10-28 19:51:56', '2026-01-14 05:17:26', 'ventas/IMG_1158.jpeg', 'ventas/IMG_1159.jpeg',
  'ventas/c11906e1-850c-4bcf-a29b-b11ef0cfe7be.jpeg', NULL, NULL, NULL, NULL, 15, 0, NULL, NULL,
  'nulo_en_reparto', 0, 0, 0, 0.00, 15.00, 0.00,
  0, 0, NULL, '', '', ''
);
