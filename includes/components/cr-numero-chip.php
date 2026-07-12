<?php
declare(strict_types=1);

/**
 * Chip de número reutilizable (recibos, correo, transferencia, admin).
 * Incluye estilos inline para clientes de correo y páginas sin app.css.
 *
 * @param string $number
 * @param string $variant     ''|'selected'|'plain'|'libre'|'vendido'|'reservado'|'lg'
 * @param string $extraClasses
 * @param int    $idTicket
 */
function cr_numero_chip(
    string $number,
    string $variant = 'selected',
    string $extraClasses = '',
    int $idTicket = 0,
    bool $forRecibo = false
): string {
    $classes = ['cr-numero-chip'];

    if ($forRecibo) {
        $classes[] = 'cr-numero-chip--recibo';
    }

    if ($variant === 'lg') {
        $classes[] = 'cr-numero-chip--lg';
    } elseif ($variant !== '' && $variant !== 'selected') {
        $classes[] = 'cr-numero-chip--' . preg_replace('/[^a-z]/', '', $variant);
    }

    if ($extraClasses !== '') {
        foreach (preg_split('/\s+/', trim($extraClasses)) as $c) {
            if ($c !== '') {
                $classes[] = $c;
            }
        }
    }

    if ($idTicket > 0) {
        $classes[] = 'numero-ticket';
    }

    $classAttr = htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8');
    $dataAttr = $idTicket > 0 ? ' data-ticket-id="' . (int)$idTicket . '"' : '';
    $numberHtml = htmlspecialchars($number, ENT_QUOTES, 'UTF-8');

    if ($forRecibo) {
        $reciboStyles = cr_numero_chip_recibo_styles($variant);
        $tableStyle = htmlspecialchars($reciboStyles['table'], ENT_QUOTES, 'UTF-8');
        $cellStyle = htmlspecialchars($reciboStyles['cell'], ENT_QUOTES, 'UTF-8');

        return '<table role="presentation" class="' . $classAttr . '" cellpadding="0" cellspacing="0" border="0"'
            . $dataAttr
            . ' style="' . $tableStyle . '">'
            . '<tr><td align="center" valign="middle" style="' . $cellStyle . '">'
            . $numberHtml
            . '</td></tr></table>';
    }

    $styleAttr = htmlspecialchars(
        str_contains($extraClasses, 'cr-recibo-num')
            ? cr_numero_chip_comprobante_style($variant)
            : cr_numero_chip_inline_style($variant, false),
        ENT_QUOTES,
        'UTF-8'
    );

    return '<span class="' . $classAttr . '" style="' . $styleAttr . '"' . $dataAttr . '>'
        . $numberHtml
        . '</span>';
}

/**
 * @return array{table: string, cell: string}
 */
function cr_numero_chip_recibo_styles(string $variant = 'selected'): array
{
    $colorKey = preg_replace('/[^a-z]/', '', $variant === '' ? 'plain' : $variant);
    if ($colorKey === '') {
        $colorKey = 'plain';
    }

    $palettes = [
        'plain' => [
            'background-color' => 'transparent',
            'color' => '#0D0D0D',
            'border' => 'none',
        ],
        'selected' => [
            'background-color' => '#D99C2B',
            'color' => '#0D0D0D',
            'border' => '2px solid #8C5716',
        ],
        'libre' => [
            'background-color' => '#ffffff',
            'color' => '#0D0D0D',
            'border' => '2px solid #8C5716',
        ],
        'vendido' => [
            'background-color' => '#eeeeee',
            'color' => '#888888',
            'border' => '2px solid #cccccc',
        ],
        'reservado' => [
            'background-color' => '#ffffff',
            'color' => '#8C5716',
            'border' => '2px dashed #D99C2B',
        ],
    ];

    $colors = $palettes[$colorKey] ?? $palettes['plain'];

    $table = [
        'width' => '72px',
        'height' => '36px',
        'border-collapse' => 'collapse',
        'border-spacing' => '0',
        'margin' => '0',
        'padding' => '0',
        'box-sizing' => 'border-box',
        'table-layout' => 'fixed',
        'border' => 'none',
        'background' => 'transparent',
    ];

    $cell = array_merge([
        'display' => 'block',
        'width' => '100%',
        'height' => '32px',
        'line-height' => '32px',
        'padding' => '0 6px',
        'text-align' => 'center',
        'vertical-align' => 'middle',
        'font-family' => 'Consolas, Monaco, Courier New, monospace',
        'font-weight' => '700',
        'font-size' => '14px',
        'letter-spacing' => '0',
        'white-space' => 'nowrap',
        'border-radius' => $colorKey === 'plain' ? '0' : '10px',
        'box-sizing' => 'border-box',
    ], $colors);

    $fmt = static function (array $styles): string {
        $parts = [];
        foreach ($styles as $property => $value) {
            $parts[] = $property . ':' . $value;
        }

        return implode(';', $parts);
    };

    return [
        'table' => $fmt($table),
        'cell' => $fmt($cell),
    ];
}

