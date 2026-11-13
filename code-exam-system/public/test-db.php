<?php

require dirname(__DIR__) . '/config/env.php';
require dirname(__DIR__) . '/core/Database.php';

try {
    $db = Database::connection();

    echo "Database connection successful!<br>";

    $result = $db->query("SELECT DATABASE() AS db_name");
    $row = $result->fetch();

    echo "Connected database: "
        . htmlspecialchars($row['db_name']);

    echo "<br><br>Tables in database:<br>";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo htmlspecialchars($table) . "<br>";
    }

} catch (Throwable $e) {
    http_response_code(500);

    echo "Database connection failed!<br>";
    echo htmlspecialchars($e->getMessage());
}
