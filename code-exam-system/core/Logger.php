<?php
declare(strict_types=1);
final class Logger {
 public static function error(Throwable $e): void {
  $dir=dirname(__DIR__).'/logs'; if (!is_dir($dir)) mkdir($dir,0775,true);
  error_log('['.date('c').'] '.$e.PHP_EOL,3,$dir.'/app.log');
 }
}
