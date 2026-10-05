<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'DashboardController::index', ['filter' => 'auth']);
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

$routes->get('rooms', 'RoomController::index', ['filter' => 'auth']);
$routes->post('rooms', 'RoomController::store', ['filter' => 'auth']);
$routes->post('rooms/(:num)', 'RoomController::update/$1', ['filter' => 'auth']);
$routes->post('rooms/(:num)/toggle', 'RoomController::toggle/$1', ['filter' => 'auth']);

$routes->group('api', static function ($routes) {
    $routes->get('rooms', 'Api\RoomApiController::index');
    $routes->post('counts/sync', 'Api\CountApiController::sync');
});
