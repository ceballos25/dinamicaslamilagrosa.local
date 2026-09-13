<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Application\Audit\AuditActionLabels;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoAuditQueryRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @return array{data: list<array>, total: int}
     */
    public function search(array $filters, int $page = 1, int $limit = 50): array
    {
        $page = max(1, $page);
        $limit = min(200, max(1, $limit));
        $offset = ($page - 1) * $limit;

        [$whereSql, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT a.*, ad.email_admin
                FROM audit_logs a
                LEFT JOIN admins ad ON ad.id_admin = a.admin_id
                WHERE {$whereSql}
                ORDER BY a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }

    /**
     * @return array<string, int>
     */
    public function countByAction(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT a.action_audit, COUNT(*) AS total
             FROM audit_logs a
             WHERE {$whereSql}
             GROUP BY a.action_audit"
        );
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $counts[(string)$row['action_audit']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['scope']) && $filters['scope'] === 'ops') {
            $where[] = "a.action_audit NOT LIKE 'meta.%'";
        }
        if (!empty($filters['actions']) && is_array($filters['actions'])) {
            $allowed = [];
            foreach ($filters['actions'] as $actionKey) {
                $actionKey = preg_replace('/[^a-z._]/', '', strtolower((string)$actionKey)) ?? '';
                if ($actionKey !== '') {
                    $allowed[] = $actionKey;
                }
            }
            if ($allowed !== []) {
                $placeholders = [];
                foreach (array_values($allowed) as $i => $actionKey) {
                    $ph = ':inact' . $i;
                    $placeholders[] = $ph;
                    $params[$ph] = $actionKey;
                }
                $where[] = 'a.action_audit IN (' . implode(',', $placeholders) . ')';
            }
        }
        if (!empty($filters['action'])) {
            $actionQuery = (string)$filters['action'];
            $matched = AuditActionLabels::actionsMatching($actionQuery);
            if ($matched !== []) {
                $placeholders = [];
                foreach ($matched as $i => $actionKey) {
                    $ph = ':act' . $i;
                    $placeholders[] = $ph;
                    $params[$ph] = $actionKey;
                }
                $where[] = '(a.action_audit LIKE :action OR a.action_audit IN (' . implode(',', $placeholders) . '))';
                $params[':action'] = '%' . $actionQuery . '%';
            } else {
                $where[] = 'a.action_audit LIKE :action';
                $params[':action'] = '%' . $actionQuery . '%';
            }
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = :etype';
            $params[':etype'] = $filters['entity_type'];
        }
        if (!empty($filters['admin_id'])) {
            $where[] = 'a.admin_id = :admin';
            $params[':admin'] = (int)$filters['admin_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.created_at) >= :df';
            $params[':df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.created_at) <= :dt';
            $params[':dt'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $params];
    }
}
