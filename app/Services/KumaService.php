<?php

namespace App\Services;

use PDO;

class KumaService
{
    private function connect(): PDO
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                env('KUMA_DB_HOST', 'db'),
                env('KUMA_DB_PORT', '3306'),
                env('KUMA_DB_NAME', 'kuma')
            ),
            env('KUMA_DB_USERNAME', 'lintune'),
            env('KUMA_DB_PASSWORD', '')
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    // Returns [{id, name, status, url, admin_only}]
    // status: 0=down, 1=up, 2=pending/unknown, 3=maintenance
    // admin_only: true for monitors whose name contains "aio"
    public function getStatus(): array
    {
        try {
            $pdo = $this->connect();
            $rows = $pdo->query("
                SELECT m.id, m.name, m.url,
                    COALESCE(h.status, 2) AS status
                FROM monitor m
                LEFT JOIN heartbeat h ON h.id = (
                    SELECT MAX(id) FROM heartbeat WHERE monitor_id = m.id
                )
                WHERE m.active = 1
                ORDER BY m.id
            ")->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($row) => [
                'id'         => (int) $row['id'],
                'name'       => $row['name'],
                'status'     => (int) $row['status'],
                'url'        => $row['url'] ?? '',
                'admin_only' => str_contains(strtolower($row['name'] ?? ''), 'aio'),
            ], $rows);
        } catch (\Throwable) {
            return [];
        }
    }
}
