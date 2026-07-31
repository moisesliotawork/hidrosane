-- CASO 2 PASO B: Nota 3442 / 07613
-- Cmd+A → Cmd+Enter  (debe decir 1 row affected)
INSERT INTO `notes` (
  `id`, `user_id`, `status`, `assignment_date`, `de_camino`, `observations`, `visit_date`, `visit_schedule`,
  `created_at`, `updated_at`, `customer_id`, `comercial_id`, `fuente`, `nro_nota`,
  `lat`, `lng`, `lat_dentro`, `lng_dentro`, `show_phone`, `estado_terminal`,
  `fecha_declaracion`, `sent_to_sala_at`, `printed`, `reten`
) VALUES (
  3442, 20, 'contacted', '2025-10-28', 0, NULL, NULL, 'TD',
  '2025-10-28 19:51:56', NOW(), 3367, 20, 'CALLE', '07613',
  NULL, NULL, NULL, NULL, 0, 'venta',
  NULL, NULL, 1, 0
);

-- Verifica: SELECT id, nro_nota FROM notes WHERE id=3442;
