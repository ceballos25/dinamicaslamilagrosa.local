<?php

class SettingsController
{
    public const TABLE = 'settings';

    public static function obtenerSettings()
    {
        $rows = Db::fetchAll(
            'SELECT id_setting, key_setting, value_setting FROM settings ORDER BY id_setting ASC'
        );

        return ['success' => true, 'data' => $rows];
    }

    /**
     * Solo claves necesarias en la landing pública (sin API keys ni secretos).
     *
     * @return array{success: true, data: list<array{id_setting: int, key_setting: string, value_setting: string}>}
     */
    public static function obtenerSettingsPublicas()
    {
        $allowed = [
            'web_id_raffle',
            'web_compras_habilitadas',
            'web_mensaje_compras_bloqueadas',
            'barra',
            'barra_modo',
            'barra_fija',
            'pricing_packages_enabled',
            'pricing_tiered_enabled',
            'pricing_first_unit',
            'pricing_tier1_unit',
            'pricing_tier2_unit',
            'pricing_bulk_threshold',
            'social_instagram_url',
            'social_facebook_url',
            'social_tiktok_url',
            'whatsapp',
            'whatsapp_chat_url',
            'whatsapp_secondary',
            'transfer_breb_key',
            'transfer_breb_name',
            'transfer_bancolombia',
            'transfer_bancolombia_name',
            'transfer_nequi',
            'transfer_nequi_name',
            'nombre_rifa',
            'landing_meta_description',
            'landing_hero_title',
            'landing_premio_mayor_titulo',
            'landing_premio_mayor_desc',
            'landing_premio_extra_titulo',
            'landing_premio_extra_label',
            'landing_premio_extra_desc',
            'footer_tagline',
            'footer_phones',
            'contact_email',
            'footer_location',
            'footer_payments',
        ];

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $rows = Db::fetchAll(
            "SELECT id_setting, key_setting, value_setting
             FROM settings
             WHERE key_setting IN ($placeholders)
             ORDER BY id_setting ASC",
            $allowed
        );

        return ['success' => true, 'data' => $rows];
    }

    public static function actualizarSettings($data)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, ['action', 'csrf_token', '_csrf'], true)) {
                continue;
            }

            $key = trim((string)$key);
            $value = trim((string)$value);

            if ($key === '' || !preg_match('/^[a-z0-9_]+$/i', $key)) {
                return [
                    'success' => false,
                    'message' => "Key inválida: $key",
                ];
            }

            $exists = Db::fetchOne(
                'SELECT id_setting, value_setting FROM settings WHERE key_setting = :k LIMIT 1',
                [':k' => $key]
            );

            if (!$exists) {
                Db::insert(self::TABLE, [
                    'key_setting' => strtolower($key),
                    'value_setting' => $value,
                    'date_created_setting' => date('Y-m-d'),
                    'date_updated_setting' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            if ((string)($exists->value_setting ?? '') === $value) {
                continue;
            }

            $n = Db::update(
                self::TABLE,
                [
                    'value_setting' => $value,
                    'date_updated_setting' => date('Y-m-d H:i:s'),
                ],
                'key_setting = :k',
                [':k' => $key]
            );

            if ($n < 1) {
                return [
                    'success' => false,
                    'message' => "No se pudo actualizar: $key",
                ];
            }
        }

        if (class_exists(\App\Shared\Config\DynamicConfig::class)) {
            (new \App\Shared\Config\DynamicConfig())->flush();
        }

        return ['success' => true, 'message' => 'Settings actualizados'];
    }

    public static function crearSetting($data)
    {
        if (trim((string)($data['key_setting'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Key requerida'];
        }

        $key = strtolower(trim($data['key_setting']));
        $key = preg_replace('/\s+/', '_', $key);
        $value = isset($data['value_setting']) ? trim((string)$data['value_setting']) : '';

        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            return ['success' => false, 'message' => 'Key inválida'];
        }

        $exists = Db::fetchOne(
            'SELECT id_setting FROM settings WHERE key_setting = :k LIMIT 1',
            [':k' => $key]
        );

        if ($exists) {
            return ['success' => false, 'message' => 'La key ya existe'];
        }

        Db::insert(self::TABLE, [
            'key_setting' => $key,
            'value_setting' => $value,
            'date_created_setting' => date('Y-m-d'),
            'date_updated_setting' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Setting creado'];
    }

    public static function eliminarSetting($data)
    {
        if (empty($data['id_setting'])) {
            return ['success' => false, 'message' => 'ID requerido'];
        }

        $row = Db::fetchOne(
            'SELECT key_setting FROM settings WHERE id_setting = :id LIMIT 1',
            [':id' => (int)$data['id_setting']]
        );

        if (!$row) {
            return ['success' => false, 'message' => 'Setting no encontrado'];
        }

        $key = (string)$row->key_setting;
        $protected = ['precio_ticket', 'max_tickets', 'min_tickets'];

        if (in_array($key, $protected, true)) {
            return ['success' => false, 'message' => 'Este setting no se puede eliminar'];
        }

        $n = Db::delete(self::TABLE, 'id_setting = :id', [':id' => (int)$data['id_setting']]);

        return $n > 0
            ? ['success' => true, 'message' => 'Setting eliminado']
            : ['success' => false, 'message' => 'Error al eliminar'];
    }
}
