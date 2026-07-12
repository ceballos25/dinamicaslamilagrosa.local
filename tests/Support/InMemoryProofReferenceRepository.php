<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Comprobante\Repository\ProofReferenceRepositoryInterface;

final class InMemoryProofReferenceRepository implements ProofReferenceRepositoryInterface
{
    /** @var array<string, true> */
    private array $references = [];

    /** @var array<string, true> */
    private array $hashes = [];

    public function seedReference(string $referenceNorm): void
    {
        $this->references[$referenceNorm] = true;
    }

    public function seedHash(string $hash): void
    {
        $this->hashes[strtolower($hash)] = true;
    }

    public function existsByReferenceNorm(string $referenceNorm, ?int $excludeTransferId = null): bool
    {
        return isset($this->references[$referenceNorm]);
    }

    public function existsByFileHash(string $fileHash, ?int $excludeTransferId = null): bool
    {
        return isset($this->hashes[strtolower($fileHash)]);
    }
}
