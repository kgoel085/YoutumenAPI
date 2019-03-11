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

/**
 * Below routes handles the user verification and reistration
 */
$router->post('/register', [
    'as' => 'register.user', 'uses' => 'VerifyUserController@register'
]);
$router->get('/verify/{token}', [
    'as' => 'verify.user', 'uses' => 'VerifyUserController@verifyToken'
]);

/**
 * Below routes require JWT token in order to pass
 */
$router->group(['middleware' => 'jwt.auth'], function () use ($router) {
    $router->get('/', function ()    {
        return 'Test Route';
    });
});

// $router->get('/', function () use ($router) {
//     return $router->app->version();
// });