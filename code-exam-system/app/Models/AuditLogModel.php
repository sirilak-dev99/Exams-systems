<?php

declare(strict_types=1);

final class AuditLogModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function record(
        ?int $userId,
        string $action,
        string $entityTable,
        int|string|null $entityId,
        mixed $oldValues,
        mixed $newValues,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (
                users_id, action, entity_table, entity_id,
                old_values, new_values, reason, ip_address, user_agent, created_at
            ) VALUES (
                :users_id, :action, :entity_table, :entity_id,
                :old_values, :new_values, :reason, :ip_address, :user_agent, NOW()
            )
        ");
        $stmt->execute([
            ':users_id'     => $userId,
            ':action'       => mb_substr($action, 0, 150),
            ':entity_table' => mb_substr($entityTable, 0, 255),
            ':entity_id'    => $entityId !== null && is_numeric((string)$entityId) ? (int)$entityId : null,
            ':old_values'   => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            ':new_values'   => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            ':reason'       => $reason,
            ':ip_address'   => $ipAddress !== null ? substr($ipAddress, 0, 45) : null,
            ':user_agent'   => $userAgent !== null ? substr($userAgent, 0, 255) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function listLogs(
        ?string $entityTable = null,
        ?int $userId = null,
        ?string $action = null,
        int $limit = 50
    ): array {
        $sql = "
            SELECT
                al.*,
                u.display_name AS actor_name,
                u.email AS actor_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id_users = al.users_id
            WHERE 1 = 1
        ";
        $params = [];

        if ($entityTable !== null && $entityTable !== '') {
            $sql .= " AND al.entity_table = :entity_table";
            $params[':entity_table'] = $entityTable;
        }
        if ($userId !== null) {
            $sql .= " AND al.users_id = :users_id";
            $params[':users_id'] = $userId;
        }
        if ($action !== null && $action !== '') {
            $sql .= " AND al.action LIKE :action";
            $params[':action'] = '%' . $action . '%';
        }

        $safeLimit = max(1, min(200, $limit));
        $sql .= " ORDER BY al.id_audit_logs DESC LIMIT {$safeLimit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            if (is_string($r['old_values'])) {
                $decoded = json_decode($r['old_values'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $r['old_values'] = $decoded;
                }
            }
            if (is_string($r['new_values'])) {
                $decoded = json_decode($r['new_values'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $r['new_values'] = $decoded;
                }
            }
        }
        unset($r);

        return $rows;
    }
}