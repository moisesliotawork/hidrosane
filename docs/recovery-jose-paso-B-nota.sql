-- PASO B — Nota (solo si el paso A funcionó)
INSERT INTO `notes` (
  `id`, `user_id`, `status`, `assignment_date`, `de_camino`, `observations`, `visit_date`, `visit_schedule`,
  `created_at`, `updated_at`, `customer_id`, `comercial_id`, `fuente`, `nro_nota`,
  `lat`, `lng`, `lat_dentro`, `lng_dentro`, `show_phone`, `estado_terminal`,
  `fecha_declaracion`, `sent_to_sala_at`, `printed`, `reten`
) VALUES (
  3400, 18, 'contacted', '2025-10-28', 0, NULL, '2025-10-28', 'TD',
  '2025-10-28 15:17:11', '2026-02-21 16:09:24', 3329, 18, 'CALLE', '07571',
  NULL, NULL, NULL, NULL, 1, 'venta',
  NULL, NULL, 1, 0
)
ON DUPLICATE KEY UPDATE
  `customer_id`=VALUES(`customer_id`),
  `estado_terminal`='venta';

-- SELECT id, nro_nota, customer_id, estado_terminal FROM notes WHERE id=3400;
