<?php
declare(strict_types=1);
abstract class Controller {
 protected function view(string $name, array $data=[]): void {
  $file=dirname(__DIR__).'/app/Views/'.$name.'.php';
  if (!is_file($file)) { http_response_code(404); echo 'ไม่พบหน้าที่ต้องการ'; return; }
  extract($data, EXTR_SKIP); require $file;
 }
 protected function redirect(string $path): never {
  header('Location: '.rtrim((string)env('APP_URL',''),'/').'/'.ltrim($path,'/')); exit;
 }
}
