<?php
declare(strict_types=1);
return [
 'host'=>env('DB_HOST','127.0.0.1'), 'port'=>env('DB_PORT','3306'),
 'name'=>env('DB_NAME','exam_system'), 'user'=>env('DB_USER','root'),
 'pass'=>env('DB_PASS',''), 'charset'=>env('DB_CHARSET','utf8mb4'),
];
