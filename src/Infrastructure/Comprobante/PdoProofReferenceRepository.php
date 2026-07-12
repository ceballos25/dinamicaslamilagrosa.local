<?php
declare(strict_types=1);

namespace App\Infrastructure\Comprobante;

use App\Domain\Comprobante\Repository\ProofReferenceRepositoryInterface;

final class PdoProofReferenceRepository implements ProofReferenceRepositoryInterface
{
    public function existsByReferenceNorm(string $referenceNorm, ?int $excludeTransferId = null): bool
    {
        $referenceNorm = trim($referenceNorm);
        if ($referenceNorm === '') {
            return false;
        }

        $sql = 'SELECT id_transfer FROM transfers WHERE proof_reference_norm = :ref';
        $params = [':ref' => $referenceNorm];
        if ($excludeTransferId !== null && $excludeTransferId > 0) {
            $sql .= ' AND id_transfer <> :id';
            $params[':id'] = $excludeTransferId;
        }
        $sql .= ' LIMIT 1';

        $row = \Db::fetchOne($sql, $params);

        return $row !== null && $row !== false;
    }

    public function existsByFileHash(string $fileHash, ?int $excludeTransferId = null): bool
    {
        $fileHash = strtolower(trim($fileHash));
        if ($fileHash === '') {
            return false;
        }

        $sql = 'SELECT id_transfer FROM transfers WHERE proof_file_hash = :hash';
        $params = [':hash' => $fileHash];
        if ($excludeTransferId !== null && $excludeTransferId > 0) {
            $sql .= ' AND id_transfer <> :id';
            $params[':id'] = $excludeTransferId;
        }
        $sql .= ' LIMIT 1';

        $row = \Db::fetchOne($sql, $params);

        return $row !== null && $row !== false;
    }
}