/**
 * Comprobante de venta: misma estética compacta que numeros.php (admin-numero-inventario-chip).
 */
function cr_numero_chip_comprobante_style(string $variant = 'libre'): string
{
    $colorKey = preg_replace('/[^a-z]/', '', $variant === '' ? 'libre' : $variant);
    if ($colorKey === '') {
        $colorKey = 'libre';
    }

    $palettes = [
        'libre' => [
            'background-color' => '#ffffff',
            'color' => '#0D0D0D',
            'border' => '2px solid #8C5716',
        ],
        'selected' => [
            'background-color' => '#D99C2B',
            'color' => '#0D0D0D',
            'border' => '2px solid #8C5716',
        ],
    ];

    $colors = $palettes[$colorKey] ?? $palettes['libre'];

    $styles = array_merge([
        'display' => 'inline-block',
        'text-align' => 'center',
        'vertical-align' => 'middle',
        'font-family' => 'Montserrat, Arial, Helvetica, sans-serif',
        'font-weight' => '700',
        'font-size' => '14px',
        'line-height' => '1.2',
        'letter-spacing' => '0.02em',
        'border-radius' => '10px',
        'padding' => '6px 10px',
        'margin' => '0',
        'box-sizing' => 'border-box',
        'white-space' => 'nowrap',
    ], $colors);

    $parts = [];
    foreach ($styles as $property => $value) {
        $parts[] = $property . ':' . $value;
    }

    return implode(';', $parts);
}

/**
 * Estilos inline (email-safe: inline-block, colores hex, sin flex).
 */
function cr_numero_chip_inline_style(string $variant = 'selected', bool $forRecibo = false): string
{
    if ($forRecibo) {
        return cr_numero_chip_recibo_styles($variant)['table'];
    }

    $isLarge = ($variant === 'lg');
    $colorKey = $isLarge ? 'selected' : ($variant === '' ? 'selected' : preg_replace('/[^a-z]/', '', $variant));
    if ($colorKey === '') {
        $colorKey = 'selected';
    }

    $palettes = [
        'selected' => [
            'background-color' => '#D99C2B',
            'color' => '#0D0D0D',
            'border' => '2px solid #8C5716',
        ],
        'libre' => [
            'background-color' => '#ffffff',
            'color' => '#0D0D0D',
            'border' => '2px solid #8C5716',
        ],
        'vendido' => [
            'background-color' => '#eeeeee',
            'color' => '#888888',
            'border' => '2px solid #cccccc',
        ],
        'reservado' => [
            'background-color' => '#ffffff',
            'color' => '#8C5716',
            'border' => '2px dashed #D99C2B',
        ],
    ];

    $colors = $palettes[$colorKey] ?? $palettes['selected'];

    $styles = array_merge([
        'display' => 'inline-block',
        'text-align' => 'center',
        'vertical-align' => 'middle',
        'font-family' => 'Montserrat, Arial, Helvetica, sans-serif',
        'font-weight' => '700',
        'font-size' => $isLarge ? '17px' : '16px',
        'line-height' => '1.2',
        'letter-spacing' => '0.02em',
        'border-radius' => '10px',
        'padding' => $isLarge ? '0 12px' : '7px 10px',
        'min-width' => $isLarge ? '54px' : '46px',
        'min-height' => $isLarge ? '48px' : 'auto',
        'margin' => '0 6px 6px 0',
        'box-sizing' => 'border-box',
        'text-decoration' => 'none',
        'white-space' => 'nowrap',
    ], $colors);

    $parts = [];
    foreach ($styles as $property => $value) {
        $parts[] = $property . ':' . $value;
    }

    return implode(';', $parts);
}
