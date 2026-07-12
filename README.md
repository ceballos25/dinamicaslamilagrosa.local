# Combo Stickers

Rifas en línea con venta por transferencia Bre-B, validación de comprobantes (OCR) y panel administrador.

**Arquitectura detallada:** lee [ARCHITECTURE.txt](ARCHITECTURE.txt) antes de tocar código nuevo.

## Requisitos

- PHP 8.1+, MySQL 8, extensiones: pdo_mysql, curl, gd, fileinfo
- Composer

## Instalación rápida

```bash
composer install --no-dev
cp .env-co.example ../.env-co   # ajustar SITE_URL, DB, OCR, Meta
```

## Estructura mental

| Ruta | Qué es |
|------|--------|
| `index.php` | Landing + checkout |
| `transferencia.php` | Confirmación post-compra |
| `front/` | Admin (requiere login en `dash.php`) |
| `front/ajax/*.ajax.php` | API JSON → `bootstrap/app.php` |
| `controllers/` | Negocio legacy |
| `src/` | Capas nuevas (checkout, OCR, reportes) |

## Producción

```
APP_ENV=production
DEBUG_MODE=false
DISPLAY_ERRORS=false
SESSION_COOKIE_SECURE=true
```

Settings en BD: `transfer_breb_key`, `comprobante_validation_mode=strict`.

## Tests

```bash
./vendor/bin/phpunit
```

