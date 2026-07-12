<?php
declare(strict_types=1);

namespace App\Application\Export;

final class CsvBuilder
{
    /** @var list<list<string|int|float|null>> */
    private array $rows = [];

    /** @param list<string> $headers */
    public function __construct(
        private readonly array $headers
    ) {
    }

    /** @param list<string|int|float|null> $row */
    public function addRow(array $row): self
    {
        $this->rows[] = $row;

        return $this;
    }

    public function toString(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->headers);
        foreach ($this->rows as $row) {
            fputcsv($handle, array_map(
                static fn (mixed $cell): string => $cell === null ? '' : (string)$cell,
                $row
            ));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }
}
