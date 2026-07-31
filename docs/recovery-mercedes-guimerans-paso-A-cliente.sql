-- CASO 3 PASO A: Cliente Mercedes Guimerans Lorenzo
-- Solo si el diagnóstico NO encontró la cliente (dni 35301073Y).
-- Si ya existe con OTRO id, NO ejecutes este paso: anota ese id y úsalo en B/C.
-- Cmd+A → Cmd+Enter

INSERT INTO `customers` (
  `id`, `nro_cliente`, `first_names`, `last_names`, `phone`, `secondary_phone`, `email`, `dni`,
  `fecha_nac`, `iban`, `tipo_vivienda`, `estado_civil`, `situacion_laboral`, `ingresos_rango`,
  `num_hab_casa`, `age`, `edadTelOp`, `postal_code_id`, `postal_code`, `ciudad`, `provincia`,
  `primary_address`, `secondary_address`, `nro_piso`, `parish`, `ayuntamiento`, `created_at`, `updated_at`, `third_phone`
) VALUES (
  1774, '00725', 'Mercedes', 'Guimerans Lorenzo', '986328741', '666188250', NULL, '35301073Y',
  '1963-05-29', 'ES3620805118313000000892', 'propia', 'viudo', 'pensionista', '>1200',
  1, 62, NULL, NULL, '36948', 'Cangas', 'Pontevedra',
  'Rúa Gondesendes', NULL, '25', 'Cangas ', NULL, '2025-10-02 16:21:54', '2026-01-09 17:15:28', NULL
);

-- Verifica: SELECT id, first_names, last_names, dni FROM customers WHERE dni = '35301073Y';
