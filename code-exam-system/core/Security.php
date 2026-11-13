<?php
declare(strict_types=1);
final class Security {
 public static function startSession(): void {
  if (session_status()!==PHP_SESSION_ACTIVE) {
   session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
   session_start();
  }
 }
 public static function csrfToken(): string {
  self::startSession(); return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
 }
 public static function verifyCsrf(?string $token): bool {
  self::startSession(); return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'],$token);
 }
 public static function escape(?string $value): string { return htmlspecialchars($value??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
}
