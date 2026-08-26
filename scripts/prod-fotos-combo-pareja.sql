-- Fotos nuevas COMBO PAREJA (rompe caché de Cloudflare)
-- Sube primero:
--   assets/images/hero-combo-pareja-2027.png
--   assets/images/premio-nmax-mt15-2027.jpeg
-- Luego ejecuta esto en producción:

UPDATE site_images
SET url_image = '/assets/images/hero-combo-pareja-2027.png', updated_at = NOW()
WHERE key_image IN ('hero_banner', 'hero_mobile', 'fallback_image');

UPDATE site_images
SET url_image = '/assets/images/premio-nmax-mt15-2027.jpeg', updated_at = NOW()
WHERE key_image = 'hero_gallery_2';

SELECT key_image, url_image
FROM site_images
WHERE key_image IN ('hero_banner', 'hero_mobile', 'hero_gallery_2', 'fallback_image');
