<?php

declare(strict_types=1);
final class UserModel
{
    public function __construct(private PDO $db) {}
    // TODO: เพิ่ม SQL หลังตรวจสอบชื่อตารางและคอลัมน์จริง
}
