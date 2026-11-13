<?php

declare(strict_types=1);
final class QuestionModel
{
    public function __construct(private PDO $db) {}
    // TODO: เพิ่ม SQL สำหรับ questions, question_versions, question_options
}
