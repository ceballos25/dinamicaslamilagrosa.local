<?php
declare(strict_types=1);

namespace App\Application\Comprobante\Service;

use App\Application\Comprobante\Contract\ReferenceExtractorInterface;
use App\Application\Comprobante\ReferenceNormalizer;

/**
 * Extrae la referencia única del movimiento (UUID, Nº comprobante, aprobación).
 * Nunca debe tomar la llave Bre-B / cuenta destino como referencia.
 */
final class ColombianBankReferenceExtractor implements ReferenceExtractorInterface
{
    /**
     * Orden = prioridad. Cada patrón exige etiqueta de referencia de pago.
     *
     * @var list<array{pattern: string, bank: string|null, bonus: int}>
     */
    private const PATTERNS = [
        // UUID Bancolombia / Bre-B web: "Referencia No. 52f6cf0d-e315-..."
        [
            'pattern' => '/referencia\s*(?:no\.?|nro\.?|n[°º]|num(?:ero|éro)?\.?)?\s*[:#.\-]?\s*([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/iu',
            'bank' => null,
            'bonus' => 40,
        ],
        // Referencia alfanumérica / numérica etiquetada
        [
            'pattern' => '/referencia\s*(?:no\.?|nro\.?|n[°º]|num(?:ero|éro)?\.?)?\s*[:#.\-]?\s*([A-Z0-9][A-Z0-9\-]{5,48})/iu',
            'bank' => null,
            'bonus' => 30,
        ],
        // "Comprobante No. TR9drli4k5EC" — No./N° obligatorio (evita "comprobante escaneando")
        [
            'pattern' => '/comprobante\s+(?:no\.?|nro\.?|n[°º]|num(?:ero|éro)?\.?)\s*[:#.\-]?\s*([A-Z0-9][A-Z0-9\-]{5,48})/iu',
            'bank' => null,
            'bonus' => 28,
        ],
        [
            'pattern' => '/n[uú]mero\s+de\s+aprobaci[oó]n\s*[:#.\-]?\s*([0-9]{6,40})/iu',
            'bank' => null,
            'bonus' => 35,
        ],
        [
            'pattern' => '/num(?:ero|éro)?\.?\s*de\s*autorizaci[oó]n\s*[:#.\-]?\s*([0-9A-Z\-]{6,40})/iu',
            'bank' => null,
            'bonus' => 25,
        ],
        [
            'pattern' => '/id(?:\s+de)?\s*transacci[oó]n\s*[:#.\-]?\s*([A-Z0-9\-]{6,48})/iu',
            'bank' => null,
            'bonus' => 22,
        ],
        [
            'pattern' => '/c[oó]digo(?:\s+de)?\s+(?:transacci[oó]n|transferencia|operaci[oó]n)\s*[:#.\-]?\s*([A-Z0-9\-]{6,48})/iu',
            'bank' => null,
            'bonus' => 22,
        ],
        [
            'pattern' => '/codigo(?:\s+de)?\s+seguridad\s*[:#.\-]?\s*([0-9]{6,20})/iu',
            'bank' => null,
            'bonus' => 15,
        ],
    ];

    private const REJECT_RAW = [
        'ESCANEANDO',
        'AUTENTICIDAD',
        'TRANSFERENCIA',
        'COMPROBANTE',
        'EXITOSA',
        'EXITOSO',
        'REALIZADO',
        'REALIZADA',
        'DAVIPLATA',
        'NEQUI',
        'BANCOLOMBIA',
        'BREB',
        'BBVA',
        'BOAVIVIENDA',
        'DAVIVIENDA',
        'MARTIN',
        'RODRIGUEZ',
        'CAUSIL',
    ];

    public function extract(string $ocrText): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($ocrText)) ?? '';
        if ($text === '') {
            return ['raw' => null, 'normalized' => null, 'bank' => null];
        }

        $destinationKeys = $this->collectDestinationKeys($text);

        $bestRaw = null;
        $bestNorm = null;
        $bestBank = null;
        $bestScore = 0;

        foreach (self::PATTERNS as $index => $item) {
            if (!preg_match_all($item['pattern'], $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches as $match) {
                $raw = trim((string)($match[1][0] ?? ''));
                $offset = (int)($match[1][1] ?? 0);
                if ($raw === '' || $this->isRejectedCandidate($text, $raw, $offset, $destinationKeys)) {
                    continue;
                }

                $norm = ReferenceNormalizer::normalize($raw);
                if ($norm === null || ReferenceNormalizer::isNonTransactional($norm)) {
                    continue;
                }

                $score = (100 - $index) + (int)$item['bonus'] + $this->candidateBonus($raw);
                if ($score <= $bestScore) {
                    continue;
                }

                $bestScore = $score;
                $bestRaw = $raw;
                $bestNorm = $norm;
                $bestBank = $item['bank'];
            }
        }

        return [
            'raw' => $bestRaw,
            'normalized' => $bestNorm,
            'bank' => $bestBank,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectDestinationKeys(string $text): array
    {
        $keys = [];
        // "a la llave BBVA 0091706852" / "Llave: 0091706852" / "llave 0091706852"
        if (preg_match_all(
            '/\bllave\b(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ.]+){0,3}\s*[:#.\-]?\s*([0-9]{6,15})\b/iu',
            $text,
            $m
        )) {
            foreach ($m[1] as $key) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param list<string> $destinationKeys
     */
    private function isRejectedCandidate(string $text, string $raw, int $offset, array $destinationKeys): bool
    {
        $digitsOnly = preg_replace('/\D/', '', $raw) ?? '';
        foreach ($destinationKeys as $key) {
            if ($digitsOnly !== '' && $digitsOnly === $key) {
                return true;
            }
            if (strcasecmp($raw, $key) === 0) {
                return true;
            }
        }

        $upper = strtoupper(preg_replace('/[^A-Z0-9]/', '', $raw) ?? '');
        if ($upper === '' || in_array($upper, self::REJECT_RAW, true)) {
            return true;
        }

        // Solo letras (p. ej. ESCANEANDO): una ref real suele traer dígitos o mixto.
        if (preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\-]+$/u', $raw) === 1 && !preg_match('/[0-9]/', $raw)) {
            return true;
        }

        $before = substr($text, max(0, $offset - 56), min(56, $offset));
        if (preg_match(
            '/(?:llave|documento|cedula|c[eé]dula|celular|tel[eé]fono|cuenta|para)\b(?:\s+[A-Za-zÁÉÍÓÚáéíóúÑñ.]+){0,4}\s*$/iu',
            $before
        ) === 1) {
            return true;
        }

        return false;
    }

    private function candidateBonus(string $raw): int
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $raw) === 1) {
            return 50;
        }
        if (preg_match('/^\d{20,40}$/', $raw) === 1) {
            return 20;
        }
        if (preg_match('/[A-Za-z]/', $raw) === 1 && preg_match('/\d/', $raw) === 1) {
            return 10;
        }

        return 0;
    }
}
