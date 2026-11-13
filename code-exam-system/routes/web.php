<?php
declare(strict_types=1);
/** @var Router $router */
$router->get('/',[HomeController::class,'index']);
$router->get('/login',[AuthController::class,'showLogin']);
$router->post('/login',[AuthController::class,'login']);
$router->post('/logout',[AuthController::class,'logout']);
