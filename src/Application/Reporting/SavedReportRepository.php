<?php
declare(strict_types=1);

namespace App\Application\Reporting;

use App\Infrastructure\Database\PdoFactory;
use PDO;

final class SavedReportRepository
{
    public function listAll(): array
    {
        $pdo = PdoFactory::get();
        $st = $pdo->query(
            'SELECT id_saved_report, name_report, id_admin_created, date_created_report FROM saved_reports ORDER BY date_updated_report DESC LIMIT 500'
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(int $id): ?array
    {
        $pdo = PdoFactory::get();
        $st = $pdo->prepare('SELECT * FROM saved_reports WHERE id_saved_report = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(string $name, string $specJson, ?int $adminId): int
    {
        $pdo = PdoFactory::get();
        $st = $pdo->prepare(
            'INSERT INTO saved_reports (name_report, spec_report, id_admin_created) VALUES (:n, :s, :a)'
        );
        $st->execute([':n' => $name, ':s' => $specJson, ':a' => $adminId]);
        return (int)$pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $specJson, ?int $adminId): bool
    {
        $pdo = PdoFactory::get();
        $sql = 'UPDATE saved_reports SET name_report = :n, spec_report = :s, date_updated_report = NOW()';
        $params = ['n' => $name, 's' => $specJson, 'id' => $id];
        if ($adminId !== null && $adminId > 0) {
            $sql .= ', id_admin_created = :a';
            $params['a'] = $adminId;
        }
        $sql .= ' WHERE id_saved_report = :id';
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return $st->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $pdo = PdoFactory::get();
        $st = $pdo->prepare('DELETE FROM saved_reports WHERE id_saved_report = :id');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
