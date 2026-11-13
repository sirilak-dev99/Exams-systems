<?php
declare(strict_types=1);
final class App {
 public static function boot(): Router {
  require_once dirname(__DIR__).'/config/env.php';
  foreach (['Database','Controller','Router','Security','Logger'] as $f) require_once __DIR__.'/'.$f.'.php';
  Security::startSession();
  require_once dirname(__DIR__).'/app/Controllers/HomeController.php';
  require_once dirname(__DIR__).'/app/Controllers/AuthController.php';
  $router=new Router(); require dirname(__DIR__).'/routes/web.php'; return $router;
 }
}
