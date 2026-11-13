<?php
declare(strict_types=1);
require dirname(__DIR__).'/core/App.php';
require dirname(__DIR__).'/config/env.php';
$config=require dirname(__DIR__).'/config/app.php';
if ($config['debug']) { error_reporting(E_ALL); ini_set('display_errors','1'); }
else { error_reporting(0); ini_set('display_errors','0'); }
try { $router=App::boot(); $router->dispatch($_SERVER['REQUEST_METHOD']??'GET',$_SERVER['REQUEST_URI']??'/'); }
catch (Throwable $e) {
 if (class_exists('Logger')) Logger::error($e);
 http_response_code(500);
 echo $config['debug'] ? '<h1>Application error</h1><pre>'.htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8').'</pre>' : 'เกิดข้อผิดพลาดภายในระบบ';
}
