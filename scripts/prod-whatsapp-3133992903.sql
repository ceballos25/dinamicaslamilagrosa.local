-- WhatsApp nuevo: 313 399 2903 (página + confirmación de transferencias)
-- NO toca Nequi ni otras cuentas de pago.

UPDATE settings
SET value_setting = '573133992903', date_updated_setting = NOW()
WHERE key_setting = 'whatsapp';

UPDATE settings
SET value_setting = 'https://wa.me/573133992903', date_updated_setting = NOW()
WHERE key_setting = 'whatsapp_chat_url';

UPDATE settings
SET value_setting = '313 399 2903 · 320 259 5184', date_updated_setting = NOW()
WHERE key_setting = 'footer_phones';

SELECT key_setting, value_setting
FROM settings
WHERE key_setting IN ('whatsapp', 'whatsapp_chat_url', 'footer_phones', 'transfer_nequi', 'transfer_nequi_name');
