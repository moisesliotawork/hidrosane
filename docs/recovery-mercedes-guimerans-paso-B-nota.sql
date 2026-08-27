-- CASO 3 PASO B: Nota 1837 / 06031 (contrato octubre)
-- Requiere customer_id = 1774 (o el id real de Mercedes en producción).
-- Si 1837 ya existe o choca, cambia el id y actualiza el note_id del paso C.
-- Cmd+A → Cmd+Enter

INSERT INTO `notes` (
  `id`, `user_id`, `status`, `assignment_date`, `de_camino`, `observations`, `visit_date`, `visit_schedule`,
  `created_at`, `updated_at`, `customer_id`, `comercial_id`, `fuente`, `nro_nota`,
  `lat`, `lng`, `lat_dentro`, `lng_dentro`, `show_phone`, `estado_terminal`,
  `fecha_declaracion`, `sent_to_sala_at`, `printed`, `reten`
) VALUES (
  1837, 18, 'contacted', '2025-10-02', 0, NULL, '2025-10-02', 'TD',
  '2025-10-02 16:21:54', NOW(), 1774, 18, 'CALLE', '06031',
  NULL, NULL, NULL, NULL, 1, 'sala',
  NULL, NULL, 1, 0
);

-- Verifica: SELECT id, nro_nota, customer_id FROM notes WHERE nro_nota = '06031' OR id = 1837;
