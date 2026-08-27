-- PASO B2 — Crear nota 3400 (sin ON DUPLICATE)
-- 1) Selecciona TODO (Cmd+A)  2) Run (Cmd+Enter)

INSERT INTO `notes` (
  `id`, `user_id`, `status`, `assignment_date`, `de_camino`, `observations`, `visit_date`, `visit_schedule`,
  `created_at`, `updated_at`, `customer_id`, `comercial_id`, `fuente`, `nro_nota`,
  `lat`, `lng`, `lat_dentro`, `lng_dentro`, `show_phone`, `estado_terminal`,
  `fecha_declaracion`, `sent_to_sala_at`, `printed`, `reten`
) VALUES (
  3400, 18, 'contacted', '2025-10-28', 0, NULL, '2025-10-28', 'TD',
  '2025-10-28 15:17:11', NOW(), 3329, 18, 'CALLE', '07571',
  NULL, NULL, NULL, NULL, 1, 'venta',
  NULL, NULL, 1, 0
);

-- Después ejecuta:
-- SELECT id, nro_nota, customer_id FROM notes WHERE id=3400;
