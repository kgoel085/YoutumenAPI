<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->post('/register', [
    'as' => 'register.user', 'uses' => 'VerifyUserController@register'
]);
$router->get('/verify/{token}', [
    'as' => 'verify.user', 'uses' => 'VerifyUserController@verifyToken'
]);