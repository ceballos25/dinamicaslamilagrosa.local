-- =============================================================================
-- Dinámicas La Milagrosa — 3era Dinámica · COMBO PAREJA
-- Ejecutar en PRODUCCIÓN (phpMyAdmin / MySQL) DESPUÉS de subir los archivos.
-- No crea tablas nuevas. Solo actualiza rifa + ajustes de la web.
-- =============================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1) Rifa principal (ajusta id_raffle si en prod NO es 1)
-- ---------------------------------------------------------------------------
UPDATE raffles
SET
  title_raffle = '3era Dinámica · COMBO PAREJA',
  description_raffle = 'NMAX 2027 + MT-15 2027. Inicia 26 ago. Final probable 24 oct (si hay 70% vendido). 1er sorteo 19 sep · 2do sorteo 10 oct con Lotería de Boyacá. 2 sorteos de $4.000.000 en tecnología. 6 bendecidos $500.000. Valor puesto $2.000.',
  price_raffle = 2000.00,
  date_raffle = '2026-10-24 20:00:00',
  status_raffle = 1,
  type_raffle = 'automatic',
  min_quantity_raffle = 10,
  digits_raffle = 5
WHERE id_raffle = 1;

-- ---------------------------------------------------------------------------
-- 2) Ajustes del sitio (upsert por key_setting)
-- ---------------------------------------------------------------------------
INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting) VALUES
('web_compras_habilitadas', '1', CURDATE(), NOW()),
('web_id_raffle', '1', CURDATE(), NOW()),
('nombre_rifa', 'Dinámicas La Milagrosa', CURDATE(), NOW()),

('landing_hero_title', '¡Gana el COMBO PAREJA!\nNMAX 2027 + MT-15', CURDATE(), NOW()),
('landing_meta_description', 'Dinámicas La Milagrosa — 3era Dinámica COMBO PAREJA: NMAX 2027 + MT-15 2027, sorteos con Boyacá y 6 bendecidos. Compra segura por transferencia.', CURDATE(), NOW()),
('landing_premio_mayor_titulo', '3era Dinámica · COMBO PAREJA', CURDATE(), NOW()),
('landing_premio_mayor_desc', 'NMAX 2027 + MT-15 2027\nJuega con Lotería de Boyacá\n19 sep y 10 oct · 2 sorteos de $4M en tecnología\nFinal probable 24 oct (si hay 70% vendido)', CURDATE(), NOW()),
('landing_premio_extra_titulo', 'Bendecidos', CURDATE(), NOW()),
('landing_premio_extra_label', '6 nros · $500.000 c/u', CURDATE(), NOW()),
('landing_premio_extra_desc', '', CURDATE(), NOW()),

('footer_tagline', 'Participa fácil, rápido y seguro desde cualquier lugar de Colombia. Transparencia y respaldo en cada dinámica.', CURDATE(), NOW()),
('footer_phones', '320 259 5153 · 320 259 5184', CURDATE(), NOW()),
('contact_email', 'info@dinamicaslamilagrosa.com', CURDATE(), NOW()),
('footer_location', 'Colombia', CURDATE(), NOW()),
('footer_payments', 'Bre-B · Bancolombia · Nequi', CURDATE(), NOW()),

('pricing_packages_enabled', '1', CURDATE(), NOW()),
('pricing_tiered_enabled', '1', CURDATE(), NOW()),
('pricing_first_unit', '2000', CURDATE(), NOW()),
('pricing_tier1_unit', '1700', CURDATE(), NOW()),
('pricing_tier2_unit', '1500', CURDATE(), NOW()),
('pricing_bulk_threshold', '40', CURDATE(), NOW()),

('barra_modo', 'sumar', CURDATE(), NOW()),
('barra', '0', CURDATE(), NOW()),
('barra_fija', '0', CURDATE(), NOW()),

('transfer_nequi', '3239108041', CURDATE(), NOW()),
('transfer_nequi_name', 'Maira Figueroa', CURDATE(), NOW())
ON DUPLICATE KEY UPDATE
  value_setting = VALUES(value_setting),
  date_updated_setting = NOW();

COMMIT;

-- ---------------------------------------------------------------------------
-- 3) Verificación rápida
-- ---------------------------------------------------------------------------
SELECT id_raffle, title_raffle, price_raffle, date_raffle, min_quantity_raffle, status_raffle
FROM raffles WHERE id_raffle = 1;

SELECT key_setting, value_setting
FROM settings
WHERE key_setting IN (
  'web_compras_habilitadas', 'web_id_raffle', 'nombre_rifa',
  'landing_hero_title', 'landing_premio_mayor_titulo', 'landing_premio_mayor_desc',
  'landing_premio_extra_titulo', 'landing_premio_extra_label',
  'pricing_packages_enabled', 'pricing_first_unit', 'pricing_tier1_unit',
  'pricing_tier2_unit', 'pricing_bulk_threshold',
  'transfer_nequi', 'transfer_nequi_name'
)
ORDER BY key_setting;

-- =============================================================================
-- MANUAL (no va en SQL):
-- 1) Subir archivos del proyecto (imágenes, pricing-packages.php, hero, JS, CSS…).
-- 2) En Inventario: marcar los 6 nros bendecidos (premium).
-- 3) Revisar en Ajustes: Bre-B / Bancolombia / WhatsApp / redes (no se tocan aquí
--    para no pisar lo que ya tengas en prod, salvo Nequi).
-- 4) Ctrl+F5 en la web pública.
-- =============================================================================
