<?php

declare(strict_types=1);

final class ProgramModel
{
    private PDO $db;

    public const ALLOWED_STATUSES = ['active', 'inactive', 'archived'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(array $filters = []): array
    {
        $sql = "
            SELECT
                p.id_programs,
                p.code,
                p.name,
                p.status,
                COUNT(pv.id_program_versions) AS versions_count
            FROM programs p
            LEFT JOIN program_versions pv ON pv.programs_id = p.id_programs
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::ALLOWED_STATUSES, true)) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (p.code LIKE :q OR p.name LIKE :q)";
            $params[':q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $sql .= " GROUP BY p.id_programs, p.code, p.name, p.status ORDER BY p.id_programs DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT id_programs, code, name, status
            FROM programs
            WHERE id_programs = :id
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM programs WHERE code = :code";
        $params = [':code' => $code];

        if ($excludeId !== null) {
            $sql .= " AND id_programs <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO programs (code, name, status)
            VALUES (:code, :name, :status)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':code'   => $data['code'],
            ':name'   => $data['name'],
            ':status' => $data['status'] ?? 'active',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE programs
            SET code = :code,
                name = :name,
                status = :status
            WHERE id_programs = :id
        ";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'     => $id,
            ':code'   => $data['code'],
            ':name'   => $data['name'],
            ':status' => $data['status'],
        ]);
    }

    public function hasDependencies(int $programId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM program_versions WHERE programs_id = :id LIMIT 1");
        $stmt->execute([':id' => $programId]);

        return (bool)$stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM programs WHERE id_programs = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- ส่วนของรุ่นหลักสูตร (program_versions) ---

    public function getVersionsByProgramId(int $programId): array
    {
        $sql = "
            SELECT
                id_program_versions,
                programs_id,
                version_no,
                effective_from,
                effective_to,
                status
            FROM program_versions
            WHERE programs_id = :program_id
            ORDER BY version_no DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':program_id' => $programId]);

        return $stmt->fetchAll();
    }

    public function versionNoExists(int $programId, int $versionNo, ?int $excludeVersionId = null): bool
    {
        $sql = "
            SELECT 1
            FROM program_versions
            WHERE programs_id = :program_id
              AND version_no = :version_no
        ";
        $params = [
            ':program_id' => $programId,
            ':version_no' => $versionNo,
        ];

        if ($excludeVersionId !== null) {
            $sql .= " AND id_program_versions <> :exclude_id";
            $params[':exclude_id'] = $excludeVersionId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createVersion(int $programId, array $data): int
    {
        $sql = "
            INSERT INTO program_versions (
                programs_id,
                version_no,
                effective_from,
                effective_to,
                status
            ) VALUES (
                :programs_id,
                :version_no,
                :effective_from,
                :effective_to,
                :status
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':programs_id'    => $programId,
            ':version_no'     => $data['version_no'],
            ':effective_from' => $data['effective_from'] ?: null,
            ':effective_to'   => $data['effective_to'] ?: null,
            ':status'         => $data['status'] ?? 'active',
        ]);

        return (int)$this->db->lastInsertId();
    }
}