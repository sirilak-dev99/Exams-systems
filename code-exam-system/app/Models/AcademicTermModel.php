<?php

declare(strict_types=1);

final class AcademicTermModel
{
    private PDO $db;

    public const ALLOWED_STATUSES = ['active', 'inactive', 'upcoming', 'archived'];
    public const ALLOWED_TERM_NOS = [1, 2, 3];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(array $filters = []): array
    {
        $sql = "
            SELECT
                id_academic_terms,
                academic_year,
                term_no,
                start_date,
                end_date,
                status
            FROM academic_terms
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::ALLOWED_STATUSES, true)) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['academic_year']) && ctype_digit((string)$filters['academic_year'])) {
            $sql .= " AND academic_year = :academic_year";
            $params[':academic_year'] = (int)$filters['academic_year'];
        }

        $sql .= " ORDER BY academic_year DESC, term_no DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_academic_terms,
                academic_year,
                term_no,
                start_date,
                end_date,
                status
            FROM academic_terms
            WHERE id_academic_terms = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function termExists(int $academicYear, int $termNo, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM academic_terms
            WHERE academic_year = :academic_year
              AND term_no = :term_no
        ";
        $params = [
            ':academic_year' => $academicYear,
            ':term_no'       => $termNo,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_academic_terms <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO academic_terms (
                academic_year,
                term_no,
                start_date,
                end_date,
                status
            ) VALUES (
                :academic_year,
                :term_no,
                :start_date,
                :end_date,
                :status
            )
        ");
        $stmt->execute([
            ':academic_year' => $data['academic_year'],
            ':term_no'       => $data['term_no'],
            ':start_date'    => $data['start_date'],
            ':end_date'      => $data['end_date'],
            ':status'        => $data['status'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE academic_terms
            SET academic_year = :academic_year,
                term_no = :term_no,
                start_date = :start_date,
                end_date = :end_date,
                status = :status
            WHERE id_academic_terms = :id
        ");

        return $stmt->execute([
            ':id'            => $id,
            ':academic_year' => $data['academic_year'],
            ':term_no'       => $data['term_no'],
            ':start_date'    => $data['start_date'],
            ':end_date'      => $data['end_date'],
            ':status'        => $data['status'],
        ]);
    }

    public function hasDependencies(int $termId): bool
    {
        $tables = [
            'course_offerings',
            'student_promotion_batches',
            'student_year_level_histories',
        ];

        foreach ($tables as $table) {
            $stmt = $this->db->prepare("SELECT 1 FROM {$table} WHERE academic_terms_id = :id LIMIT 1");
            $stmt->execute([':id' => $termId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM academic_terms WHERE id_academic_terms = :id");
        return $stmt->execute([':id' => $id]);
    }
}