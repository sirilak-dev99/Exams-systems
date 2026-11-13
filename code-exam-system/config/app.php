<?php
declare(strict_types=1);
return [
 'name'=>env('APP_NAME','Exam System'), 'env'=>env('APP_ENV','local'),
 'debug'=>filter_var(env('APP_DEBUG','true'), FILTER_VALIDATE_BOOLEAN),
 'url'=>rtrim((string)env('APP_URL','http://localhost/code-exam-system/public'),'/'),
];
