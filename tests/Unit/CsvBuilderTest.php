<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Export\CsvBuilder;
use PHPUnit\Framework\TestCase;

final class CsvBuilderTest extends TestCase
{
    public function testBuildsUtf8CsvWithHeaders(): void
    {
        $csv = (new CsvBuilder(['col_a', 'col_b']))
            ->addRow(['hola', '1000'])
            ->toString();

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('col_a,col_b', $csv);
        self::assertStringContainsString('hola,1000', $csv);
    }
}
