-- CASO 2: Maria Angeles Caldas Castiñeira — contrato 1299 / cliente 727
-- ANTES (en producción):
-- SELECT id, nro_contr_adm FROM ventas WHERE id=347 OR nro_contr_adm='1299';
-- SELECT id, empleado_id, name FROM users WHERE id IN (15,19,20);

-- ========== PASO A: Cliente ==========
-- Cmd+A → Cmd+Enter
INSERT INTO `customers` (
  `id`, `nro_cliente`, `first_names`, `last_names`, `phone`, `secondary_phone`, `email`, `dni`,
  `fecha_nac`, `iban`, `tipo_vivienda`, `estado_civil`, `situacion_laboral`, `ingresos_rango`,
  `num_hab_casa`, `age`, `edadTelOp`, `postal_code_id`, `postal_code`, `ciudad`, `provincia`,
  `primary_address`, `secondary_address`, `nro_piso`, `parish`, `ayuntamiento`, `created_at`, `updated_at`, `third_phone`
) VALUES (
  3367, '00860', 'Maria Angeles', 'Caldas Castiñeira', '986770255', '655395104', NULL, '35244615J',
  '1953-07-30', 'ES8201825180190201537673', 'propia', 'casado', 'pensionista', '900-1200',
  2, 72, NULL, NULL, '36995', 'Poio', 'Pontevedra',
  'Rua Convento 7', NULL, NULL, NULL, NULL, '2025-10-28 19:51:56', '2025-10-29 09:13:33', NULL
);

-- Verifica: SELECT id, first_names, last_names FROM customers WHERE id=3367;
