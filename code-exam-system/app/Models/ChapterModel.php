<?php

declare(strict_types=1);

final class ChapterModel
{
    private PDO $db;

    public const ALLOWED_STATUSES = ['active', 'inactive'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function activeCourseExists(int $courseId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM courses
            WHERE id_courses = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $courseId]);

        return (bool)$stmt->fetchColumn();
    }

    public function getByCourse(int $courseId, ?string $status = null): array
    {
        $sql = "
            SELECT
                ch.id_chapters,
                ch.courses_id,
                c.code AS course_code,
                c.name AS course_name,
                ch.chapters_no,
                ch.name,
                ch.status
            FROM chapters ch
            JOIN courses c ON c.id_courses = ch.courses_id
            WHERE ch.courses_id = :course_id
        ";
        $params = [':course_id' => $courseId];

        if ($status !== null && in_array($status, self::ALLOWED_STATUSES, true)) {
            $sql .= " AND ch.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY ch.chapters_no ASC, ch.id_chapters ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ch.id_chapters,
                ch.courses_id,
                c.code AS course_code,
                c.name AS course_name,
                ch.chapters_no,
                ch.name,
                ch.status
            FROM chapters ch
            JOIN courses c ON c.id_courses = ch.courses_id
            WHERE ch.id_chapters = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function chapterNoExists(int $courseId, int $chapterNo, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM chapters
            WHERE courses_id = :course_id
              AND chapters_no = :chapters_no
        ";
        $params = [
            ':course_id'   => $courseId,
            ':chapters_no' => $chapterNo,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_chapters <> :exclude_id";
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
            INSERT INTO chapters (courses_id, chapters_no, name, status)
            VALUES (:courses_id, :chapters_no, :name, :status)
        ");
        $stmt->execute([
            ':courses_id'  => $data['courses_id'],
            ':chapters_no' => $data['chapters_no'],
            ':name'        => $data['name'],
            ':status'      => $data['status'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE chapters
            SET chapters_no = :chapters_no,
                name = :name,
                status = :status
            WHERE id_chapters = :id
        ");

        return $stmt->execute([
            ':id'          => $id,
            ':chapters_no' => $data['chapters_no'],
            ':name'        => $data['name'],
            ':status'      => $data['status'],
        ]);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE chapters
            SET status = 'inactive'
            WHERE id_chapters = :id
        ");

        return $stmt->execute([':id' => $id]);
    }
}