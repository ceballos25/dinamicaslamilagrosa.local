<?php
declare(strict_types=1);

namespace App\Domain\Comprobante\Repository;

interface ProofReferenceRepositoryInterface
{
    public function existsByReferenceNorm(string $referenceNorm, ?int $excludeTransferId = null): bool;

    public function existsByFileHash(string $fileHash, ?int $excludeTransferId = null): bool;
}
