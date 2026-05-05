<?php

namespace App\Services;

use PDO;

class KumaService
{
    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = env('KUMA_DB_PATH', '/opt/kuma_data/kuma.db');
    }

    // Returns [{id, name, status, url, admin_only}]
    // status: 0=down, 1=up, 2=pending/unknown, 3=maintenance
    // admin_only: true for monitors whose name contains "aio"
    public function getStatus(): array
    {
        if (!file_exists($this->dbPath)) {
            return [];
        }
        try {
            $pdo = new PDO('sqlite:' . $this->dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA busy_timeout=3000;');

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
