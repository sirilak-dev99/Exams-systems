<?php
declare(strict_types=1);
final class App {
 public static function boot(): Router {
  require_once dirname(__DIR__).'/config/env.php';
  foreach (['Database','Controller','Router','Security','Logger'] as $f) require_once __DIR__.'/'.$f.'.php';
  Security::startSession();
  require_once dirname(__DIR__).'/app/Models/UserModel.php';
  require_once dirname(__DIR__).'/app/Controllers/HomeController.php';
  require_once dirname(__DIR__).'/app/Controllers/AuthController.php';
  $router=new Router(); require dirname(__DIR__).'/routes/web.php'; return $router;
 }
}

spl_autoload_register(function (string $class): void {
    $dirs = [
        dirname(__DIR__) . '/core/',
        dirname(__DIR__) . '/app/Controllers/',
        dirname(__DIR__) . '/app/Models/',
    ];

    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

spl_autoload_register(function (string $class): void {
    $baseDir = dirname(__DIR__);
    $paths = [
        $baseDir . '/core/' . $class . '.php',
        $baseDir . '/app/Controllers/' . $class . '.php',
        $baseDir . '/app/Models/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});