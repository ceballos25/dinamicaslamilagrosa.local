<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Repository\PdoSiteImageRepository;
use Tests\Support\IntegrationTestCase;

final class LandingImagesRepositoryTest extends IntegrationTestCase
{
    public function testSiteImagesSeeded(): void
    {
        $map = (new PdoSiteImageRepository())->asKeyMap();
        self::assertArrayHasKey('logo', $map);
        self::assertArrayHasKey('favicon', $map);
        $logoUrl = (string)($map['logo']['url'] ?? '');
        self::assertNotSame('', $logoUrl);
        self::assertTrue(
            str_starts_with($logoUrl, 'https://') || str_starts_with($logoUrl, '/'),
            'Logo URL debe ser absoluta o ruta bajo el sitio'
        );
    }
}
